<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::get('/employees', [EmployeeController::class, 'index']);
Route::get('/employees/search', [EmployeeController::class, 'search']);
Route::get('/employees/by-department/{department_id}', [EmployeeController::class, 'byDepartment']);
Route::get('/employees/{emp_id}', [EmployeeController::class, 'show']);
