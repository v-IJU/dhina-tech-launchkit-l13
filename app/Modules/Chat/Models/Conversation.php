<?php

declare(strict_types=1);

namespace App\Modules\Chat\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A chat between staff users. Today: "direct" (two people). The participants table is ready for groups.
 *
 * @property int $id
 * @property string $type
 * @property string|null $direct_key
 * @property Carbon|null $last_message_at
 */
class Conversation extends Model
{
    protected $table = 'chat_conversations';

    protected $fillable = ['type', 'direct_key', 'created_by', 'last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_participants', 'conversation_id', 'user_id')->withTimestamps();
    }

    /** @return HasMany<Participant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class, 'conversation_id');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    /** @return HasOne<Message, $this> */
    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class, 'conversation_id')->latestOfMany();
    }

    /** @param  Builder<Conversation>  $query */
    public function scopeFor(Builder $query, User $user): void
    {
        $query->whereHas('participants', fn (Builder $q) => $q->where('user_id', $user->id));
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participants()->where('user_id', $user->id)->exists();
    }

    public static function directKey(int $a, int $b): string
    {
        return min($a, $b).':'.max($a, $b);
    }

    /** The other person in a direct chat. */
    public function otherUser(User $me): ?User
    {
        return $this->users->first(fn (User $u) => $u->id !== $me->id);
    }

    /** Last message id this user has seen (load "participants" first). */
    public function lastReadBy(User $user): int
    {
        return (int) $this->participants->firstWhere('user_id', $user->id)?->last_read_message_id;
    }

    public function unreadFor(User $user): int
    {
        $lastRead = $this->lastReadBy($user);

        return $this->messages()->where('id', '>', $lastRead)->where('user_id', '!=', $user->id)->count();
    }
}
