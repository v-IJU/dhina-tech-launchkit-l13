<?php

use App\Modules\Settings\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
Route::put('settings/{tab}', [SettingsController::class, 'update'])->name('settings.update');
Route::post('settings/test-mail', [SettingsController::class, 'testMail'])->name('settings.test-mail');
