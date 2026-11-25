<?php

use App\Modules\Monitoring\Controllers\Admin\ErrorController;
use App\Modules\Monitoring\Controllers\Admin\FailedJobController;
use Illuminate\Support\Facades\Route;

Route::get('errors', [ErrorController::class, 'index'])->name('errors.index');
Route::post('errors/bulk', [ErrorController::class, 'bulk'])->name('errors.bulk');
Route::delete('errors/resolved', [ErrorController::class, 'clearResolved'])->name('errors.clear-resolved');
Route::get('errors/{error}', [ErrorController::class, 'show'])->name('errors.show');
Route::post('errors/{error}/resolve', [ErrorController::class, 'resolve'])->name('errors.resolve');
Route::delete('errors/{error}', [ErrorController::class, 'destroy'])->name('errors.destroy');

Route::get('failed-jobs', [FailedJobController::class, 'index'])->name('failed-jobs.index');
Route::post('failed-jobs/bulk', [FailedJobController::class, 'bulk'])->name('failed-jobs.bulk');
Route::post('failed-jobs/retry-all', [FailedJobController::class, 'retryAll'])->name('failed-jobs.retry-all');
Route::delete('failed-jobs', [FailedJobController::class, 'flush'])->name('failed-jobs.flush');
Route::get('failed-jobs/{job}', [FailedJobController::class, 'show'])->name('failed-jobs.show');
Route::post('failed-jobs/{job}/retry', [FailedJobController::class, 'retry'])->name('failed-jobs.retry');
Route::delete('failed-jobs/{job}', [FailedJobController::class, 'destroy'])->name('failed-jobs.destroy');
