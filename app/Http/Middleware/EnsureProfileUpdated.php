<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileUpdated
{
    /**
     * Force accounts with login_attempt = 0 to complete a profile update
     * before using the rest of the app (legacy first-login / annual update gate).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if ((int) ($user->login_attempt ?? 1) !== 0) {
            return $next($request);
        }

        // Avoid lockout for accounts that cannot open My Profile.
        if (! $user->can('self-service.profile') && ! $user->can('self-service.access')) {
            return $next($request);
        }

        if ($this->isAllowedWhilePending($request)) {
            return $next($request);
        }

        return redirect()
            ->route('self-service.profile')
            ->with('status', 'Please review and save your profile before continuing.');
    }

    private function isAllowedWhilePending(Request $request): bool
    {
        if ($request->routeIs('self-service.profile')
            || $request->routeIs('self-service.profile.print')
            || $request->routeIs('logout')
            || $request->routeIs('login')
        ) {
            return true;
        }

        // Livewire update endpoint must remain reachable while editing profile.
        if ($request->is('livewire/*')) {
            return true;
        }

        return false;
    }
}
