<?php

use App\Modules\Saas\Controllers\Admin\InvoiceController;
use App\Modules\Saas\Controllers\Admin\OverviewController;
use App\Modules\Saas\Controllers\Admin\PlanController;
use App\Modules\Saas\Controllers\Admin\SubscriptionController;
use App\Modules\Saas\Controllers\Admin\TenantController;
use Illuminate\Support\Facades\Route;

// SaaS console (central domain only; "module:saas" returns 404 anywhere else). Names: admin.saas.*
Route::prefix('saas')->name('saas.')->group(function () {
    Route::get('/', OverviewController::class)->name('overview');

    Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::get('tenants/create', [TenantController::class, 'create'])->name('tenants.create');
    Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
    Route::get('tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
    Route::get('tenants/{tenant}/edit', [TenantController::class, 'edit'])->name('tenants.edit');
    Route::put('tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
    Route::delete('tenants/{tenant}', [TenantController::class, 'destroy'])->name('tenants.destroy');
    Route::post('tenants/{tenant}/suspend', [TenantController::class, 'toggleSuspend'])->name('tenants.suspend');
    Route::post('tenants/{tenant}/extend-trial', [TenantController::class, 'extendTrial'])->name('tenants.extend-trial');
    Route::post('tenants/{tenant}/impersonate', [TenantController::class, 'impersonate'])->name('tenants.impersonate');
    Route::post('tenants/{tenant}/domains', [TenantController::class, 'addDomain'])->name('tenants.domains.store');
    Route::delete('tenants/{tenant}/domains/{domain}', [TenantController::class, 'removeDomain'])->name('tenants.domains.destroy');
    Route::post('tenants/{tenant}/usage', [TenantController::class, 'refreshUsage'])->name('tenants.usage');
    Route::post('tenants/{tenant}/payments', [SubscriptionController::class, 'recordPayment'])->name('tenants.payments.store');

    Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');

    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('invoices/{invoice}/paid', [InvoiceController::class, 'markPaid'])->name('invoices.paid');
    Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');

    Route::resource('plans', PlanController::class)->except('show');
});
