<?php

use App\Modules\Services\Controllers\Admin\ServiceController;
use Illuminate\Support\Facades\Route;

// Loaded inside the admin group: URL /{admin_path}/services, names "admin.services.*"
Route::get('services/import', [ServiceController::class, 'importForm'])->name('services.import');
Route::post('services/import', [ServiceController::class, 'import'])->name('services.import.store');
Route::get('services/import/template', [ServiceController::class, 'importTemplate'])->name('services.import.template');
Route::post('services/bulk', [ServiceController::class, 'bulk'])->name('services.bulk');
Route::patch('services/{service}/restore', [ServiceController::class, 'restore'])->name('services.restore')->withTrashed();
Route::delete('services/{service}/force', [ServiceController::class, 'forceDelete'])->name('services.force-delete')->withTrashed();
Route::patch('services/{service}/toggle', [ServiceController::class, 'toggle'])->name('services.toggle');
Route::resource('services', ServiceController::class)->except('show')->parameters(['services' => 'service']);
