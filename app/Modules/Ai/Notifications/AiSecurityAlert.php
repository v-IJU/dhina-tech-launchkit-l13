<?php

declare(strict_types=1);

namespace App\Modules\Ai\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email + bell to the people who manage the site, when an AI client does something unusual:
 * a denied tool call, or far more calls than normal in an hour. Sent at most once an hour per user and kind.
 */
class AiSecurityAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $subject,
        public string $body,
        public string $url,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject.' · '.setting('site_name', config('app.name')))
            ->line($this->body)
            ->line('If this wasn\'t expected, revoke the token (the user deletes it under My profile > Security > API tokens), remove the user\'s AI permissions (Users > key icon or their role) or turn AI clients off under Settings > AI.')
            ->action('See AI activity', $this->url);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['message' => $this->subject.': '.$this->body, 'url' => $this->url, 'icon' => 'bi-shield-exclamation'];
    }
}
