<?php

declare(strict_types=1);

namespace App\Modules\Customers\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

/**
 * "Confirm your email" for customers. The link is signed and works without
 * being signed in, so it also works for customers who registered in the app.
 */
class CustomerVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute('customer.verification.verify', now()->addHours(48), [
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
        ]);
    }

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirm your email address')
            ->greeting('Welcome to '.setting('site_name', config('app.name')).'!')
            ->line('Please confirm your email address to finish setting up your account.')
            ->action('Confirm email address', $url)
            ->line('This link expires in 48 hours. If you did not create an account, you can ignore this email.');
    }
}
