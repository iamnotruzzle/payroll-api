<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDeviceApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->hasValidDeviceKey($request)) {
            return response()->json([
                'message' => 'Unauthorized. Provide a valid device API key.',
                'status' => 'Unauthorized',
            ], 401);
        }

        return $next($request);
    }

    public static function extractKey(Request $request): ?string
    {
        $headerKey = $request->header('X-API-Key');
        if (is_string($headerKey) && $headerKey !== '') {
            return $headerKey;
        }

        $authorization = $request->header('Authorization');
        if (is_string($authorization) && preg_match('/^Bearer\s+(\S+)/i', $authorization, $matches) === 1) {
            return $matches[1];
        }

        $queryKey = $request->query('api_key');
        if (is_string($queryKey) && $queryKey !== '') {
            return $queryKey;
        }

        return null;
    }

    public static function hasValidDeviceKey(Request $request): bool
    {
        $provided = self::extractKey($request);
        if ($provided === null) {
            return false;
        }

        $keys = config('api.device_keys', []);
        if (! is_array($keys) || $keys === []) {
            return false;
        }

        foreach ($keys as $key) {
            if (is_string($key) && $key !== '' && hash_equals($key, $provided)) {
                return true;
            }
        }

        return false;
    }
}
