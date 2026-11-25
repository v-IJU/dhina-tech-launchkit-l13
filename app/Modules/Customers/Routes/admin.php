<?php

use App\Modules\Customers\Controllers\Admin\CustomerActionController;
use App\Modules\Customers\Controllers\Admin\CustomerAddressController;
use App\Modules\Customers\Controllers\Admin\CustomerController;
use App\Modules\Customers\Controllers\Admin\CustomerLoginController;
use Illuminate\Support\Facades\Route;

Route::get('customers/logins', CustomerLoginController::class)->name('customers.logins');
Route::get('customers/import', [CustomerController::class, 'importForm'])->name('customers.import');
Route::post('customers/import', [CustomerController::class, 'import'])->name('customers.import.store');
Route::get('customers/import/template', [CustomerController::class, 'importTemplate'])->name('customers.import.template');
Route::post('customers/bulk', [CustomerController::class, 'bulk'])->name('customers.bulk');
Route::patch('customers/{customer}/restore', [CustomerController::class, 'restore'])->name('customers.restore')->withTrashed();
Route::delete('customers/{customer}/force', [CustomerController::class, 'forceDelete'])->name('customers.force-delete')->withTrashed();
Route::resource('customers', CustomerController::class);

// Account actions on the customer profile page.
Route::controller(CustomerActionController::class)->prefix('customers/{customer}')->name('customers.')->group(function () {
    Route::post('block', 'toggleBlock')->name('block');
    Route::post('password-reset', 'sendPasswordReset')->name('password-reset');
    Route::post('verify-email', 'verifyEmail')->name('verify-email');
    Route::post('resend-verification', 'resendVerification')->name('resend-verification');
    Route::delete('two-factor', 'resetTwoFactor')->name('two-factor.reset');
    Route::delete('apps', 'revokeApps')->name('apps.revoke');
    Route::delete('apps/{token}', 'revokeApp')->whereNumber('token')->name('apps.revoke-one');
    Route::post('impersonate', 'impersonate')->name('impersonate');
});

// Saved addresses (scoped: the address must belong to the customer in the URL).
Route::resource('customers.addresses', CustomerAddressController::class)->except(['index', 'show'])->scoped();
