<?php

use App\Modules\Blog\Controllers\Admin\CategoryController;
use App\Modules\Blog\Controllers\Admin\PostController;
use Illuminate\Support\Facades\Route;

Route::prefix('blog')->name('blog.')->group(function () {
    Route::post('posts/{post}/translate/{locale}', [PostController::class, 'translate'])->name('posts.translate');
    Route::post('posts/bulk', [PostController::class, 'bulk'])->name('posts.bulk');
    Route::patch('posts/{post}/restore', [PostController::class, 'restore'])->name('posts.restore')->withTrashed();
    Route::delete('posts/{post}/force', [PostController::class, 'forceDelete'])->name('posts.force-delete')->withTrashed();
    Route::resource('posts', PostController::class)->except('show')->parameters(['posts' => 'post:id']);
    Route::resource('categories', CategoryController::class)->except('show')->parameters(['categories' => 'category:id']);
});
