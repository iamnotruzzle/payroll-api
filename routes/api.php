<?php

use App\Http\Controllers\Api\DtrLabelOptionController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\Api\PayrollOperationsController;
use App\Http\Controllers\Api\PayrollAdditionalController;
use App\Http\Controllers\Api\PayrollDeductionController;
use App\Http\Controllers\Api\PayrollTypeController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\ReferenceDataController;
use App\Http\Controllers\Api\SalaryGradeController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\ScheduleModuleController;
use App\Http\Controllers\Api\ShiftCodeController;
use App\Http\Controllers\Api\TimeTemplateController;
use App\Http\Controllers\Api\UserAccountController;
use Illuminate\Support\Facades\Route;

// Route::post('/login', [AuthController::class, 'login']);


Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::get('/users', [UserAccountController::class, 'index']);
Route::get('/departments', [ReferenceDataController::class, 'departments']);
Route::get('/payroll-items', [ReferenceDataController::class, 'payrollItems']);
Route::get('/payroll-items/calculations', [ReferenceDataController::class, 'payrollItemCalculations']);

Route::prefix('employees')->group(function () {
    Route::get('/', [EmployeeController::class, 'index']);
    Route::get('/by-department/{departmentId}', [EmployeeController::class, 'byDepartment']);
    Route::get('/{empId}', [EmployeeController::class, 'show']);
    Route::patch('/{empId}/active', [EmployeeController::class, 'setActive']);
    Route::get('/{empId}/dependents', [EmployeeController::class, 'dependents']);
    Route::get('/{empId}/education', [EmployeeController::class, 'education']);
    Route::get('/{empId}/eligibilities', [EmployeeController::class, 'eligibilities']);
    Route::get('/{empId}/work-experiences', [EmployeeController::class, 'workExperiences']);
    Route::get('/{empId}/trainings', [EmployeeController::class, 'trainings']);
    Route::get('/{empId}/other-info', [EmployeeController::class, 'otherInfo']);
    Route::get('/{empId}/leaves', [EmployeeController::class, 'leaves']);
    Route::get('/{empId}/dtrs', [EmployeeController::class, 'dtrs']);
});

Route::prefix('positions')->group(function () {
    Route::get('/', [PositionController::class, 'index']);
    Route::get('/salary-grades', [PositionController::class, 'salaryGrades']);
});

Route::prefix('salary-grades')->group(function () {
    Route::get('/', [SalaryGradeController::class, 'index']);
    Route::get('/options/salary-grades', [SalaryGradeController::class, 'salaryGradeOptions']);
    Route::get('/options/tranches', [SalaryGradeController::class, 'trancheOptions']);
});

Route::prefix('time-templates')->group(function () {
    Route::get('/', [TimeTemplateController::class, 'index']);
    Route::post('/', [TimeTemplateController::class, 'store']);
    Route::put('/{id}', [TimeTemplateController::class, 'update']);
});

Route::prefix('holidays')->group(function () {
    Route::get('/', [HolidayController::class, 'index']);
    Route::post('/', [HolidayController::class, 'store']);
    Route::put('/{id}', [HolidayController::class, 'update']);
});

Route::prefix('dtr-label-options')->group(function () {
    Route::get('/', [DtrLabelOptionController::class, 'index']);
    Route::post('/', [DtrLabelOptionController::class, 'store']);
    Route::put('/{id}', [DtrLabelOptionController::class, 'update']);
});

Route::prefix('payroll-types')->group(function () {
    Route::get('/', [PayrollTypeController::class, 'index']);
});

Route::prefix('payroll-additionals')->group(function () {
    Route::get('/', [PayrollAdditionalController::class, 'index']);
    Route::post('/', [PayrollAdditionalController::class, 'store']);
    Route::put('/{id}', [PayrollAdditionalController::class, 'update']);
    Route::delete('/{id}', [PayrollAdditionalController::class, 'destroy']);
});

Route::prefix('payroll-deductions')->group(function () {
    Route::get('/', [PayrollDeductionController::class, 'index']);
    Route::post('/', [PayrollDeductionController::class, 'store']);
    Route::put('/{id}', [PayrollDeductionController::class, 'update']);
    Route::delete('/{id}', [PayrollDeductionController::class, 'destroy']);
});

Route::prefix('payroll')->group(function () {
    Route::get('/periods', [PayrollOperationsController::class, 'periods']);
    Route::get('/runs', [PayrollOperationsController::class, 'runs']);
    Route::get('/runs/{id}', [PayrollOperationsController::class, 'run']);
    Route::get('/runs/{id}/lines', [PayrollOperationsController::class, 'runLines']);
    Route::get('/payslips', [PayrollOperationsController::class, 'payslips']);

    Route::get('/mra-reports', [PayrollOperationsController::class, 'mraReports']);
    Route::post('/mra-reports', [PayrollOperationsController::class, 'saveMraReport']);
    Route::post('/mra-reports/finalize', [PayrollOperationsController::class, 'finalizeMraReport']);

    Route::get('/dtr-labels', [PayrollOperationsController::class, 'dtrLabels']);
    Route::post('/dtr-labels/bulk', [PayrollOperationsController::class, 'saveDtrLabels']);

    Route::get('/dtr-adjustments', [PayrollOperationsController::class, 'dtrAdjustments']);
    Route::post('/dtr-adjustments/bulk', [PayrollOperationsController::class, 'saveDtrAdjustments']);

    Route::get('/dtr-schedule-encodings', [PayrollOperationsController::class, 'dtrScheduleEncodings']);
    Route::post('/dtr-schedule-encodings/bulk', [PayrollOperationsController::class, 'saveDtrScheduleEncodings']);

    Route::get('/leave-credit-adjustments', [PayrollOperationsController::class, 'leaveCreditAdjustments']);
    Route::post('/leave-credit-adjustments/replace', [PayrollOperationsController::class, 'replaceLeaveCreditAdjustments']);

    Route::get('/employee-deductions', [PayrollOperationsController::class, 'employeeDeductions']);
    Route::get('/office-dtr-state', [PayrollOperationsController::class, 'officeDtrState']);
    Route::get('/mra-preview-state', [PayrollOperationsController::class, 'mraPreviewState']);
    Route::post('/generate-run', [PayrollOperationsController::class, 'generateRun']);
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
    Route::put('/assignments/{assignment}', [ScheduleModuleController::class, 'updateAssignment']);
    Route::post('/monthly/{schedule}/review', [ScheduleModuleController::class, 'review']);
    Route::post('/monthly/{schedule}/approve', [ScheduleModuleController::class, 'approve']);
    Route::post('/monthly/{schedule}/lock', [ScheduleModuleController::class, 'lock']);
});
