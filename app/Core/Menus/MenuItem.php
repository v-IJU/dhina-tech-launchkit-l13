<?php

declare(strict_types=1);

namespace App\Core\Menus;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;

/**
 * One menu entry. location = admin (sidebar), header or footer (website).
 *
 * @property int $id
 * @property string $location
 * @property string|null $key
 * @property string|null $module
 * @property int|null $parent_id
 * @property string|null $group
 * @property string $label
 * @property string|null $icon
 * @property string|null $route
 * @property string|null $url
 * @property string|null $permission
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $new_tab
 */
class MenuItem extends Model
{
    protected $fillable = [
        'location', 'key', 'module', 'parent_id', 'group', 'label', 'icon',
        'route', 'url', 'permission', 'sort_order', 'is_active', 'new_tab', 'translations',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'new_tab' => 'boolean', 'sort_order' => 'integer', 'translations' => 'array'];
    }

    /** @return BelongsTo<MenuItem, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<MenuItem, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function href(): string
    {
        if ($this->route && Route::has($this->route)) {
            return route($this->route);
        }

        return $this->url ?: '#';
    }

    /**
     * Label in the given language (falls back to the main label).
     */
    public function labelFor(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return (string) (($this->translations[$locale] ?? null) ?: $this->label);
    }
}
