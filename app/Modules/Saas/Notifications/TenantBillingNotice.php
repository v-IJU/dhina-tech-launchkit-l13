<?php

declare(strict_types=1);

namespace App\Modules\Saas\Notifications;

use App\Modules\Saas\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Billing emails to a tenant's owner / billing address. Each one links to their Billing page.
 *   trial_ending · trial_ended · payment_failed · suspended
 */
class TenantBillingNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Tenant $tenant, public string $type, public ?string $reason = null) {}

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
        $billing = $this->tenant->url(trim((string) config('starter.admin_path'), '/').'/billing');
        $grace = (int) config('starter.tenancy.grace_days', 7);

        $mail = (new MailMessage)->greeting("Hello {$this->tenant->name},");

        return match ($this->type) {
            'trial_ending' => $mail->subject("Your {$site} trial ends in {$this->tenant->trialDaysLeft()} days")
                ->line("Your free trial ends on {$this->tenant->trial_ends_at?->format('d M Y')}.")
                ->line('Choose a plan to keep your account and data without interruption.')
                ->action('Choose a plan', $billing),
            'trial_ended' => $mail->subject("Your {$site} trial has ended")
                ->line("Your free trial has ended. Your account keeps working for {$grace} more days.")
                ->action('Choose a plan', $billing),
            'payment_failed' => $mail->error()->subject("Payment failed for your {$site} subscription")
                ->line('We could not collect your latest payment. Please update your payment method.')
                ->line("Your account keeps working for {$grace} days.")
                ->action('Open billing', $billing),
            default => $mail->error()->subject("Your {$site} account is suspended")
                ->line($this->reason ?? 'Your account has been suspended.')
                ->line('Your data is safe. Choose a plan to switch everything back on straight away.')
                ->action('Reactivate', $billing),
        };
    }
}
