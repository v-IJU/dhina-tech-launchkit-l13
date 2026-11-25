<?php

use App\Modules\Blog\Controllers\Web\BlogController;
use Illuminate\Support\Facades\Route;

Route::get('blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('blog/category/{category}', [BlogController::class, 'category'])->name('blog.category');
Route::get('blog/{post}', [BlogController::class, 'show'])->name('blog.show');
