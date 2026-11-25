@extends('layouts.admin')

@section('content')
    <x-admin.page-header :title="$customer->name" :breadcrumbs="['Customers', 'All customers' => route('admin.customers.index'), $customer->name]">
        <a href="{{ route('admin.customers.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
        @can('customers.impersonate')
            @if ($customer->isActive())
                <form method="POST" action="{{ route('admin.customers.impersonate', $customer) }}" target="_blank" class="d-inline"
                      data-confirm="Open the website as {{ $customer->name }}?" data-confirm-body="Opens in a new tab. You stay signed in to the admin panel. Use the amber bar on the website to finish." data-confirm-button="Open website">
                    @csrf
                    <button class="btn btn-outline-secondary"><i class="bi bi-box-arrow-up-right me-1"></i> Sign in as customer</button>
                </form>
            @endif
        @endcan
        @can('customers.edit')
            <a href="{{ route('admin.customers.edit', $customer) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i> Edit</a>
        @endcan
    </x-admin.page-header>

    <div class="row g-4">
        <div class="col-xl-8 d-flex flex-column gap-4">
            <x-admin.card>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <x-admin.avatar :user="$customer" size="lg" />
                    <div class="flex-fill">
                        <div class="fs-5 fw-semibold">{{ $customer->name }}</div>
                        <div class="text-secondary">{{ $customer->email }} @if ($customer->phone) · {{ $customer->phone }} @endif</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <x-admin.status-badge :status="$customer->status" />
                        <span class="badge {{ $customer->email_verified_at ? 'badge-soft-success' : 'badge-soft-warning' }}">{{ $customer->email_verified_at ? 'Email confirmed' : 'Email not confirmed' }}</span>
                        <span class="badge {{ $customer->two_factor_confirmed_at ? 'badge-soft-success' : 'badge-soft-secondary' }}">2FA {{ $customer->two_factor_confirmed_at ? 'on' : 'off' }}</span>
                    </div>
                </div>
                <hr>
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-secondary fw-normal">Customer ID</dt><dd class="col-sm-8">#{{ $customer->id }}</dd>
                    <dt class="col-sm-4 text-secondary fw-normal">Joined</dt><dd class="col-sm-8">{{ $customer->created_at?->format('d M Y, H:i') }}</dd>
                    <dt class="col-sm-4 text-secondary fw-normal">Last sign-in</dt><dd class="col-sm-8">{{ $customer->last_login_at?->format('d M Y, H:i') ?? 'Never' }} @if ($customer->last_login_ip)<span class="text-secondary">from {{ $customer->last_login_ip }}</span>@endif</dd>
                    <dt class="col-sm-4 text-secondary fw-normal">Date of birth</dt><dd class="col-sm-8">{{ $customer->date_of_birth?->format('d M Y') ?? '—' }}</dd>
                    <dt class="col-sm-4 text-secondary fw-normal">Gender</dt><dd class="col-sm-8">{{ \App\Modules\Customers\Models\Customer::GENDERS[$customer->gender] ?? '—' }}</dd>
                    <dt class="col-sm-4 text-secondary fw-normal">Language</dt><dd class="col-sm-8">{{ $customer->locale ? (config('starter.locales.'.$customer->locale.'.name') ?? $customer->locale) : 'Website default' }}</dd>
                    <dt class="col-sm-4 text-secondary fw-normal">Marketing emails</dt><dd class="col-sm-8 mb-0">{{ $customer->marketing_opt_in ? 'Yes' : 'No' }}</dd>
                </dl>
            </x-admin.card>

            <x-admin.card title="Addresses" :subtitle="$customer->addresses->count().' saved'" flush>
                @can('customers.edit')
                    <x-slot:actions><a href="{{ route('admin.customers.addresses.create', $customer) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i> Add address</a></x-slot:actions>
                @endcan
                @forelse ($customer->addresses as $address)
                    <div class="d-flex gap-3 align-items-start px-4 py-3 border-top">
                        <i class="bi bi-geo-alt text-secondary mt-1"></i>
                        <div class="flex-fill">
                            <div class="fw-semibold">{{ $address->label ?: 'Address' }} @if ($address->is_default)<span class="badge badge-soft-primary ms-1">Default</span>@endif</div>
                            <div class="text-secondary small">{{ $address->name }} · {{ $address->oneLine() }} @if ($address->phone) · {{ $address->phone }} @endif</div>
                        </div>
                        @can('customers.edit')
                            <a href="{{ route('admin.customers.addresses.edit', [$customer, $address]) }}" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="Edit address" data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>
                            <x-admin.delete-button :action="route('admin.customers.addresses.destroy', [$customer, $address])" confirm="Remove this address?" body="The customer will no longer see it in their account." button="Remove" />
                        @endcan
                    </div>
                @empty
                    <div class="px-4 py-4 border-top text-secondary small">No saved addresses.</div>
                @endforelse
            </x-admin.card>

            <x-admin.card title="Recent sign-ins" subtitle="Website and app. Failed attempts included." flush>
                <x-slot:actions><a href="{{ route('admin.customers.logins', ['q' => $customer->email]) }}" class="btn btn-sm btn-outline-secondary">Full history</a></x-slot:actions>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>When</th><th>Event</th><th>Device</th><th>IP address</th></tr></thead>
                        <tbody>
                        @forelse ($logins as $login)
                            <tr>
                                <td class="text-secondary text-nowrap">{{ $login->created_at?->format('d M Y, H:i') }}</td>
                                <td><span class="badge {{ ['login' => 'badge-soft-success', 'failed' => 'badge-soft-danger'][$login->event] ?? 'badge-soft-secondary' }}">{{ \App\Models\LoginEvent::EVENTS[$login->event] ?? $login->event }}</span>
                                    @if ($login->is_new_device)<span class="badge badge-soft-warning ms-1">New device</span>@endif</td>
                                <td>{{ $login->device() }}</td>
                                <td class="code text-secondary">{{ $login->ip_address }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-secondary small">No sign-ins yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>

        <div class="col-xl-4 d-flex flex-column gap-4">
            @can('customers.edit')
                <x-admin.card title="Account actions">
                    <div class="d-grid gap-2">
                        <form method="POST" action="{{ route('admin.customers.password-reset', $customer) }}" class="d-grid">
                            @csrf
                            <button class="btn btn-outline-secondary text-start"><i class="bi bi-key me-2"></i>Send password reset link</button>
                        </form>
                        @if ($customer->email_verified_at)
                            <div class="small text-secondary px-1"><i class="bi bi-envelope-check me-2 text-success"></i>Email confirmed {{ $customer->email_verified_at->format('d M Y') }}</div>
                        @else
                            <form method="POST" action="{{ route('admin.customers.resend-verification', $customer) }}" class="d-grid">
                                @csrf
                                <button class="btn btn-outline-secondary text-start"><i class="bi bi-envelope me-2"></i>Resend confirmation email</button>
                            </form>
                            <form method="POST" action="{{ route('admin.customers.verify-email', $customer) }}" class="d-grid">
                                @csrf
                                <button class="btn btn-outline-secondary text-start"><i class="bi bi-envelope-check me-2"></i>Mark email as confirmed</button>
                            </form>
                        @endif
                        @if ($customer->two_factor_secret)
                            <form method="POST" action="{{ route('admin.customers.two-factor.reset', $customer) }}" class="d-grid"
                                  data-confirm="Turn off two-factor login for {{ $customer->name }}?" data-confirm-body="Only do this after checking who you are talking to, e.g. a customer who lost their phone." data-confirm-button="Reset 2FA">
                                @csrf @method('DELETE')
                                <button class="btn btn-outline-warning text-start"><i class="bi bi-shield-x me-2"></i>Reset two-factor login</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.customers.block', $customer) }}" class="d-grid"
                              data-confirm="{{ $customer->isActive() ? 'Block '.$customer->name.'?' : 'Unblock '.$customer->name.'?' }}"
                              data-confirm-body="{{ $customer->isActive() ? 'They are signed out of the website and app and cannot sign in until you unblock them.' : 'They will be able to sign in again.' }}"
                              data-confirm-button="{{ $customer->isActive() ? 'Block' : 'Unblock' }}">
                            @csrf
                            @if ($customer->isActive())
                                <button class="btn btn-outline-danger text-start"><i class="bi bi-slash-circle me-2"></i>Block customer</button>
                            @else
                                <button class="btn btn-outline-success text-start"><i class="bi bi-check-circle me-2"></i>Unblock customer</button>
                            @endif
                        </form>
                    </div>
                </x-admin.card>
            @endcan

            <x-admin.card title="Signed-in apps" :subtitle="$apps->count().' device(s) using the mobile app'" flush>
                @forelse ($apps as $app)
                    <div class="d-flex align-items-center gap-3 px-4 py-3 border-top">
                        <i class="bi bi-phone text-secondary"></i>
                        <div class="flex-fill">
                            <div class="fw-semibold">{{ $app->name }}</div>
                            <small class="text-secondary">{{ $app->last_used_at ? 'Used '.$app->last_used_at->diffForHumans() : 'Not used yet' }}@if ($app->expires_at) · expires {{ $app->expires_at->format('d M Y') }}@endif</small>
                        </div>
                        @can('customers.edit')
                            <x-admin.delete-button :action="route('admin.customers.apps.revoke-one', [$customer, $app->id])" confirm="Sign out of this device?" body="The app on that device will ask them to sign in again." button="Sign out" />
                        @endcan
                    </div>
                @empty
                    <div class="px-4 py-3 border-top text-secondary small">Not signed in to the app anywhere.</div>
                @endforelse
                @if ($apps->count() > 1)
                    @can('customers.edit')
                        <div class="px-4 py-3 border-top">
                            <x-admin.delete-button :action="route('admin.customers.apps.revoke', $customer)" label="Sign out of every device" confirm="Sign out of every device?" body="Every app session ends." button="Sign out all" />
                        </div>
                    @endcan
                @endif
            </x-admin.card>

            <x-admin.card title="Staff notes">
                @if ($customer->notes)
                    <p class="mb-0 small" style="white-space: pre-line">{{ $customer->notes }}</p>
                @else
                    <p class="mb-0 small text-secondary">No notes. Add some with Edit.</p>
                @endif
            </x-admin.card>

            @can('customers.delete')
                <x-admin.delete-button :action="route('admin.customers.destroy', $customer)" label="Delete customer" :confirm="'Delete '.$customer->name.'?'" body="They are signed out everywhere and go to the Trash, where they can be restored." button="Delete customer" class="w-100" />
            @endcan
        </div>
    </div>
@endsection
