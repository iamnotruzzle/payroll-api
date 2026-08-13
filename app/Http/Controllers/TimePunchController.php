<?php

namespace App\Http\Controllers;

use App\Services\Attendance\TimePunchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimePunchController extends Controller
{
    public function index(TimePunchService $punches): View
    {
        $employeeId = (string) auth()->user()->emp_id;
        $status = $punches->status($employeeId);

        return view('time-punch.index', [
            'today' => $status['today'],
            'todayDtr' => $status['today_dtr'],
            'openDtr' => $status['current_dtr'],
            'recentDtrs' => $status['recent_dtrs'],
        ]);
    }

    public function store(Request $request, TimePunchService $punches): RedirectResponse
    {
        $data = $request->validate([
            'punch' => ['required', 'in:time_in,time_out'],
        ]);

        $result = $punches->punch((string) auth()->user()->emp_id, $data['punch']);

        return back()->with($result['ok'] ? 'status' : 'warning', $result['message']);
    }
}
