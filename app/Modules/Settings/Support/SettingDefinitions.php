<?php

declare(strict_types=1);

namespace App\Modules\Settings\Support;

use App\Modules\Ai\Support\AiSettings;

/**
 * Every setting shown on the Settings page, grouped into tabs.
 * To add a setting: add a line here, then read it anywhere with setting('key').
 *
 * Field keys: type (text|email|url|number|textarea|select|checkboxes|toggle|color|media|password),
 * label, rules, default, hint, options, span (full width).
 */
class SettingDefinitions
{
    public const COLORS = ['#2563eb', '#7c3aed', '#db2777', '#ea580c', '#16a34a', '#0891b2', '#0f172a'];

    /**
     * Tabs shown in this context: a tab with a "module" key only appears when that module can be used here
     * (e.g. Customers in a tenant, Billing and Monitoring in your SaaS console).
     *
     * @return array<string, array{label: string, icon: string, description: string, fields: array<string, array<string, mixed>>}>
     */
    public static function tabs(): array
    {
        return array_filter(self::definitions(), fn (array $tab) => ! isset($tab['module']) || module_enabled($tab['module']));
    }

    /**
     * Every tab and field (seeder defaults use all of them).
     *
     * @return array<string, array{label: string, icon: string, description: string, fields: array<string, array<string, mixed>>}>
     */
    public static function definitions(): array
    {
        return [
            'general' => [
                'label' => 'General', 'icon' => 'bi-globe2', 'description' => 'Basic information shown across the website.',
                'fields' => [
                    'site_name' => ['type' => 'text', 'label' => 'Site name', 'rules' => ['required', 'string', 'max:80'], 'default' => config('app.name')],
                    'site_tagline' => ['type' => 'text', 'label' => 'Tagline', 'rules' => ['nullable', 'string', 'max:120'], 'default' => 'Web, mobile and cloud solutions'],
                    'contact_email' => ['type' => 'email', 'label' => 'Contact email', 'rules' => ['nullable', 'email'], 'hint' => 'Contact form enquiries are also emailed here.'],
                    'contact_phone' => ['type' => 'text', 'label' => 'Phone', 'rules' => ['nullable', 'string', 'max:40']],
                    'whatsapp_number' => ['type' => 'text', 'label' => 'WhatsApp number', 'rules' => ['nullable', 'string', 'max:20'], 'hint' => 'International format without +, e.g. 919876543210'],
                    'business_hours' => ['type' => 'text', 'label' => 'Business hours', 'rules' => ['nullable', 'string', 'max:80'], 'default' => 'Mon – Fri, 9:00 – 18:00'],
                    'contact_address' => ['type' => 'textarea', 'label' => 'Office address', 'rules' => ['nullable', 'string', 'max:300'], 'span' => true],
                    'map_embed_url' => ['type' => 'url', 'label' => 'Google Maps embed URL', 'rules' => ['nullable', 'url', 'starts_with:https://www.google.com/maps'], 'span' => true, 'hint' => 'Google Maps › Share › Embed a map › copy the src link.'],
                ],
            ],
            'branding' => [
                'label' => 'Branding', 'icon' => 'bi-palette', 'description' => 'Logo, favicon and colour used by the website and emails.',
                'fields' => [
                    'logo' => ['type' => 'media', 'label' => 'Logo', 'rules' => ['nullable', 'string', 'max:500'], 'hint' => 'SVG or PNG, at least 240 px wide.'],
                    'favicon' => ['type' => 'media', 'label' => 'Favicon', 'rules' => ['nullable', 'string', 'max:500'], 'hint' => 'Square PNG, 512 × 512.'],
                    'brand_color' => ['type' => 'color', 'label' => 'Primary colour', 'rules' => ['nullable', 'in:'.implode(',', self::COLORS)], 'default' => '#2563eb', 'span' => true],
                    'footer_text' => ['type' => 'textarea', 'label' => 'Footer about text', 'rules' => ['nullable', 'string', 'max:300'], 'span' => true, 'default' => 'We design and build websites, mobile apps and business software for growing companies.'],
                ],
            ],
            'seo' => [
                'label' => 'SEO', 'icon' => 'bi-search', 'description' => 'Default meta tags, analytics and search indexing.',
                'fields' => [
                    'meta_title' => ['type' => 'text', 'label' => 'Default meta title', 'rules' => ['nullable', 'string', 'max:70'], 'span' => true],
                    'meta_description' => ['type' => 'textarea', 'label' => 'Default meta description', 'rules' => ['nullable', 'string', 'max:170'], 'span' => true],
                    'og_image' => ['type' => 'media', 'label' => 'Default social share image', 'rules' => ['nullable', 'string', 'max:500'], 'hint' => '1200 × 630 px.'],
                    'google_analytics_id' => ['type' => 'text', 'label' => 'Google Analytics ID', 'rules' => ['nullable', 'regex:/^G-[A-Z0-9]+$/'], 'hint' => 'e.g. G-XXXXXXX'],
                    'allow_indexing' => ['type' => 'toggle', 'label' => 'Allow search engines', 'rules' => ['boolean'], 'default' => true, 'hint' => 'Controls robots.txt and the noindex tag.'],
                ],
            ],
            'mail' => [
                'label' => 'Mail', 'icon' => 'bi-envelope', 'description' => 'Outgoing mail server. Leave off to use the values from .env.',
                'fields' => [
                    'mail_override' => ['type' => 'toggle', 'label' => 'Use these settings instead of .env', 'rules' => ['boolean'], 'span' => true],
                    'mail_mailer' => ['type' => 'select', 'label' => 'Mailer', 'rules' => ['nullable', 'in:smtp,log'], 'options' => ['smtp' => 'SMTP', 'log' => 'Log only (testing)'], 'default' => 'smtp'],
                    'mail_host' => ['type' => 'text', 'label' => 'Host', 'rules' => ['nullable', 'string', 'max:255']],
                    'mail_port' => ['type' => 'number', 'label' => 'Port', 'rules' => ['nullable', 'integer', 'between:1,65535'], 'default' => 587],
                    'mail_encryption' => ['type' => 'select', 'label' => 'Encryption', 'rules' => ['nullable', 'in:tls,ssl,none'], 'options' => ['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'], 'default' => 'tls'],
                    'mail_username' => ['type' => 'text', 'label' => 'Username', 'rules' => ['nullable', 'string', 'max:255']],
                    'mail_password' => ['type' => 'password', 'label' => 'Password', 'rules' => ['nullable', 'string', 'max:255'], 'hint' => 'Stored encrypted. Leave empty to keep the saved password.'],
                    'mail_from_address' => ['type' => 'email', 'label' => 'From address', 'rules' => ['nullable', 'email']],
                ],
            ],
            'social' => [
                'label' => 'Social links', 'icon' => 'bi-share', 'description' => 'Shown in the website footer.',
                'fields' => [
                    'social_facebook' => ['type' => 'url', 'label' => 'Facebook', 'rules' => ['nullable', 'url']],
                    'social_instagram' => ['type' => 'url', 'label' => 'Instagram', 'rules' => ['nullable', 'url']],
                    'social_linkedin' => ['type' => 'url', 'label' => 'LinkedIn', 'rules' => ['nullable', 'url']],
                    'social_x' => ['type' => 'url', 'label' => 'X (Twitter)', 'rules' => ['nullable', 'url']],
                    'social_youtube' => ['type' => 'url', 'label' => 'YouTube', 'rules' => ['nullable', 'url']],
                ],
            ],
            'security' => [
                'label' => 'Security', 'icon' => 'bi-shield-lock', 'description' => 'Sign-in rules for everyone who uses the admin panel.',
                'fields' => [
                    'enforce_2fa' => ['type' => 'toggle', 'label' => 'Require 2FA for Super Admin and Admin roles', 'rules' => ['boolean'], 'span' => true, 'hint' => 'They must set up an authenticator app before using the panel.'],
                    'session_lifetime' => ['type' => 'number', 'label' => 'Session timeout (minutes)', 'rules' => ['nullable', 'integer', 'between:5,10080'], 'default' => 120],
                    'password_min_length' => ['type' => 'number', 'label' => 'Minimum password length', 'rules' => ['nullable', 'integer', 'between:8,64'], 'default' => 8],
                    'login_attempts' => ['type' => 'number', 'label' => 'Sign-in attempts per minute', 'rules' => ['nullable', 'integer', 'between:3,20'], 'default' => 5],
                    'notify_new_device' => ['type' => 'toggle', 'label' => 'Email users about sign-ins from a new device', 'rules' => ['boolean'], 'default' => true, 'hint' => 'Sent when an account signs in from a browser/network it has not used before.'],
                    'api_enabled' => ['type' => 'toggle', 'label' => 'Allow API access (/api/v1)', 'rules' => ['boolean'], 'default' => true, 'hint' => 'Users create tokens on their profile page. Turning this off blocks every token at once.'],
                    'api_token_days' => ['type' => 'number', 'label' => 'App sign-in tokens expire after (days)', 'rules' => ['nullable', 'integer', 'between:0,3650'], 'default' => 0, 'hint' => 'For tokens from POST /api/v1/auth/token. 0 = never expire.'],
                    'api_rate_limit' => ['type' => 'number', 'label' => 'API requests per minute (per user)', 'rules' => ['nullable', 'integer', 'between:10,10000'], 'default' => 60],
                    'login_history_days' => ['type' => 'number', 'label' => 'Keep sign-in history for (days)', 'rules' => ['nullable', 'integer', 'between:7,3650'], 'default' => 180],
                ],
            ],
            'customers' => [
                'module' => 'customers',
                'label' => 'Customers', 'icon' => 'bi-person-vcard', 'description' => 'Website and app accounts for your customers (Customers module). Staff accounts are not affected.',
                'fields' => [
                    'customer_accounts' => ['type' => 'toggle', 'label' => 'Customer accounts', 'rules' => ['boolean'], 'default' => true, 'span' => true, 'hint' => 'Shows "Sign in" in the website header and turns on /account and /api/v1/shop. Off = those pages return 404.'],
                    'customer_registration' => ['type' => 'toggle', 'label' => 'Allow customers to sign up', 'rules' => ['boolean'], 'default' => true, 'hint' => 'Off = only staff can add customers (Customers > Add customer).'],
                    'customer_verify_email' => ['type' => 'toggle', 'label' => 'Customers must confirm their email', 'rules' => ['boolean'], 'default' => true, 'hint' => 'Until they click the emailed link they can only see the "check your inbox" page.'],
                    'customer_2fa' => ['type' => 'toggle', 'label' => 'Let customers turn on two-factor login', 'rules' => ['boolean'], 'default' => true, 'hint' => 'Optional for each customer, under My account > Security.'],
                    'customer_welcome_email' => ['type' => 'toggle', 'label' => 'Send a welcome email after sign up', 'rules' => ['boolean'], 'default' => true],
                    'customer_notify_new_device' => ['type' => 'toggle', 'label' => 'Email customers about sign-ins from a new device', 'rules' => ['boolean'], 'default' => true],
                    'customer_notify_admins' => ['type' => 'toggle', 'label' => 'Notify staff about new sign ups', 'rules' => ['boolean'], 'default' => false, 'hint' => 'Bell notification for everyone who can view customers.'],
                    'customer_token_days' => ['type' => 'number', 'label' => 'App sign-in lasts (days)', 'rules' => ['nullable', 'integer', 'between:0,3650'], 'default' => 365, 'hint' => 'Tokens from POST /api/v1/shop/auth/token. 0 = until the customer signs out.'],
                ],
            ],
            'languages' => [
                'label' => 'Languages', 'icon' => 'bi-translate', 'description' => 'Show the website in more than one language. Pages, posts and menus can then be translated.',
                'fields' => [
                    'default_locale' => ['type' => 'select', 'label' => 'Main language', 'rules' => ['required', 'in:'.implode(',', array_keys(config('starter.locales')))], 'options' => collect(config('starter.locales'))->map(fn ($l) => $l['name'].' ('.$l['native'].')')->all(), 'default' => 'en', 'hint' => 'The language your content is written in first.'],
                    'website_locales' => ['type' => 'checkboxes', 'label' => 'Also show the website in', 'rules' => ['nullable', 'array'], 'options' => collect(config('starter.locales'))->map(fn ($l) => $l['name'].' ('.$l['native'].')')->all(), 'span' => true, 'hint' => 'A language switcher appears in the website header. Untranslated pages show in the main language.'],
                ],
            ],
            'monitoring' => [
                'module' => 'monitoring',
                'label' => 'Monitoring', 'icon' => 'bi-bug', 'description' => 'Built-in error tracker (System > Monitoring > Error log). For Sentry as well, see DEPLOY.md.',
                'fields' => [
                    'error_log_enabled' => ['type' => 'toggle', 'label' => 'Record errors', 'rules' => ['boolean'], 'default' => true, 'span' => true, 'hint' => 'Every server error is saved once, with a counter for repeats. Errors always go to the log files too.'],
                    'error_notify_admins' => ['type' => 'toggle', 'label' => 'Bell notification for new errors', 'rules' => ['boolean'], 'default' => true, 'hint' => 'For everyone with the "Errors & failed jobs: view" permission. Once per error per hour.'],
                    'error_alert_email' => ['type' => 'email', 'label' => 'Email new errors to', 'rules' => ['nullable', 'email', 'max:255'], 'default' => '', 'hint' => 'A developer or support address. Empty = no emails.'],
                    'error_log_days' => ['type' => 'number', 'label' => 'Keep errors for (days)', 'rules' => ['required', 'integer', 'between:1,365'], 'default' => 30, 'hint' => 'Counted from the last time the error happened.'],
                ],
            ],
            'ai' => [
                'module' => 'ai',
                'label' => 'AI', 'icon' => 'bi-robot', 'description' => 'AI clients (MCP) and the in-panel assistant. See Admin > AI & MCP for how to connect.',
                'fields' => [
                    'ai_mcp_enabled' => ['type' => 'toggle', 'label' => 'Allow AI clients (MCP)', 'rules' => ['boolean'], 'default' => false, 'span' => true, 'hint' => 'Lets Claude, ChatGPT, Cursor… connect at /mcp with a user\'s API token. They can only do what that user may do.'],
                    'ai_provider' => ['type' => 'select', 'label' => 'Assistant AI provider', 'rules' => ['nullable', 'in:'.implode(',', array_keys(AiSettings::PROVIDERS))], 'options' => ['' => 'Off'] + AiSettings::PROVIDERS, 'default' => ''],
                    'ai_model' => ['type' => 'text', 'label' => 'Model', 'rules' => ['nullable', 'string', 'max:100'], 'default' => '', 'hint' => 'Empty = the provider\'s default model (config/ai.php).'],
                    'ai_api_key' => ['type' => 'password', 'label' => 'API key', 'rules' => ['nullable', 'string', 'max:500'], 'hint' => 'Stored encrypted. Empty = use the key from .env (OPENAI_API_KEY, ANTHROPIC_API_KEY…).'],
                ],
            ],
            'billing' => [
                'module' => 'saas',
                'label' => 'Billing', 'icon' => 'bi-receipt', 'description' => 'SaaS mode: your invoices to tenants and the payment gateways. Gateway keys live in .env (RAZORPAY_*, STRIPE_*), see SAAS.md.',
                'fields' => [
                    'billing_company' => ['type' => 'text', 'label' => 'Your company name on invoices', 'rules' => ['nullable', 'string', 'max:150'], 'default' => ''],
                    'billing_gstin' => ['type' => 'text', 'label' => 'Your GSTIN / tax number', 'rules' => ['nullable', 'string', 'max:30'], 'default' => ''],
                    'billing_address' => ['type' => 'textarea', 'label' => 'Your address on invoices', 'rules' => ['nullable', 'string', 'max:500'], 'default' => '', 'span' => true],
                    'billing_tax_label' => ['type' => 'text', 'label' => 'Tax name', 'rules' => ['required', 'string', 'max:20'], 'default' => 'GST'],
                    'billing_tax_rate' => ['type' => 'number', 'label' => 'Tax rate (%)', 'rules' => ['required', 'numeric', 'between:0,50'], 'default' => 18, 'hint' => 'Added on top of plan prices. 0 = no tax line.'],
                    'billing_invoice_prefix' => ['type' => 'text', 'label' => 'Invoice number prefix', 'rules' => ['required', 'alpha_dash', 'max:10'], 'default' => 'INV', 'hint' => 'INV → INV-2026-00042'],
                    'billing_razorpay' => ['type' => 'toggle', 'label' => 'Offer Razorpay', 'rules' => ['boolean'], 'default' => true, 'hint' => 'Cards, UPI, netbanking (India).'],
                    'billing_stripe' => ['type' => 'toggle', 'label' => 'Offer Stripe', 'rules' => ['boolean'], 'default' => true, 'hint' => 'International cards.'],
                    'saas_signup' => ['type' => 'toggle', 'label' => 'Allow self signup', 'rules' => ['boolean'], 'default' => true, 'span' => true, 'hint' => 'Off = only you onboard tenants (SaaS > Onboard tenant). The pricing page then shows "Contact us".'],
                ],
            ],
            'maintenance' => [
                'label' => 'Maintenance', 'icon' => 'bi-cone-striped', 'description' => 'Take the public website offline while you work on it.',
                'fields' => [
                    'maintenance_mode' => ['type' => 'toggle', 'label' => 'Maintenance mode', 'rules' => ['boolean'], 'span' => true, 'hint' => 'Visitors see a "back soon" page. Signed-in staff can still browse the site.'],
                    'maintenance_message' => ['type' => 'textarea', 'label' => 'Message shown to visitors', 'rules' => ['nullable', 'string', 'max:300'], 'span' => true, 'default' => 'We are making some improvements. Back soon!'],
                    'trash_days' => ['type' => 'number', 'label' => 'Empty the trash after (days)', 'rules' => ['nullable', 'integer', 'between:0,3650'], 'default' => 30, 'hint' => 'Items in module trash tabs are deleted forever after this many days. 0 = keep forever. Users are never removed automatically.'],
                ],
            ],
        ];
    }

    /**
     * Default values for the seeder.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $defaults = [];
        foreach (self::definitions() as $tab) {
            foreach ($tab['fields'] as $key => $field) {
                if (array_key_exists('default', $field)) {
                    $defaults[$key] = $field['default'];
                }
            }
        }

        return $defaults;
    }
}
