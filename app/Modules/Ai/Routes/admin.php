<?php

use App\Modules\Ai\Controllers\Admin\AiController;
use Illuminate\Support\Facades\Route;

Route::get('ai', [AiController::class, 'index'])->name('ai.index');
Route::post('ai/ask', [AiController::class, 'ask'])->middleware('throttle:20,1')->name('ai.ask');
