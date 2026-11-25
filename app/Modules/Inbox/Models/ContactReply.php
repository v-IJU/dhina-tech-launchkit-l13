<?php

declare(strict_types=1);

namespace App\Modules\Inbox\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactReply extends Model
{
    protected $fillable = ['contact_message_id', 'user_id', 'body'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
