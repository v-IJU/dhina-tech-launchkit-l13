<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Core\Security\PasskeyService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Passkey;

/**
 * Admin users: sign in with a passkey (fingerprint, Face ID, Windows Hello, phone) and manage
 * passkeys under My profile > Security. Settings > Security > "Passkey sign-in for staff" turns it off.
 *
 * A passkey counts as strong sign-in on its own (it needs the device AND its fingerprint / PIN),
 * so it replaces password + 2FA code, like on Google, Apple and GitHub.
 */
class PasskeyController extends Controller
{
    public function __construct(private readonly PasskeyService $passkeys) {}

    public function loginOptions(Request $request): JsonResponse
    {
        $this->ensureEnabled();

        return response()->json([
            'options' => $this->passkeys->for(PasskeyService::STAFF, $request)->loginOptions($request, PasskeyService::STAFF),
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $this->ensureEnabled();

        $passkey = $this->passkeys->for(PasskeyService::STAFF, $request)->verify($request, PasskeyService::STAFF);
        $user = $passkey->user;

        if (! $user instanceof User || $user->status !== 'active') {
            throw ValidationException::withMessages(['credential' => __('This account cannot sign in. Please contact an administrator.')]);
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        activity('auth')->causedBy($user)->event('passkey')->log('Signed in with a passkey ('.$passkey->name.')');

        return response()->json(['redirect' => $request->session()->pull('url.intended', url((string) config('starter.admin_path')))]);
    }

    /** Adding a passkey needs the account password, so a borrowed signed-in browser can't add one. */
    public function registrationOptions(Request $request): JsonResponse
    {
        $this->ensureEnabled();
        $request->validate(['password' => ['required', 'string']]);

        /** @var User $user */
        $user = $request->user();
        if (! Hash::check((string) $request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => __('The password is incorrect.')]);
        }

        return response()->json([
            'options' => $this->passkeys->for(PasskeyService::STAFF, $request)->registrationOptions($request, PasskeyService::STAFF, $user),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureEnabled();
        $data = $request->validate(['name' => ['nullable', 'string', 'max:60']]);

        /** @var User $user */
        $user = $request->user();
        $passkey = $this->passkeys->for(PasskeyService::STAFF, $request)
            ->store($request, PasskeyService::STAFF, $user, $data['name'] ?: $this->passkeys->defaultName($request));

        activity('auth')->causedBy($user)->event('passkey')->log('Added a passkey ('.$passkey->name.')');
        session()->flash('success', __('Passkey added. Next time, sign in with it.'));

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, int $passkeyId): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $row = Passkey::query()->where('user_id', $user->id)->findOrFail($passkeyId);
        $row->delete();

        activity('auth')->causedBy($user)->event('passkey')->log('Removed a passkey ('.$row->name.')');

        return redirect()->route('admin.profile.edit', ['tab' => 'security'])->with('success', __('Passkey removed.'));
    }

    private function ensureEnabled(): void
    {
        abort_unless((bool) setting('passkeys_enabled', true), 404);
    }
}
