<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /account/addresses — the customer's saved addresses.
 */
class AddressController extends Controller
{
    public function index(Request $request): View
    {
        return view('customers::web.account.addresses', ['customer' => $this->customer($request)->load('addresses')]);
    }

    public function create(Request $request): View
    {
        $customer = $this->customer($request);

        return view('customers::web.account.address-form', [
            'customer' => $customer,
            'address' => new CustomerAddress(['name' => $customer->name, 'phone' => $customer->phone, 'country' => 'India']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(CustomerAddress::rules());
        $this->customer($request)->addresses()->create($data + ['is_default' => $request->boolean('is_default')]);

        return redirect()->route('customer.addresses.index')->with('success', __('Address saved.'));
    }

    public function edit(Request $request, CustomerAddress $address): View
    {
        $this->authorizeAddress($request, $address);

        return view('customers::web.account.address-form', ['customer' => $this->customer($request), 'address' => $address]);
    }

    public function update(Request $request, CustomerAddress $address): RedirectResponse
    {
        $this->authorizeAddress($request, $address);
        $data = $request->validate(CustomerAddress::rules());
        $address->update(['is_default' => $request->boolean('is_default') || $address->is_default] + $data);

        return redirect()->route('customer.addresses.index')->with('success', __('Address updated.'));
    }

    public function makeDefault(Request $request, CustomerAddress $address): RedirectResponse
    {
        $this->authorizeAddress($request, $address);
        $address->update(['is_default' => true]);

        return back()->with('success', __('Default address changed.'));
    }

    public function destroy(Request $request, CustomerAddress $address): RedirectResponse
    {
        $this->authorizeAddress($request, $address);
        $address->delete();

        return back()->with('success', __('Address removed.'));
    }

    /**
     * Customers can only touch their own addresses (404 otherwise, so ids can't be probed).
     */
    private function authorizeAddress(Request $request, CustomerAddress $address): void
    {
        abort_unless($address->customer_id === $this->customer($request)->id, 404);
    }

    private function customer(Request $request): Customer
    {
        /** @var Customer */
        return $request->user('customer');
    }
}
