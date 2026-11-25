<?php

declare(strict_types=1);

namespace App\Modules\Inbox\Models;

use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    protected $table = 'newsletter_subscribers';

    protected $fillable = ['email', 'ip_address'];
}
