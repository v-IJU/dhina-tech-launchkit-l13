<?php

use App\Modules\Media\Controllers\Api\MediaController;
use Illuminate\Support\Facades\Route;

// /api/v1/media  (Sanctum token required)
Route::get('media', [MediaController::class, 'index'])->name('media.index');
Route::post('media', [MediaController::class, 'store'])->name('media.store');
