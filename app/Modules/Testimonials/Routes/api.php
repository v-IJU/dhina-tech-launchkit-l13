<?php

use App\Modules\Testimonials\Controllers\Api\TestimonialController;
use Illuminate\Support\Facades\Route;

// Loaded inside the API group: URL /api/v1/testimonials, names "api.testimonials.*", Sanctum token required.
Route::apiResource('testimonials', TestimonialController::class)->parameters(['testimonials' => 'testimonial']);
