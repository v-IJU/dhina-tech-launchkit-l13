<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers\Web;

use App\Core\Security\PasskeyService;
use App\Http\Controllers\Controller;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerPasskey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Website customers: sign in with a passkey and manage them under My account > Security.
 * Settings > Customers > "Passkey sign-in for customers" turns it off.
 */
class PasskeyController extends Controller
{
    public function __construct(private readonly PasskeyService $passkeys) {}

    public function loginOptions(Request $request): JsonResponse
    {
        $this->ensureEnabled();

        return response()->json([
            'options' => $this->passkeys->for(PasskeyService::CUSTOMER, $request)->loginOptions($request, PasskeyService::CUSTOMER),
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $this->ensureEnabled();

        $passkey = $this->passkeys->for(PasskeyService::CUSTOMER, $request)->verify($request, PasskeyService::CUSTOMER);
        $customer = $passkey->user;

        if (! $customer instanceof Customer || ! $customer->isActive()) {
            throw ValidationException::withMessages(['credential' => __('Your account has been blocked. Please contact us.')]);
        }

        Auth::guard('customer')->login($customer, $request->boolean('remember'));
        $request->session()->regenerate();

        return response()->json(['redirect' => $request->session()->pull('url.intended', route('customer.account'))]);
    }

    public function registrationOptions(Request $request): JsonResponse
    {
        $this->ensureEnabled();
        $request->validate(['password' => ['required', 'string']]);

        /** @var Customer $customer */
        $customer = $request->user('customer');
        if (! Hash::check((string) $request->input('password'), $customer->password)) {
            throw ValidationException::withMessages(['password' => __('The password is incorrect.')]);
        }

        return response()->json([
            'options' => $this->passkeys->for(PasskeyService::CUSTOMER, $request)->registrationOptions($request, PasskeyService::CUSTOMER, $customer),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureEnabled();
        $data = $request->validate(['name' => ['nullable', 'string', 'max:60']]);

        /** @var Customer $customer */
        $customer = $request->user('customer');
        $this->passkeys->for(PasskeyService::CUSTOMER, $request)
            ->store($request, PasskeyService::CUSTOMER, $customer, $data['name'] ?: $this->passkeys->defaultName($request));

        session()->flash('success', __('Passkey added. Next time, sign in with it.'));

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, int $passkeyId): RedirectResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');
        CustomerPasskey::query()->where('user_id', $customer->id)->findOrFail($passkeyId)->delete();

        return redirect()->route('customer.security')->with('success', __('Passkey removed.'));
    }

    private function ensureEnabled(): void
    {
        abort_unless((bool) setting('customer_passkeys', true), 404);
    }
}
