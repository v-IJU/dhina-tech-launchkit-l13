@php
    $faqs = module_enabled('faqs')
        ? \App\Modules\Faqs\Models\Faq::query()->where('is_active', true)
            ->when($data['group'] ?? null, fn ($q, $g) => $q->where('group_name', $g))
            ->orderBy('sort_order')->get()
        : collect();
@endphp
@if ($faqs->isNotEmpty())
    <section class="section bg-slate-50">
        <div class="container-site max-w-3xl">
            @if (! empty($data['heading']))<h2 class="section-title text-center">{{ $data['heading'] }}</h2>@endif
            <div class="mt-10 divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-white">
                @foreach ($faqs as $faq)
                    <details class="group p-6" @if ($loop->first) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-ink">
                            {{ $faq->question }}
                            <span class="text-brand-600 transition group-open:rotate-45" aria-hidden="true">+</span>
                        </summary>
                        <p class="mt-3 leading-relaxed whitespace-pre-line text-slate-600">{{ $faq->answer }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>
    @push('head')
        <script type="application/ld+json" nonce="{{ Vite::cspNonce() }}">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqs->map(fn ($f) => ['@type' => 'Question', 'name' => $f->question, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f->answer]])->values()], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endpush
@endif
