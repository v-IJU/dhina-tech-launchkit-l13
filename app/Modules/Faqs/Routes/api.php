<?php

use App\Modules\Faqs\Controllers\Api\FaqController;
use Illuminate\Support\Facades\Route;

// Loaded inside the API group: URL /api/v1/faqs, names "api.faqs.*", Sanctum token required.
Route::apiResource('faqs', FaqController::class)->parameters(['faqs' => 'faq']);
