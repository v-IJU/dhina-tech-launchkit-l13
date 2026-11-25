{{-- Central domain /signup (SaaS mode): creates a tenant on a free trial. --}}
@extends('layouts.web')

@section('title', __('Start your free trial'))

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <section class="bg-slate-50 py-14 sm:py-20">
        <div class="mx-auto w-full max-w-xl px-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm sm:p-9">
                <h1 class="text-2xl font-bold tracking-tight text-ink">{{ __('Start your free trial') }}</h1>
                <p class="mt-2 text-sm text-slate-600">{{ __('Your own admin panel and website, ready in a few seconds.') }}</p>

                <form method="POST" action="{{ route('saas.signup.store') }}" class="mt-7 space-y-5" data-signup>
                    @csrf
                    <div>
                        <label for="f_plan" class="field-label">{{ __('Plan') }}</label>
                        <select id="f_plan" name="plan_id" class="field" required>
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}" @selected((string) old('plan_id', $plans->firstWhere('slug', $selected)?->id ?? $plans->firstWhere('is_featured', true)?->id) === (string) $plan->id)>
                                    {{ $plan->name }} · {{ $plan->formattedPrice() }}/{{ __('month') }} · {{ __(':days days free', ['days' => $plan->trial_days]) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="f_name" class="field-label">{{ __('Company name') }}</label>
                        <input id="f_name" name="name" value="{{ old('name') }}" required autocomplete="organization" @class(['field', '!border-red-400' => $errors->has('name')])>
                        @error('name')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="f_subdomain" class="field-label">{{ __('Your address') }}</label>
                        <div class="flex items-stretch overflow-hidden rounded-lg border border-slate-300 focus-within:border-brand-600 focus-within:ring-2 focus-within:ring-brand-100">
                            <input id="f_subdomain" name="subdomain" value="{{ old('subdomain') }}" required autocomplete="off" spellcheck="false"
                                   class="min-w-0 flex-1 border-0 px-3.5 py-2.5 text-sm outline-none focus:ring-0" placeholder="acme" data-check-url="{{ route('saas.signup.check') }}">
                            <span class="flex items-center bg-slate-50 px-3 text-sm text-slate-500">.{{ config('tenancy.central_domains.0') }}</span>
                        </div>
                        <p class="mt-1.5 text-sm {{ $errors->has('subdomain') ? 'text-red-600' : 'text-slate-500' }}" data-subdomain-hint>{{ $errors->first('subdomain') ?: __('Letters, numbers and dashes. You can add your own domain later.') }}</p>
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="f_owner" class="field-label">{{ __('Your name') }}</label>
                            <input id="f_owner" name="owner_name" value="{{ old('owner_name') }}" required autocomplete="name" class="field">
                        </div>
                        <div>
                            <label for="f_email" class="field-label">{{ __('Work email') }}</label>
                            <input id="f_email" name="owner_email" type="email" value="{{ old('owner_email') }}" required autocomplete="email" @class(['field', '!border-red-400' => $errors->has('owner_email')])>
                            @error('owner_email')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="f_password" class="field-label">{{ __('Password') }}</label>
                            <input id="f_password" name="password" type="password" required autocomplete="new-password" @class(['field', '!border-red-400' => $errors->has('password')])>
                            @error('password')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="f_password2" class="field-label">{{ __('Confirm password') }}</label>
                            <input id="f_password2" name="password_confirmation" type="password" required autocomplete="new-password" class="field">
                        </div>
                    </div>
                    <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
                    <label class="flex items-start gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="terms" value="1" required @checked(old('terms')) class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600">
                        <span>{{ __('I agree to the terms of service and privacy policy') }}</span>
                    </label>
                    @error('terms')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <button class="btn-brand w-full">{{ __('Create my account') }}</button>
                    <p class="text-center text-xs text-slate-500">{{ __('No card needed for the trial.') }}</p>
                </form>
            </div>
            <p class="mt-6 text-center text-sm text-slate-600">
                <a href="{{ route('saas.pricing') }}" class="font-semibold text-brand-700 hover:underline">{{ __('Compare plans') }}</a>
            </p>
        </div>
    </section>
@endsection
