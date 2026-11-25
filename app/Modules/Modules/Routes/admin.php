<?php

use App\Modules\Modules\Controllers\Admin\ModuleController;
use Illuminate\Support\Facades\Route;

Route::get('modules', [ModuleController::class, 'index'])->name('modules.index');
Route::post('modules', [ModuleController::class, 'generate'])->name('modules.generate');
Route::patch('modules/{slug}/toggle', [ModuleController::class, 'toggle'])->name('modules.toggle');
Route::post('modules/sync', [ModuleController::class, 'sync'])->name('modules.sync');
