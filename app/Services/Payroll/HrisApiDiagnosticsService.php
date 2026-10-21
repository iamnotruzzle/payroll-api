<?php

namespace App\Services\Payroll;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HrisApiDiagnosticsService
{
    public const FILTERS = [
        'basic-pay' => ['emp_ids', 'type', 'division_ids', 'department_ids', 'include_inactive'],
        'divisions' => ['active_only'], 'departments' => ['active_only'],
        'positions' => [], 'cos-positions' => [], 'employment-statuses' => [], 'leave-types' => [],
        'employees' => ['emp_ids', 'groups'], 'salary-rates' => ['year'],
        'leaves' => ['from', 'to', 'department_ids'],
        'timekeeping' => ['period', 'from', 'to', 'department_ids', 'groups', 'emp_ids'],
    ];

    public function inspect(string $endpoint, array $parameters): array
    {
        if (! array_key_exists($endpoint, self::FILTERS) || array_diff(array_keys($parameters), self::FILTERS[$endpoint])) {
            throw new RuntimeException('Choose a documented endpoint and supported filters.');
        }
        $resource = config("hris_api.resources.{$endpoint}");
        $base = rtrim((string) config('hris_api.base_url'), '/');
        $path = $resource['path'] ?? '';
        $method = strtoupper($resource['method'] ?? 'GET');
        $token = (string) config('hris_api.token');
        if (! ($resource['enabled'] ?? false) || ! filter_var($base, FILTER_VALIDATE_URL)
            || ! in_array(parse_url($base, PHP_URL_SCHEME), ['http', 'https'], true)
            || parse_url($base, PHP_URL_USER) || parse_url($base, PHP_URL_QUERY) || parse_url($base, PHP_URL_FRAGMENT)
            || ! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')
            || ! in_array($method, ['GET', 'POST'], true) || $token === '') {
            throw new RuntimeException('Configure a valid HRIS API base URL, token, and endpoint first.');
        }
        $start = hrtime(true);
        try {
            $response = Http::withToken($token)->acceptJson()->asJson()->withoutRedirecting()
                ->connectTimeout((int) config('hris_api.connect_timeout', 5))
                ->timeout((int) config('hris_api.timeout', 30))
                ->send($method, $base.$path, [$method === 'GET' ? 'query' : 'json' => $parameters]);
        } catch (ConnectionException) {
            throw new RuntimeException('HRIS connection failed or timed out. Check the configured address and server connectivity.');
        }
        $body = $response->json();
        $issues = [];
        $data = is_array($body) ? data_get($body, $resource['data_path'] ?? 'data') : null;
        if (! $response->successful()) {
            $issues[] = 'The endpoint returned a non-success HTTP status.';
        }
        if (! is_array($data) || ! array_is_list($data)) {
            $issues[] = 'The response does not contain the expected data list.';
        } else {
            if (! isset($body['count']) || ! is_int($body['count']) || $body['count'] !== count($data)) {
                $issues[] = 'Response count is missing, invalid, or does not match the number of records.';
            }
            foreach (array_slice($data, 0, 25) as $index => $row) {
                if (! is_array($row)) {
                    $issues[] = 'A preview record is not a JSON object.';
                    break;
                }
                foreach ($resource['required_keys'] ?? $resource['required'] ?? [] as $field) {
                    $source = $resource['fields'][$field] ?? $field;
                    if (! array_key_exists($source, $row)) {
                        $issues[] = 'Preview row '.($index + 1)." is missing {$source}.";
                    }
                }
                foreach ($resource['required'] ?? [] as $field) {
                    $source = $resource['fields'][$field] ?? $field;
                    if (array_key_exists($source, $row) && ($row[$source] === null || $row[$source] === '')) {
                        $issues[] = 'Preview row '.($index + 1)." has an empty required field {$source}.";
                    }
                }
                $employeeField = $resource['fields']['employee_id'] ?? null;
                if ($employeeField && isset($row[$employeeField]) && ! is_string($row[$employeeField])) {
                    $issues[] = 'Employee IDs must be strings to preserve leading zeros.';
                }
            }
        }
        if (! empty($body['warnings'])) {
            $issues[] = 'HRIS returned warnings; review the response before using this data.';
        }
        $preview = is_array($body)
            ? json_encode($this->redact($body), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
            : mb_substr($response->body(), 0, 4000);

        return [
            'method' => $method, 'path' => $path, 'parameters' => $parameters,
            'status' => $response->status(), 'elapsed_ms' => round((hrtime(true) - $start) / 1e6),
            'success' => $response->successful(), 'count' => is_array($data) ? count($data) : null,
            'issues' => array_values(array_unique($issues)),
            'preview' => mb_substr(str_replace($token, '[redacted]', (string) $preview), 0, 50000),
            'limited' => (is_array($data) && count($data) > 25) || mb_strlen((string) $preview) > 50000,
        ];
    }

    private function redact(array $value, int $depth = 0): array
    {
        $result = [];
        foreach (array_slice($value, 0, 25, true) as $key => $item) {
            $result[$key] = is_string($key) && preg_match('/password|token|secret|authorization|cookie|api.?key/i', $key)
                ? '[redacted]' : (is_array($item) ? ($depth < 8 ? $this->redact($item, $depth + 1) : '[preview depth limit]')
                    : (is_string($item) ? mb_substr($item, 0, 4000) : $item));
        }

        return $result;
    }
}
