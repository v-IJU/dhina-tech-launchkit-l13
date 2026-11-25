<?php

use App\Modules\Users\Controllers\Admin\ImpersonationController;
use App\Modules\Users\Controllers\Admin\LoginHistoryController;
use App\Modules\Users\Controllers\Admin\ProfileController;
use App\Modules\Users\Controllers\Admin\UserController;
use App\Modules\Users\Controllers\Admin\UserPermissionController;
use Illuminate\Support\Facades\Route;

Route::get('users/logins', LoginHistoryController::class)->name('users.logins');
Route::get('users/import', [UserController::class, 'importForm'])->name('users.import');
Route::post('users/import', [UserController::class, 'import'])->name('users.import.store');
Route::get('users/import/template', [UserController::class, 'importTemplate'])->name('users.import.template');
Route::post('users/bulk', [UserController::class, 'bulk'])->name('users.bulk');
Route::patch('users/{user}/restore', [UserController::class, 'restore'])->name('users.restore')->withTrashed();
Route::delete('users/{user}/force', [UserController::class, 'forceDelete'])->name('users.force-delete')->withTrashed();
Route::resource('users', UserController::class)->except('show');
Route::get('users/{user}/permissions', [UserPermissionController::class, 'edit'])->name('users.permissions');
Route::put('users/{user}/permissions', [UserPermissionController::class, 'update'])->name('users.permissions.update');

Route::post('users/{user}/impersonate', [ImpersonationController::class, 'start'])->name('users.impersonate');
Route::post('impersonate/stop', [ImpersonationController::class, 'stop'])->name('users.impersonate.stop');

// Every signed-in user can manage their own profile.
Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
// Two-factor setup with the password typed on the same form (no separate confirm-password page).
Route::post('profile/two-factor/{action}', [ProfileController::class, 'twoFactor'])->whereIn('action', ['enable', 'disable', 'recovery-codes'])->name('profile.two-factor');
Route::delete('profile/sessions', [ProfileController::class, 'logoutOtherSessions'])->name('profile.sessions.destroy');
Route::post('profile/tokens', [ProfileController::class, 'createToken'])->name('profile.tokens.store');
Route::delete('profile/tokens/{token}', [ProfileController::class, 'destroyToken'])->name('profile.tokens.destroy');
