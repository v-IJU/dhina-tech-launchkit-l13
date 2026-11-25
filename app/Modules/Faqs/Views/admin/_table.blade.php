{{-- Rows + footer. Reloaded on its own when you search, filter, sort or change page. --}}
<div class="table-responsive">
    <table class="table align-middle">
        <thead>
        <tr>
            <x-admin.datatable.check-all :table="$table" />
            <x-admin.datatable.th :table="$table" sort="question">Question</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="group_name">Group name</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="sort_order">Sort order</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="is_active">Status</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="created_at">Created</x-admin.datatable.th>
            <th class="text-end">Actions</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($table->rows as $faq)
            <tr>
                <x-admin.datatable.check :table="$table" :value="$faq->id" />
                <td class="fw-semibold">{{ \Illuminate\Support\Str::limit((string) $faq->question, 60) }}</td>
                <td>{{ \Illuminate\Support\Str::limit((string) $faq->group_name, 60) }}</td>
                <td>{{ \Illuminate\Support\Str::limit((string) $faq->sort_order, 60) }}</td>
                <td>@can('faqs.edit')<form method="POST" action="{{ route('admin.faqs.toggle', $faq) }}">@csrf @method('PATCH')<button class="btn p-0 border-0" title="Click to change"><x-admin.status-badge :status="(bool) $faq->is_active" /></button></form>@else<x-admin.status-badge :status="(bool) $faq->is_active" />@endcan</td>
                <td class="text-secondary text-nowrap">{{ $faq->created_at?->format('d M Y') }}</td>
                <x-admin.datatable.row-actions :table="$table" slug="faqs" :record="$faq" label="FAQ" />
            </tr>
        @empty
            <tr><td colspan="99"><x-admin.empty :icon="$table->inTrash() ? 'bi-trash3' : 'bi-inbox'" :message="$table->emptyMessage('faqs')" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<x-admin.datatable.footer :table="$table" label="faqs" />
