<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Permission\PermissionController;
use App\Http\Controllers\Role\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Public routes with rate limiting
// Format: throttle:max_attempts,decay_minutes
$loginRateLimit = env('RATE_LIMIT_LOGIN', 5);
Route::middleware(["throttle:{$loginRateLimit},1"])->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes
$apiRateLimit = env('RATE_LIMIT_API', 60);
Route::middleware(['auth:sanctum', "throttle:{$apiRateLimit},1"])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Users routes with permissions - Use route model binding
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index'])
            ->middleware('permission:users.view');
        Route::get('/status-counts', [UserController::class, 'statusCounts'])
            ->middleware('permission:users.view');
        Route::post('/', [UserController::class, 'store'])
            ->middleware('permission:users.create');
        // Use {user} for automatic model binding
        Route::put('/{user}', [UserController::class, 'update'])
            ->middleware('permission:users.update');
        Route::delete('/{user}', [UserController::class, 'destroy'])
            ->middleware('permission:users.delete');
    });

    // Roles routes with permissions
    Route::prefix('roles')->middleware('permission:roles.view')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        // Use {role} for automatic model binding
        Route::get('/{role}', [RoleController::class, 'show']);
        Route::post('/', [RoleController::class, 'store'])
            ->middleware('permission:roles.create');
        Route::put('/{role}', [RoleController::class, 'update'])
            ->middleware('permission:roles.update');
        Route::delete('/{role}', [RoleController::class, 'destroy'])
            ->middleware('permission:roles.delete');
    });

    // Permissions routes
    Route::prefix('permissions')->middleware('permission:permissions.view')->group(function () {
        Route::get('/', [PermissionController::class, 'index']);
        Route::get('/grouped', [PermissionController::class, 'grouped']);
        Route::post('/', [PermissionController::class, 'store'])
            ->middleware('permission:permissions.create');
        // Use {permission} for automatic model binding
        Route::put('/{permission}', [PermissionController::class, 'update'])
            ->middleware('permission:permissions.update');
        Route::delete('/{permission}', [PermissionController::class, 'destroy'])
            ->middleware('permission:permissions.delete');
    });
});
