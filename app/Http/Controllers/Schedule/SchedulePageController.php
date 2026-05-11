<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;

class SchedulePageController extends Controller
{
    public function shiftCodes()
    {
        return view('schedule.shift-codes');
    }

    public function dashboard()
    {
        return view('schedule.dashboard');
    }
}
