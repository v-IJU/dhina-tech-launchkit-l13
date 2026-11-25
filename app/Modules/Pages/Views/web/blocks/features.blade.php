@php
    $services = module_enabled('services')
        ? \App\Modules\Services\Models\Service::query()->where('is_active', true)->orderBy('sort_order')->get()
        : collect();
@endphp
<section class="section bg-slate-50">
    <div class="container-site">
        <div class="mx-auto max-w-2xl text-center">
            @if (! empty($data['eyebrow']))<p class="eyebrow">{{ $data['eyebrow'] }}</p>@endif
            @if (! empty($data['heading']))<h2 class="section-title mt-2">{{ $data['heading'] }}</h2>@endif
            @if (! empty($data['subheading']))<p class="mt-4 text-lg text-slate-600">{{ $data['subheading'] }}</p>@endif
        </div>
        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($services as $service)
                <div class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-600 text-xl font-bold text-white" aria-hidden="true">
                        @if ($service->icon && str_starts_with($service->icon, 'bi-'))<i class="bi {{ $service->icon }}"></i>@else{{ mb_substr($service->title, 0, 1) }}@endif
                    </span>
                    <h3 class="mt-5 text-lg font-semibold text-ink">{{ $service->title }}</h3>
                    @if ($service->summary)<p class="mt-2 leading-relaxed text-slate-600">{{ $service->summary }}</p>@endif
                </div>
            @empty
                <p class="col-span-full text-center text-slate-500">Add services in the admin panel under Website CMS › Services.</p>
            @endforelse
        </div>
    </div>
</section>
