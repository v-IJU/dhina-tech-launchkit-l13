<?php

declare(strict_types=1);

namespace App\Modules\Testimonials\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // access is checked by the permission middleware on the route
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'quote' => ['required', 'string'],
            'photo' => ['nullable', 'image', 'max:'.config('starter.max_upload_kb')],
            'is_active' => ['boolean'],
        ];
    }
}
