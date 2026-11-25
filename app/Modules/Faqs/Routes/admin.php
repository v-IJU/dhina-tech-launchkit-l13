<?php

use App\Modules\Faqs\Controllers\Admin\FaqController;
use Illuminate\Support\Facades\Route;

// Loaded inside the admin group: URL /{admin_path}/faqs, names "admin.faqs.*"
Route::get('faqs/import', [FaqController::class, 'importForm'])->name('faqs.import');
Route::post('faqs/import', [FaqController::class, 'import'])->name('faqs.import.store');
Route::get('faqs/import/template', [FaqController::class, 'importTemplate'])->name('faqs.import.template');
Route::post('faqs/bulk', [FaqController::class, 'bulk'])->name('faqs.bulk');
Route::patch('faqs/{faq}/restore', [FaqController::class, 'restore'])->name('faqs.restore')->withTrashed();
Route::delete('faqs/{faq}/force', [FaqController::class, 'forceDelete'])->name('faqs.force-delete')->withTrashed();
Route::patch('faqs/{faq}/toggle', [FaqController::class, 'toggle'])->name('faqs.toggle');
Route::resource('faqs', FaqController::class)->except('show')->parameters(['faqs' => 'faq']);
