<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Support\ErpNavigation;
use Illuminate\View\View;

class SetupPageController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        abort_unless(
            $user?->can('employees.manage')
            || $user?->can('schedule.view')
            || $user?->can('schedule.manage')
            || $user?->can('timekeeping.view')
            || $user?->can('timekeeping.manage')
            || $user?->can('payroll.view')
            || $user?->can('payroll.configure')
            || $user?->can('training.manage')
            || $user?->can('performance.manage')
            || $user?->can('performance.view')
            || $user?->can('admin.users.view')
            || $user?->can('admin.users.manage')
            || $user?->can('admin.roles.view')
            || $user?->can('admin.roles.manage'),
            403
        );

        $setupApp = collect(ErpNavigation::apps())->firstWhere('key', 'setup');

        return view('setup.index', [
            'sections' => $setupApp['menu_sections'] ?? [],
            'icons' => ErpNavigation::icons(),
        ]);
    }

    public function organization(): View
    {
        $this->authorizeHris();

        return view('setup.organization');
    }

    public function positions(): View
    {
        $this->authorizeHris();

        return view('setup.positions');
    }

    public function salarySchedules(): View
    {
        $this->authorizeHris();

        return view('setup.salary-schedules');
    }

    public function plantilla(): View
    {
        $this->authorizeHris();

        return view('setup.plantilla');
    }

    private function authorizeHris(): void
    {
        abort_unless(auth()->user()?->can('employees.manage') || auth()->user()?->can('payroll.configure'), 403);
    }
}
