<?php

use App\Core\Tenancy\Tenancy;
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
