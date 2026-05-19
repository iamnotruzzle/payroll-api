<?php

use App\Http\Controllers\Auth\WebLoginController;
use App\Http\Controllers\Payroll\PayrollLoanImportController;
use App\Http\Controllers\Payroll\PayrollPageController;
use App\Http\Controllers\Schedule\SchedulePageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('schedule.dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [WebLoginController::class, 'create'])->name('login');
    Route::post('/login', [WebLoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [WebLoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/schedule', [SchedulePageController::class, 'dashboard'])->name('schedule.dashboard');
    Route::get('/schedule/shift-codes', [SchedulePageController::class, 'shiftCodes'])->name('schedule.shift-codes');
    Route::get('/schedule/employees', [SchedulePageController::class, 'employees'])->name('schedule.employees');
    Route::get('/schedule/employee-references', [SchedulePageController::class, 'employeeReferences'])->name('schedule.employee-references');
    Route::get('/schedule/rotation-groups', [SchedulePageController::class, 'rotationGroups'])->name('schedule.rotation-groups');
    Route::get('/schedule/staffing-requirements', [SchedulePageController::class, 'staffingRequirements'])->name('schedule.staffing-requirements');
    Route::get('/schedule/templates', [SchedulePageController::class, 'scheduleTemplates'])->name('schedule.templates');
    Route::get('/schedule/user-manual', [SchedulePageController::class, 'userManual'])->name('schedule.user-manual');
    Route::get('/schedule/print-settings', [SchedulePageController::class, 'printSettings'])->name('schedule.print-settings');
    Route::get('/schedule/{schedule}/print', [SchedulePageController::class, 'printable'])->name('schedule.print');

    Route::get('/payroll/dtr', [PayrollPageController::class, 'dtr'])->name('payroll.dtr');
    Route::get('/payroll/dtr-encoding', [PayrollPageController::class, 'dtrEncoding'])->name('payroll.dtr-encoding');
    Route::get('/payroll/mra', [PayrollPageController::class, 'mra'])->name('payroll.mra');
    Route::get('/payroll/generation', [PayrollPageController::class, 'generation'])->name('payroll.generation');
    Route::get('/payroll/loan-imports', [PayrollPageController::class, 'loanImports'])->name('payroll.loan-imports');
    Route::get('/payroll/loan-imports/template', [PayrollLoanImportController::class, 'template'])->name('payroll.loan-imports.template');
    Route::get('/payroll/loan-references', [PayrollPageController::class, 'loanReferences'])->name('payroll.loan-references');
    Route::get('/payroll/compensations', [PayrollPageController::class, 'compensations'])->name('payroll.compensations');
    Route::get('/payroll/holidays', [PayrollPageController::class, 'holidays'])->name('payroll.holidays');
});
