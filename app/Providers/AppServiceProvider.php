<?php

namespace App\Providers;

use App\Core\Health\HealthCheck;
use App\Core\Mail\RedirectMailOnStaging;
use App\Core\Menus\MenuService;
use App\Core\Security\LoginRecorder;
use App\Core\Settings\SettingsRepository;
use App\Core\Tenancy\Tenancy;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passkeys\Passkeys;
use Opcodes\LogViewer\Facades\LogViewer;
use RuntimeException;
use Stancl\Tenancy\Events\RevertedToCentralContext;
use Stancl\Tenancy\Events\TenancyBootstrapped;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Passkey routes are our own (admin users and customers): app/Http/Controllers/Auth/PasskeyController
        // and the Customers module. The package's single-guard routes are not used.
        Passkeys::ignoreRoutes();

        $this->app->singleton(SettingsRepository::class);
        $this->app->singleton(MenuService::class);
    }

    /** @var array<string, mixed>|null config values from .env, before settings are applied */
    private ?array $configDefaults = null;

    public function boot(): void
    {
        Event::listen(MessageSending::class, RedirectMailOnStaging::class);

        // Strict in development; staging behaves exactly like production (what you test is what goes live).
        Model::preventLazyLoading(! $this->app->environment(['production', 'staging']));
        Paginator::useBootstrapFive();

        // super-admin passes every permission check.
        // Customers (website guard) never get admin abilities.
        // SaaS: a tenant's super admin can't add or delete permission names. A permission only works when
        // the code checks it, and tenants can't change the code; they assign the existing ones to roles.
        Gate::before(function ($user, string $ability) {
            if (Tenancy::inTenant() && in_array($ability, ['permissions.create', 'permissions.delete'], true)) {
                return false;
            }

            return $user instanceof User && $user->hasRole(config('starter.super_admin_role')) ? true : null;
        });

        Password::defaults(fn () => Password::min((int) setting('password_min_length', 8))
            ->letters()->mixedCase()->numbers()->symbols());

        $this->applySettings();
        // SaaS mode: the tenant's own settings (site name, mail, session lifetime) once it is identified,
        // and the central ones again when a request or command switches back.
        Event::listen([TenancyBootstrapped::class, RevertedToCentralContext::class], fn () => $this->applySettings());
        $this->configureApiDocs();
        $this->registerRateLimiters();
        $this->registerAuthListeners();

        View::composer('layouts.admin', function ($view) {
            $user = auth()->user();
            $view->with('sidebar', $user ? app(MenuService::class)->adminSidebar($user) : []);
            $view->with('notifications', $user ? $user->unreadNotifications()->latest()->limit(8)->get() : collect());
        });

        View::composer('layouts.web', function ($view) {
            $menus = app(MenuService::class);
            $view->with('headerMenu', $menus->website('header'));
            $view->with('footerMenu', $menus->website('footer'));
        });
    }

    /**
     * Settings saved in the admin panel override the matching config values.
     */
    private function applySettings(): void
    {
        // Start from .env every time, so one tenant's values never leak into the next context.
        $keys = ['app.name', 'mail.from.name', 'session.lifetime', 'mail.default', 'mail.mailers.smtp.host', 'mail.mailers.smtp.port',
            'mail.mailers.smtp.username', 'mail.mailers.smtp.password', 'mail.mailers.smtp.scheme', 'mail.from.address'];
        $this->configDefaults ??= array_combine($keys, array_map(fn (string $k) => config($k), $keys));
        config($this->configDefaults);

        if ($name = setting('site_name')) {
            config(['app.name' => $name, 'mail.from.name' => $name]);
        }
        if ($minutes = (int) setting('session_lifetime')) {
            config(['session.lifetime' => $minutes]);
        }
        if (setting('mail_override')) {
            config([
                'mail.default' => setting('mail_mailer', 'smtp'),
                'mail.mailers.smtp.host' => setting('mail_host'),
                'mail.mailers.smtp.port' => (int) setting('mail_port', 587),
                'mail.mailers.smtp.username' => setting('mail_username'),
                'mail.mailers.smtp.password' => setting('mail_password'),
                'mail.mailers.smtp.scheme' => setting('mail_encryption') === 'ssl' ? 'smtps' : null,
                'mail.from.address' => setting('mail_from_address', config('mail.from.address')),
            ]);
        }

        if ($this->app->resolved('mail.manager')) {
            $this->app->make('mail.manager')->forgetMailers();
        }
    }

    /**
     * API documentation (generated from the code by Scramble), one page per API version
     * in config('starter.api_versions'):
     *   v1 → /docs/api      (+ /docs/api.json)
     *   v2 → /docs/api/v2   (+ /docs/api/v2.json)   …and so on.
     * Open on local machines; elsewhere only for signed-in super-admins and users with "settings.view".
     */
    private function configureApiDocs(): void
    {
        $bearer = fn (OpenApi $openApi) => $openApi->secure(SecurityScheme::http('bearer'));

        Scramble::configure()->withDocumentTransformers($bearer);   // v1 (api_path in config/scramble.php)

        foreach (array_keys(config('starter.api_versions', [])) as $version) {
            if ($version !== 'v1') {
                Scramble::registerApi($version, ['api_path' => "api/{$version}", 'info' => ['version' => $version] + config('scramble.info')])
                    ->expose(ui: "docs/api/{$version}", document: "docs/api/{$version}.json")
                    ->withDocumentTransformers($bearer);
            }
        }

        Gate::define('viewApiDocs', fn ($user = null) => $user instanceof User && ($user->isSuperAdmin() || $user->can('settings.view')));
    }

    private function registerRateLimiters(): void
    {
        // Public contact and newsletter forms.
        RateLimiter::for('forms', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // API: per token user (or IP when signed out). Token requests: 5/min per IP.
        // Keyed by model + id so staff user 5 and customer 5 never share a bucket.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute((int) setting('api_rate_limit', 60))
            ->by($request->user() ? class_basename($request->user()).':'.$request->user()->getAuthIdentifier() : $request->ip()));
        RateLimiter::for('api-token', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
    }

    private function registerAuthListeners(): void
    {
        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof Customer && ! session('customer_impersonated_by')) {
                $event->user->forceFill(['last_login_at' => now(), 'last_login_ip' => request()->ip()])->saveQuietly();
            }
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now(), 'last_login_ip' => request()->ip()])->saveQuietly();
                activity('auth')->causedBy($event->user)->event('login')->log('Signed in');
            }
        });

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user instanceof User) {
                activity('auth')->causedBy($event->user)->event('logout')->log('Signed out');
            }
        });

        // Log files viewer (System > Monitoring > Log files, opcodesio/log-viewer): staff with logs.* permissions.
        // Server log files are shared: never visible from inside a tenant (SaaS mode).
        LogViewer::auth(fn (Request $request) => ! Tenancy::inTenant() && $request->user() instanceof User && $request->user()->can('logs.view'));
        Gate::define('downloadLogFile', fn (User $user) => $user->can('logs.download'));
        Gate::define('downloadLogFolder', fn (User $user) => $user->can('logs.download'));
        Gate::define('deleteLogFile', fn (User $user) => $user->can('logs.delete'));
        Gate::define('deleteLogFolder', fn (User $user) => $user->can('logs.delete'));

        // /up (load balancer, Docker, deploy.sh): answer 500 when the database, cache or storage is broken.
        Event::listen(DiagnosingHealth::class, function () {
            if ($failed = HealthCheck::criticalFailures()) {
                throw new RuntimeException('Health check failed: '.implode(', ', $failed));
            }
        });

        // Sign-in history (Users > Sign-in history, Profile > Security) and new-device emails.
        Event::listen(Login::class, [LoginRecorder::class, 'login']);
        Event::listen(Failed::class, [LoginRecorder::class, 'failed']);
        Event::listen(Logout::class, [LoginRecorder::class, 'logout']);
        Event::listen(Lockout::class, [LoginRecorder::class, 'lockout']);
    }
}
