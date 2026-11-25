<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Customers\Resources\AddressResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The signed-in customer's saved addresses. The default address is listed first.
 * Needs a confirmed email when Settings > Customers asks for one (403 with email_verified: false).
 */
#[Group('Shop · Addresses', weight: 13)]
class AddressController extends Controller
{
    /**
     * List addresses.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return AddressResource::collection($this->customer($request)->addresses);
    }

    /**
     * Add an address.
     *
     * The first address becomes the default automatically.
     */
    public function store(Request $request): JsonResponse
    {
        $address = $this->customer($request)->addresses()->create($request->validate(CustomerAddress::rules()));

        return (new AddressResource($address->refresh()))->response()->setStatusCode(201);
    }

    /**
     * Show one address.
     */
    public function show(Request $request, CustomerAddress $address): AddressResource
    {
        return new AddressResource($this->own($request, $address));
    }

    /**
     * Update an address.
     */
    public function update(Request $request, CustomerAddress $address): AddressResource
    {
        $address = $this->own($request, $address);
        $rules = collect(CustomerAddress::rules())->map(fn (array $r) => ['sometimes', ...$r])->all();
        $address->update($request->validate($rules));

        return new AddressResource($address->refresh());
    }

    /**
     * Make this the default address.
     */
    public function makeDefault(Request $request, CustomerAddress $address): AddressResource
    {
        $address = $this->own($request, $address);
        $address->update(['is_default' => true]);

        return new AddressResource($address);
    }

    /**
     * Delete an address.
     */
    public function destroy(Request $request, CustomerAddress $address): JsonResponse
    {
        $this->own($request, $address)->delete();

        return response()->json(['message' => 'Address removed.']);
    }

    /**
     * Someone else's address id answers 404, so ids can't be probed.
     */
    private function own(Request $request, CustomerAddress $address): CustomerAddress
    {
        abort_unless($address->customer_id === $this->customer($request)->id, 404);

        return $address;
    }

    private function customer(Request $request): Customer
    {
        /** @var Customer */
        return $request->user();
    }
}
