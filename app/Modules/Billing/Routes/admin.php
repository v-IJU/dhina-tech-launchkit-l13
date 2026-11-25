<?php

use App\Modules\Billing\Controllers\Admin\BillingController;
use Illuminate\Support\Facades\Route;

// Inside a tenant's admin panel only (SaaS mode). Still open when the tenant is suspended, so owners can pay.
Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
Route::get('billing/plans', [BillingController::class, 'plans'])->name('billing.plans');
Route::post('billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
Route::get('billing/return', [BillingController::class, 'returned'])->name('billing.return');
Route::post('billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
Route::get('billing/invoices/{invoice}', [BillingController::class, 'invoice'])->whereNumber('invoice')->name('billing.invoice');
