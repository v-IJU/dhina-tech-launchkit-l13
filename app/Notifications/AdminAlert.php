<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notifier;

/**
 * Short message for the admin bell. Saved in the database and pushed live
 * over Reverb.
 *
 *   AdminAlert::toAdmins('New enquiry from Anita', route('admin.inbox.show', $message));
 */
class AdminAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $message, public ?string $url = null, public string $icon = 'bi-bell') {}

    /**
     * Send to every user who has one of the given permissions (super-admins always included).
     */
    public static function toAdmins(string $message, ?string $url = null, string $permission = 'dashboard.view', string $icon = 'bi-bell'): void
    {
        $users = User::query()->where('status', 'active')->get()
            ->filter(fn (User $u) => $u->isSuperAdmin() || $u->can($permission));

        Notifier::send($users, new self($message, $url, $icon));
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return config('broadcasting.default') === 'reverb' ? ['database', 'broadcast'] : ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['message' => $this->message, 'url' => $this->url, 'icon' => $this->icon];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
