<?php

declare(strict_types=1);

namespace App\Modules\Customers\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Reset your password" for customers (website, app and "Send reset link" in the admin panel).
 * The link opens the website reset page: /account/reset-password/{token}?email=…
 */
class CustomerResetPassword extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token, public bool $newAccount = false) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('customer.password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $site = setting('site_name', config('app.name'));

        if ($this->newAccount) {
            return (new MailMessage)
                ->subject("Your {$site} account is ready")
                ->greeting("Hello {$notifiable->name},")
                ->line("An account has been created for you at {$site}.")
                ->action('Set your password', $url)
                ->line('This link expires in '.config('auth.passwords.customers.expire').' minutes. After that, use "Forgot password" on the sign-in page.');
        }

        return (new MailMessage)
            ->subject('Reset your password')
            ->line('We received a request to reset the password for your account.')
            ->action('Reset password', $url)
            ->line('This link expires in '.config('auth.passwords.customers.expire').' minutes.')
            ->line('If you did not ask for this, no action is needed.');
    }
}
