{{-- One block in the page builder. $token keeps field names unique; block order = order on screen. --}}
@php
    $def = $registry[$type];
    $prefix = "blocks[{$token}]";
@endphp
<div class="card block-card" data-block data-token="{{ $token }}">
    <div class="card-header py-2">
        <div class="d-flex align-items-center gap-3 flex-fill min-w-0">
            <i class="bi bi-grip-vertical drag-handle fs-5" title="Drag to reorder"></i>
            <span class="block-icon"><i class="bi {{ $def['icon'] }}"></i></span>
            <div class="min-w-0">
                <div class="fw-semibold"><span data-block-number>1</span>. {{ $def['label'] }}</div>
                <small class="text-secondary d-block text-truncate">{{ $def['description'] }}</small>
            </div>
        </div>
        <div class="d-flex gap-1">
            <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" data-block-up aria-label="Move up"><i class="bi bi-arrow-up"></i></button>
            <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" data-block-down aria-label="Move down"><i class="bi bi-arrow-down"></i></button>
            <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" data-block-duplicate aria-label="Duplicate"><i class="bi bi-copy"></i></button>
            <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#body-{{ $token }}" aria-label="Show or hide fields"><i class="bi bi-chevron-expand"></i></button>
            <button type="button" class="btn btn-sm btn-icon btn-outline-danger" data-block-remove aria-label="Remove block"><i class="bi bi-trash"></i></button>
        </div>
    </div>
    <div class="collapse show" id="body-{{ $token }}">
        <div class="card-body">
            <input type="hidden" name="{{ $prefix }}[type]" value="{{ $type }}">
            <div class="row g-3">
                @foreach ($def['fields'] as $name => $field)
                    @php
                        $id = "blk_{$token}_{$name}";
                        $fieldName = "{$prefix}[data][{$name}]";
                        $value = $data[$name] ?? ($field['default'] ?? null);
                        $wide = in_array($field['type'], ['textarea', 'editor', 'media', 'toggle'], true);
                    @endphp
                    <div class="{{ $wide ? 'col-12' : 'col-md-6' }}">
                        @switch($field['type'])
                            @case('editor')
                                <label class="form-label" for="{{ $id }}">{{ $field['label'] }}</label>
                                <input type="hidden" id="{{ $id }}" name="{{ $fieldName }}" value="{{ $value }}">
                                <trix-editor input="{{ $id }}" class="form-control trix-content" style="min-height: 160px"></trix-editor>
                                @break
                            @case('textarea')
                                <label class="form-label" for="{{ $id }}">{{ $field['label'] }}</label>
                                <textarea id="{{ $id }}" name="{{ $fieldName }}" rows="3" class="form-control">{{ $value }}</textarea>
                                @break
                            @case('select')
                                <label class="form-label" for="{{ $id }}">{{ $field['label'] }}</label>
                                <select id="{{ $id }}" name="{{ $fieldName }}" class="form-select">
                                    @foreach ($field['options'] as $optValue => $optLabel)
                                        <option value="{{ $optValue }}" @selected((string) $value === (string) $optValue)>{{ $optLabel }}</option>
                                    @endforeach
                                </select>
                                @break
                            @case('toggle')
                                <div class="form-check form-switch">
                                    <input type="hidden" name="{{ $fieldName }}" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="{{ $id }}" name="{{ $fieldName }}" value="1" @checked($value)>
                                    <label class="form-check-label" for="{{ $id }}">{{ $field['label'] }}</label>
                                </div>
                                @break
                            @case('media')
                                <x-admin.form.media :name="$fieldName" :label="$field['label']" :value="$value" />
                                @break
                            @default
                                <label class="form-label" for="{{ $id }}">{{ $field['label'] }}</label>
                                <input type="{{ $field['type'] === 'number' ? 'number' : ($field['type'] === 'url' ? 'url' : 'text') }}" id="{{ $id }}" name="{{ $fieldName }}" value="{{ $value }}" class="form-control">
                        @endswitch
                        @if (! empty($field['hint']))<div class="form-text">{{ $field['hint'] }}</div>@endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
