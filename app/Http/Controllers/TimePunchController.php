<?php

namespace App\Http\Controllers;

use App\Models\Hris\EmployeeDtr;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $todayDtr = $this->dtrForDate($employeeId, $today);
        $openPreviousDtr = $this->openPreviousDtr($employeeId);
        $dtr = $todayDtr ?: new EmployeeDtr([
            'emp_id' => $employeeId,
            'dtr_date' => $today,
            'machine_id' => '103',
        ]);

        if ($data['punch'] === 'time_in') {
            if ($openPreviousDtr) {
                return back()->with('warning', 'Record your pending time out before starting a new DTR day.');
            }

            if (filled($dtr->timein_am)) {
                return back()->with('warning', 'Time in has already been recorded for today.');
            }

            $dtr->timein_am = $now->toTimeString();
            $message = 'Time in recorded at '.$now->format('h:i A').'.';
        } else {
            $dtr = $todayDtr ?: $openPreviousDtr;

            if (! $dtr) {
                return back()->with('warning', 'Record your time in before timing out.');
            }

            if (blank($dtr->timein_am)) {
                return back()->with('warning', 'Record your time in before timing out.');
            }

            if (filled($dtr->timeout_pm) || filled($dtr->timeout_nextday)) {
                return back()->with('warning', 'Time out has already been recorded for today.');
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

        return back()->with('status', $message);
    }

    private function dtrForDate(string $employeeId, string $date): ?EmployeeDtr
    {
        return EmployeeDtr::query()
            ->where('emp_id', $employeeId)
            ->whereDate('dtr_date', $date)
            ->orderBy('dtr_id')
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
            ->orderBy('dtr_id')
            ->first();
    }
}
