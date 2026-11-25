@extends('layouts.admin')

@php $editing = $user->exists; @endphp

@section('content')
    <x-admin.page-header :title="$editing ? 'Edit user' : 'Add new user'" :breadcrumbs="['Access control', 'Users' => route('admin.users.index'), $editing ? 'Edit' : 'Add']">
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
        @if ($editing && ! $user->isSuperAdmin() && ! $user->is(auth()->user()))
            @can('users.permissions')
                <a href="{{ route('admin.users.permissions', $user) }}" class="btn btn-outline-secondary"><i class="bi bi-key me-1"></i> Extra permissions @if ($user->permissions()->exists())<span class="badge text-bg-primary ms-1">{{ $user->permissions()->count() }}</span>@endif</a>
            @endcan
        @endif
    </x-admin.page-header>

    @if ($errors->any())
        <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <span>Please fix {{ $errors->count() }} highlighted field(s) before saving.</span>
        </div>
    @endif

    <form method="POST" action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}" enctype="multipart/form-data" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="row g-4">
            <div class="col-xl-8 d-flex flex-column gap-4">
                <x-admin.card title="Personal information" subtitle="Basic details shown on the user profile.">
                    <div class="row g-3">
                        <x-admin.form.input class="col-md-6" name="first_name" label="First name" :value="$user->first_name" required autocomplete="off" />
                        <x-admin.form.input class="col-md-6" name="last_name" label="Last name" :value="$user->last_name" required autocomplete="off" />
                        <x-admin.form.input class="col-md-6" name="email" type="email" label="Email address" :value="$user->email" required placeholder="name@company.com" />
                        <x-admin.form.input class="col-md-6" name="phone" label="Phone" :value="$user->phone" placeholder="+91 98765 43210" hint="Used for SMS alerts and account recovery." />
                        <x-admin.form.input class="col-md-6" name="date_of_birth" type="date" label="Date of birth" :value="$user->date_of_birth?->format('Y-m-d')" />
                        <x-admin.form.select class="col-md-6" name="gender" label="Gender" :options="['female' => 'Female', 'male' => 'Male', 'other' => 'Other']" :value="$user->gender" placeholder="Prefer not to say" />
                        <x-admin.form.input class="col-md-6" name="job_title" label="Job title" :value="$user->job_title" placeholder="Content manager" />
                        <x-admin.form.select class="col-md-6" name="department" label="Department" :options="array_combine($d = ['Management', 'Marketing', 'Sales', 'Support', 'Development', 'Operations'], $d)" :value="$user->department" placeholder="Select department" />
                    </div>
                </x-admin.card>

                <x-admin.card title="Login & security" subtitle="Credentials used to sign in to the admin panel.">
                    <div class="row g-3">
                        <x-admin.form.input class="col-md-6" name="username" label="Username" :value="$user->username" required placeholder="priya.nair" hint="Letters, numbers, dots, dashes and underscores." />
                        <div class="col-md-6"></div>
                        <div class="col-md-6">
                            <label for="f_password" @class(['form-label', 'required' => ! $editing])>Password</label>
                            <div class="input-group">
                                <input type="password" name="password" id="f_password" autocomplete="new-password" data-strength="#pwMeter"
                                       placeholder="{{ $editing ? 'Leave empty to keep the current password' : '' }}"
                                       @class(['form-control', 'is-invalid' => $errors->has('password')])>
                                <button type="button" class="btn btn-outline-secondary" data-toggle-password="#f_password" aria-label="Show or hide password"><i class="bi bi-eye"></i></button>
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="d-flex align-items-center gap-2 mt-2" id="pwMeter">
                                <div class="progress flex-fill" style="height: 6px"><div class="progress-bar" style="width: 0"></div></div>
                                <small class="fw-semibold" style="width: 52px" data-strength-label></small>
                            </div>
                            <button type="button" class="btn btn-link btn-sm px-0" data-generate-password="#f_password, #f_password_confirmation">Generate strong password</button>
                        </div>
                        <div class="col-md-6">
                            <label for="f_password_confirmation" @class(['form-label', 'required' => ! $editing])>Confirm password</label>
                            <input type="password" name="password_confirmation" id="f_password_confirmation" autocomplete="new-password" class="form-control">
                            <div class="form-text">At least {{ setting('password_min_length', 8) }} characters with upper and lower case, a number and a symbol.</div>
                        </div>
                    </div>
                </x-admin.card>

                <x-admin.card title="Address" subtitle="Optional. Used on invoices and staff records.">
                    <div class="row g-3">
                        <x-admin.form.input class="col-12" name="address" label="Street address" :value="$user->address" />
                        <x-admin.form.input class="col-md-6" name="city" label="City" :value="$user->city" />
                        <x-admin.form.input class="col-md-6" name="state" label="State / region" :value="$user->state" />
                        <x-admin.form.select class="col-md-6" name="country" label="Country" :options="array_combine($c = ['India', 'United Arab Emirates', 'Saudi Arabia', 'Qatar', 'United Kingdom', 'United States', 'Germany', 'Singapore', 'Australia', 'Canada'], $c)" :value="$user->country" placeholder="Select country" />
                        <x-admin.form.input class="col-md-6" name="postal_code" label="Postal code" :value="$user->postal_code" />
                        <x-admin.form.textarea class="col-12" name="bio" label="Short bio" :value="$user->bio" rows="3" maxlength="300" placeholder="Shown on author pages for blog posts" hint="Up to 300 characters." />
                    </div>
                </x-admin.card>
            </div>

            <div class="col-xl-4 d-flex flex-column gap-4">
                <x-admin.card title="Profile photo">
                    <div class="d-flex flex-column align-items-center gap-3 text-center">
                        <div class="image-preview rounded-circle" id="avatarPreview" style="width: 112px; height: 112px">
                            @if ($user->avatar_url)
                                <img src="{{ $user->avatar_url }}" alt="">
                            @else
                                <span class="avatar avatar-lg" style="background: {{ avatar_color($user->name ?: 'New user') }}">{{ initials($user->name ?: 'New User') }}</span>
                            @endif
                        </div>
                        <input type="file" name="avatar" accept="image/*" class="form-control form-control-sm" data-image-preview="#avatarPreview" aria-label="Upload photo">
                        @error('avatar')<div class="text-danger small">{{ $message }}</div>@enderror
                        @if ($user->avatar)
                            <x-admin.form.checkbox name="remove_avatar" label="Remove current photo" />
                        @endif
                        <small class="text-secondary">JPG or PNG, max 2 MB.</small>
                    </div>
                </x-admin.card>

                <x-admin.card title="Role & access">
                    <div class="d-flex flex-column gap-3">
                        <x-admin.form.select name="role" label="Role" :options="$roles" :value="$user->roles->first()?->name ?? 'editor'" required hint="Permissions come from the role. Edit them under Roles." />
                        <fieldset>
                            <legend class="form-label">Account status</legend>
                            @foreach (['active' => ['Active', 'Can sign in and use the panel'], 'inactive' => ['Inactive', 'Account exists but cannot sign in'], 'suspended' => ['Suspended', 'Blocked, e.g. after misuse']] as $value => [$label, $hint])
                                <label class="d-flex gap-2 align-items-start border rounded-3 p-2 px-3 mb-2" style="cursor: pointer">
                                    <input class="form-check-input mt-1" type="radio" name="status" value="{{ $value }}" @checked(old('status', $user->status) === $value)>
                                    <span><span class="fw-semibold d-block">{{ $label }}</span><small class="text-secondary">{{ $hint }}</small></span>
                                </label>
                            @endforeach
                            @error('status')<div class="text-danger small">{{ $message }}</div>@enderror
                        </fieldset>
                    </div>
                </x-admin.card>

                <x-admin.card title="Options">
                    <div class="d-flex flex-column gap-3">
                        <x-admin.form.checkbox name="email_verified" label="Mark email as verified" hint="Skip the verification email." :checked="$editing ? (bool) $user->email_verified_at : true" />
                        <x-admin.form.checkbox name="require_two_factor" label="Require two-factor login" hint="User sets up an authenticator app at first sign-in." :checked="$user->require_two_factor" />
                        @unless ($editing)
                            <x-admin.form.checkbox name="send_welcome" label="Send welcome email" hint="Includes a link to choose a password. Sent from the queue." :checked="true" />
                        @endunless
                        <x-admin.form.checkbox name="force_password_reset" label="Force password change" hint="Asked to set a new password at next sign-in." :checked="$user->force_password_reset" />
                        <x-admin.form.textarea name="notes" label="Internal notes" :value="$user->notes" rows="3" placeholder="Only visible to admins" />
                    </div>
                </x-admin.card>

                <div class="card card-body flex-grow-0 d-grid gap-2">
                    <button class="btn btn-primary btn-lg fs-6">{{ $editing ? 'Update user' : 'Create user' }}</button>
                    @unless ($editing)
                        <button name="after" value="another" class="btn btn-outline-secondary">Save &amp; add another</button>
                    @endunless
                    <a href="{{ route('admin.users.index') }}" class="btn btn-link text-secondary">Cancel</a>
                    @if ($editing)
                        <small class="text-secondary text-center">Last login: {{ $user->last_login_at?->format('d M Y, H:i') ?? 'never' }} {{ $user->last_login_ip ? 'from '.$user->last_login_ip : '' }}</small>
                    @endif
                </div>
            </div>
        </div>
    </form>
@endsection
