<?php

declare(strict_types=1);

namespace App\Modules\Testimonials\Resources;

use App\Modules\Testimonials\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * JSON shape of one testimonial in the API. Add, rename or hide fields here.
 *
 * @mixin Testimonial
 */
class TestimonialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_name' => $this->client_name,
            'company' => $this->company,
            'rating' => $this->rating,
            'quote' => $this->quote,
            'photo' => $this->photo ? Storage::disk('uploads')->url($this->photo) : null,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
