<?php

declare(strict_types=1);

namespace App\Modules\Blog\Models;

use App\Core\Localization\HasTranslations;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $status draft|published|scheduled
 * @property Carbon|null $published_at
 * @property array<int, string>|null $tags
 * @property string|null $locale null = main language
 * @property int|null $translation_of id of the original post
 * @property-read Category|null $category
 * @property-read User|null $author
 */
class Post extends Model
{
    use HasSlug, HasTranslations, LogsActivity, SoftDeletes;

    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published', 'scheduled' => 'Scheduled'];

    protected $fillable = [
        'title', 'slug', 'excerpt', 'content', 'featured_image', 'category_id', 'author_id',
        'status', 'published_at', 'is_featured', 'tags', 'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'tags' => 'array',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()->generateSlugsFrom('title')->saveSlugsTo('slug')->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Posts visible on the website: published, or scheduled for a time that has passed.
     *
     * @param  Builder<Post>  $query
     */
    public function scopePublished(Builder $query): void
    {
        // Website lists show main-language posts; each is swapped for its translation with ->localized().
        $query->whereIn('status', ['published', 'scheduled'])->where('published_at', '<=', now())->whereNull('translation_of');
    }

    /**
     * Translations share the original's URL (/blog/{original-slug}?lang=ta).
     */
    public function getRouteKey(): mixed
    {
        return $this->originalRecord()->slug;
    }

    protected function translationIsVisible(self $translation): bool
    {
        return $translation->isLive();
    }

    public function isLive(): bool
    {
        return in_array($this->status, ['published', 'scheduled'], true) && $this->published_at?->isPast();
    }

    public function readingMinutes(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags((string) $this->content)) / 200));
    }

    public function summary(int $limit = 160): string
    {
        return $this->excerpt ?: Str::limit(trim(strip_tags((string) $this->content)), $limit);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('blog')
            ->logOnly(['title', 'slug', 'status', 'published_at', 'category_id', 'is_featured'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
