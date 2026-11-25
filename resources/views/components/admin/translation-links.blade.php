@props(['record', 'route', 'editRoute'])
{{--
    "Languages" cell on Pages / Posts lists: one badge per website language.
    Existing translation → link to edit it (green = published). Missing → "+ TA" creates it.

    <x-admin.translation-links :record="$page" route="admin.pages.translate" edit-route="admin.pages.edit" />
--}}
@php $locales = \App\Core\Localization\Locales::translatable(); @endphp
@if ($locales)
    <td class="text-nowrap">
        @foreach ($locales as $locale)
            @php $translation = $record->translations->firstWhere('locale', $locale); @endphp
            @if ($translation)
                <a href="{{ route($editRoute, $translation) }}" class="badge {{ $translation->status === 'published' ? 'badge-soft-success' : 'badge-soft-secondary' }} text-decoration-none"
                   data-bs-toggle="tooltip" title="{{ \App\Core\Localization\Locales::name($locale) }}: {{ $translation->status }}. Click to edit.">{{ strtoupper($locale) }}</a>
            @else
                <form method="POST" action="{{ route($route, [$record, $locale]) }}" class="d-inline">
                    @csrf
                    <button class="badge badge-soft-warning border-0" data-bs-toggle="tooltip" title="Add {{ \App\Core\Localization\Locales::name($locale) }} translation">+ {{ strtoupper($locale) }}</button>
                </form>
            @endif
        @endforeach
    </td>
@endif
