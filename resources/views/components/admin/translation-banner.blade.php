@props(['record', 'editRoute'])
{{-- Shown on the edit form of a translation (Pages / Posts). --}}
@if ($record->exists && $record->isTranslation())
    @php $original = $record->originalRecord(); @endphp
    <div class="alert alert-info d-flex flex-wrap align-items-center gap-2">
        <i class="bi bi-translate fs-5"></i>
        <div class="flex-fill">
            <strong>{{ \App\Core\Localization\Locales::name($record->locale) }} translation</strong> of
            “{{ $original->title }}”. Visitors who choose {{ \App\Core\Localization\Locales::name($record->locale, true) }} see this version at the original's URL once it is published.
        </div>
        <a href="{{ route($editRoute, $original) }}" class="btn btn-sm btn-outline-secondary">Edit the original</a>
    </div>
@elseif ($record->exists && \App\Core\Localization\Locales::translatable())
    <div class="small text-secondary mb-3"><i class="bi bi-translate me-1"></i>Translations: use the language badges on the list page.</div>
@endif
