<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySuperAdminElevation
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $request->session()->get('super_admin_elevated', false)) {
            return $next($request);
        }

        $roles = $user->roles;
        if (! $roles->contains('name', 'super-admin')) {
            $superAdmin = $user->roles()
                ->getRelated()
                ->newQuery()
                ->where('name', 'super-admin')
                ->where('guard_name', 'web')
                ->first();

            if ($superAdmin) {
                $user->setRelation('roles', $roles->push($superAdmin));
            }
        }

        return $next($request);
    }
}
