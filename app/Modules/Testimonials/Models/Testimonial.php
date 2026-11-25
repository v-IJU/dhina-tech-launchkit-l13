<?php

declare(strict_types=1);

namespace App\Modules\Testimonials\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Testimonial extends Model
{
    use LogsActivity, SoftDeletes;

    /** @var array<int, string> Uploaded files removed from storage when a row is deleted forever */
    public const UPLOADS = ['photo'];

    protected $table = 'testimonials';

    protected $attributes = ['is_active' => true];

    protected $fillable = [
        'client_name',
        'company',
        'rating',
        'quote',
        'photo',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Soft delete keeps files (the row can be restored); delete forever removes them.
        static::forceDeleted(function (self $item) {
            foreach (self::UPLOADS as $field) {
                if ($item->{$field}) {
                    Storage::disk('uploads')->delete($item->{$field});
                }
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('testimonials')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
