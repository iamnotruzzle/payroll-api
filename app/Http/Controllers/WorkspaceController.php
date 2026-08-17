<?php

namespace App\Http\Controllers;

use App\Support\ErpNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function home(Request $request): View
    {
        $user = $request->user();

        return view('home', [
            'title' => 'Apps',
            'moduleGroups' => ErpNavigation::launcherGroups(),
            'employeeName' => $user?->loadMissing('employee')->employee?->full_name ?: $user?->emp_id,
        ]);
    }

    public function comingSoon(string $module, ?string $feature = null): View
    {
        $labels = [
            'employees' => 'Employees',
            'leave' => 'Leave',
            'scheduling' => 'Schedule',
            'timekeeping' => 'Timekeeping',
            'payroll' => 'Payroll',
            'training' => 'Training',
            'performance' => 'Performance',
            'administration' => 'Settings',
            'self-service' => 'Self Service',
            'reports' => 'Reports',
        ];

        $featureLabels = [
            'directory' => 'Employee Directory',
            'pds' => 'Personal Data Sheet',
            'dependents' => 'Dependents',
            'requests' => 'Leave Requests',
            'approvals' => 'Leave Approvals',
            'credits' => 'Leave Credits',
            'leave-card' => 'Leave Card',
            'reports' => 'Leave Reports',
            'my-profile' => 'My Profile',
            'my-leave' => 'My Leave',
            'my-schedule' => 'My Schedule',
            'my-payslip' => 'My Payslip',
            'my-dtr' => 'My DTR',
            'tarf' => 'Training / TARF',
            'ipcr' => 'IPCR',
            'dashboard' => 'Dashboard',
            'dtr' => 'DTR',
            'generation' => 'Payroll Generation',
            'access' => 'Access Control',
            'units' => 'Schedule Units',
            'floaters' => 'Floaters',
            'on-call' => 'On Call',
            'swaps' => 'Shift Swaps',
            'census' => 'Duty Census',
        ];

        $moduleLabel = $labels[$module] ?? str($module)->headline()->toString();
        $featureLabel = $feature
            ? ($featureLabels[$feature] ?? str($feature)->headline()->toString())
            : null;

        return view('coming-soon', [
            'title' => $featureLabel ? "{$featureLabel} · Coming Soon" : "{$moduleLabel} · Coming Soon",
            'module' => $module,
            'moduleLabel' => $moduleLabel,
            'feature' => $feature,
            'featureLabel' => $featureLabel,
        ]);
    }
}
