<?php

use App\Modules\Customers\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

// Public customer endpoints: /api/v1/shop/...  (middleware group "api.customer.guest", 5 requests/min per email + IP)
Route::post('auth/register', [AuthController::class, 'register'])->name('auth.register');
Route::post('auth/token', [AuthController::class, 'token'])->name('auth.token');
Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
