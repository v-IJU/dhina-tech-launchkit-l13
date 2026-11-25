<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Staff add, edit or remove a customer's saved addresses (customer profile page).
 * Routes are scoped: /customers/{customer}/addresses/{address}.
 */
class CustomerAddressController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:customers.edit')];
    }

    public function create(Customer $customer): View
    {
        return view('customers::admin.addresses.form', [
            'customer' => $customer,
            'address' => new CustomerAddress(['name' => $customer->name, 'phone' => $customer->phone, 'country' => 'India']),
        ]);
    }

    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $customer->addresses()->create($request->validate(CustomerAddress::rules()) + ['is_default' => $request->boolean('is_default')]);

        return redirect()->route('admin.customers.show', $customer)->with('success', 'Address added.');
    }

    public function edit(Customer $customer, CustomerAddress $address): View
    {
        return view('customers::admin.addresses.form', ['customer' => $customer, 'address' => $address]);
    }

    public function update(Request $request, Customer $customer, CustomerAddress $address): RedirectResponse
    {
        $address->update(['is_default' => $request->boolean('is_default') || $address->is_default] + $request->validate(CustomerAddress::rules()));

        return redirect()->route('admin.customers.show', $customer)->with('success', 'Address updated.');
    }

    public function destroy(Customer $customer, CustomerAddress $address): RedirectResponse
    {
        $address->delete();

        return back()->with('success', 'Address removed.');
    }
}
