@extends('customers::web.account.layout')

@section('title', __('Security'))

@php
    $twoFactorBag = $errors->getBag('twoFactor');
    $confirmError = $errors->getBag('confirmTwoFactorAuthentication')->first('code');
    $showCodes = in_array(session('two_factor'), ['confirmed', 'recovery-codes'], true);
@endphp

@section('account')
    @component('customers::web.account._card', ['title' => __('Two-factor login'), 'text' => __('Ask for a 6-digit code from an app such as Google Authenticator every time you sign in.')])
        @slot('action')
            <span @class(['rounded-full px-3 py-1 text-xs font-semibold', 'bg-emerald-50 text-emerald-700' => $customer->two_factor_confirmed_at, 'bg-slate-100 text-slate-600' => ! $customer->two_factor_confirmed_at])>
                {{ $customer->two_factor_confirmed_at ? __('On') : __('Off') }}
            </span>
        @endslot

        @if (! \App\Modules\Customers\Support\CustomerAccounts::twoFactorAllowed() && ! $customer->two_factor_secret)
            <p class="text-sm text-slate-500">{{ __('Two-factor login is not available at the moment.') }}</p>
        @elseif (! $customer->two_factor_secret)
            {{-- Step 1: password, then the QR code appears --}}
            <form method="POST" action="{{ route('customer.two-factor.manage', 'enable') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                @csrf
                @include('customers::web._field', ['name' => 'password', 'id' => 'tf_password', 'label' => __('Your password'), 'type' => 'password', 'bag' => 'twoFactor', 'required' => true, 'autocomplete' => 'current-password', 'class' => 'sm:w-72'])
                <button class="btn-brand">{{ __('Turn on two-factor login') }}</button>
            </form>
        @elseif (! $customer->two_factor_confirmed_at)
            {{-- Step 2: scan + confirm with a code --}}
            <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
                <div class="w-fit rounded-xl border border-slate-200 bg-white p-3">{!! $customer->twoFactorQrCodeSvg() !!}</div>
                <div class="flex-1 space-y-4">
                    <ol class="list-decimal space-y-1 ps-5 text-sm text-slate-700">
                        <li>{{ __('Open your authenticator app and scan this QR code.') }}</li>
                        <li>{{ __('Enter the 6-digit code the app shows.') }}</li>
                    </ol>
                    <p class="text-xs text-slate-500">{{ __('Can\'t scan? Type this key in the app:') }} <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-slate-800">{{ decrypt($customer->two_factor_secret) }}</code></p>
                    <form method="POST" action="{{ route('customer.two-factor.manage', 'confirm') }}" class="flex flex-col gap-3 sm:flex-row sm:items-start">
                        @csrf
                        <div>
                            <label for="tf_code" class="sr-only">{{ __('Code') }}</label>
                            <input id="tf_code" name="code" inputmode="numeric" autocomplete="one-time-code" required placeholder="123456" @class(['field sm:w-40', '!border-red-400' => $confirmError])>
                            @if ($confirmError)<p class="mt-1.5 text-sm text-red-600">{{ $confirmError }}</p>@endif
                        </div>
                        <button class="btn-brand">{{ __('Confirm') }}</button>
                    </form>
                </div>
            </div>
        @else
            @if ($showCodes)
                <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-5">
                    <p class="text-sm font-semibold text-amber-900">{{ __('Save these recovery codes somewhere safe. Each one works once if you lose your phone.') }}</p>
                    <div id="recoveryCodes" class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm text-slate-800">
                        @foreach ($customer->recoveryCodes() as $code)<div>{{ $code }}</div>@endforeach
                    </div>
                    <button type="button" data-copy="#recoveryCodes" data-copied="{{ __('Copied') }}" class="btn-outline mt-4 !py-2">{{ __('Copy codes') }}</button>
                </div>
            @endif
            <form method="POST" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                @csrf
                @include('customers::web._field', ['name' => 'password', 'id' => 'tf_password', 'label' => __('Your password'), 'type' => 'password', 'bag' => 'twoFactor', 'required' => true, 'autocomplete' => 'current-password', 'class' => 'sm:w-72'])
                <button class="btn-outline" formaction="{{ route('customer.two-factor.manage', 'recovery-codes') }}">{{ __('New recovery codes') }}</button>
                <button class="inline-flex items-center justify-center rounded-lg border border-red-200 px-5 py-3 text-sm font-semibold text-red-700 hover:bg-red-50" formaction="{{ route('customer.two-factor.manage', 'disable') }}">{{ __('Turn off') }}</button>
            </form>
        @endif
    @endcomponent

    @component('customers::web.account._card', ['title' => __('Signed-in apps'), 'text' => __('Phones and tablets where you are signed in to our app.')])
        @if ($apps->isEmpty())
            <p class="text-sm text-slate-500">{{ __('You are not signed in to the app anywhere.') }}</p>
        @else
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($apps as $app)
                    <li class="flex justify-between gap-4 py-2.5">
                        <span class="font-medium text-slate-800">{{ $app->name }}</span>
                        <span class="text-slate-500">{{ $app->last_used_at ? __('Used :time', ['time' => $app->last_used_at->diffForHumans()]) : __('Not used yet') }}</span>
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('customer.apps.destroy') }}" class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end">
                @csrf @method('DELETE')
                @include('customers::web._field', ['name' => 'password', 'id' => 'apps_password', 'label' => __('Your password'), 'type' => 'password', 'bag' => 'apps', 'required' => true, 'autocomplete' => 'current-password', 'class' => 'sm:w-72'])
                <button class="btn-outline">{{ __('Sign out of the app everywhere') }}</button>
            </form>
        @endif
    @endcomponent

    @component('customers::web.account._card', ['title' => __('Recent sign-ins'), 'text' => __('If you see something you don\'t recognise, change your password.')])
        @if ($logins->isEmpty())
            <p class="text-sm text-slate-500">{{ __('No sign-ins yet.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="py-2 pe-4 font-semibold">{{ __('When') }}</th><th class="py-2 pe-4 font-semibold">{{ __('What') }}</th><th class="py-2 pe-4 font-semibold">{{ __('Device') }}</th><th class="py-2 font-semibold">{{ __('IP address') }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($logins as $login)
                            <tr>
                                <td class="py-2.5 pe-4 whitespace-nowrap text-slate-700">{{ $login->created_at?->diffForHumans() }}</td>
                                <td class="py-2.5 pe-4"><span @class(['font-medium', 'text-red-600' => $login->event === 'failed', 'text-slate-800' => $login->event !== 'failed'])>{{ __(\App\Models\LoginEvent::EVENTS[$login->event] ?? $login->event) }}</span>
                                    @if ($login->is_new_device)<span class="ms-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">{{ __('New device') }}</span>@endif</td>
                                <td class="py-2.5 pe-4 text-slate-600">{{ $login->device() }}</td>
                                <td class="py-2.5 font-mono text-xs text-slate-500">{{ $login->ip_address }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endcomponent
@endsection
