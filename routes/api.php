<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

// Public routes with rate limiting
$loginRateLimit = env('RATE_LIMIT_LOGIN', 5);
Route::middleware(["throttle:{$loginRateLimit},1"])->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    // Route::post('/logout', [AuthController::class, 'logout']);
});
