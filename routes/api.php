<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Role\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Public routes with rate limiting
$loginRateLimit = env('RATE_LIMIT_LOGIN', 5);
Route::middleware(["throttle:{$loginRateLimit},1"])->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes
$apiRateLimit = env('RATE_LIMIT_API', 60);
Route::middleware(['auth:sanctum', "throttle:{$apiRateLimit},1"])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Users routes
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/status-counts', [UserController::class, 'statusCounts']);
        Route::post('/', [UserController::class, 'store']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::delete('/{user}', [UserController::class, 'destroy']);
    });

    // Roles routes
    Route::prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::get('/{role}', [RoleController::class, 'show']);
        Route::post('/', [RoleController::class, 'store']);
        Route::put('/{role}', [RoleController::class, 'update']);
        Route::delete('/{role}', [RoleController::class, 'destroy']);
    });
});
