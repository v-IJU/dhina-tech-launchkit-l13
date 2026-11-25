<?php

use App\Modules\Menus\Controllers\Admin\MenuController;
use Illuminate\Support\Facades\Route;

Route::get('menus', [MenuController::class, 'index'])->name('menus.index');
Route::post('menus', [MenuController::class, 'store'])->name('menus.store');
Route::post('menus/sort', [MenuController::class, 'sort'])->name('menus.sort');
Route::put('menus/{item}', [MenuController::class, 'update'])->name('menus.update');
Route::patch('menus/{item}/toggle', [MenuController::class, 'toggle'])->name('menus.toggle');
Route::delete('menus/{item}', [MenuController::class, 'destroy'])->name('menus.destroy');
