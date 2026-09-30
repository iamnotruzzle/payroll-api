<?php

namespace App\Services\Payroll;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HrisApiClient
{
    /** Fetch and map a complete resource without modifying payroll or contacting its databases. */
    public function fetch(string $name, array $context = []): array
    {
        $resource = config("hris_api.resources.{$name}");
        if (! is_array($resource) || ! ($resource['enabled'] ?? false)) {
            throw new RuntimeException('Unknown or disabled HRIS API resource. Check config/hris_api.php.');
        }
        $base = rtrim((string) config('hris_api.base_url'), '/');
        $token = (string) config('hris_api.token');
        $path = $resource['path'] ?? '';
        $method = strtoupper($resource['method'] ?? 'GET');
        if (! filter_var($base, FILTER_VALIDATE_URL) || ! in_array(parse_url($base, PHP_URL_SCHEME), ['http', 'https'], true)
            || parse_url($base, PHP_URL_USER) || parse_url($base, PHP_URL_QUERY) || parse_url($base, PHP_URL_FRAGMENT)
            || ! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')
            || ! in_array($method, ['GET', 'POST'], true) || $token === '') {
            throw new RuntimeException('Configure a valid HRIS API base URL, token, relative path, and GET/POST method.');
        }
        $parameters = $resource['parameters'] ?? [];
        array_walk_recursive($parameters, function (&$value) use ($context): void {
            if (is_string($value) && preg_match('/^\{(\w+)\}$/', $value, $match)) {
                if (! isset($context[$match[1]]) || $context[$match[1]] === '') {
                    throw new RuntimeException("Missing request context: {$match[1]}.");
                }
                $value = $context[$match[1]];
            }
        });
        if ($name === 'basic-pay' && isset($context['employee_id'])) {
            $parameters['emp_ids'] = [$context['employee_id']];
        }
        $rows = [];
        $warnings = 0;
        $missing = [];
        $pagination = $resource['pagination'] ?? null;
        $maxPages = max(1, (int) config('hris_api.max_pages', 100));
        $lastPage = null;
        for ($page = 1; $page <= $maxPages; $page++) {
            if ($pagination) {
                $parameters[$pagination['page_parameter']] = $page;
            }
            try {
                $response = Http::withToken($token)->acceptJson()->asJson()
                    ->connectTimeout((int) config('hris_api.connect_timeout', 5))
                    ->timeout((int) config('hris_api.timeout', 30))
                    ->withoutRedirecting()
                    ->send($method, $base.$path, [$method === 'GET' ? 'query' : 'json' => $parameters]);
            } catch (ConnectionException) {
                // Do not surface request headers, tokens, or remote response bodies in errors.
                throw new RuntimeException('HRIS API connection failed or timed out.');
            }
            if (! $response->successful()) {
                throw new RuntimeException("HRIS API returned HTTP {$response->status()}.");
            }
            $body = $response->json();
            $data = ($resource['data_path'] ?? '') === '' ? $body : data_get($body, $resource['data_path']);
            if (! is_array($data) || ! array_is_list($data)) {
                throw new RuntimeException('HRIS API data path must resolve to a JSON list. Check the response mapping.');
            }
            $remoteWarnings = data_get($body, 'warnings', []);
            $warnings += is_array($remoteWarnings) ? count($remoteWarnings) : (int) filled($remoteWarnings);
            foreach ($data as $index => $record) {
                if (! is_array($record)) {
                    throw new RuntimeException('HRIS API records must be JSON objects.');
                }
                $mapped = [];
                foreach ($resource['fields'] as $target => $source) {
                    $mapped[$target] = data_get($record, $source);
                    if ($mapped[$target] === null) {
                        $missing[$target] = ($missing[$target] ?? 0) + 1;
                    }
                }
                foreach ($resource['required'] ?? [] as $field) {
                    if (! array_key_exists($field, $mapped) || $mapped[$field] === null || $mapped[$field] === '') {
                        throw new RuntimeException("Missing required mapped field {$field} on page {$page}, row ".($index + 1).'.');
                    }
                }
                if (isset($mapped['employee_id']) && ! is_string($mapped['employee_id'])) {
                    throw new RuntimeException('HRIS employee IDs must be strings to preserve leading zeros.');
                }
                if (isset($mapped['monthly_salary']) && (! is_numeric($mapped['monthly_salary']) || $mapped['monthly_salary'] < 0)) {
                    throw new RuntimeException('HRIS monthly salary must be a non-negative number.');
                }
                $rows[] = $mapped;
            }
            if (! $pagination) {
                if (data_get($body, 'links.next') || data_get($body, 'next_page_url') || data_get($body, 'meta.last_page', 1) > 1 || data_get($body, 'last_page', 1) > 1) {
                    throw new RuntimeException('HRIS returned pagination metadata. Configure pagination before fetching this resource.');
                }
                break;
            }
            $reportedLast = data_get($body, $pagination['last_page_path']);
            if (filter_var($reportedLast, FILTER_VALIDATE_INT) === false || (int) $reportedLast < $page || (int) $reportedLast > $maxPages) {
                throw new RuntimeException('Invalid pagination metadata or HRIS page limit exceeded.');
            }
            if ($lastPage !== null && $lastPage !== (int) $reportedLast) {
                throw new RuntimeException('HRIS pagination changed during download. Retry against a consistent export.');
            }
            $lastPage = (int) $reportedLast;
            if ($page === $lastPage) {
                break;
            }
        }

        return ['rows' => $rows, 'pages' => $page, 'warning_count' => $warnings, 'missing_fields' => $missing];
    }
}
