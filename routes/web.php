<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Schedule\SchedulePageController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/schedule', [SchedulePageController::class, 'dashboard'])->name('schedule.dashboard');
Route::get('/schedule/shift-codes', [SchedulePageController::class, 'shiftCodes'])->name('schedule.shift-codes');
