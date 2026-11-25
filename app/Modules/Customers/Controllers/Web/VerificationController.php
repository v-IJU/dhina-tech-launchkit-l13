<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Models\Customer;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "Confirm your email" page, resend button and the emailed link.
 */
class VerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        return $customer->needsVerification()
            ? view('customers::web.auth.verify-email')
            : redirect()->route('customer.account');
    }

    public function resend(Request $request): RedirectResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        if (! $customer->hasVerifiedEmail()) {
            $customer->sendEmailVerificationNotification();
        }

        return back()->with('status', __('A new confirmation link has been sent to :email.', ['email' => $customer->email]));
    }

    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $customer = Customer::query()->findOrFail($id);
        abort_unless(hash_equals(sha1($customer->getEmailForVerification()), $hash), 403);

        if (! $customer->hasVerifiedEmail()) {
            $customer->markEmailAsVerified();
            event(new Verified($customer));
        }

        $message = __('Thanks! Your email address is confirmed.');

        return Auth::guard('customer')->id() === $customer->id
            ? redirect()->route('customer.account')->with('success', $message)
            : redirect()->route('customer.login')->with('status', $message);
    }
}
