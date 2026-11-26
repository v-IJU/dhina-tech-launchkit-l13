<?php

use App\Core\Tenancy\Tenancy;
use App\Http\Controllers\Auth\PasskeyController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Features\UserImpersonation;

// Routes live inside each module:
//   app/Modules/{Module}/Routes/admin.php  -> admin panel (prefix, auth and permissions applied for you)
//   app/Modules/{Module}/Routes/web.php    -> public website
//
// Add app-wide routes that don't belong to a module here.

// SaaS mode: "Sign in as tenant owner" from the SaaS console lands here on the tenant's domain.
// One-time token, valid 60 seconds. Not recorded as the owner's own sign-in.
Route::get('impersonate/{token}', function (string $token) {
    abort_unless(Tenancy::inTenant(), 404);
    session()->put('saas_impersonation', true);

    return UserImpersonation::makeResponse($token);
})->where('token', '[A-Za-z0-9]+')->name('saas.impersonate');

// Admin users: passkey sign-in (fingerprint, Face ID…) and My profile > Security > Passkeys.
// Customers have their own passkey routes in the Customers module (/account/passkeys/...).
Route::prefix((string) config('starter.admin_path'))->group(function () {
    Route::middleware(['guest:web', 'throttle:10,1'])->group(function () {
        Route::get('passkeys/login/options', [PasskeyController::class, 'loginOptions'])->name('passkey.login-options');
        Route::post('passkeys/login', [PasskeyController::class, 'login'])->name('passkey.login');
    });

    Route::middleware(['auth:web', 'active', 'throttle:20,1'])->group(function () {
        Route::post('profile/passkeys/options', [PasskeyController::class, 'registrationOptions'])->name('admin.profile.passkeys.options');
        Route::post('profile/passkeys', [PasskeyController::class, 'store'])->name('admin.profile.passkeys.store');
        Route::delete('profile/passkeys/{passkeyId}', [PasskeyController::class, 'destroy'])->whereNumber('passkeyId')->name('admin.profile.passkeys.destroy');
    });
});
