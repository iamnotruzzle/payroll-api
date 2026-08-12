<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('api.require_auth', true)) {
            return $next($request);
        }

        if ($this->isPublic($request)) {
            return $next($request);
        }

        if (AuthenticateDeviceApiKey::hasValidDeviceKey($request)) {
            return $next($request);
        }

        if (Auth::guard('web')->check() || Auth::guard('sanctum')->check()) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Unauthenticated. Use a Sanctum token, web session, or device API key.',
        ], 401);
    }

    private function isPublic(Request $request): bool
    {
        $path = trim($request->path(), '/');
        if (str_starts_with($path, 'api/')) {
            $path = substr($path, 4);
        }

        foreach (config('api.public_prefixes', []) as $prefix) {
            $prefix = trim((string) $prefix, '/');
            if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix.'/'))) {
                return true;
            }
        }

        return false;
    }
}
