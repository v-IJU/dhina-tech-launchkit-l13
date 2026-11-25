<?php

use App\Modules\Customers\Controllers\Api\AddressController;
use App\Modules\Customers\Controllers\Api\AuthController;
use App\Modules\Customers\Controllers\Api\ProfileController;
use App\Modules\Customers\Controllers\Api\TwoFactorController;
use Illuminate\Support\Facades\Route;

// Signed-in customers: /api/v1/shop/...  (middleware group "api.customer": customer token required)
// Route names get the "api.shop." prefix, e.g. route('api.shop.me').

Route::delete('auth/token', [AuthController::class, 'revoke'])->name('auth.revoke');

Route::get('me', [ProfileController::class, 'show'])->name('me');
Route::put('me', [ProfileController::class, 'update'])->name('me.update');
Route::put('me/password', [ProfileController::class, 'password'])->name('me.password');
Route::delete('me', [ProfileController::class, 'destroy'])->name('me.destroy');
Route::post('email/verification-notification', [ProfileController::class, 'resendVerification'])->middleware('throttle:6,1')->name('verification.send');

Route::post('two-factor', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
Route::post('two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
Route::post('two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('two-factor.recovery-codes');
Route::delete('two-factor', [TwoFactorController::class, 'disable'])->name('two-factor.disable');

// Needs a confirmed email (when Settings > Customers asks for it).
Route::middleware('customer.verified')->group(function () {
    Route::apiResource('addresses', AddressController::class);
    Route::post('addresses/{address}/default', [AddressController::class, 'makeDefault'])->name('addresses.default');
});
