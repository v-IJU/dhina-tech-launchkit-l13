@php
    $testimonials = module_enabled('testimonials')
        ? \App\Modules\Testimonials\Models\Testimonial::query()->where('is_active', true)->latest()->limit(6)->get()
        : collect();
@endphp
@if ($testimonials->isNotEmpty())
    <section class="section">
        <div class="container-site">
            <div class="mx-auto max-w-2xl text-center">
                @if (! empty($data['heading']))<h2 class="section-title">{{ $data['heading'] }}</h2>@endif
                @if (! empty($data['subheading']))<p class="mt-4 text-lg text-slate-600">{{ $data['subheading'] }}</p>@endif
            </div>
            <div class="mt-14 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($testimonials as $t)
                    <figure class="flex flex-col rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
                        <div class="text-amber-500" aria-label="{{ $t->rating ?? 5 }} out of 5 stars">{{ str_repeat('★', (int) ($t->rating ?? 5)) }}</div>
                        <blockquote class="mt-4 flex-1 leading-relaxed text-slate-700">“{{ $t->quote }}”</blockquote>
                        <figcaption class="mt-6 flex items-center gap-3">
                            @if ($t->photo)
                                <img src="{{ Storage::disk('uploads')->url($t->photo) }}" alt="" class="h-11 w-11 rounded-full object-cover">
                            @else
                                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-700">{{ initials($t->client_name) }}</span>
                            @endif
                            <span>
                                <span class="block font-semibold text-ink">{{ $t->client_name }}</span>
                                @if ($t->company)<span class="block text-sm text-slate-500">{{ $t->company }}</span>@endif
                            </span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>
@endif
