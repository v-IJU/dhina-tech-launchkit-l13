<?php

declare(strict_types=1);

namespace App\Modules\Chat\Events;

use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sent straight to Reverb (no queue) on each recipient's private user channel, the same channel the
 * bell notifications use (tenant-safe in SaaS mode). The browser listens for ".chat.{type}".
 */
class ChatEvent implements ShouldBroadcastNow
{
    /** @var list<string> */
    private array $channels;

    /**
     * @param  iterable<User>  $recipients
     * @param  array<string, mixed>  $payload
     */
    public function __construct(iterable $recipients, public string $type, public array $payload)
    {
        $this->channels = [];
        foreach ($recipients as $user) {
            $this->channels[] = $user->receivesBroadcastNotificationsOn();
        }
    }

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return array_map(fn (string $name) => new PrivateChannel($name), $this->channels);
    }

    public function broadcastAs(): string
    {
        return 'chat.'.$this->type;
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->payload;
    }

    /**
     * Never fail a chat action because the realtime server is down: the message is saved and the
     * other person still gets it on their next refresh (the page also polls when Reverb is off).
     *
     * @param  iterable<User>  $recipients
     * @param  array<string, mixed>  $payload
     */
    public static function send(iterable $recipients, string $type, array $payload): void
    {
        if (config('broadcasting.default') === null || in_array(config('broadcasting.default'), ['null', 'log'], true)) {
            return;
        }
        try {
            broadcast(new self($recipients, $type, $payload));
        } catch (Throwable $e) {
            Log::warning('Chat realtime event not sent: '.$e->getMessage());
        }
    }
}
