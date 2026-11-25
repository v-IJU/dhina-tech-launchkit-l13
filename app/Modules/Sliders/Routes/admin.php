<?php

use App\Modules\Sliders\Controllers\Admin\SliderController;
use Illuminate\Support\Facades\Route;

// Loaded inside the admin group: URL /{admin_path}/sliders, names "admin.sliders.*"
Route::get('sliders/import', [SliderController::class, 'importForm'])->name('sliders.import');
Route::post('sliders/import', [SliderController::class, 'import'])->name('sliders.import.store');
Route::get('sliders/import/template', [SliderController::class, 'importTemplate'])->name('sliders.import.template');
Route::post('sliders/bulk', [SliderController::class, 'bulk'])->name('sliders.bulk');
Route::patch('sliders/{slider}/restore', [SliderController::class, 'restore'])->name('sliders.restore')->withTrashed();
Route::delete('sliders/{slider}/force', [SliderController::class, 'forceDelete'])->name('sliders.force-delete')->withTrashed();
Route::patch('sliders/{slider}/toggle', [SliderController::class, 'toggle'])->name('sliders.toggle');
Route::resource('sliders', SliderController::class)->except('show')->parameters(['sliders' => 'slider']);
