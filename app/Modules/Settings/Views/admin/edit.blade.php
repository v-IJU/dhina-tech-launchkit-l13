@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Settings" :breadcrumbs="['System', 'Settings', $tabs[$tab]['label']]" />

    <div class="row g-4">
        <div class="col-lg-3">
            <div class="card p-2">
                <div class="list-group vtabs">
                    @foreach ($tabs as $key => $t)
                        <a href="{{ route('admin.settings.edit', ['tab' => $key]) }}" class="list-group-item {{ $tab === $key ? 'active' : '' }}"><i class="bi {{ $t['icon'] }}"></i> {{ $t['label'] }}</a>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-lg-9">
            <form method="POST" action="{{ route('admin.settings.update', $tab) }}">
                @csrf
                @method('PUT')
                <x-admin.card :title="$tabs[$tab]['label']" :subtitle="$tabs[$tab]['description']">
                    <fieldset class="row g-3" @cannot('settings.edit') disabled @endcannot>
                        @foreach ($tabs[$tab]['fields'] as $key => $field)
                            @php $value = $values[$key] ?? null; $col = ! empty($field['span']) ? 'col-12' : 'col-md-6'; $hint = $field['hint'] ?? null; @endphp
                            @switch($field['type'])
                                @case('textarea')
                                    <x-admin.form.textarea :class="$col" :name="$key" :label="$field['label']" :value="$value" rows="3" :hint="$hint" />
                                    @break
                                @case('select')
                                    <x-admin.form.select :class="$col" :name="$key" :label="$field['label']" :options="$field['options']" :value="$value" :hint="$hint" />
                                    @break
                                @case('checkboxes')
                                    @php $checked = array_filter(explode(',', (string) (is_array(old($key)) ? implode(',', old($key)) : $value))); @endphp
                                    <div class="{{ $col }}">
                                        <span class="form-label d-block">{{ $field['label'] }}</span>
                                        <div class="d-flex flex-wrap gap-2">
                                            @foreach ($field['options'] as $optValue => $optLabel)
                                                <input type="checkbox" class="btn-check" name="{{ $key }}[]" id="{{ $key }}_{{ $optValue }}" value="{{ $optValue }}" @checked(in_array((string) $optValue, $checked, true))>
                                                <label class="btn btn-sm btn-outline-secondary" for="{{ $key }}_{{ $optValue }}">{{ $optLabel }}</label>
                                            @endforeach
                                        </div>
                                        @if ($hint)<div class="form-text">{{ $hint }}</div>@endif
                                    </div>
                                    @break
                                @case('toggle')
                                    <div class="{{ $col }}"><div class="border rounded-3 p-3"><x-admin.form.checkbox :name="$key" :label="$field['label']" :checked="(bool) $value" :hint="$hint" /></div></div>
                                    @break
                                @case('color')
                                    <div class="{{ $col }}">
                                        <span class="form-label d-block">{{ $field['label'] }}</span>
                                        <div class="d-flex gap-2">
                                            @foreach ($colors as $color)
                                                <input type="radio" class="btn-check" name="{{ $key }}" id="{{ $key }}_{{ $loop->index }}" value="{{ $color }}" @checked(old($key, $value) === $color)>
                                                <label class="swatch" for="{{ $key }}_{{ $loop->index }}" style="background: {{ $color }}"><span class="visually-hidden">{{ $color }}</span></label>
                                            @endforeach
                                        </div>
                                    </div>
                                    @break
                                @case('media')
                                    <x-admin.form.media :class="$col" :name="$key" :label="$field['label']" :value="$value" :hint="$hint" />
                                    @break
                                @case('password')
                                    <x-admin.form.input :class="$col" type="password" :name="$key" :label="$field['label']" :hint="$hint" autocomplete="new-password" />
                                    @break
                                @default
                                    <x-admin.form.input :class="$col" :type="$field['type']" :name="$key" :label="$field['label']" :value="$value" :hint="$hint" />
                            @endswitch
                        @endforeach
                    </fieldset>
                    <x-slot:footer>
                        @if ($tab === 'mail')
                            @can('settings.edit')
                                <button type="submit" form="testMailForm" class="btn btn-outline-secondary me-auto"><i class="bi bi-send me-1"></i> Send test email</button>
                            @endcan
                        @endif
                        @can('settings.edit')
                            <button class="btn btn-primary">Save changes</button>
                        @endcan
                    </x-slot:footer>
                </x-admin.card>
            </form>
            @if ($tab === 'mail')
                <form method="POST" action="{{ route('admin.settings.test-mail') }}" id="testMailForm">@csrf</form>
            @endif
        </div>
    </div>
@endsection
