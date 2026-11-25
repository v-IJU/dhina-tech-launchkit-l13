<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ErrorLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Email to Settings > Monitoring > "Email new errors to" (a developer or support address).
 */
class ErrorOccurred extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ErrorLog $log, public bool $reopened = false, public ?string $url = null) {}

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
        $mail = (new MailMessage)
            ->error()
            ->subject(($this->reopened ? '[Error is back] ' : '[New error] ').$site.': '.Str::limit($this->log->shortClass().' '.$this->log->message, 90))
            ->line('**'.$this->log->class.'**')
            ->line(Str::limit($this->log->message, 500))
            ->line('Where: '.$this->log->location())
            ->line('Request: '.($this->log->method ? $this->log->method.' ' : '').($this->log->url ?? '—'))
            ->line('Environment: '.app()->environment());

        if ($this->url) {
            $mail->action('Open in the error log', $this->url);
        }

        return $mail->line('You get one email per error per hour. Turn this off under Settings > Monitoring.');
    }
}
