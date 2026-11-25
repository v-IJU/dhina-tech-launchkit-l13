{{-- Shared by create and edit. Add, remove or reorder fields freely. --}}
<div class="row g-4">
    <div class="col-lg-8">
        <x-admin.card title="Testimonial details">
            <div class="d-flex flex-column gap-3">
                <x-admin.form.input name="client_name" label="Client name" :value="$testimonial->client_name" required />
                <x-admin.form.input name="company" label="Company" :value="$testimonial->company" />
                <x-admin.form.select name="rating" label="Rating" :options="[5 => '★★★★★  5 stars', 4 => '★★★★  4 stars', 3 => '★★★  3 stars']" :value="$testimonial->rating ?? 5" />
                <x-admin.form.textarea name="quote" label="Quote" :value="$testimonial->quote" required />
                <x-admin.form.image name="photo" label="Photo" :url="$testimonial->photo ? Storage::disk('uploads')->url($testimonial->photo) : null" />
                <x-admin.form.checkbox name="is_active" label="Is active" :checked="$testimonial->is_active" />
            </div>
        </x-admin.card>
    </div>
    <div class="col-lg-4">
        <x-admin.card>
            <div class="d-grid gap-2">
                <button class="btn btn-primary">{{ $testimonial->exists ? 'Save changes' : 'Create testimonial' }}</button>
                <a href="{{ route('admin.testimonials.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
            @if ($testimonial->exists)
                <div class="small text-secondary mt-3">Created {{ $testimonial->created_at?->diffForHumans() }} · updated {{ $testimonial->updated_at?->diffForHumans() }}</div>
            @endif
        </x-admin.card>
    </div>
</div>
