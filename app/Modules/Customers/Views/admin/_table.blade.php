{{-- Rows + footer. Reloaded on its own when you search, filter, sort or change page. --}}
<div class="table-responsive">
    <table class="table align-middle">
        <thead>
        <tr>
            <x-admin.datatable.check-all :table="$table" />
            <x-admin.datatable.th :table="$table" sort="name">Customer</x-admin.datatable.th>
            <th>Phone</th>
            <x-admin.datatable.th :table="$table" sort="status">Status</x-admin.datatable.th>
            <th>Email</th>
            <th>2FA</th>
            <x-admin.datatable.th :table="$table" sort="last_login_at">Last sign-in</x-admin.datatable.th>
            <x-admin.datatable.th :table="$table" sort="created_at">Joined</x-admin.datatable.th>
            <th class="text-end">Actions</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($table->rows as $customer)
            <tr>
                <x-admin.datatable.check :table="$table" :value="$customer->id" />
                <td>
                    <div class="d-flex align-items-center gap-3">
                        <x-admin.avatar :user="$customer" />
                        <div class="d-flex flex-column">
                            @if ($table->inTrash())
                                <span class="fw-semibold">{{ $customer->name }}</span>
                            @else
                                <a href="{{ route('admin.customers.show', $customer) }}" class="fw-semibold text-body">{{ $customer->name }}</a>
                            @endif
                            <small class="text-secondary">{{ $customer->email }}</small>
                        </div>
                    </div>
                </td>
                <td class="text-secondary text-nowrap">{{ $customer->phone ?: '—' }}</td>
                <td><x-admin.status-badge :status="$customer->status" /></td>
                <td>
                    @if ($customer->email_verified_at)
                        <span class="badge badge-soft-success"><i class="bi bi-check2 me-1"></i>Confirmed</span>
                    @else
                        <span class="badge badge-soft-warning">Not confirmed</span>
                    @endif
                </td>
                <td>
                    @if ($customer->two_factor_confirmed_at)
                        <span class="badge badge-soft-success">On</span>
                    @else
                        <span class="badge badge-soft-secondary">Off</span>
                    @endif
                </td>
                <td class="text-secondary text-nowrap">{{ $customer->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                <td class="text-secondary text-nowrap">{{ $customer->created_at?->format('d M Y') }}</td>
                <td class="actions">
                    @if ($table->inTrash())
                        @can('customers.delete')
                            <x-admin.restore-button :action="route('admin.customers.restore', $customer)" />
                            <x-admin.delete-button :action="route('admin.customers.force-delete', $customer)" :confirm="'Delete '.$customer->name.' forever?'" body="Their account, addresses and sign-in history are removed for good." button="Delete forever" />
                        @endcan
                    @else
                        @can('customers.impersonate')
                            @if ($customer->isActive())
                                <form method="POST" action="{{ route('admin.customers.impersonate', $customer) }}" class="d-inline" target="_blank"
                                      data-confirm="Open the website as {{ $customer->name }}?" data-confirm-body="Opens in a new tab. You stay signed in to the admin panel. Use the amber bar on the website to finish." data-confirm-button="Open website">
                                    @csrf
                                    <button class="btn btn-sm btn-icon btn-outline-secondary" aria-label="Sign in as {{ $customer->name }}" data-bs-toggle="tooltip" title="Sign in as customer"><i class="bi bi-box-arrow-up-right"></i></button>
                                </form>
                            @endif
                        @endcan
                        <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="View {{ $customer->name }}" data-bs-toggle="tooltip" title="View"><i class="bi bi-eye"></i></a>
                        @can('customers.edit')
                            <a href="{{ route('admin.customers.edit', $customer) }}" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="Edit {{ $customer->name }}" data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>
                        @endcan
                        @can('customers.delete')
                            <x-admin.delete-button :action="route('admin.customers.destroy', $customer)" :confirm="'Delete '.$customer->name.'?'" body="They are signed out everywhere and go to the Trash, where they can be restored." button="Delete customer" />
                        @endcan
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="99"><x-admin.empty :icon="$table->inTrash() ? 'bi-trash3' : 'bi-person-vcard'" :message="$table->emptyMessage('customers')" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<x-admin.datatable.footer :table="$table" label="customers" />
