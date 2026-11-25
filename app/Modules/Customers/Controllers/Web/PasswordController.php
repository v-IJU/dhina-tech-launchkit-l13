<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Models\Customer;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * "Forgot password" and "Reset password" for customers (password broker "customers").
 * The same reset link is used by the website, the app and staff ("Send reset link").
 */
class PasswordController extends Controller
{
    public function showForgot(): View
    {
        return view('customers::web.auth.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::broker('customers')->sendResetLink(['email' => strtolower((string) $request->input('email'))]);

        // Same answer whether or not the email exists, so accounts can't be discovered.
        return back()->with('status', __('If that email has an account, a reset link is on its way.'));
    }

    public function showReset(Request $request, string $token): View
    {
        return view('customers::web.auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::broker('customers')->reset(
            ['email' => strtolower((string) $request->input('email'))] + $request->only('password', 'password_confirmation', 'token'),
            function (Customer $customer, string $password) {
                $customer->forceFill(['password' => $password, 'remember_token' => Str::random(60)]);
                // Opening the emailed link proves they own the address.
                $customer->email_verified_at ??= now();
                $customer->save();
                $customer->tokens()->delete(); // sign out of the app everywhere

                event(new PasswordReset($customer));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
        }

        return redirect()->route('customer.login')->with('status', __('Your password has been reset. You can sign in now.'));
    }
}
