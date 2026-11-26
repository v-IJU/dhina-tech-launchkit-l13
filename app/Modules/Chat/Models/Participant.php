<?php

declare(strict_types=1);

namespace App\Modules\Chat\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $conversation_id
 * @property int $user_id
 * @property int|null $last_read_message_id
 */
class Participant extends Model
{
    protected $table = 'chat_participants';

    protected $fillable = ['conversation_id', 'user_id', 'last_read_message_id'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
