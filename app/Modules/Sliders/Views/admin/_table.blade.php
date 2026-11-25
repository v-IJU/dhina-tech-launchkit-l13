{{-- Rows + footer. Reloaded on its own when you search, filter, sort or change page. --}}
<div class="table-responsive">
    <table class="table align-middle">
        <thead>
        <tr>
            <x-admin.datatable.check-all :table="$table" />
            <x-admin.datatable.th :table="$table" sort="title">Title</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="subtitle">Subtitle</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="button_text">Button text</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="button_url">Button url</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="is_active">Status</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="created_at">Created</x-admin.datatable.th>
            <th class="text-end">Actions</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($table->rows as $slider)
            <tr>
                <x-admin.datatable.check :table="$table" :value="$slider->id" />
                <td class="fw-semibold">{{ \Illuminate\Support\Str::limit((string) $slider->title, 60) }}</td>
                <td>{{ \Illuminate\Support\Str::limit((string) $slider->subtitle, 60) }}</td>
                <td>{{ \Illuminate\Support\Str::limit((string) $slider->button_text, 60) }}</td>
                <td>{{ \Illuminate\Support\Str::limit((string) $slider->button_url, 60) }}</td>
                <td>@can('sliders.edit')<form method="POST" action="{{ route('admin.sliders.toggle', $slider) }}">@csrf @method('PATCH')<button class="btn p-0 border-0" title="Click to change"><x-admin.status-badge :status="(bool) $slider->is_active" /></button></form>@else<x-admin.status-badge :status="(bool) $slider->is_active" />@endcan</td>
                <td class="text-secondary text-nowrap">{{ $slider->created_at?->format('d M Y') }}</td>
                <x-admin.datatable.row-actions :table="$table" slug="sliders" :record="$slider" label="slider" />
            </tr>
        @empty
            <tr><td colspan="99"><x-admin.empty :icon="$table->inTrash() ? 'bi-trash3' : 'bi-inbox'" :message="$table->emptyMessage('sliders')" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<x-admin.datatable.footer :table="$table" label="sliders" />
