{{-- Central domain /pricing (SaaS mode). Uses the central website's layout, menus and brand colour. --}}
@extends('layouts.web')

@section('title', __('Pricing'))

@section('content')
    <section class="bg-slate-50 py-16 sm:py-24" x-data>
        <div class="container-site">
            <div class="mx-auto max-w-2xl text-center">
                <p class="eyebrow">{{ __('Pricing') }}</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink sm:text-5xl">{{ __('Simple plans that grow with you') }}</h1>
                <p class="mt-4 text-lg text-slate-600">{{ __('Start with a free trial. No card needed. Cancel any time.') }}</p>
            </div>

            <div class="mx-auto mt-14 grid max-w-6xl gap-6 lg:grid-cols-{{ max(1, min(3, $plans->count())) }}">
                @foreach ($plans as $plan)
                    <div @class(['flex flex-col rounded-2xl border bg-white p-8 shadow-sm', 'border-brand-600 ring-2 ring-brand-600' => $plan->is_featured, 'border-slate-200' => ! $plan->is_featured])>
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-bold text-ink">{{ $plan->name }}</h2>
                            @if ($plan->is_featured)<span class="rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">{{ __('Most popular') }}</span>@endif
                        </div>
                        <p class="mt-2 text-sm text-slate-600">{{ $plan->description }}</p>
                        <p class="mt-6"><span class="text-4xl font-bold tracking-tight text-ink">{{ $plan->formattedPrice() }}</span><span class="text-slate-500">/{{ __('month') }}</span></p>
                        <p class="mt-1 text-sm text-slate-500">{{ __('or :price per year', ['price' => $plan->formattedPrice('year')]) }} · {{ __('+ tax') }}</p>
                        <ul class="mt-8 flex-1 space-y-3 text-sm text-slate-700">
                            @foreach ($plan->features ?? [] as $feature)
                                <li class="flex gap-3"><svg class="h-5 w-5 flex-none text-brand-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0z" clip-rule="evenodd"/></svg>{{ $feature }}</li>
                            @endforeach
                        </ul>
                        @if ($signup)
                            <a href="{{ route('saas.signup', ['plan' => $plan->slug]) }}" @class(['mt-8', 'btn-brand' => $plan->is_featured, 'btn-outline' => ! $plan->is_featured])>
                                {{ $plan->trial_days ? __('Start :days-day free trial', ['days' => $plan->trial_days]) : __('Get started') }}
                            </a>
                        @else
                            <a href="{{ url('/contact') }}" class="btn-outline mt-8">{{ __('Contact us') }}</a>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
