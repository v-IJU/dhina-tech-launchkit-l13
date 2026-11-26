<?php

use App\Modules\Customers\Controllers\Web\AccountController;
use App\Modules\Customers\Controllers\Web\AddressController;
use App\Modules\Customers\Controllers\Web\AuthController;
use App\Modules\Customers\Controllers\Web\PasskeyController;
use App\Modules\Customers\Controllers\Web\PasswordController;
use App\Modules\Customers\Controllers\Web\SecurityController;
use App\Modules\Customers\Controllers\Web\VerificationController;
use Illuminate\Support\Facades\Route;

// Customer accounts on the public website: /account/...
// Guard "customer" (config/auth.php). Staff sign in separately at /admin.
Route::middleware('customer.enabled')->prefix('account')->name('customer.')->group(function () {
    Route::middleware('guest:customer')->group(function () {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:customer-login')->name('login.store');
        Route::get('register', [AuthController::class, 'showRegister'])->name('register');
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:customer-login')->name('register.store');
        Route::get('two-factor', [AuthController::class, 'showTwoFactor'])->name('two-factor');
        Route::post('two-factor', [AuthController::class, 'twoFactor'])->middleware('throttle:customer-2fa')->name('two-factor.store');
        Route::get('forgot-password', [PasswordController::class, 'showForgot'])->name('password.request');
        Route::post('forgot-password', [PasswordController::class, 'sendLink'])->middleware('throttle:customer-mail')->name('password.email');
        Route::get('reset-password/{token}', [PasswordController::class, 'showReset'])->name('password.reset');
        Route::post('reset-password', [PasswordController::class, 'reset'])->name('password.update');
        Route::get('passkeys/login/options', [PasskeyController::class, 'loginOptions'])->middleware('throttle:customer-login')->name('passkey.login-options');
        Route::post('passkeys/login', [PasskeyController::class, 'login'])->middleware('throttle:customer-login')->name('passkey.login');
    });

    // Link in the "Confirm your email" email. Signed, so it works whether or not the customer is signed in.
    Route::get('verify-email/{id}/{hash}', [VerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

    Route::middleware(['auth:customer', 'customer.active'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('stop-impersonating', [AuthController::class, 'stopImpersonating'])->name('impersonate.stop');
        Route::get('verify-email', [VerificationController::class, 'notice'])->name('verification.notice');
        Route::post('verify-email', [VerificationController::class, 'resend'])->middleware('throttle:customer-mail')->name('verification.send');

        // Everything below needs a confirmed email (when Settings > Customers asks for it).
        Route::middleware('customer.verified')->group(function () {
            Route::get('/', [AccountController::class, 'show'])->name('account');
            Route::put('profile', [AccountController::class, 'update'])->name('profile.update');
            Route::put('password', [AccountController::class, 'password'])->name('password.change');
            Route::delete('/', [AccountController::class, 'destroy'])->name('destroy');

            Route::get('addresses', [AddressController::class, 'index'])->name('addresses.index');
            Route::get('addresses/create', [AddressController::class, 'create'])->name('addresses.create');
            Route::post('addresses', [AddressController::class, 'store'])->name('addresses.store');
            Route::get('addresses/{address}/edit', [AddressController::class, 'edit'])->name('addresses.edit');
            Route::put('addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
            Route::post('addresses/{address}/default', [AddressController::class, 'makeDefault'])->name('addresses.default');
            Route::delete('addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');

            Route::get('security', [SecurityController::class, 'show'])->name('security');
            Route::post('security/two-factor/{action}', [SecurityController::class, 'twoFactor'])
                ->whereIn('action', ['enable', 'confirm', 'disable', 'recovery-codes'])->name('two-factor.manage');
            Route::delete('security/apps', [SecurityController::class, 'signOutApps'])->name('apps.destroy');
            Route::post('security/passkeys/options', [PasskeyController::class, 'registrationOptions'])->middleware('throttle:20,1')->name('passkeys.options');
            Route::post('security/passkeys', [PasskeyController::class, 'store'])->middleware('throttle:20,1')->name('passkeys.store');
            Route::delete('security/passkeys/{passkeyId}', [PasskeyController::class, 'destroy'])->whereNumber('passkeyId')->name('passkeys.destroy');
        });
    });
});
