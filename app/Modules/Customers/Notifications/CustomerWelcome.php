<?php

declare(strict_types=1);

namespace App\Modules\Customers\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent after a customer signs up (Settings > Customers > "Send a welcome email").
 */
class CustomerWelcome extends Notification implements ShouldQueue
{
    use Queueable;

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

        return (new MailMessage)
            ->subject("Welcome to {$site}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Thanks for creating an account with {$site}.")
            ->action('Go to my account', route('customer.account'))
            ->line('You can update your details, saved addresses and security settings there at any time.');
    }
}
