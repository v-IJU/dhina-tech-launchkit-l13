<?php

declare(strict_types=1);

namespace App\Modules\Blog\Requests;

use App\Modules\Blog\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // checked by route permission middleware
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => $this->filled('slug') ? Str::slug((string) $this->input('slug')) : null,
            'status' => $this->input('intent') === 'draft' ? 'draft' : $this->input('status', 'draft'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Post|null $post */
        $post = $this->route('post');

        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200', Rule::unique('posts', 'slug')->ignore($post)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'featured_image' => ['nullable', 'string', 'max:500'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'author_id' => ['nullable', 'integer', Rule::exists('users', 'id')], // empty = the signed-in user
            'status' => ['required', Rule::in(array_keys(Post::STATUSES))],
            'published_at' => ['nullable', 'date', Rule::requiredIf($this->input('status') === 'scheduled')],
            'is_featured' => ['boolean'],
            'tags' => ['nullable', 'string', 'max:300'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:170'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'Please choose a category.',
            'published_at.required' => 'Pick a date and time for scheduled posts.',
        ];
    }
}
