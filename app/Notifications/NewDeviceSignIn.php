<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LoginEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email to a user when their account signs in from a device/network not seen before.
 * Turn off in Settings > Security > "Email users about new devices".
 */
class NewDeviceSignIn extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string|null  $reviewUrl  where "Not you?" goes (default: admin profile > Security)
     */
    public function __construct(public LoginEvent $login, public ?string $reviewUrl = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New sign-in to your '.setting('site_name', config('app.name')).' account')
            ->line('Your account was just used to sign in from a new device or location:')
            ->line('**Device:** '.$this->login->device())
            ->line('**IP address:** '.$this->login->ip_address)
            ->line('**Time:** '.$this->login->created_at?->format('d M Y, H:i').' ('.config('app.timezone').')')
            ->line('If this was you, you can ignore this email.')
            ->action('Not you? Review your account', $this->reviewUrl ?? route('admin.profile.edit', ['tab' => 'security']))
            ->line('If you don\'t recognise it, change your password and sign out of other sessions right away.');
    }
}
