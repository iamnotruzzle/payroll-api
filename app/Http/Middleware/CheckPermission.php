<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!$request->user()) {
            return response()->json([
                'error' => 'Unauthenticated'
            ], 401);
        }

        if (!$request->user()->hasPermission($permission)) {
            return response()->json([
                'error' => 'Unauthorized. You do not have the required permission.',
                'required_permission' => $permission
            ], 403);
        }

        return $next($request);
    }
}
