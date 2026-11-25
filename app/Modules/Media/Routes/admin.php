<?php

use App\Modules\Media\Controllers\Admin\MediaController;
use Illuminate\Support\Facades\Route;

Route::get('media/picker', [MediaController::class, 'picker'])->name('media.picker');
Route::get('media', [MediaController::class, 'index'])->name('media.index');
Route::post('media', [MediaController::class, 'store'])->name('media.store');
Route::put('media/{item}', [MediaController::class, 'update'])->name('media.update');
Route::delete('media/{item}', [MediaController::class, 'destroy'])->name('media.destroy');
