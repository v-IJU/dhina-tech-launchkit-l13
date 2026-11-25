<?php

declare(strict_types=1);

namespace App\Core\Security;

use App\Models\LoginEvent;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Notifications\AdminAlert;
use App\Notifications\NewDeviceSignIn;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Writes the sign-in history (table login_events) and warns users about
 * sign-ins from a new device. Registered in AppServiceProvider::registerAuthListeners().
 */
class LoginRecorder
{
    public function __construct(private readonly Request $request) {}

    public function login(Login $event): void
    {
        if ($event->user instanceof Customer) {
            $this->customerLogin($event->user);

            return;
        }

        // Your staff opening a tenant from the SaaS console is not the owner signing in.
        if (! $event->user instanceof User || session('saas_impersonation')) {
            return;
        }

        $user = $event->user;
        $hash = $this->deviceHash($user->id);
        $knownDevice = LoginEvent::query()->where('user_id', $user->id)->where('event', 'login')->where('device_hash', $hash)->exists();
        $hasHistory = LoginEvent::query()->where('user_id', $user->id)->where('event', 'login')->exists();
        $isNew = $hasHistory && ! $knownDevice;

        $record = $this->record('login', $user->id, $user->email, $hash, $isNew);

        if ($isNew && setting('notify_new_device', true)) {
            $user->notify(new NewDeviceSignIn($record));
        }
    }

    public function failed(Failed $event): void
    {
        // Customer sign-ins (website guard "customer", app tokens "customer-api") have their own history.
        if (str_starts_with((string) $event->guard, 'customer')) {
            $email = Str::lower((string) ($event->credentials['email'] ?? ''));
            $customer = $event->user instanceof Customer ? $event->user : Customer::query()->where('email', $email)->first();
            $this->record('failed', null, $email ?: null, customerId: $customer?->id, guard: 'customer');

            return;
        }

        $email = Str::lower((string) ($event->credentials['email'] ?? ''));
        $user = $event->user instanceof User ? $event->user : User::query()->where('email', $email)->first();

        $this->record('failed', $user?->id, $email ?: null);

        // The sign-in form is rate limited (Settings > Security > attempts per minute).
        // When an address reaches the limit, record a lockout and warn the admins once.
        $limit = (int) setting('login_attempts', 5);
        $recent = LoginEvent::query()->where('event', 'failed')->where('email', $email)
            ->where('ip_address', $this->request->ip())->where('created_at', '>=', now()->subMinute())->count();
        if ($email !== '' && $recent === $limit) {
            $this->lockout(new Lockout($this->request));
        }
    }

    public function logout(Logout $event): void
    {
        if ($event->user instanceof Customer && ! session('customer_impersonated_by')) {
            $this->record('logout', null, $event->user->email, customerId: $event->user->id, guard: 'customer');
        }

        if ($event->user instanceof User) {
            $this->record('logout', $event->user->id, $event->user->email);
        }
    }

    public function lockout(Lockout $event): void
    {
        $email = Str::lower((string) $event->request->input('email'));
        $user = User::query()->where('email', $email)->first();
        $this->record('lockout', $user?->id, $email ?: null);

        AdminAlert::toAdmins(
            "Too many failed sign-ins for {$email} from {$event->request->ip()}",
            route('admin.users.logins', ['q' => $email]),
            'users.view',
            'bi-shield-exclamation',
        );
    }

    /**
     * Customer sign-in: history + "new device" email (Settings > Customers).
     * Skipped while staff use "Sign in as customer".
     */
    private function customerLogin(Customer $customer): void
    {
        if (session('customer_impersonated_by')) {
            return;
        }

        $hash = $this->deviceHash($customer->id, 'customer');
        $history = LoginEvent::query()->where('customer_id', $customer->id)->where('event', 'login');
        $isNew = (clone $history)->exists() && ! (clone $history)->where('device_hash', $hash)->exists();

        $record = $this->record('login', null, $customer->email, $hash, $isNew, $customer->id, 'customer');

        if ($isNew && setting('customer_notify_new_device', true)) {
            $customer->notify(new NewDeviceSignIn($record, route('customer.security')));
        }
    }

    private function record(string $event, ?int $userId, ?string $email, ?string $hash = null, bool $isNew = false, ?int $customerId = null, string $guard = 'web'): LoginEvent
    {
        $agent = new UserAgent((string) $this->request->userAgent());

        return LoginEvent::query()->create(array_filter(['customer_id' => $customerId]) + [
            'user_id' => $userId,
            'guard' => $guard,
            'email' => $email,
            'event' => $event,
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 500, ''),
            'browser' => $agent->browser(),
            'platform' => $agent->platform(),
            'device_hash' => $hash,
            'is_new_device' => $isNew,
        ]);
    }

    /**
     * Same browser + operating system + network (first 3 parts of the IP) = same device.
     */
    private function deviceHash(int $userId, string $type = 'user'): string
    {
        $agent = new UserAgent((string) $this->request->userAgent());
        $network = implode('.', array_slice(explode('.', (string) $this->request->ip()), 0, 3));

        return hash('sha256', ($type === 'user' ? '' : $type.':').$userId.'|'.$agent->browser().'|'.$agent->platform().'|'.$network);
    }
}
