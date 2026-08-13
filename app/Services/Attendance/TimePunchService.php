<?php

namespace App\Services\Attendance;

use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeDtr;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TimePunchService
{
    public const MACHINE_ID = '103';

    public const PUNCH_TIME_IN = 'time_in';

    public const PUNCH_TIME_OUT = 'time_out';

    /**
     * @return array{
     *     today: string,
     *     today_dtr: ?EmployeeDtr,
     *     open_previous_dtr: ?EmployeeDtr,
     *     current_dtr: ?EmployeeDtr,
     *     can_time_in: bool,
     *     can_time_out: bool,
     *     open_previous_day: bool,
     *     recent_dtrs: \Illuminate\Support\Collection<int, EmployeeDtr>
     * }
     */
    public function status(string $employeeId): array
    {
        $today = CarbonImmutable::today()->toDateString();
        $todayDtr = $this->dtrForDate($employeeId, $today);
        $openPreviousDtr = $this->openPreviousDtr($employeeId);
        $current = $todayDtr ?: $openPreviousDtr;

        $hasTimeIn = filled($current?->timein_am);
        $hasTimeOut = filled($current?->timeout_pm) || filled($current?->timeout_nextday);

        return [
            'today' => $today,
            'today_dtr' => $todayDtr,
            'open_previous_dtr' => $openPreviousDtr,
            'current_dtr' => $current,
            'can_time_in' => $openPreviousDtr === null && ! filled($todayDtr?->timein_am),
            'can_time_out' => $hasTimeIn && ! $hasTimeOut,
            'open_previous_day' => $openPreviousDtr !== null,
            'recent_dtrs' => EmployeeDtr::query()
                ->where('emp_id', $employeeId)
                ->orderByDesc('dtr_date')
                ->orderByDesc('dtr_id')
                ->limit(10)
                ->get(),
        ];
    }

    /**
     * @return array{ok: bool, message: string, dtr: ?EmployeeDtr}
     */
    public function punch(string $employeeId, string $punch, ?CarbonImmutable $now = null): array
    {
        $today = CarbonImmutable::today()->toDateString();
        $now ??= CarbonImmutable::now();

        return DB::connection('hris')->transaction(function () use ($punch, $employeeId, $today, $now): array {
            Employee::query()
                ->whereKey($employeeId)
                ->lockForUpdate()
                ->firstOrFail();

            $todayDtr = $this->dtrForDate($employeeId, $today);
            $openPreviousDtr = $this->openPreviousDtr($employeeId);
            $dtr = $todayDtr ?: new EmployeeDtr([
                'emp_id' => $employeeId,
                'dtr_date' => $today,
                'machine_id' => self::MACHINE_ID,
            ]);

            if ($punch === self::PUNCH_TIME_IN) {
                if ($openPreviousDtr) {
                    return [
                        'ok' => false,
                        'message' => 'Record your pending time out before starting a new DTR day.',
                        'dtr' => $openPreviousDtr,
                    ];
                }

                if (filled($dtr->timein_am)) {
                    return [
                        'ok' => false,
                        'message' => 'Time in has already been recorded for today.',
                        'dtr' => $dtr,
                    ];
                }

                $dtr->timein_am = $now->toTimeString();
                $message = 'Time in recorded at '.$now->format('h:i A').'.';
            } else {
                $dtr = $todayDtr ?: $openPreviousDtr;

                if (! $dtr || blank($dtr->timein_am)) {
                    return [
                        'ok' => false,
                        'message' => 'Record your time in before timing out.',
                        'dtr' => $dtr,
                    ];
                }

                if (filled($dtr->timeout_pm) || filled($dtr->timeout_nextday)) {
                    return [
                        'ok' => false,
                        'message' => 'Time out has already been recorded for today.',
                        'dtr' => $dtr,
                    ];
                }

                $dtrDate = $dtr->dtr_date->toDateString();
                if ($dtrDate !== $today) {
                    $dtr->timeout_nextday = $now->toDateTimeString();
                } else {
                    $dtr->timeout_pm = $now->toTimeString();
                }

                $message = 'Time out recorded at '.$now->format('h:i A').'.';
            }

            $dtr->machine_id = $dtr->machine_id ?: self::MACHINE_ID;
            $dtr->save();

            return [
                'ok' => true,
                'message' => $message,
                'dtr' => $dtr->fresh(),
            ];
        });
    }

    public function dtrForDate(string $employeeId, string $date): ?EmployeeDtr
    {
        return EmployeeDtr::query()
            ->where('emp_id', $employeeId)
            ->whereDate('dtr_date', $date)
            ->orderBy('created_at')
            ->first();
    }

    public function openPreviousDtr(string $employeeId): ?EmployeeDtr
    {
        return EmployeeDtr::query()
            ->where('emp_id', $employeeId)
            ->whereDate('dtr_date', CarbonImmutable::yesterday()->toDateString())
            ->whereNotNull('timein_am')
            ->whereNull('timeout_pm')
            ->whereNull('timeout_nextday')
            ->orderBy('created_at')
            ->first();
    }
}
