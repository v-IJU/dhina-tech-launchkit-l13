<?php

declare(strict_types=1);

namespace App\Modules\Chat\Models;

use App\Models\User;
use App\Modules\Chat\Support\ChatFiles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int|null $user_id
 * @property string $type text|image|audio|file
 * @property string|null $body
 * @property string|null $attachment_path
 * @property string|null $attachment_name
 * @property string|null $attachment_mime
 * @property int|null $attachment_size
 * @property int|null $duration
 * @property Carbon $created_at
 * @property Carbon|null $deleted_at
 */
class Message extends Model
{
    use Prunable, SoftDeletes;

    protected $table = 'chat_messages';

    protected $fillable = ['conversation_id', 'user_id', 'type', 'body', 'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size', 'duration'];

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Settings > Team chat > "Delete messages after (days)". 0 = keep forever. */
    public function prunable(): Builder
    {
        $days = (int) setting('chat_retention_days', 0);

        return $days > 0
            ? static::withTrashed()->where('created_at', '<', now()->subDays($days))
            : static::query()->whereRaw('1 = 0');
    }

    protected function pruning(): void
    {
        ChatFiles::delete($this->attachment_path);
    }

    /** Shape sent to the browser (page load, JSON and realtime events). @return array<string, mixed> */
    public function toChat(): array
    {
        $deleted = $this->deleted_at !== null;
        $author = $this->getRelationValue('user');

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'user_id' => $this->user_id,
            'user' => $author instanceof User ? $author->name : 'Deleted user',
            'type' => $deleted ? 'deleted' : $this->type,
            'body' => $deleted ? null : $this->body,
            'attachment' => ! $deleted && $this->attachment_path ? [
                'url' => route('admin.chat.attachment', $this->id),
                'download' => route('admin.chat.attachment', [$this->id, 'download' => 1]),
                'name' => $this->attachment_name,
                'mime' => $this->attachment_mime,
                'size' => Number::fileSize((int) $this->attachment_size),
            ] : null,
            'duration' => $this->duration,
            'time' => $this->created_at->format('H:i'),
            'date' => $this->created_at->toDateString(),
            'iso' => $this->created_at->toIso8601String(),
        ];
    }
}
