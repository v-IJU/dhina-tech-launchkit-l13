<?php

use App\Modules\Dashboard\Controllers\Admin\DashboardController;
use App\Modules\Dashboard\Controllers\Admin\ImportRunController;
use App\Modules\Dashboard\Controllers\Admin\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

// Bell dropdown in the top bar (every signed-in user).
Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
Route::get('notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');

// Import progress / result (every module's import), and the skipped rows as a spreadsheet.
Route::get('imports/{importRun}', [ImportRunController::class, 'show'])->whereNumber('importRun')->name('imports.show');
Route::get('imports/{importRun}/errors', [ImportRunController::class, 'errors'])->whereNumber('importRun')->name('imports.errors');
