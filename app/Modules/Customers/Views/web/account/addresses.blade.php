@extends('customers::web.account.layout')

@section('title', __('Addresses'))

@section('account')
    @component('customers::web.account._card', ['title' => __('Saved addresses'), 'text' => __('Your default address is filled in for you at checkout.')])
        @slot('action')
            <a href="{{ route('customer.addresses.create') }}" class="btn-brand !py-2.5">{{ __('Add address') }}</a>
        @endslot

        @if ($customer->addresses->isEmpty())
            <p class="rounded-xl border border-dashed border-slate-300 px-6 py-10 text-center text-sm text-slate-500">{{ __('No saved addresses yet.') }}</p>
        @else
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($customer->addresses as $address)
                    <div @class(['flex flex-col rounded-xl border p-5', 'border-brand-600 ring-1 ring-brand-600' => $address->is_default, 'border-slate-200' => ! $address->is_default])>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-ink">{{ $address->label ?: __('Address') }}</span>
                            @if ($address->is_default)<span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-700">{{ __('Default') }}</span>@endif
                        </div>
                        <address class="mt-2 flex-1 text-sm leading-relaxed text-slate-600 not-italic">
                            {{ $address->name }}<br>
                            {{ $address->line1 }}@if ($address->line2), {{ $address->line2 }}@endif<br>
                            {{ $address->city }} {{ $address->postal_code }}@if ($address->state), {{ $address->state }}@endif<br>
                            {{ $address->country }}
                            @if ($address->phone)<br>{{ $address->phone }}@endif
                        </address>
                        <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-sm font-semibold">
                            <a href="{{ route('customer.addresses.edit', $address) }}" class="text-brand-700 hover:underline">{{ __('Edit') }}</a>
                            @unless ($address->is_default)
                                <form method="POST" action="{{ route('customer.addresses.default', $address) }}">@csrf<button class="text-slate-700 hover:underline">{{ __('Make default') }}</button></form>
                            @endunless
                            <form method="POST" action="{{ route('customer.addresses.destroy', $address) }}" data-confirm="{{ __('Remove this address?') }}">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">{{ __('Remove') }}</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endcomponent
@endsection
