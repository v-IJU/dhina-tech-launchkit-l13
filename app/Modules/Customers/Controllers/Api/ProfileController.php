<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Resources\CustomerResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * The signed-in customer's own account.
 */
#[Group('Shop · My account', weight: 11)]
class ProfileController extends Controller
{
    /**
     * Current customer.
     */
    public function show(Request $request): CustomerResource
    {
        return new CustomerResource($this->customer($request)->load('defaultAddress'));
    }

    /**
     * Update profile details.
     *
     * Changing `email` needs `current_password`, and the new address must be confirmed again.
     */
    public function update(Request $request): CustomerResource
    {
        $customer = $this->customer($request);
        $emailChanged = $request->filled('email') && strtolower((string) $request->input('email')) !== $customer->email;

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customer)],
            'current_password' => [Rule::requiredIf($emailChanged), 'nullable', 'string'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender' => ['sometimes', 'nullable', Rule::in(array_keys(Customer::GENDERS))],
            'locale' => ['sometimes', 'nullable', Rule::in(array_keys(config('starter.locales')))],
            'marketing_opt_in' => ['sometimes', 'boolean'],
        ]);

        if ($emailChanged && ! Hash::check((string) $request->input('current_password'), $customer->password)) {
            throw ValidationException::withMessages(['current_password' => __('The password is incorrect.')]);
        }

        unset($data['current_password']);
        $customer->fill($data);

        if ($emailChanged) {
            $customer->email_verified_at = setting('customer_verify_email', true) ? null : now();
        }
        $customer->save();

        if ($emailChanged && ! $customer->hasVerifiedEmail()) {
            $customer->sendEmailVerificationNotification();
        }

        return new CustomerResource($customer->load('defaultAddress'));
    }

    /**
     * Change password.
     *
     * Other devices are signed out; the token used for this request keeps working.
     */
    public function password(Request $request): JsonResponse
    {
        $customer = $this->customer($request);

        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check((string) $request->input('current_password'), $customer->password)) {
            throw ValidationException::withMessages(['current_password' => __('The password is incorrect.')]);
        }

        $customer->update(['password' => $request->input('password')]);
        $customer->tokens()->whereKeyNot($customer->currentAccessToken()->getKey())->delete();

        return response()->json(['message' => 'Password changed.']);
    }

    /**
     * Send the email confirmation link again.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $customer = $this->customer($request);

        if ($customer->hasVerifiedEmail()) {
            return response()->json(['message' => 'Your email address is already confirmed.']);
        }

        $customer->sendEmailVerificationNotification();

        return response()->json(['message' => "A new confirmation link has been sent to {$customer->email}."]);
    }

    /**
     * Delete my account.
     *
     * Required by the App Store and Google Play for apps with sign up. Signs out every device.
     */
    public function destroy(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check((string) $request->input('password'), $customer->password)) {
            throw ValidationException::withMessages(['password' => __('The password is incorrect.')]);
        }

        $customer->tokens()->delete();
        $customer->delete();

        return response()->json(['message' => 'Your account has been deleted.']);
    }

    private function customer(Request $request): Customer
    {
        /** @var Customer */
        return $request->user();
    }
}
