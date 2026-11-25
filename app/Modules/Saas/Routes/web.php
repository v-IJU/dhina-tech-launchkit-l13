<?php

use App\Modules\Saas\Controllers\Web\PricingController;
use App\Modules\Saas\Controllers\Web\SignupController;
use App\Modules\Saas\Controllers\Web\WebhookController;
use Illuminate\Support\Facades\Route;

// Central domain only (SaaS mode). Tenants' websites never see these ("module:saas" → 404).
Route::get('pricing', PricingController::class)->name('saas.pricing');
Route::get('signup', [SignupController::class, 'create'])->name('saas.signup');
Route::post('signup', [SignupController::class, 'store'])->middleware('throttle:5,1')->name('saas.signup.store');
Route::get('signup/check', [SignupController::class, 'check'])->middleware('throttle:30,1')->name('saas.signup.check');

// Payment providers call these (no CSRF, signature checked instead). Set them in the Razorpay / Stripe dashboards.
Route::post('billing/webhooks/{gateway}', WebhookController::class)->whereIn('gateway', ['razorpay', 'stripe'])->name('saas.webhooks');
