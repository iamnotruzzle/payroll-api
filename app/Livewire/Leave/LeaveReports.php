<?php

namespace App\Livewire\Leave;

use App\Models\Hris\EmployeeLeave;
use App\Models\Hris\LeaveType;
use App\Support\Hris\LeaveStatuses;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class LeaveReports extends Component
{
    public string $reportType = 'monthly';

    public string $month;

    public string $year;

    public ?int $leaveTypeId = null;

    public function mount(): void
    {
        $today = CarbonImmutable::today();
        $this->month = $today->format('m');
        $this->year = $today->format('Y');
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('leave.reports') || auth()->user()?->can('leave.view'), 403);

        $year = (int) $this->year;
        $month = (int) $this->month;
        $from = CarbonImmutable::create($year, max(1, min(12, $month)), 1)->startOfDay();
        $to = $from->endOfMonth();

        $base = EmployeeLeave::query()
            ->with(['leaveType', 'employee.department', 'statusLookup'])
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->whereNotIn('status', \App\Services\Hris\LeaveService::LEDGER_STATUS_IDS)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->when($this->leaveTypeId, fn ($q) => $q->where('leave_type', $this->leaveTypeId));

        $rows = (clone $base)
            ->orderBy('start_date')
            ->orderBy('emp_id')
            ->limit(500)
            ->get();

        $byType = EmployeeLeave::query()
            ->select([
                'leave_type',
                DB::raw('COUNT(*) as request_count'),
                DB::raw('COALESCE(SUM(days_wpay),0) as total_wpay'),
                DB::raw('COALESCE(SUM(days_wopay),0) as total_wopay'),
            ])
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->whereNotIn('status', \App\Services\Hris\LeaveService::LEDGER_STATUS_IDS)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->groupBy('leave_type')
            ->orderByDesc('request_count')
            ->get()
            ->map(function ($row) {
                $type = LeaveType::query()->find($row->leave_type);
                $row->leave_name = $type?->leave_name ?: "Leave #{$row->leave_type}";

                return $row;
            });

        $statusCounts = $rows
            ->groupBy(fn (EmployeeLeave $leave) => LeaveStatuses::keyFor($leave->status !== null ? (int) $leave->status : null))
            ->map->count();

        return view('livewire.leave.leave-reports', [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'byType' => $byType,
            'statusCounts' => $statusCounts,
            'leaveTypes' => LeaveType::query()->orderBy('leave_name')->get(),
            'canRun' => (bool) auth()->user()?->can('leave.reports'),
        ]);
    }
}
