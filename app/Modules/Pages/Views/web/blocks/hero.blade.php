@php
    $slides = ! empty($data['use_sliders']) && module_enabled('sliders')
        ? \App\Modules\Sliders\Models\Slider::query()->where('is_active', true)->orderBy('sort_order')->get()
        : collect();
@endphp

@if ($slides->isNotEmpty())
    <section class="relative overflow-hidden bg-ink" data-slider>
        @foreach ($slides as $slide)
            <div class="relative {{ $loop->first ? '' : 'hidden' }}" data-slide>
                @if ($slide->image)
                    <img src="{{ Storage::disk('uploads')->url($slide->image) }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-40">
                @endif
                <div class="container-site relative py-28 sm:py-36">
                    <div class="max-w-2xl">
                        <h1 class="text-4xl font-bold tracking-tight text-white sm:text-6xl">{{ $slide->title }}</h1>
                        @if ($slide->subtitle)<p class="mt-6 text-lg leading-8 text-slate-200">{{ $slide->subtitle }}</p>@endif
                        @if ($slide->button_text)
                            <a href="{{ $slide->button_url ?: '#' }}" class="btn-brand mt-10">{{ $slide->button_text }}</a>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
        @if ($slides->count() > 1)
            <div class="absolute bottom-6 left-1/2 flex -translate-x-1/2 gap-2">
                @foreach ($slides as $slide)
                    <button type="button" class="h-2.5 w-2.5 rounded-full bg-white/50 aria-[current=true]:w-8 aria-[current=true]:bg-white transition-all" data-slide-dot aria-label="Show slide {{ $loop->iteration }}"></button>
                @endforeach
            </div>
        @endif
    </section>
@else
    <section class="relative overflow-hidden bg-gradient-to-b from-brand-50 to-white">
        <div class="container-site grid items-center gap-12 py-20 sm:py-28 lg:grid-cols-2">
            <div>
                @if (! empty($data['heading']))
                    <h1 class="text-4xl font-bold tracking-tight text-ink sm:text-6xl">{{ $data['heading'] }}</h1>
                @endif
                @if (! empty($data['subheading']))
                    <p class="mt-6 text-lg leading-8 text-slate-600">{{ $data['subheading'] }}</p>
                @endif
                <div class="mt-10 flex flex-wrap gap-3">
                    @if (! empty($data['button_text']))<a href="{{ $data['button_url'] ?: '#' }}" class="btn-brand">{{ $data['button_text'] }}</a>@endif
                    @if (! empty($data['secondary_text']))<a href="{{ $data['secondary_url'] ?: '#' }}" class="btn-outline">{{ $data['secondary_text'] }}</a>@endif
                </div>
            </div>
            @if (! empty($data['image']))
                <img src="{{ $data['image'] }}" alt="" class="w-full rounded-3xl object-cover shadow-xl ring-1 ring-slate-200" loading="{{ ($first ?? false) ? 'eager' : 'lazy' }}">
            @endif
        </div>
    </section>
@endif
