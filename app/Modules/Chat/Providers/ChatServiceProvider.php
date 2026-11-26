<?php

declare(strict_types=1);

namespace App\Modules\Chat\Providers;

use App\Models\User;
use App\Modules\Chat\Controllers\Admin\ChatController;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

/** Chat icon with the unread count in the admin top bar (for people with chat.view). */
class ChatServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer('layouts.admin', function ($view) {
            $user = auth()->user();
            if (! $user instanceof User || ! module_enabled('chat') || ! $user->can('chat.view')) {
                return;
            }
            try {
                $view->with('chatUnread', ChatController::unreadTotal($user));
            } catch (Throwable) {
                // tables not migrated yet (e.g. right after an update): no badge rather than a broken page
            }
        });
    }
}
