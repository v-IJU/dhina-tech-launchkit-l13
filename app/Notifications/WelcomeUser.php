<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when an admin creates an account with "Send welcome email" ticked.
 * Contains a link to choose a password; passwords are never emailed.
 */
class WelcomeUser extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = setting('site_name', config('app.name'));
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);

        return (new MailMessage)
            ->subject("Your {$site} admin account")
            ->greeting("Hello {$notifiable->name},")
            ->line("An account has been created for you on the {$site} admin panel.")
            ->action('Choose your password', $url)
            ->line('This link expires in '.config('auth.passwords.users.expire', 60).' minutes. After that, use "Forgot password?" on the sign-in page.');
    }
}
