<?php

namespace App\Services\Schedule;

use App\Models\Hris\EmployeeLeave;
use App\Models\Hris\LeaveStatusLookup;
use App\Models\Payroll\PayrollDtrLabel;
use App\Models\Payroll\PayrollHoliday;
use App\Models\Schedule\ShiftCode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ScheduleAvailabilityService
{
    public function exceptionShiftCodes(Collection $employeeIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $shiftCodes = ShiftCode::query()
            ->whereIn('code', ['H', 'OB', 'CTO', 'RO', 'ML'])
            ->get()
            ->keyBy('code');
        $genericLeaveShift = ShiftCode::query()
            ->where('is_leave_code', true)
            ->whereNotIn('code', ['CTO', 'ML'])
            ->orderBy('code')
            ->first();

        $exceptions = [];

        $holidayShift = $shiftCodes->get('H');
        if ($holidayShift) {
            PayrollHoliday::query()
                ->where('is_active', true)
                ->whereBetween('holiday_date', [$from->toDateString(), $to->toDateString()])
                ->get()
                ->each(function (PayrollHoliday $holiday) use (&$exceptions, $employeeIds, $holidayShift) {
                    $date = $holiday->holiday_date->toDateString();
                    foreach ($employeeIds as $employeeId) {
                        $exceptions[$employeeId][$date] = $holidayShift;
                    }
                });
        }

        $approvedStatusIds = LeaveStatusLookup::query()
            ->where('status_name', 'like', '%approved%')
            ->pluck('status_id');

        if ($approvedStatusIds->isNotEmpty()) {
            EmployeeLeave::query()
                ->whereIn('emp_id', $employeeIds)
                ->whereIn('status', $approvedStatusIds)
                ->whereNotNull('start_date')
                ->whereNotNull('end_date')
                ->whereDate('start_date', '<=', $to->toDateString())
                ->whereDate('end_date', '>=', $from->toDateString())
                ->get()
                ->each(function (EmployeeLeave $leave) use (&$exceptions, $from, $to, $shiftCodes, $genericLeaveShift) {
                    $shift = $this->leaveShiftCode($leave, $shiftCodes, $genericLeaveShift);
                    if (! $shift) {
                        return;
                    }

                    $cursor = CarbonImmutable::parse($leave->start_date);
                    if ($cursor->lt($from)) {
                        $cursor = $from;
                    }

                    $last = CarbonImmutable::parse($leave->end_date);
                    if ($last->gt($to)) {
                        $last = $to;
                    }

                    while ($cursor <= $last) {
                        $exceptions[$leave->emp_id][$cursor->toDateString()] = $shift;
                        $cursor = $cursor->addDay();
                    }
                });
        }

        PayrollDtrLabel::query()
            ->whereIn('emp_id', $employeeIds)
            ->whereBetween('dtr_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('label', ['OB', 'CTO', 'RO', 'ML'])
            ->get()
            ->each(function (PayrollDtrLabel $label) use (&$exceptions, $shiftCodes) {
                $shift = $shiftCodes->get(strtoupper((string) $label->label));
                if ($shift) {
                    $exceptions[$label->emp_id][$label->dtr_date->toDateString()] = $shift;
                }
            });

        return $exceptions;
    }

    private function leaveShiftCode(EmployeeLeave $leave, Collection $shiftCodes, ?ShiftCode $genericLeaveShift): ?ShiftCode
    {
        $remarks = strtoupper((string) $leave->remarks);

        if (str_contains($remarks, 'MATERNITY')) {
            return $shiftCodes->get('ML');
        }

        if (str_contains($remarks, 'CTO')) {
            return $shiftCodes->get('CTO');
        }

        return $genericLeaveShift ?? $shiftCodes->get('RO') ?? $shiftCodes->get('CTO');
    }
}
