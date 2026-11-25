<?php

declare(strict_types=1);

namespace App\Modules\Faqs\Resources\V2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * FAQ in API v2: shorter names.
 *
 * @mixin \App\Modules\Faqs\Models\Faq
 */
class FaqResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->question,
            'body' => $this->answer,
            'group' => $this->group_name ?: 'General',
            'position' => $this->sort_order,
            'active' => $this->is_active,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}