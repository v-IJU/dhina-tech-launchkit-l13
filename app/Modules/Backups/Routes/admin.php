<?php

use App\Modules\Backups\Controllers\Admin\BackupController;
use Illuminate\Support\Facades\Route;

Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
Route::post('backups', [BackupController::class, 'store'])->name('backups.store');
Route::get('backups/download', [BackupController::class, 'download'])->name('backups.download');
Route::delete('backups', [BackupController::class, 'destroy'])->name('backups.destroy');
Route::post('backups/tools/{tool}', [BackupController::class, 'tool'])->name('backups.tool');
