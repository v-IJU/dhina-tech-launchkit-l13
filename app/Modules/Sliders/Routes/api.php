<?php

use App\Modules\Sliders\Controllers\Api\SliderController;
use Illuminate\Support\Facades\Route;

// Loaded inside the API group: URL /api/v1/sliders, names "api.sliders.*", Sanctum token required.
Route::apiResource('sliders', SliderController::class)->parameters(['sliders' => 'slider']);
