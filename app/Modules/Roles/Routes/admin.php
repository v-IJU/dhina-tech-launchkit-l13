<?php

use App\Modules\Roles\Controllers\Admin\PermissionController;
use App\Modules\Roles\Controllers\Admin\RoleController;
use Illuminate\Support\Facades\Route;

Route::resource('roles', RoleController::class)->except('show');

Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
Route::post('permissions', [PermissionController::class, 'store'])->name('permissions.store');
Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');
