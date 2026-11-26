<?php

use App\Modules\Chat\Controllers\Admin\ChatController;
use Illuminate\Support\Facades\Route;

// Team chat. Everything needs chat.view; starting new conversations also chat.start (checked in the controller).
Route::middleware('can:chat.view')->prefix('chat')->name('chat.')->group(function () {
    Route::get('/', [ChatController::class, 'index'])->name('index');
    Route::get('conversations', [ChatController::class, 'conversations'])->name('conversations');
    Route::get('users', [ChatController::class, 'users'])->name('users');
    Route::post('start', [ChatController::class, 'start'])->name('start');
    Route::get('{conversation}/messages', [ChatController::class, 'messages'])->whereNumber('conversation')->name('messages');
    Route::post('{conversation}/messages', [ChatController::class, 'send'])->whereNumber('conversation')->middleware('throttle:60,1')->name('send');
    Route::post('{conversation}/read', [ChatController::class, 'read'])->whereNumber('conversation')->name('read');
    Route::post('{conversation}/typing', [ChatController::class, 'typing'])->whereNumber('conversation')->middleware('throttle:30,1')->name('typing');
    Route::delete('messages/{message}', [ChatController::class, 'destroy'])->whereNumber('message')->name('destroy');
    Route::get('attachments/{message}', [ChatController::class, 'attachment'])->whereNumber('message')->name('attachment');
});
