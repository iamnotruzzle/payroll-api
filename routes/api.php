<?php

use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\ScheduleModuleController;
use App\Http\Controllers\Api\ShiftCodeController;
use Illuminate\Support\Facades\Route;

// Route::post('/login', [AuthController::class, 'login']);

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::prefix('employees')->group(function () {
    Route::get('/', [EmployeeController::class, 'index']);
    Route::get('/search', [EmployeeController::class, 'search']);
    Route::get('/by-department/{department_id}', [EmployeeController::class, 'byDepartment']);
    Route::get('/{emp_id}', [EmployeeController::class, 'show']);
});

Route::prefix('schedule')->group(function () {
    Route::post('/employee-references/sync', [ScheduleModuleController::class, 'syncEmployees']);
    Route::post('/employee-settings', [ScheduleModuleController::class, 'settings']);

    Route::get('/shift-codes', [ShiftCodeController::class, 'index']);
    Route::post('/shift-codes', [ShiftCodeController::class, 'store']);
    Route::post('/shift-codes/seed-defaults', [ShiftCodeController::class, 'seedDefaults']);
    Route::get('/shift-codes/{shiftCode}', [ShiftCodeController::class, 'show']);
    Route::put('/shift-codes/{shiftCode}', [ShiftCodeController::class, 'update']);

    Route::get('/rotation-groups', [ScheduleModuleController::class, 'rotationGroups']);
    Route::post('/rotation-groups', [ScheduleModuleController::class, 'saveRotationGroup']);
    Route::get('/staffing-requirements', [ScheduleModuleController::class, 'staffingRequirements']);
    Route::post('/staffing-requirements', [ScheduleModuleController::class, 'saveStaffingRequirement']);
    Route::get('/templates', [ScheduleModuleController::class, 'templates']);
    Route::post('/templates', [ScheduleModuleController::class, 'saveTemplate']);

    Route::post('/monthly/generate-draft', [ScheduleModuleController::class, 'generateDraft']);
    Route::get('/monthly/{schedule}', [ScheduleModuleController::class, 'showSchedule']);
    Route::get('/monthly/{schedule}/conflicts', [ScheduleModuleController::class, 'conflicts']);
    Route::post('/monthly/{schedule}/review', [ScheduleModuleController::class, 'review']);
    Route::post('/monthly/{schedule}/approve', [ScheduleModuleController::class, 'approve']);
    Route::post('/monthly/{schedule}/lock', [ScheduleModuleController::class, 'lock']);
});
