<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $roles - Comma-separated list of roles
     */
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        if (!$request->user()) {
            return response()->json([
                'error' => 'Unauthenticated'
            ], 401);
        }

        $allowedRoles = explode('|', $roles);

        if (!$request->user()->hasAnyRole($allowedRoles)) {
            return response()->json([
                'error' => 'Unauthorized. You do not have the required role.',
                'required_roles' => $allowedRoles
            ], 403);
        }

        return $next($request);
    }
}
