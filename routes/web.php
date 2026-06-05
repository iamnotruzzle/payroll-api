<?php

use App\Http\Controllers\Admin\AdminPageController;
use App\Http\Controllers\Auth\WebLoginController;
use App\Http\Controllers\Payroll\PayrollLoanImportController;
use App\Http\Controllers\Payroll\PayrollPageController;
use App\Http\Controllers\Schedule\SchedulePageController;
use App\Http\Controllers\TimePunchController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $user = auth()->user();

    return $user
        ? redirect()->route(match (true) {
            $user->can('schedule.view') => 'schedule.dashboard',
            $user->can('payroll.view') => 'payroll.generation.configuration',
            $user->can('timekeeping.view') => 'payroll.dtr-encoding',
            $user->can('admin.users.view') => 'admin.user-accounts',
            $user->can('admin.roles.view') => 'admin.roles-permissions',
            default => 'access.pending',
        })
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [WebLoginController::class, 'create'])->name('login');
    Route::post('/login', [WebLoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [WebLoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/access-pending', fn () => view('auth.access-pending'))
    ->middleware('auth')
    ->name('access.pending');

Route::middleware('auth')->group(function () {
    Route::get('/time-punch', [TimePunchController::class, 'index'])->name('time-punch.index');
    Route::post('/time-punch', [TimePunchController::class, 'store'])->name('time-punch.store');

    Route::middleware('permission:admin.users.view')->group(function () {
        Route::get('/admin/user-accounts', [AdminPageController::class, 'userAccounts'])->name('admin.user-accounts');
    });

    Route::middleware('permission:admin.roles.view')->group(function () {
        Route::get('/admin/roles-permissions', [AdminPageController::class, 'rolesPermissions'])->name('admin.roles-permissions');
    });

    Route::middleware('permission:schedule.view')->group(function () {
        Route::get('/schedule', [SchedulePageController::class, 'dashboard'])->name('schedule.dashboard');
        Route::get('/schedule/shift-codes', [SchedulePageController::class, 'shiftCodes'])->name('schedule.shift-codes');
        Route::get('/schedule/employees', [SchedulePageController::class, 'employees'])->name('schedule.employees');
        Route::get('/schedule/rotation-groups', [SchedulePageController::class, 'rotationGroups'])->name('schedule.rotation-groups');
        Route::get('/schedule/staffing-requirements', [SchedulePageController::class, 'staffingRequirements'])->name('schedule.staffing-requirements');
        Route::get('/schedule/templates', [SchedulePageController::class, 'scheduleTemplates'])->name('schedule.templates');
        Route::get('/schedule/print-settings', [SchedulePageController::class, 'printSettings'])->name('schedule.print-settings');
        Route::get('/schedule/{schedule}/print', [SchedulePageController::class, 'printable'])->name('schedule.print');
    });

    Route::middleware('permission:references.view')->group(function () {
        Route::get('/schedule/employee-references', [SchedulePageController::class, 'employeeReferences'])->name('schedule.employee-references');
        Route::get('/schedule/user-manual', [SchedulePageController::class, 'userManual'])->name('schedule.user-manual');
        Route::get('/references/roles-permissions-manual', [SchedulePageController::class, 'rolesPermissionsManual'])->name('references.roles-permissions-manual');
        Route::get('/payroll/user-manual', [PayrollPageController::class, 'userManual'])->name('payroll.user-manual');
    });

    Route::middleware('permission:timekeeping.view')->group(function () {
        Route::get('/payroll/daily-attendance', [PayrollPageController::class, 'dailyAttendance'])->name('payroll.daily-attendance');
        Route::get('/payroll/attendance-report', [PayrollPageController::class, 'attendanceReport'])->name('payroll.attendance-report');
        Route::get('/payroll/dtr', [PayrollPageController::class, 'dtr'])->name('payroll.dtr');
        Route::get('/payroll/dtr-encoding', [PayrollPageController::class, 'dtrEncoding'])->name('payroll.dtr-encoding');
        Route::get('/payroll/dtr-encoding/print', [PayrollPageController::class, 'dtrPrintable'])->name('payroll.dtr-encoding.print');
        Route::get('/payroll/dtr-correction-requests', [PayrollPageController::class, 'dtrCorrectionRequests'])->name('payroll.dtr-correction-requests');
        Route::get('/payroll/dtr-correction-approvers', [PayrollPageController::class, 'dtrCorrectionApprovers'])->name('payroll.dtr-correction-approvers');
        Route::get('/payroll/mra', [PayrollPageController::class, 'mra'])->name('payroll.mra');
        Route::get('/payroll/holidays', [PayrollPageController::class, 'holidays'])->name('payroll.holidays');
    });

    Route::middleware('permission:payroll.view')->group(function () {
        Route::get('/payroll/generation/configuration', [PayrollPageController::class, 'generationConfiguration'])->name('payroll.generation.configuration');
        Route::get('/payroll/generation', [PayrollPageController::class, 'generation'])->name('payroll.generation');
        Route::get('/payroll/generation/hazard', [PayrollPageController::class, 'hazardGeneration'])->name('payroll.generation.hazard');
        Route::get('/payroll/generation/medicare', [PayrollPageController::class, 'medicareGeneration'])->name('payroll.generation.medicare');
        Route::get('/payroll/loan-imports', [PayrollPageController::class, 'loanImports'])->name('payroll.loan-imports');
        Route::get('/payroll/loan-imports/template', [PayrollLoanImportController::class, 'template'])->name('payroll.loan-imports.template');
        Route::get('/payroll/loan-references', [PayrollPageController::class, 'loanReferences'])->name('payroll.loan-references');
        Route::get('/payroll/compensations', [PayrollPageController::class, 'compensations'])->name('payroll.compensations');
        Route::get('/payroll/adjustment-types', [PayrollPageController::class, 'adjustmentTypes'])->name('payroll.adjustment-types');
        Route::get('/payroll/deduction-programs', [PayrollPageController::class, 'deductionPrograms'])->name('payroll.deduction-programs');
        Route::get('/payroll/statutory-contributions', [PayrollPageController::class, 'statutoryContributions'])->name('payroll.statutory-contributions');
        Route::get('/payroll/history', [PayrollPageController::class, 'history'])->name('payroll.history');
    });
});
