<?php

use App\Modules\Inbox\Controllers\Web\FormController;
use Illuminate\Support\Facades\Route;

// Public forms, limited to 5 submissions per minute per visitor.
Route::middleware('throttle:forms')->group(function () {
    Route::post('contact-submit', [FormController::class, 'contact'])->name('contact.submit');
    Route::post('newsletter', [FormController::class, 'subscribe'])->name('newsletter.subscribe');
});
