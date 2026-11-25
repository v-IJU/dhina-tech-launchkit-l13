<?php

declare(strict_types=1);

namespace App\Modules\Media\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property string $title
 * @property string|null $alt
 * @property string $type
 */
class MediaItem extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['title', 'alt', 'type', 'uploaded_by'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Small preview for the library grid and pickers (made straight away).
        $this->addMediaConversion('thumb')
            ->performOnCollections('file')
            ->nonQueued()
            ->fit(Fit::Crop, 400, 300);

        // Lighter WebP copy for the website (made on the queue).
        $this->addMediaConversion('webp')
            ->performOnCollections('file')
            ->fit(Fit::Max, 1920, 1920)
            ->format('webp');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function file(): ?Media
    {
        return $this->getFirstMedia('file');
    }

    public function url(): string
    {
        return $this->getFirstMediaUrl('file');
    }

    public function thumbUrl(): string
    {
        if ($this->type !== 'image') {
            return '';
        }
        $media = $this->file();

        return $media && $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $this->url();
    }

    public static function typeFromMime(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            in_array($mime, ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain', 'text/csv'], true) => 'document',
            default => 'other',
        };
    }
}
