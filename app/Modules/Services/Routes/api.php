<?php

use App\Modules\Services\Controllers\Api\ServiceController;
use Illuminate\Support\Facades\Route;

// Loaded inside the API group: URL /api/v1/services, names "api.services.*", Sanctum token required.
Route::apiResource('services', ServiceController::class)->parameters(['services' => 'service']);
