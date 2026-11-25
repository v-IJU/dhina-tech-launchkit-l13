<?php

use App\Modules\Faqs\Controllers\Api\V2\FaqController;
use Illuminate\Support\Facades\Route;

// API v2: /api/v2/faqs..., names "api.v2.faqs.*"
Route::get('faqs/grouped', [FaqController::class, 'grouped'])->name('faqs.grouped');
Route::apiResource('faqs', FaqController::class)->parameters(['faqs' => 'faq']);