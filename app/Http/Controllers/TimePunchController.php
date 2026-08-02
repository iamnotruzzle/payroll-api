<?php

namespace App\Http\Controllers;

use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeDtr;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TimePunchController extends Controller
{
    public function index(): View
    {
        $employeeId = (string) auth()->user()->emp_id;
        $today = CarbonImmutable::today()->toDateString();
        $todayDtr = $this->dtrForDate($employeeId, $today);
        $openDtr = $todayDtr ?: $this->openPreviousDtr($employeeId);

        return view('time-punch.index', [
            'today' => $today,
            'todayDtr' => $todayDtr,
            'openDtr' => $openDtr,
            'recentDtrs' => EmployeeDtr::query()
                ->where('emp_id', $employeeId)
                ->orderByDesc('dtr_date')
                ->orderByDesc('dtr_id')
                ->limit(10)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'punch' => ['required', 'in:time_in,time_out'],
        ]);

        $employeeId = (string) auth()->user()->emp_id;
        $today = CarbonImmutable::today()->toDateString();
        $now = CarbonImmutable::now();

        $result = DB::connection('hris')->transaction(function () use ($data, $employeeId, $today, $now): array {
            // Serialize punches for this employee. Without this lock, two simultaneous
            // Time In requests can both pass the existence check and insert a row.
            Employee::query()
                ->whereKey($employeeId)
                ->lockForUpdate()
                ->firstOrFail();

            $todayDtr = $this->dtrForDate($employeeId, $today);
            $openPreviousDtr = $this->openPreviousDtr($employeeId);
            $dtr = $todayDtr ?: new EmployeeDtr([
                'emp_id' => $employeeId,
                'dtr_date' => $today,
                'machine_id' => '103',
            ]);

            if ($data['punch'] === 'time_in') {
                if ($openPreviousDtr) {
                    return ['warning', 'Record your pending time out before starting a new DTR day.'];
                }

                if (filled($dtr->timein_am)) {
                    return ['warning', 'Time in has already been recorded for today.'];
                }

                $dtr->timein_am = $now->toTimeString();
                $message = 'Time in recorded at '.$now->format('h:i A').'.';
            } else {
                $dtr = $todayDtr ?: $openPreviousDtr;

                if (! $dtr || blank($dtr->timein_am)) {
                    return ['warning', 'Record your time in before timing out.'];
                }

                if (filled($dtr->timeout_pm) || filled($dtr->timeout_nextday)) {
                    return ['warning', 'Time out has already been recorded for today.'];
                }

                $dtrDate = $dtr->dtr_date->toDateString();
                if ($dtrDate !== $today) {
                    $dtr->timeout_nextday = $now->toDateTimeString();
                } else {
                    $dtr->timeout_pm = $now->toTimeString();
                }

                $message = 'Time out recorded at '.$now->format('h:i A').'.';
            }

            $dtr->machine_id = $dtr->machine_id ?: '103';
            $dtr->save();

            return ['status', $message];
        });

        return back()->with($result[0], $result[1]);
    }

    private function dtrForDate(string $employeeId, string $date): ?EmployeeDtr
    {
        return EmployeeDtr::query()
            ->where('emp_id', $employeeId)
            ->whereDate('dtr_date', $date)
            ->orderBy('created_at')
            ->first();
    }

    private function openPreviousDtr(string $employeeId): ?EmployeeDtr
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
