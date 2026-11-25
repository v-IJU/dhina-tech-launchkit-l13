<?php

declare(strict_types=1);

namespace App\Core\Localization;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Translations as separate rows: a translation is a copy of the record with
 * "locale" set and "translation_of" pointing at the original. The original
 * (locale = null) is in the main language and owns the URL.
 *
 * Used by Pages and Blog posts. Needs columns: locale (nullable), translation_of (nullable).
 *
 *   $page->translations             all translations
 *   $page->source                   the original (on a translation)
 *   $page->localized()              the translation for the current language, or the page itself
 *   $page->makeTranslation('ta')    copy as a new draft translation
 *   Page::query()->originals()      only main-language records (for lists)
 */
trait HasTranslations
{
    public static function bootHasTranslations(): void
    {
        // Deleting / restoring the original takes its translations with it.
        static::deleting(function (self $model) {
            if ($model->translation_of === null) {
                $isForce = ! in_array(SoftDeletes::class, class_uses_recursive($model), true) || $model->isForceDeleting(); // @phpstan-ignore method.notFound
                $model->translations()->get()->each(fn (self $t) => $isForce ? $t->forceDelete() : $t->delete()); // @phpstan-ignore method.notFound
            }
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (self $model) {
                if ($model->translation_of === null) {
                    $model->translations()->onlyTrashed()->get()->each->restore(); // @phpstan-ignore method.notFound
                }
            });
        }
    }

    /**
     * @return HasMany<static, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(static::class, 'translation_of');
    }

    /**
     * @return BelongsTo<static, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(static::class, 'translation_of');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOriginals(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('translation_of'));
    }

    public function isTranslation(): bool
    {
        return $this->translation_of !== null;
    }

    /**
     * The main-language record (itself when this is the original). No lazy loading.
     */
    public function originalRecord(): static
    {
        if (! $this->isTranslation()) {
            return $this;
        }

        return $this->relationLoaded('source') && $this->source
            ? $this->source
            : (static::query()->find($this->translation_of) ?? $this);
    }

    public function translation(string $locale): ?static
    {
        return $this->translations->firstWhere('locale', $locale);
    }

    /**
     * The version to show visitors in the current language.
     */
    public function localized(?string $locale = null): self
    {
        $locale ??= Locales::current();
        if ($this->isTranslation() || Locales::isDefault($locale)) {
            return $this;
        }

        $translation = $this->relationLoaded('translations')
            ? $this->translation($locale)
            : $this->translations()->where('locale', $locale)->first();

        if (! $translation || ! $this->translationIsVisible($translation)) {
            return $this;
        }

        // Same category/author/etc. as the original: reuse what's already loaded.
        foreach ($this->getRelations() as $name => $related) {
            if (! in_array($name, ['translations', 'source'], true)) {
                $translation->setRelation($name, $related);
            }
        }

        return $translation->setRelation('source', $this);
    }

    /**
     * Copy this record as a draft translation (content stays in the main language until edited).
     */
    public function makeTranslation(string $locale): static
    {
        $copy = $this->replicate(['translation_of', 'locale']);
        $copy->forceFill([
            'locale' => $locale,
            'translation_of' => $this->getKey(),
            'slug' => $this->slug.'-'.$locale,
            'status' => 'draft',
        ]);
        if (array_key_exists('is_home', $copy->getAttributes())) {
            $copy->forceFill(['is_home' => false]);
        }
        $copy->save();

        return $copy;
    }

    /**
     * Override when "published" means something else for the model.
     */
    protected function translationIsVisible(self $translation): bool
    {
        return $translation->status === 'published';
    }
}
