<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Support\CustomerAccounts;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Website sign in, sign up, two-factor code and sign out for customers.
 *
 *   /account/login       → showLogin / login
 *   /account/register    → showRegister / register   (Settings > Customers > "Allow sign up")
 *   /account/two-factor  → showTwoFactor / twoFactor (only when the customer turned on 2FA)
 */
class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('customers::web.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $customer = Customer::query()->where('email', strtolower($data['email']))->first();

        if (! $customer || ! Hash::check($data['password'], $customer->password)) {
            event(new Failed('customer', $customer, ['email' => $data['email']]));

            throw ValidationException::withMessages(['email' => __('These credentials do not match our records.')]);
        }

        if (! $customer->isActive()) {
            throw ValidationException::withMessages(['email' => __('Your account has been blocked. Please contact us.')]);
        }

        // 2FA on: remember who is signing in, then ask for the 6-digit code.
        if ($customer->two_factor_confirmed_at) {
            $request->session()->put(['customer.login.id' => $customer->id, 'customer.login.remember' => $request->boolean('remember')]);

            return redirect()->route('customer.two-factor');
        }

        return $this->signIn($request, $customer, $request->boolean('remember'));
    }

    public function showTwoFactor(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('customer.login.id')) {
            return redirect()->route('customer.login');
        }

        return view('customers::web.auth.two-factor');
    }

    public function twoFactor(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:30']]);

        $customer = Customer::query()->find($request->session()->get('customer.login.id'));
        if (! $customer) {
            return redirect()->route('customer.login');
        }

        if (! CustomerAccounts::validTwoFactorCode($customer, (string) $request->input('code'))) {
            event(new Failed('customer', $customer, ['email' => $customer->email]));

            throw ValidationException::withMessages(['code' => __('That code is not valid. Try again or use a recovery code.')]);
        }

        $remember = (bool) $request->session()->pull('customer.login.remember', false);
        $request->session()->forget('customer.login.id');

        return $this->signIn($request, $customer, $remember);
    }

    public function showRegister(): View
    {
        abort_unless(CustomerAccounts::registrationOpen(), 404);

        return view('customers::web.auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        abort_unless(CustomerAccounts::registrationOpen(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('customers', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'marketing_opt_in' => ['boolean'],
            'terms' => ['accepted'],
            'website' => ['prohibited'], // honeypot: bots fill every field
        ]);

        $customer = CustomerAccounts::register($data);

        return $this->signIn($request, $customer, false, __('Welcome! Your account is ready.'));
    }

    public function logout(Request $request): RedirectResponse
    {
        // Only the customer guard: a staff session in the same browser stays signed in.
        Auth::guard('customer')->logout();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', __('You have been signed out.'));
    }

    /**
     * End "Sign in as customer" and go back to the customer in the admin panel.
     */
    public function stopImpersonating(Request $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($request->session()->has('customer_impersonated_by'), 403);

        Auth::guard('customer')->logout();
        $request->session()->forget('customer_impersonated_by');

        return redirect()->route('admin.customers.show', $customer?->getAuthIdentifier());
    }

    private function signIn(Request $request, Customer $customer, bool $remember, ?string $message = null): RedirectResponse
    {
        Auth::guard('customer')->login($customer, $remember);
        $request->session()->regenerate();

        $redirect = redirect()->intended(route('customer.account'));

        return $message ? $redirect->with('success', $message) : $redirect;
    }
}
