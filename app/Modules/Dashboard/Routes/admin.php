<?php

use App\Modules\Dashboard\Controllers\Admin\DashboardController;
use App\Modules\Dashboard\Controllers\Admin\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

// Bell dropdown in the top bar (every signed-in user).
Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
Route::get('notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
