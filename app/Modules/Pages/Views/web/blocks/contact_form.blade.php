<section class="section" id="contact">
    <div class="container-site grid gap-12 lg:grid-cols-5">
        <div class="lg:col-span-2">
            <h2 class="section-title">{{ $data['heading'] ?: __('Get in touch') }}</h2>
            @if (! empty($data['text']))<p class="mt-4 text-lg text-slate-600">{{ $data['text'] }}</p>@endif
            <dl class="mt-8 space-y-4 text-slate-700">
                @if ($email = setting('contact_email'))<div><dt class="text-sm font-semibold text-slate-500">{{ __('Email') }}</dt><dd><a href="mailto:{{ $email }}" class="font-medium text-brand-700">{{ $email }}</a></dd></div>@endif
                @if ($phone = setting('contact_phone'))<div><dt class="text-sm font-semibold text-slate-500">{{ __('Phone') }}</dt><dd>{{ $phone }}</dd></div>@endif
                @if ($hours = setting('business_hours'))<div><dt class="text-sm font-semibold text-slate-500">{{ __('Hours') }}</dt><dd>{{ $hours }}</dd></div>@endif
                @if ($address = setting('contact_address'))<div><dt class="text-sm font-semibold text-slate-500">{{ __('Office') }}</dt><dd class="whitespace-pre-line">{{ $address }}</dd></div>@endif
            </dl>
        </div>
        <div class="lg:col-span-3">
            @if (module_enabled('inbox'))
                @if (session('contact_sent'))
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-8 text-emerald-800" role="status">
                        <h3 class="text-lg font-semibold">{{ __('Thank you, your message is on its way.') }}</h3>
                        <p class="mt-1">{{ __('We usually reply within one business day.') }}</p>
                    </div>
                @else
                    <form method="POST" action="{{ route('contact.submit') }}" class="grid gap-5 rounded-2xl border border-slate-200 bg-white p-8 shadow-sm sm:grid-cols-2">
                        @csrf
                        <input type="hidden" name="page" value="{{ '/'.request()->path() }}">
                        <div class="hidden" aria-hidden="true"><label>Leave empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                        <div>
                            <label for="c_name" class="field-label">{{ __('Name') }}</label>
                            <input id="c_name" name="name" value="{{ old('name') }}" required autocomplete="name" class="field">
                            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="c_email" class="field-label">{{ __('Email') }}</label>
                            <input id="c_email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" class="field">
                            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="c_phone" class="field-label">{{ __('Phone') }} <span class="font-normal text-slate-400">{{ __('(optional)') }}</span></label>
                            <input id="c_phone" name="phone" value="{{ old('phone') }}" autocomplete="tel" class="field">
                        </div>
                        <div>
                            <label for="c_subject" class="field-label">{{ __('Subject') }}</label>
                            <input id="c_subject" name="subject" value="{{ old('subject') }}" class="field">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="c_message" class="field-label">{{ __('Message') }}</label>
                            <textarea id="c_message" name="message" rows="5" required class="field">{{ old('message') }}</textarea>
                            @error('message')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <button class="btn-brand w-full sm:w-auto">{{ __('Send message') }}</button>
                        </div>
                    </form>
                @endif
            @else
                <p class="text-slate-500">{{ __('Turn on the Contact inbox module to show the form.') }}</p>
            @endif
        </div>
    </div>
</section>
