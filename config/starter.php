<?php

return [

    /*
    | Big Excel / CSV imports run as one long job (up to an hour). They get their own queue so a long
    | import never blocks emails and other short jobs, and so the queue can allow long jobs:
    |   SQS (production): leave the connection empty and create an SQS queue "imports" with a
    |                     visibility timeout of 3600 s or more.
    |   Redis + Horizon:  IMPORT_QUEUE_CONNECTION=redis-imports (config/queue.php, retry_after 3700;
    |                     Horizon runs it with the "imports" supervisor in config/horizon.php).
    */
    // Staging behind a browser password: "user:password". Empty = off (production). App\Http\Middleware\StagingLock
    'staging_basic_auth' => env('STAGING_BASIC_AUTH', ''),

    'imports' => [
        'connection' => env('IMPORT_QUEUE_CONNECTION') ?: null,   // null = QUEUE_CONNECTION
        'queue' => env('IMPORT_QUEUE', 'imports'),
    ],

    /*
    | URL prefix of the admin panel. Change it to hide the login page,
    | e.g. ADMIN_PATH=control-room  ->  https://site.com/control-room/login
    */
    'admin_path' => env('ADMIN_PATH', 'admin'),

    /*
    | Role that bypasses every permission check (Gate::before).
    */
    'super_admin_role' => 'super-admin',

    /*
    | Roles that can never be deleted from the UI.
    */
    'system_roles' => ['super-admin', 'admin', 'editor'],

    /*
    | Where modules live. Each folder needs a module.json file.
    */
    'modules_path' => app_path('Modules'),

    /*
    | Modules that cannot be disabled from the Modules page.
    */
    /*
    | SaaS mode (multi-tenant). Off = one business, exactly as before.
    | On  = central app on CENTRAL_DOMAIN (pricing, signup, tenants console) + one database
    |       per tenant at {subdomain}.CENTRAL_DOMAIN or their own domain. See SAAS.md.
    | Choose it once at install (starter:install). Existing install → php artisan tenancy:convert
    */
    'tenancy' => [
        'enabled' => (bool) env('STARTER_TENANCY', false),
        // Subdomains nobody can sign up with.
        'reserved_subdomains' => ['www', 'admin', 'app', 'api', 'mail', 'smtp', 'ftp', 'billing', 'help', 'support', 'status', 'docs', 'blog', 'static', 'assets', 'cdn', 'dev', 'staging', 'test', 'demo-admin', 'central', 'console', 'root', 'system'],
        // Billing: days a tenant keeps working after a trial ends or a payment fails.
        'grace_days' => (int) env('SAAS_GRACE_DAYS', 7),
        'currency' => env('SAAS_CURRENCY', 'INR'),
    ],

    'locked_modules' => ['dashboard', 'users', 'roles', 'menus', 'modules', 'settings'],

    /*
    | Sidebar groups, in display order. Menu items from module.json
    | use one of these names in their "group" key.
    */
    'menu_groups' => [
        'Main',
        'Access control',
        'Content',
        'Website CMS',
        'Appearance',
        'Modules',
        'System',
    ],

    /*
    | Standard actions created for every permission resource.
    */
    'standard_actions' => ['view', 'create', 'edit', 'delete'],

    /*
    | Items per page in admin tables.
    */
    'per_page' => 15,

    /*
    | Upload limits (kilobytes).
    */
    'max_upload_kb' => 4096,

    /*
    | Languages the website can be shown in. Turn them on in
    | Settings > Languages; "dir" => "rtl" flips the layout (Arabic, Urdu…).
    */
    'locales' => [
        'en' => ['name' => 'English', 'native' => 'English', 'dir' => 'ltr'],
        'ta' => ['name' => 'Tamil', 'native' => 'தமிழ்', 'dir' => 'ltr'],
        'hi' => ['name' => 'Hindi', 'native' => 'हिन्दी', 'dir' => 'ltr'],
        'ml' => ['name' => 'Malayalam', 'native' => 'മലയാളം', 'dir' => 'ltr'],
        'te' => ['name' => 'Telugu', 'native' => 'తెలుగు', 'dir' => 'ltr'],
        'kn' => ['name' => 'Kannada', 'native' => 'ಕನ್ನಡ', 'dir' => 'ltr'],
        'ar' => ['name' => 'Arabic', 'native' => 'العربية', 'dir' => 'rtl'],
        'fr' => ['name' => 'French', 'native' => 'Français', 'dir' => 'ltr'],
        'de' => ['name' => 'German', 'native' => 'Deutsch', 'dir' => 'ltr'],
        'es' => ['name' => 'Spanish', 'native' => 'Español', 'dir' => 'ltr'],
    ],

    /*
    | API versions: version => route file inside every module.
    | v1 routes are named "api.*" (e.g. api.faqs.index); other versions "api.v2.*".
    | To start v2: uncomment it, then add Routes/api_v2.php to the modules that change.
    | Modules without that file simply have no v2 endpoints; v1 keeps working.
    */
    'api_versions' => [
        'v1' => 'Routes/api.php',
        // 'v2' => 'Routes/api_v2.php',
    ],

    /*
    | Customer (shop) API — for your website customers' mobile app, not staff.
    |   Routes/shop.php        -> /api/v1/shop/...  signed-in customers only (middleware group "api.customer")
    |   Routes/shop_guest.php  -> /api/v1/shop/...  public: register, sign in, forgot password
    | Add a version the same way as above, e.g. 'v2' => 'Routes/shop_v2.php'
    | (its public routes then go in Routes/shop_v2_guest.php).
    */
    'shop_api_versions' => [
        'v1' => 'Routes/shop.php',
    ],

    /*
    | Where browsers reach Laravel Reverb (used by the Content-Security-Policy).
    */
    'reverb_public' => [
        'host' => env('REVERB_HOST', 'localhost'),
        'port' => env('REVERB_PORT'),
        'scheme' => env('REVERB_SCHEME', 'https'),
    ],

    /*
    | Horizon queue dashboard. Defaults to /{admin_path}/horizon (super admins only).
    */
    'horizon_path' => env('HORIZON_PATH'),
    'horizon_alert_email' => env('HORIZON_ALERT_EMAIL'),

    /*
    | Content Security Policy. Extra sources are added for the Vite dev
    | server automatically when APP_ENV=local.
    */
    'csp' => [
        'enabled' => env('CSP_ENABLED', true),
        'frame_src' => ['https://www.google.com', 'https://maps.google.com', 'https://www.youtube.com'],
        'img_src' => ['data:', 'blob:', 'https:'],
    ],
];
