<?php

namespace App\Http\Middleware;

use App\Enums\PayrollOperatingMode;
use App\Services\Payroll\PayrollOperatingModeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStandalonePayrollAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user
            || $user->hasRole('super-admin')
            || app(PayrollOperatingModeService::class)->current() !== PayrollOperatingMode::Standalone
            || $this->isPayrollRelated($request)
        ) {
            return $next($request);
        }

        abort(403, 'Only Payroll and Timekeeping applications are available while the system is in Standalone mode.');
    }

    private function isPayrollRelated(Request $request): bool
    {
        if ($request->path() === '/'
            || $request->is('livewire/*')
            || $request->routeIs('login', 'login.store', 'logout', 'access.pending', 'home')
            || $request->routeIs('self-service.profile', 'self-service.profile.print')
            || $request->routeIs('payroll.*', 'timekeeping.*', 'admin.payroll-system')
            || $request->routeIs('setup.index', 'setup.hris', 'setup.organization', 'setup.positions', 'setup.salary-schedules', 'setup.plantilla')
        ) {
            return true;
        }

        return $request->routeIs('coming-soon')
            && in_array($request->route('module'), ['payroll', 'timekeeping'], true);
    }
}
