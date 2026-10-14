<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyRolePreview
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $eligible = $user && $user->hasRole('super-admin');
        $request->attributes->set('role_preview_allowed', (bool) $eligible);

        if (! $eligible) {
            $request->session()->forget('role_preview_id');
        }

        $role = $eligible && $request->session()->has('role_preview_id')
            ? Role::query()->where('guard_name', 'web')->where('is_active', true)
                ->where('name', '!=', 'super-admin')->find($request->session()->get('role_preview_id'))
            : null;

        if (! $role) {
            $request->session()->forget('role_preview_id');

            return $next($request);
        }

        $request->attributes->set('role_preview', $role);
        if ($request->routeIs('admin.role-preview.*', 'logout')) {
            return $next($request);
        }

        $roles = $user->roles;
        $permissions = $user->permissions;
        $user->setRelation('roles', new Collection([$role]));
        $user->setRelation('permissions', new Collection);

        try {
            return $next($request);
        } finally {
            $user->setRelation('roles', $roles);
            $user->setRelation('permissions', $permissions);
        }
    }
}
