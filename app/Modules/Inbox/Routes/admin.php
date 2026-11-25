<?php

use App\Modules\Inbox\Controllers\Admin\InboxController;
use App\Modules\Inbox\Controllers\Admin\SubscriberController;
use Illuminate\Support\Facades\Route;

Route::get('inbox', [InboxController::class, 'index'])->name('inbox.index');
Route::get('inbox/{message}', [InboxController::class, 'show'])->name('inbox.show');
Route::patch('inbox/{message}/unread', [InboxController::class, 'unread'])->name('inbox.unread');
Route::patch('inbox/{message}/star', [InboxController::class, 'star'])->name('inbox.star');
Route::post('inbox/{message}/reply', [InboxController::class, 'reply'])->name('inbox.reply');
Route::delete('inbox/{message}', [InboxController::class, 'destroy'])->name('inbox.destroy');

Route::get('subscribers', [SubscriberController::class, 'index'])->name('subscribers.index');
Route::get('subscribers/import', [SubscriberController::class, 'importForm'])->name('subscribers.import');
Route::post('subscribers/import', [SubscriberController::class, 'import'])->name('subscribers.import.store');
Route::get('subscribers/import/template', [SubscriberController::class, 'importTemplate'])->name('subscribers.import.template');
Route::post('subscribers/bulk', [SubscriberController::class, 'bulk'])->name('subscribers.bulk');
Route::delete('subscribers/{subscriber}', [SubscriberController::class, 'destroy'])->name('subscribers.destroy');
