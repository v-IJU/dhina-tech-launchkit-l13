{{-- Shared by create and edit. Add, remove or reorder fields freely. --}}
<div class="row g-4">
    <div class="col-lg-8">
        <x-admin.card title="Faq details">
            <div class="d-flex flex-column gap-3">
                <x-admin.form.input name="question" label="Question" :value="$faq->question" required />
                <x-admin.form.textarea name="answer" label="Answer" :value="$faq->answer" required />
                <x-admin.form.input name="group_name" label="Group name" :value="$faq->group_name" />
                <x-admin.form.input type="number" name="sort_order" label="Sort order" :value="$faq->sort_order" />
                <x-admin.form.checkbox name="is_active" label="Is active" :checked="$faq->is_active" />
            </div>
        </x-admin.card>
    </div>
    <div class="col-lg-4">
        <x-admin.card>
            <div class="d-grid gap-2">
                <button class="btn btn-primary">{{ $faq->exists ? 'Save changes' : 'Create FAQ' }}</button>
                <a href="{{ route('admin.faqs.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
            @if ($faq->exists)
                <div class="small text-secondary mt-3">Created {{ $faq->created_at?->diffForHumans() }} · updated {{ $faq->updated_at?->diffForHumans() }}</div>
            @endif
        </x-admin.card>
    </div>
</div>
