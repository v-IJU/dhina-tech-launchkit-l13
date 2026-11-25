@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="My profile" :breadcrumbs="['Account', 'My profile']" />

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card overflow-hidden">
                <div style="height: 90px; background: linear-gradient(120deg, #1e3a8a, #2563eb)"></div>
                <div class="card-body text-center" style="margin-top: -60px">
                    <x-admin.avatar :user="$user" size="lg" class="border border-4 border-white" />
                    <h2 class="h5 fw-bold mt-2 mb-0">{{ $user->name }}</h2>
                    <div class="text-secondary">{{ $user->email }}</div>
                    <div class="mt-2">
                        @foreach ($user->roles as $role)<span class="badge badge-soft-primary">{{ $role->display_name ?: $role->name }}</span>@endforeach
                    </div>
                    <div class="row mt-4 g-0 border-top pt-3">
                        <div class="col"><div class="fw-bold fs-5">{{ $user->getAllPermissions()->count() ?: ($user->isSuperAdmin() ? 'All' : 0) }}</div><small class="text-secondary">Permissions</small></div>
                        <div class="col"><div class="fw-bold fs-5">{{ \Spatie\Activitylog\Models\Activity::causedBy($user)->count() }}</div><small class="text-secondary">Actions</small></div>
                        <div class="col"><div class="fw-bold fs-5">{{ $user->two_factor_confirmed_at ? 'On' : 'Off' }}</div><small class="text-secondary">2FA</small></div>
                    </div>
                </div>
            </div>
            <div class="list-group vtabs mt-3">
                <a href="{{ route('admin.profile.edit') }}" class="list-group-item {{ $tab === 'profile' ? 'active' : '' }}"><i class="bi bi-person"></i> Profile details</a>
                <a href="{{ route('admin.profile.edit', ['tab' => 'security']) }}" class="list-group-item {{ $tab === 'security' ? 'active' : '' }}"><i class="bi bi-shield-lock"></i> Password &amp; security</a>
            </div>
        </div>

        <div class="col-xl-8 d-flex flex-column gap-4">
            @if ($tab === 'profile')
                <form method="POST" action="{{ route('admin.profile.update') }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <x-admin.card title="Profile details">
                        <div class="row g-3">
                            <x-admin.form.input class="col-md-6" name="first_name" label="First name" :value="$user->first_name ?: \Illuminate\Support\Str::before($user->name, ' ')" required />
                            <x-admin.form.input class="col-md-6" name="last_name" label="Last name" :value="$user->last_name ?: \Illuminate\Support\Str::after($user->name, ' ')" required />
                            <x-admin.form.input class="col-md-6" name="email" type="email" label="Email" :value="$user->email" required />
                            <x-admin.form.input class="col-md-6" name="phone" label="Phone" :value="$user->phone" />
                            <x-admin.form.input class="col-md-6" name="job_title" label="Job title" :value="$user->job_title" />
                            <div class="col-md-6">
                                <label class="form-label" for="avatar">Photo</label>
                                <input type="file" id="avatar" name="avatar" accept="image/*" class="form-control">
                                @error('avatar')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <x-admin.form.textarea class="col-12" name="bio" label="Short bio" :value="$user->bio" rows="3" />
                        </div>
                        <x-slot:footer><button class="btn btn-primary">Save profile</button></x-slot:footer>
                    </x-admin.card>
                </form>
            @else
                <form method="POST" action="{{ route('user-password.update') }}">
                    @csrf
                    @method('PUT')
                    <x-admin.card title="Change password" subtitle="Use a long password you don't use anywhere else.">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="current_password">Current password</label>
                                <input type="password" id="current_password" name="current_password" autocomplete="current-password" @class(['form-control', 'is-invalid' => $errors->updatePassword->has('current_password')])>
                                @error('current_password', 'updatePassword')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="new_password">New password</label>
                                <input type="password" id="new_password" name="password" autocomplete="new-password" data-strength="#profileMeter" @class(['form-control', 'is-invalid' => $errors->updatePassword->has('password')])>
                                @error('password', 'updatePassword')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="d-flex align-items-center gap-2 mt-2" id="profileMeter">
                                    <div class="progress flex-fill" style="height: 6px"><div class="progress-bar" style="width: 0"></div></div>
                                    <small class="fw-semibold" style="width: 52px" data-strength-label></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="password_confirmation">Confirm new password</label>
                                <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" class="form-control">
                            </div>
                        </div>
                        @if (session('status') === 'password-updated')
                            <div class="alert alert-success mt-3 mb-0">Password updated.</div>
                        @endif
                        <x-slot:footer><button class="btn btn-primary">Update password</button></x-slot:footer>
                    </x-admin.card>
                </form>

                <x-admin.card title="Two-factor authentication" subtitle="A 6-digit code from an authenticator app at every sign-in.">
                    <x-slot:actions>
                        <span class="badge {{ $user->two_factor_confirmed_at ? 'badge-soft-success' : 'badge-soft-warning' }}">{{ $user->two_factor_confirmed_at ? 'Enabled' : 'Disabled' }}</span>
                    </x-slot:actions>

                    @if (! $user->two_factor_secret)
                        <p class="text-secondary">Protect your account with Google Authenticator, Microsoft Authenticator, 1Password or any TOTP app.</p>
                        <form method="POST" action="{{ route('admin.profile.two-factor', 'enable') }}" class="d-flex flex-wrap gap-2 align-items-start">
                            @csrf
                            <div>
                                <label for="tfa_password" class="visually-hidden">Current password</label>
                                <input type="password" id="tfa_password" name="password" placeholder="Your current password" autocomplete="current-password" required
                                       @class(['form-control', 'is-invalid' => $errors->twoFactor->has('password')]) style="max-width: 260px">
                                @error('password', 'twoFactor')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <button class="btn btn-primary"><i class="bi bi-shield-plus me-1"></i> Enable two-factor login</button>
                        </form>
                    @elseif (! $user->two_factor_confirmed_at)
                        <p>Scan this QR code with your authenticator app, then enter the 6-digit code to finish.</p>
                        <div class="d-flex flex-wrap gap-4 align-items-start">
                            <div class="p-2 bg-white border rounded-3">{!! $user->twoFactorQrCodeSvg() !!}</div>
                            <form method="POST" action="{{ route('two-factor.confirm') }}" class="d-flex flex-column gap-2" style="max-width: 240px">
                                @csrf
                                <label class="form-label" for="tfa_code">Code from the app</label>
                                <input id="tfa_code" name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" @class(['form-control form-control-lg text-center', 'is-invalid' => $errors->confirmTwoFactorAuthentication->has('code')])>
                                @error('code', 'confirmTwoFactorAuthentication')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <button class="btn btn-primary">Confirm</button>
                            </form>
                        </div>
                        <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-3">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-link text-secondary px-0">Cancel setup</button>
                        </form>
                        <p class="small text-secondary mb-0">No phone at hand? Most apps also let you type this key instead of scanning: <code>{{ decrypt($user->two_factor_secret) }}</code></p>
                    @else
                        @if (in_array(session('status'), ['two-factor-authentication-confirmed', 'recovery-codes-generated'], true))
                            <div class="alert alert-warning">
                                <div class="fw-semibold mb-2">Save these recovery codes somewhere safe. Each one works once if you lose your phone.</div>
                                <div class="row row-cols-2 g-1 code">
                                    @foreach ($user->recoveryCodes() as $code)<div class="col">{{ $code }}</div>@endforeach
                                </div>
                            </div>
                        @endif
                        <p class="text-secondary">Your account asks for a code from your authenticator app at every sign-in.</p>
                        <form method="POST" class="d-flex flex-wrap gap-2 align-items-start">
                            @csrf
                            <div>
                                <label for="tfa_password_on" class="visually-hidden">Current password</label>
                                <input type="password" id="tfa_password_on" name="password" placeholder="Your current password" autocomplete="current-password" required
                                       @class(['form-control', 'is-invalid' => $errors->twoFactor->has('password')]) style="max-width: 240px">
                                @error('password', 'twoFactor')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <button class="btn btn-outline-secondary" formaction="{{ route('admin.profile.two-factor', 'recovery-codes') }}">Show new recovery codes</button>
                            <button class="btn btn-outline-danger" formaction="{{ route('admin.profile.two-factor', 'disable') }}"
                                    data-confirm="Disable two-factor login?" data-confirm-body="Your account will only be protected by your password." data-confirm-button="Disable 2FA">Disable 2FA</button>
                        </form>
                    @endif
                </x-admin.card>

                <x-admin.card title="Active sessions" subtitle="Devices currently signed in to your account." :flush="true">
                    <div class="list-group list-group-flush">
                        @forelse ($sessions as $session)
                            <div class="list-group-item d-flex align-items-center gap-3 py-3">
                                <i class="bi {{ $session->mobile ? 'bi-phone' : 'bi-laptop' }} fs-4 text-secondary"></i>
                                <div class="flex-fill"><div class="fw-semibold">{{ $session->agent }}</div><small class="text-secondary">{{ $session->ip }} · {{ $session->last_active }}</small></div>
                                @if ($session->current)<span class="badge badge-soft-success">This device</span>@endif
                            </div>
                        @empty
                            <div class="p-4 text-secondary">Session list is available when SESSION_DRIVER=database.</div>
                        @endforelse
                    </div>
                    <x-slot:footer>
                        <form method="POST" action="{{ route('admin.profile.sessions.destroy') }}" class="d-flex flex-wrap gap-2 w-100 justify-content-end">
                            @csrf
                            @method('DELETE')
                            <label for="sess_password" class="visually-hidden">Current password</label>
                            <input type="password" id="sess_password" name="password" placeholder="Current password" class="form-control @error('password') is-invalid @enderror" style="max-width: 240px" required>
                            <button class="btn btn-outline-danger">Sign out other sessions</button>
                            @error('password')<div class="text-danger small w-100 text-end">{{ $message }}</div>@enderror
                        </form>
                    </x-slot:footer>
                </x-admin.card>

                @if (setting('api_enabled', true))
                    <x-admin.card title="API tokens" subtitle="For mobile apps, scripts and other systems using /api/v1. A token can do everything your role allows." :flush="true" class="mt-4">
                        @if (session('new_token'))
                            <div class="alert alert-success m-3 mb-0">
                                <div class="fw-semibold mb-1">Your new token. Copy it now, it won't be shown again:</div>
                                <div class="d-flex gap-2 align-items-center">
                                    <code class="flex-fill text-break p-2 bg-body rounded border">{{ session('new_token') }}</code>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-copy="{{ session('new_token') }}"><i class="bi bi-clipboard"></i> Copy</button>
                                </div>
                                <div class="small mt-2">Use it as the header <code>Authorization: Bearer &lt;token&gt;</code>.</div>
                            </div>
                        @endif
                        <div class="list-group list-group-flush">
                            @forelse ($tokens as $token)
                                <div class="list-group-item d-flex align-items-center gap-3 py-3">
                                    <i class="bi bi-key fs-5 text-secondary"></i>
                                    <div class="flex-fill">
                                        <div class="fw-semibold">{{ $token->name }}
                                            <span class="badge {{ in_array('*', $token->abilities ?? [], true) ? 'badge-soft-primary' : 'badge-soft-secondary' }} ms-1">{{ in_array('*', $token->abilities ?? [], true) ? 'Full access' : 'Read only' }}</span>
                                            @if ($token->expires_at?->isPast())<span class="badge badge-soft-danger ms-1">Expired</span>@endif
                                        </div>
                                        <small class="text-secondary">
                                            Created {{ $token->created_at?->format('d M Y') }}
                                            · Last used {{ $token->last_used_at?->diffForHumans() ?? 'never' }}
                                            · {{ $token->expires_at ? 'Expires '.$token->expires_at->format('d M Y') : 'Never expires' }}
                                        </small>
                                    </div>
                                    <x-admin.delete-button :action="route('admin.profile.tokens.destroy', $token->id)" :confirm="'Revoke token '.$token->name.'?'" body="Apps using it stop working immediately." button="Revoke" />
                                </div>
                            @empty
                                <div class="p-4 text-secondary">No tokens yet.</div>
                            @endforelse
                        </div>
                        <x-slot:footer>
                            <form method="POST" action="{{ route('admin.profile.tokens.store') }}" class="row g-2 w-100 align-items-end">
                                @csrf
                                <x-admin.form.input class="col-md-4" name="token_name" label="Name" placeholder="e.g. Mobile app" required />
                                <x-admin.form.select class="col-md-3" name="token_access" label="Access" :options="['full' => 'Full access', 'read' => 'Read only']" value="full" />
                                <x-admin.form.select class="col-md-3" name="token_expires" label="Expires" :options="['never' => 'Never', '30' => 'In 30 days', '90' => 'In 90 days', '365' => 'In 1 year']" value="90" />
                                <div class="col-md-2"><button class="btn btn-primary w-100">Create</button></div>
                            </form>
                        </x-slot:footer>
                    </x-admin.card>
                @endif

                <x-admin.card title="Recent sign-ins" subtitle="Your last sign-ins and failed attempts. Something you don't recognise? Change your password." :flush="true" class="mt-4">
                    <div class="list-group list-group-flush">
                        @forelse ($logins as $login)
                            <div class="list-group-item d-flex align-items-center gap-3 py-3">
                                <i class="bi {{ $login->event === 'login' ? 'bi-box-arrow-in-right text-success' : 'bi-x-octagon text-danger' }} fs-5"></i>
                                <div class="flex-fill">
                                    <div class="fw-semibold">
                                        {{ \App\Models\LoginEvent::EVENTS[$login->event] ?? $login->event }} · {{ $login->device() }}
                                        @if ($login->is_new_device)<span class="badge badge-soft-warning ms-1">New device</span>@endif
                                    </div>
                                    <small class="text-secondary">{{ $login->ip_address }} · {{ $login->created_at?->format('d M Y, H:i') }} ({{ $login->created_at?->diffForHumans() }})</small>
                                </div>
                            </div>
                        @empty
                            <div class="p-4 text-secondary">No sign-ins recorded yet.</div>
                        @endforelse
                    </div>
                </x-admin.card>
            @endif
        </div>
    </div>
@endsection
