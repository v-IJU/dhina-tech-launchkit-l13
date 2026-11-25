<?php

use App\Modules\Testimonials\Controllers\Admin\TestimonialController;
use Illuminate\Support\Facades\Route;

// Loaded inside the admin group: URL /{admin_path}/testimonials, names "admin.testimonials.*"
Route::get('testimonials/import', [TestimonialController::class, 'importForm'])->name('testimonials.import');
Route::post('testimonials/import', [TestimonialController::class, 'import'])->name('testimonials.import.store');
Route::get('testimonials/import/template', [TestimonialController::class, 'importTemplate'])->name('testimonials.import.template');
Route::post('testimonials/bulk', [TestimonialController::class, 'bulk'])->name('testimonials.bulk');
Route::patch('testimonials/{testimonial}/restore', [TestimonialController::class, 'restore'])->name('testimonials.restore')->withTrashed();
Route::delete('testimonials/{testimonial}/force', [TestimonialController::class, 'forceDelete'])->name('testimonials.force-delete')->withTrashed();
Route::patch('testimonials/{testimonial}/toggle', [TestimonialController::class, 'toggle'])->name('testimonials.toggle');
Route::resource('testimonials', TestimonialController::class)->except('show')->parameters(['testimonials' => 'testimonial']);
