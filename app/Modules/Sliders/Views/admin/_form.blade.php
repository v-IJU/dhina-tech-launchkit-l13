{{-- Shared by create and edit. Add, remove or reorder fields freely. --}}
<div class="row g-4">
    <div class="col-lg-8">
        <x-admin.card title="Slider details">
            <div class="d-flex flex-column gap-3">
                <x-admin.form.input name="title" label="Title" :value="$slider->title" required />
                <x-admin.form.input name="subtitle" label="Subtitle" :value="$slider->subtitle" />
                <x-admin.form.input name="button_text" label="Button text" :value="$slider->button_text" />
                <x-admin.form.input name="button_url" label="Button url" :value="$slider->button_url" />
                <x-admin.form.image name="image" label="Image" :url="$slider->image ? Storage::disk('uploads')->url($slider->image) : null" />
                <x-admin.form.input type="number" name="sort_order" label="Sort order" :value="$slider->sort_order" />
                <x-admin.form.checkbox name="is_active" label="Is active" :checked="$slider->is_active" />
            </div>
        </x-admin.card>
    </div>
    <div class="col-lg-4">
        <x-admin.card>
            <div class="d-grid gap-2">
                <button class="btn btn-primary">{{ $slider->exists ? 'Save changes' : 'Create slider' }}</button>
                <a href="{{ route('admin.sliders.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
            @if ($slider->exists)
                <div class="small text-secondary mt-3">Created {{ $slider->created_at?->diffForHumans() }} · updated {{ $slider->updated_at?->diffForHumans() }}</div>
            @endif
        </x-admin.card>
    </div>
</div>
