<?php

declare(strict_types=1);

namespace App\Modules\Inbox\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $read_at
 */
class ContactMessage extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'subject', 'message', 'page', 'ip_address', 'user_agent', 'is_starred', 'read_at', 'replied_at'];

    protected function casts(): array
    {
        return ['is_starred' => 'boolean', 'read_at' => 'datetime', 'replied_at' => 'datetime'];
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ContactReply::class)->oldest();
    }

    /**
     * @param  Builder<ContactMessage>  $query
     */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }
}
