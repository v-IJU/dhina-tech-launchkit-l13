{{-- Rows + footer. Reloaded on its own when you search, filter, sort or change page. --}}
<div class="table-responsive">
    <table class="table align-middle">
        <thead>
        <tr>
            <x-admin.datatable.check-all :table="$table" />
            <x-admin.datatable.th :table="$table" sort="email">Email</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="created_at">Subscribed</x-admin.datatable.th>
            <th class="text-end">Actions</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($table->rows as $subscriber)
            <tr>
                <x-admin.datatable.check :table="$table" :value="$subscriber->id" />
                <td class="fw-semibold">{{ $subscriber->email }}</td>
                <td class="text-secondary">{{ $subscriber->created_at->format('d M Y, H:i') }}</td>
                <td class="actions">
                    @can('subscribers.delete')
                        <x-admin.delete-button :action="route('admin.subscribers.destroy', $subscriber)" :confirm="'Remove '.$subscriber->email.'?'" />
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="99"><x-admin.empty icon="bi-envelope-paper" :message="$table->isFiltered() ? 'No subscribers match these filters.' : 'No subscribers yet. The signup form is in the website footer.'" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<x-admin.datatable.footer :table="$table" label="subscribers" />
