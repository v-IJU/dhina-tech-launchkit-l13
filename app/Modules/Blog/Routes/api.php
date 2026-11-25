<?php

use App\Modules\Blog\Controllers\Api\PostController;
use Illuminate\Support\Facades\Route;

// Loaded inside the API group: /api/v1/..., names "api.*", Sanctum token required.
Route::prefix('blog')->name('blog.')->group(function () {
    Route::get('categories', [PostController::class, 'categories'])->name('categories');
    Route::apiResource('posts', PostController::class)->parameters(['posts' => 'post:id']);
});
