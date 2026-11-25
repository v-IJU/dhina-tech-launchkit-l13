{{-- Rows + footer. Reloaded on its own when you search, filter, sort or change page. --}}
<div class="table-responsive">
    <table class="table align-middle">
        <thead>
        <tr>
            <x-admin.datatable.check-all :table="$table" />
            <x-admin.datatable.th :table="$table" sort="client_name">Client name</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="company">Company</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="rating">Rating</x-admin.datatable.th>
            <th>Photo</th>
            <x-admin.datatable.th :table="$table" sort="is_active">Status</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="created_at">Created</x-admin.datatable.th>
            <th class="text-end">Actions</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($table->rows as $testimonial)
            <tr>
                <x-admin.datatable.check :table="$table" :value="$testimonial->id" />
                <td class="fw-semibold">{{ \Illuminate\Support\Str::limit((string) $testimonial->client_name, 60) }}</td>
                <td>{{ \Illuminate\Support\Str::limit((string) $testimonial->company, 60) }}</td>
                <td>{{ \Illuminate\Support\Str::limit((string) $testimonial->rating, 60) }}</td>
                <td>@if ($testimonial->photo)<img src="{{ Storage::disk('uploads')->url($testimonial->photo) }}" alt="" class="rounded" style="width: 48px; height: 36px; object-fit: cover">@endif</td>
                <td>@can('testimonials.edit')<form method="POST" action="{{ route('admin.testimonials.toggle', $testimonial) }}">@csrf @method('PATCH')<button class="btn p-0 border-0" title="Click to change"><x-admin.status-badge :status="(bool) $testimonial->is_active" /></button></form>@else<x-admin.status-badge :status="(bool) $testimonial->is_active" />@endcan</td>
                <td class="text-secondary text-nowrap">{{ $testimonial->created_at?->format('d M Y') }}</td>
                <x-admin.datatable.row-actions :table="$table" slug="testimonials" :record="$testimonial" label="testimonial" />
            </tr>
        @empty
            <tr><td colspan="99"><x-admin.empty :icon="$table->inTrash() ? 'bi-trash3' : 'bi-inbox'" :message="$table->emptyMessage('testimonials')" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<x-admin.datatable.footer :table="$table" label="testimonials" />
