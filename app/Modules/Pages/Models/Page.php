<?php

declare(strict_types=1);

namespace App\Modules\Pages\Models;

use App\Core\Localization\HasTranslations;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $status
 * @property bool $is_home
 * @property array<int, array{type: string, data: array<string, mixed>}>|null $blocks
 * @property string|null $locale null = main language
 * @property int|null $translation_of id of the original page
 */
class Page extends Model
{
    use HasTranslations, LogsActivity;

    protected $fillable = ['title', 'slug', 'status', 'is_home', 'blocks', 'meta_title', 'meta_description', 'og_image', 'updated_by'];

    protected function casts(): array
    {
        return ['blocks' => 'array', 'is_home' => 'boolean'];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function url(): string
    {
        $page = $this->originalRecord(); // translations share the original URL

        return $page->is_home ? url('/') : url('/'.$page->slug);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('pages')->logOnly(['title', 'slug', 'status', 'is_home'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
