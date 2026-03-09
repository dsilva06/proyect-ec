<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');

Route::middleware(['auth:sanctum', 'active_user', 'throttle:auth-session'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
});
