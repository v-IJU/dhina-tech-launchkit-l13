<?php

use App\Modules\Activity\Controllers\Admin\ActivityController;
use Illuminate\Support\Facades\Route;

Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');
Route::get('activity/{activity}', [ActivityController::class, 'show'])->name('activity.show');
Route::delete('activity', [ActivityController::class, 'prune'])->name('activity.prune');
