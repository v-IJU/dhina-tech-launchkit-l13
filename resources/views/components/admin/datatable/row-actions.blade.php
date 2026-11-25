@props(['table', 'slug', 'record', 'permission' => null, 'label' => 'item'])
{{--
    Standard action buttons for a row: Edit + Delete, or Restore + Delete forever in the trash.
    Extra buttons go in the slot (shown before Edit, outside the trash).

    <x-admin.datatable.row-actions :table="$table" slug="faqs" :record="$faq" label="FAQ" />
    Routes used: admin.{slug}.edit / destroy / restore / force-delete
--}}
@php $permission ??= $slug; @endphp
<td class="actions">
    @if ($table->inTrash())
        @can($permission.'.delete')
            <x-admin.restore-button :action="route('admin.'.$slug.'.restore', $record)" />
            <x-admin.delete-button :action="route('admin.'.$slug.'.force-delete', $record)" :confirm="'Delete this '.$label.' forever?'" body="This cannot be undone." button="Delete forever" />
        @endcan
    @else
        {{ $slot }}
        @can($permission.'.edit')
            <a href="{{ route('admin.'.$slug.'.edit', $record) }}" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="Edit" data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>
        @endcan
        @can($permission.'.delete')
            <x-admin.delete-button :action="route('admin.'.$slug.'.destroy', $record)" :confirm="'Delete this '.$label.'?'" body="It goes to the Trash and can be restored." />
        @endcan
    @endif
</td>
