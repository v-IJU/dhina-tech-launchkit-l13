<?php

use App\Modules\Pages\Controllers\Admin\PageController;
use Illuminate\Support\Facades\Route;

Route::post('pages/{page}/translate/{locale}', [PageController::class, 'translate'])->name('pages.translate');
Route::post('pages/{page}/duplicate', [PageController::class, 'duplicate'])->name('pages.duplicate');
Route::resource('pages', PageController::class)->except('show');
