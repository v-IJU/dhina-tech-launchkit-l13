<?php

declare(strict_types=1);

namespace App\Modules\Sliders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Slider extends Model
{
    use LogsActivity, SoftDeletes;

    /** @var array<int, string> Uploaded files removed from storage when a row is deleted forever */
    public const UPLOADS = ['image'];

    protected $table = 'sliders';

    protected $attributes = ['is_active' => true];

    protected $fillable = [
        'title',
        'subtitle',
        'button_text',
        'button_url',
        'image',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
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
            ->useLogName('sliders')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
