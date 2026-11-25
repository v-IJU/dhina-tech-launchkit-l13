<?php

declare(strict_types=1);

namespace App\Modules\Sliders\Resources;

use App\Modules\Sliders\Models\Slider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * JSON shape of one slider in the API. Add, rename or hide fields here.
 *
 * @mixin Slider
 */
class SliderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'button_text' => $this->button_text,
            'button_url' => $this->button_url,
            'image' => $this->image ? Storage::disk('uploads')->url($this->image) : null,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
