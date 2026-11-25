<?php

declare(strict_types=1);

namespace App\Modules\Users\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Sign in as" another user to see exactly what they see. Logged in the
 * activity log. Super admins cannot be impersonated.
 */
class ImpersonationController extends Controller
{
    public function start(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->can('users.impersonate'), 403);
        abort_if($user->isSuperAdmin() || $user->is($request->user()), 403, 'This account cannot be impersonated.');
        abort_if($request->session()->has('impersonator_id'), 422, 'Return to your own account first.');

        activity('auth')->causedBy($request->user())->performedOn($user)->event('impersonate')->log("Started signing in as {$user->name}");

        $request->session()->put('impersonator_id', $request->user()->id);
        Auth::guard('web')->login($user);

        return redirect()->route('admin.dashboard')->with('success', "You are now signed in as {$user->name}.");
    }

    public function stop(Request $request): RedirectResponse
    {
        $originalId = $request->session()->pull('impersonator_id');
        abort_unless($originalId, 403);

        $original = User::query()->findOrFail($originalId);
        Auth::guard('web')->login($original);
        $request->session()->regenerate();

        return redirect()->route('admin.users.index')->with('success', 'Welcome back to your own account.');
    }
}
