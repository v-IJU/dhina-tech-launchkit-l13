<?php

declare(strict_types=1);

namespace App\Modules\Users\Controllers\Admin;

use App\Core\Security\UserAgent;
use App\Http\Controllers\Controller;
use App\Models\LoginEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

/**
 * The signed-in user's own profile: details, password, two-factor login
 * (handled by Fortify routes) and active sessions.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        $sessions = config('session.driver') === 'database'
            ? DB::table('sessions')->where('user_id', $user->id)->orderByDesc('last_activity')->get()->map(fn ($s) => (object) [
                'ip' => $s->ip_address,
                'agent' => (new UserAgent((string) $s->user_agent))->label(),
                'mobile' => (new UserAgent((string) $s->user_agent))->isMobile(),
                'current' => $s->id === $request->session()->getId(),
                'last_active' => Carbon::createFromTimestamp($s->last_activity)->diffForHumans(),
            ])
            : collect();

        return view('users::admin.profile', [
            'user' => $user,
            'tab' => in_array($request->query('tab'), ['profile', 'security'], true) ? $request->query('tab') : 'profile',
            'sessions' => $sessions,
            'tokens' => $user->tokens()->latest()->get(),
            'passkeys' => $user->passkeys()->latest()->get(),
            'logins' => LoginEvent::query()->where('user_id', $user->id)->whereIn('event', ['login', 'failed', 'lockout'])->latest('id')->limit(10)->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:300'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        $data['name'] = $data['first_name'].' '.$data['last_name'];
        unset($data['avatar']);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('uploads')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'uploads');
        }

        $user->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function logoutOtherSessions(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        Auth::logoutOtherDevices($request->input('password'));

        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
        }

        return back()->with('success', 'Signed out of all other sessions.');
    }

    /**
     * Create a personal API token. The plain token is shown once.
     */
    public function createToken(Request $request): RedirectResponse
    {
        $user = $request->user();
        $aiAllowed = module_enabled('ai') && $user->can('ai.mcp');

        $data = $request->validate([
            'token_name' => ['required', 'string', 'max:100'],
            'token_for' => ['nullable', 'in:api'.($aiAllowed ? ',mcp' : '')],
            'token_access' => ['required', 'in:full,read'],
            'token_expires' => ['required', 'in:never,30,90,365'],
        ]);
        $forAi = ($data['token_for'] ?? 'api') === 'mcp';

        if (! $forAi) {
            abort_unless(setting('api_enabled', true), 403, 'The API is turned off.');
        }

        // API tokens: ["*"] or ["read"]. AI client tokens: ["mcp","write"] or ["mcp","read"], only valid on /mcp.
        $abilities = $forAi
            ? ['mcp', $data['token_access'] === 'read' ? 'read' : 'write']
            : ($data['token_access'] === 'read' ? ['read'] : ['*']);

        $days = $data['token_expires'] === 'never' ? null : (int) $data['token_expires'];
        $maxAiDays = (int) setting('ai_token_days', 90);
        if ($forAi && $maxAiDays > 0) {
            $days = min($days ?? $maxAiDays, $maxAiDays); // AI tokens always expire (Settings > AI)
        }

        $newToken = $user->createToken($data['token_name'], $abilities, $days ? now()->addDays($days) : null);

        activity('auth')->causedBy($user)->event('token')
            ->log(($forAi ? 'Created AI client token' : 'Created API token')." \"{$data['token_name']}\"");

        return redirect()->route('admin.profile.edit', ['tab' => 'security'])
            ->with('new_token', $newToken->plainTextToken)
            ->with('success', 'Token created. Copy it now: it will not be shown again.'.($forAi && $days ? " It expires in {$days} days." : ''));
    }

    public function destroyToken(Request $request, int $token): RedirectResponse
    {
        $request->user()->tokens()->whereKey($token)->delete();

        return redirect()->route('admin.profile.edit', ['tab' => 'security'])->with('success', 'Token revoked.');
    }

    /**
     * Enable / disable two-factor login or make new recovery codes, with the
     * current password typed on the same form. (Fortify's own buttons send you to
     * a separate "confirm password" page and then back without doing the action.)
     */
    public function twoFactor(Request $request, string $action): RedirectResponse
    {
        $request->validateWithBag('twoFactor', ['password' => ['required', 'current_password']]);
        $request->session()->put('auth.password_confirmed_at', time()); // also unlocks Fortify's confirm step

        $user = $request->user();

        $status = match ($action) {
            'enable' => tap('two-factor-authentication-enabled', fn () => app(EnableTwoFactorAuthentication::class)($user)),
            'disable' => tap('two-factor-authentication-disabled', fn () => app(DisableTwoFactorAuthentication::class)($user)),
            default => tap('recovery-codes-generated', fn () => app(GenerateNewRecoveryCodes::class)($user)),
        };

        activity('auth')->causedBy($user)->event('2fa')->log(match ($action) {
            'enable' => 'Started two-factor setup',
            'disable' => 'Turned off two-factor login',
            default => 'Made new 2FA recovery codes',
        });

        return redirect()->route('admin.profile.edit', ['tab' => 'security'])->with('status', $status);
    }
}
