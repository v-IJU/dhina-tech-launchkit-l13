<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * /account — My account: profile details, password, delete account.
 */
class AccountController extends Controller
{
    public function show(Request $request): View
    {
        return view('customers::web.account.profile', ['customer' => $this->customer($request)]);
    }

    public function update(Request $request): RedirectResponse
    {
        $customer = $this->customer($request);
        $emailChanged = strtolower((string) $request->input('email')) !== $customer->email;

        $data = $request->validateWithBag('profile', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customer)],
            'current_password' => [Rule::requiredIf($emailChanged), 'nullable', 'current_password:customer'],
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(array_keys(Customer::GENDERS))],
            'marketing_opt_in' => ['boolean'],
        ]);
        unset($data['current_password']);
        $data['marketing_opt_in'] = $request->boolean('marketing_opt_in');

        $customer->fill($data);
        if ($emailChanged) {
            $customer->email_verified_at = null;
        }
        $customer->save();

        if ($emailChanged && setting('customer_verify_email', true)) {
            $customer->sendEmailVerificationNotification();

            return redirect()->route('customer.verification.notice')->with('status', __('Please confirm your new email address.'));
        }
        if ($emailChanged) {
            $customer->markEmailAsVerified();
        }

        return back()->with('success', __('Your details have been saved.'));
    }

    public function password(Request $request): RedirectResponse
    {
        $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password:customer'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $customer = $this->customer($request);
        $customer->update(['password' => $request->input('password')]);

        return back()->with('success', __('Your password has been changed.'));
    }

    /**
     * The customer closes their own account. It goes to the admin Trash
     * (staff can restore it); app tokens stop working straight away.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('deleteAccount', ['password' => ['required', 'current_password:customer']]);

        $customer = $this->customer($request);
        $customer->tokens()->delete();
        Auth::guard('customer')->logout();
        $customer->delete();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', __('Your account has been deleted.'));
    }

    private function customer(Request $request): Customer
    {
        /** @var Customer */
        return $request->user('customer');
    }
}
