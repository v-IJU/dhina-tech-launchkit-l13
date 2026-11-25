<?php

declare(strict_types=1);

namespace App\Modules\Customers\Resources;

use App\Modules\Customers\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in customer, as the shop API returns it. Staff notes are never included.
 *
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender,
            'locale' => $this->locale,
            'avatar_url' => $this->avatar_url,
            'marketing_opt_in' => $this->marketing_opt_in,
            /** False until the customer clicks the link in the confirmation email. */
            'email_verified' => $this->email_verified_at !== null,
            /** True when the app must ask for a confirmed email before checkout, addresses… */
            'needs_verification' => $this->needsVerification(),
            'two_factor' => $this->two_factor_confirmed_at !== null,
            'default_address' => $this->whenLoaded('defaultAddress', fn () => $this->defaultAddress ? new AddressResource($this->defaultAddress) : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
