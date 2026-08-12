<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\Hris\Employee;
use App\Models\Hris\IpcrPeriod;
use App\Services\Hris\IpcrService;
use Illuminate\View\View;

class PerformancePageController extends Controller
{
    public function periods(): View
    {
        return view('performance.periods');
    }

    public function employee(string $empId, int $periodId): View
    {
        $employee = Employee::query()
            ->with(['department', 'position'])
            ->where('emp_id', $empId)
            ->firstOrFail();

        $period = IpcrPeriod::query()->findOrFail($periodId);

        $user = auth()->user();
        if ($user?->can('self-service.ipcr')
            && ! $user?->can('performance.view')
            && ! $user?->can('performance.manage')
            && ! $user?->can('performance.approve')
        ) {
            abort_unless((string) $empId === (string) $user->emp_id, 403);
        }

        return view('performance.employee', [
            'empId' => $empId,
            'periodId' => $periodId,
            'employee' => $employee,
            'period' => $period,
        ]);
    }

    public function print(string $empId, int $periodId, IpcrService $ipcrService): View
    {
        $employee = Employee::query()
            ->with(['department', 'position'])
            ->where('emp_id', $empId)
            ->firstOrFail();

        $period = IpcrPeriod::query()->findOrFail($periodId);
        $targets = $ipcrService->targetsForEmployeePeriod($empId, $periodId);

        $user = auth()->user();
        if ($user?->can('self-service.ipcr')
            && ! $user?->can('performance.view')
            && ! $user?->can('performance.manage')
            && ! $user?->can('performance.approve')
        ) {
            abort_unless((string) $empId === (string) $user->emp_id, 403);
        }

        $grouped = [
            'Strategic Function' => [],
            'Core Function' => [],
            'Support Function' => [],
            'Other' => [],
        ];

        foreach ($targets as $target) {
            $label = $target->mfoSet?->mfo?->functionType?->function_type ?: 'Other';
            if (! array_key_exists($label, $grouped)) {
                $label = 'Other';
            }
            $grouped[$label][] = $target;
        }

        return view('performance.print', [
            'employee' => $employee,
            'period' => $period,
            'grouped' => $grouped,
            'backUrl' => $user?->can('performance.view') || $user?->can('performance.manage')
                ? route('performance.employee', ['empId' => $empId, 'periodId' => $periodId])
                : route('self-service.ipcr'),
        ]);
    }
}
