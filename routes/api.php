<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

// Core API routes: /api/v1/...  (module endpoints live in app/Modules/*/Routes/api.php)
// Tokens are not tied to a version: a token from /api/v1/auth/token works on /api/v2 too.

// Get a token with email + password (+ 6-digit code when 2FA is on).
Route::post('auth/token', [AuthController::class, 'token'])->middleware('throttle:api-token')->name('api.auth.token');

Route::middleware('api.staff')->group(function () {
    Route::get('me', [AuthController::class, 'me'])->name('api.me');
    Route::delete('auth/token', [AuthController::class, 'revoke'])->name('api.auth.revoke');
});
