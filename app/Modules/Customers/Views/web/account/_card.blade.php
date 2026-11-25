{{--
    White panel with a heading, used with @component:
    @component('customers::web.account._card', ['title' => __('Addresses'), 'text' => '…', 'danger' => false]) …content… @endcomponent
--}}
<div @class(['rounded-2xl border bg-white p-6 shadow-sm sm:p-7', 'border-red-200' => $danger ?? false, 'border-slate-200' => ! ($danger ?? false)])>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-bold text-ink">{{ $title }}</h2>
            @isset($text)<p class="mt-1 text-sm text-slate-600">{{ $text }}</p>@endisset
        </div>
        @isset($action){{ $action }}@endisset
    </div>
    <div class="mt-5">{{ $slot }}</div>
</div>
