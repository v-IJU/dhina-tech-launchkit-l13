{{-- Shared by create and edit. Add, remove or reorder fields freely. --}}
<div class="row g-4">
    <div class="col-lg-8">
        <x-admin.card title="Service details">
            <div class="d-flex flex-column gap-3">
                <x-admin.form.input name="title" label="Title" :value="$service->title" required />
                <x-admin.form.input name="icon" label="Icon" :value="$service->icon" placeholder="bi-code-slash" hint='Any <a href="https://icons.getbootstrap.com" target="_blank" rel="noopener">Bootstrap icon</a> name, shown on the website.' />
                <x-admin.form.textarea name="summary" label="Summary" :value="$service->summary" />
                <x-admin.form.input type="number" name="sort_order" label="Sort order" :value="$service->sort_order" />
                <x-admin.form.checkbox name="is_active" label="Is active" :checked="$service->is_active" />
            </div>
        </x-admin.card>
    </div>
    <div class="col-lg-4">
        <x-admin.card>
            <div class="d-grid gap-2">
                <button class="btn btn-primary">{{ $service->exists ? 'Save changes' : 'Create service' }}</button>
                <a href="{{ route('admin.services.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
            @if ($service->exists)
                <div class="small text-secondary mt-3">Created {{ $service->created_at?->diffForHumans() }} · updated {{ $service->updated_at?->diffForHumans() }}</div>
            @endif
        </x-admin.card>
    </div>
</div>
