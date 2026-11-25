# Laravel Starter — Admin Panel + CMS Website

A reusable Laravel 13 / PHP 8.4 monolith to start client projects from:

- **Admin panel** (Bootstrap 5) — dashboard, users, roles & permissions, menu builder, settings, activity log, backups, module manager
- **Business website** (Tailwind CSS 4) — pages built from content blocks, blog, contact form, newsletter, SEO
- **Modules** — every feature is a plain folder in `app/Modules`; `php artisan make:module` creates a new one with full CRUD
- **Data tables** — every list page has live search, column sorting, filters, date range, per-page, CSV export and bulk actions (`app/Core/DataTable`, no jQuery)
- **Trash & restore** — deleted records go to a Trash tab; restore or delete forever; auto-emptied after N days
- **CSV import** — upload, check first, see row-by-row errors; Export → edit in Excel → Import round trip
- **REST API** — `/api/v1` with Sanctum tokens, same permissions as the panel, `make:module --api`
- **Multi-language website** — language switcher, translated pages/posts/menus, RTL for Arabic
- **Sign-in history** — every sign-in and failed attempt, new-device emails, lockout alerts
- **Customer accounts** — website sign up / sign in / My account (profile, addresses, two-factor), customer app API at `/api/v1/shop`, full customer management in the admin panel
- **Extra permissions per user** — one person gets more than their role, with sidebar preview and audit log
- **Monitoring** — built-in error log with bell/email alerts, failed jobs with retry, log file viewer (`/admin/logs`), `/up` health checks, `health:check`, optional Sentry
- **SaaS mode (optional)** — one install for many companies: database per tenant, plans with modules and limits, Razorpay + Stripe subscriptions, self-signup with trial, console with usage and invoices, convert an existing install with `php artisan tenancy:convert` (`SAAS.md`)
- **Uploads on this server or S3** — `UPLOADS_DISK=public|s3`
- **Production deploy** — `compose.prod.yaml`, `./deploy.sh` (build, migrate, health check, automatic rollback), HTTPS / RDS / S3 / Auto Scaling guide in `DEPLOY.md`
- **Realtime, queues, scheduler** — Laravel Reverb, Horizon on Redis, `schedule:work`
- **Docker Compose** — one stack gives you PHP-FPM, nginx, MySQL, Redis, Horizon, scheduler, Reverb, Mailpit and phpMyAdmin

No Filament, Livewire or Nova: controllers, Blade views and form requests you can read top to bottom.

**Docs:** [README_DEVELOPER.md](README_DEVELOPER.md) explains how everything works and how to change it · [README_PROJECT_CMD.md](README_PROJECT_CMD.md) lists every command · [API_GUIDE.md](API_GUIDE.md) is for mobile/frontend developers (interactive docs at `/docs/api`) · [DEPLOY.md](DEPLOY.md) is for putting it on a server · [SAAS.md](SAAS.md) is the optional multi-tenant SaaS mode. Command cheat sheets: [README_SINGLE_BUSINESS.md](README_SINGLE_BUSINESS.md) and [README_SAAS.md](README_SAAS.md).

---

## 1. Quick start (Docker)

Requirements: Docker Desktop (or Docker Engine + Compose v2).

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan starter:install
docker compose run --rm node sh -c "npm install && npm run build"
```

`starter:install` creates the database, seeds roles, permissions, menus, settings and demo website content, and asks for the first super-admin email and password. On Git Bash for Windows, put `winpty` in front of commands that ask questions.

| What | URL |
|---|---|
| Website | http://localhost:8080 |
| Admin panel | http://localhost:8080/admin |
| Queue dashboard (Horizon) | http://localhost:8080/admin/horizon |
| Caught emails (Mailpit) | http://localhost:8025 |
| Databases (phpMyAdmin) | http://localhost:8082 |
| MySQL from your machine | `127.0.0.1:3307` (user `starter` / `secret`) |

Everyday commands (full list in [README_PROJECT_CMD.md](README_PROJECT_CMD.md)):

```bash
docker compose up -d                          # start
docker compose --profile dev up -d            # start + Vite dev server with hot reload (http://localhost:5173)
docker compose --profile dev down             # stop
docker compose exec app bash                  # shell inside the PHP container
docker compose exec app php artisan route:list
docker compose exec app php artisan test      # PHPUnit
docker compose exec app ./vendor/bin/pint     # fix code style
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G   # Larastan
docker compose exec app php artisan starter:install --fresh              # wipe the database and reinstall
```

### Without Docker

PHP 8.4 with `pdo_mysql, redis, gd, intl, zip, bcmath, pcntl, exif`, Composer 2, Node 22, MySQL 8 and Redis 7.

```bash
cp .env.example .env            # set DB_HOST=127.0.0.1, REDIS_HOST=127.0.0.1, MAIL_HOST=127.0.0.1, REVERB_INTERNAL_HOST=
composer install
php artisan key:generate
php artisan starter:install     # migrate + seed + first admin
npm install && npm run build
php artisan serve               # plus, in other terminals:
php artisan horizon
php artisan schedule:work
php artisan reverb:start
```

---

## 2. Folder structure

```
app/
├── Core/                     The engine — small and readable
│   ├── Modules/              Module, ModuleManager, ModuleServiceProvider
│   ├── Menus/                MenuItem model + MenuService (sidebar & website menus)
│   ├── Permissions/          PermissionSync (module.json → permissions table)
│   ├── Settings/             SettingsRepository + setting() helper
│   ├── Console/              make:module, module:*, starter:install
│   └── helpers.php           setting(), module_enabled(), initials(), …
├── Http/Middleware/          SecurityHeaders, EnsureUserIsActive, ForcePasswordChange, EnforceTwoFactor, WebsiteMaintenance
├── Models/                   User, Role
├── Notifications/            AdminAlert (bell + Reverb), WelcomeUser
└── Modules/                  One folder per feature
    ├── Dashboard/  Users/  Roles/  Menus/  Settings/  Activity/  Modules/  Backups/   (core)
    ├── Blog/  Media/  Pages/  Inbox/                                                   (content)
    └── Sliders/  Services/  Testimonials/  Faqs/                                       (generated with make:module)

resources/
├── admin/                    Admin assets: admin.css (Bootstrap theme) + js/ (one file per feature)
├── web/                      Website assets: web.css (Tailwind) + web.js
└── views/
    ├── layouts/              admin, auth, web
    ├── components/admin/     <x-admin.card>, <x-admin.form.input>, … (reusable UI)
    ├── auth/                 sign-in, 2FA, password reset (Laravel Fortify)
    └── errors/               403, 404, 419, 500, 503

stubs/module/                 Templates used by make:module — edit them to change generated code
docker/                       Dockerfile (dev + production targets), nginx, php.ini
```

### Anatomy of a module

```
app/Modules/Blog/
├── module.json               name, permissions, sidebar menu
├── Controllers/Admin/        admin pages (permission middleware on each action)
├── Controllers/Web/          public website pages
├── Models/
├── Requests/                 form validation
├── Routes/admin.php          loaded under /admin with auth + permissions, names "admin.*"
├── Routes/web.php            loaded on the public website
├── Views/admin/              view('blog::admin.posts.index')
├── Views/web/
├── Database/Migrations/      run by php artisan migrate
└── Console/                  artisan commands, registered automatically
```

`module.json`:

```json
{
    "name": "Blog",
    "slug": "blog",
    "enabled": true,
    "order": 30,
    "permissions": [
        { "key": "blog", "label": "Blog posts", "actions": ["view", "create", "edit", "delete", "publish"] }
    ],
    "menu": [
        {
            "key": "blog", "group": "Content", "label": "Blog", "icon": "bi-journal-text", "permission": "blog.view",
            "children": [
                { "key": "blog.posts", "label": "All posts", "route": "admin.blog.posts.index", "permission": "blog.view" }
            ]
        }
    ]
}
```

---

## 3. Create a new module

```bash
php artisan make:module Product \
  --fields="name:string,sku:string:unique,price:decimal,stock:integer,photo:image,description:editor:nullable,category_id:foreignId:nullable,is_active:boolean" \
  --group="Modules" --icon=bi-box --migrate
# Docker: docker compose exec app php artisan make:module Product --fields="name:string,price:decimal" --migrate
```

| Field type | Column | Form input |
|---|---|---|
| `string` `email` `url` | varchar | text / email / url |
| `text` | text | textarea |
| `editor` | longText | rich text (cleaned with HTML Purifier) |
| `integer` `decimal` | int / decimal(12,2) | number |
| `boolean` | boolean | switch — `is_active` also gets a status toggle in the list |
| `date` `datetime` | date / datetime | date pickers |
| `image` | path | upload with preview (stored on the `public` disk) |
| `foreignId` | foreign key | select filled from the related table (`category_id` → `categories`) |

Modifiers: `:nullable`, `:unique`. Add `--web` to also create public pages at `/products`.

You get: model with activity log, migration, admin controller (search, pagination, bulk delete, status toggle), form requests, index/create/edit views, routes, `module.json`, permissions `products.view|create|edit|delete` and a sidebar item. Super admins see it immediately; grant other roles access under **Roles**.

The same generator is available in the admin under **System › Modules** when `APP_ENV=local`.

Other module commands:

```bash
php artisan module:list
php artisan module:disable blog      # hides routes, menus and permissions from roles; data is kept
php artisan module:enable blog
php artisan module:sync              # create permissions + menu items declared in module.json files
```

---

## 4. Roles, permissions and menus

- Permission names are `{resource}.{action}`, e.g. `blog.edit`, `users.impersonate`.
- The `super-admin` role passes every check (`Gate::before`).
- Seeded roles: **Super Admin**, **Admin** (everything except modules and deleting roles), **Editor** (blog and website content).
- Create roles in **Access control › Roles** by ticking a matrix (rows = modules, columns = view/create/edit/delete) plus special permissions.
- Add custom permissions in **Access control › Permissions**, then check them anywhere.

```php
// Controller
public static function middleware(): array
{
    return [new Middleware('permission:blog.edit', only: ['edit', 'update'])];
}

// Blade
@can('blog.publish') <button>Publish</button> @endcan

// Anywhere
$user->can('blog.publish');
```

The sidebar is built from the `menu_items` table: items from `module.json` are filtered by permission and module status, and cached per permission set. **Appearance › Menu builder** lets you drag to reorder, rename, change icons and hide items, and manage the website header and footer menus.

---

## 5. Website CMS

- **Pages** are built from blocks: Hero, Rich text, Image + text, Services grid, Numbers, Testimonials, FAQ, Latest posts, Call to action, Contact form, Map and Gallery. One page is marked as the home page. Drafts are visible to signed-in staff only.
- To add a block type, add it to `app/Modules/Pages/Support/BlockRegistry.php` and create `app/Modules/Pages/Views/web/blocks/{type}.blade.php`.
- **Blog** has categories, tags, featured image, SEO fields with a Google preview, and draft, published or scheduled status. Scheduled posts go live automatically.
- **Contact inbox** collects contact form messages (honeypot plus 5 per minute rate limit), alerts admins live and by email, and lets you reply from the panel through the queue.
- **SEO**: per-page meta tags, Open Graph tags, canonical URLs, JSON-LD (Organization, BlogPosting, FAQPage), `/sitemap.xml` and `/robots.txt`.
- **Settings** cover site name, contact details, logo, favicon, colour, SEO defaults, Google Analytics, SMTP (stored encrypted), social links, security rules and maintenance mode.

Read any setting with `setting('contact_email')`. Add new settings in `app/Modules/Settings/Support/SettingDefinitions.php`.

---

## 6. Realtime, queues and scheduler

**Live notifications.** `AdminAlert::toAdmins('message', $url, 'permission.name')` stores a notification and pushes it over Reverb to the bell in the top bar. Examples already wired: new enquiry, new subscriber, new user, backup finished.

To add your own realtime event, create a normal broadcast event and listen in `resources/admin/js/realtime.js`:

```php
class OrderPlaced implements ShouldBroadcast {
    public function broadcastOn(): array { return [new PrivateChannel('App.Models.User.'.$this->userId)]; }
}
```

**Queues** (Redis + Horizon): emails, notifications, image conversions and backups. Horizon is at `/admin/horizon` for super admins.

**Scheduler** (`routes/console.php`):

| When | What |
|---|---|
| every minute | publish scheduled posts, scheduler heartbeat for the dashboard |
| every 5 minutes | Horizon metrics |
| 01:30 / 02:00 | clean old backups / new backup |
| 03:00 | prune activity log older than 365 days |
| 03:15 | rebuild sitemap |

---

## 7. Security

- Laravel Fortify sign-in with rate limiting, optional or enforced TOTP two-factor login, recovery codes, password confirmation for sensitive actions. Public registration is off.
- Inactive and suspended users can't sign in, and are signed out on their next request.
- **Force password change** and **Require 2FA for admin roles** settings.
- Strong passwords (`Password::defaults()`: mixed case, numbers, symbols, configurable minimum length).
- Security headers on every response: Content-Security-Policy with a per-request nonce, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, and HSTS in production. Add inline scripts with `nonce="{{ Vite::cspNonce() }}"`.
- Rich text is cleaned with HTML Purifier. Uploads are allow-listed by type (no SVG or PHP), and names are randomised.
- Activity log (spatie/laravel-activitylog) for sign-ins, model changes, impersonation, settings and tools.
- "Sign in as" (impersonation) needs `users.impersonate`, is logged, and never works on super admins.
- The admin URL can be changed with `ADMIN_PATH=control-room`.

---

## 8. Tests and code quality

```bash
php artisan test                         # 51 feature tests
vendor/bin/pint                          # code style (Laravel preset)
vendor/bin/phpstan analyse               # Larastan level 5
```

The tests cover sign-in rules, permissions and the sidebar, user CRUD and bulk actions, roles and custom permissions, publishing and scheduling, the page builder, contact and newsletter forms, maintenance mode, security headers, media uploads, menus, backups path safety, and the module generator.

CI runs all three on every push (`.github/workflows/ci.yml`).

---

## 9. Deploying to production

1. Build the production image: `docker build -f docker/php/Dockerfile --target production -t your-app:1.0 .` (PHP-FPM with `vendor/` and built assets inside). Put nginx in front using `docker/nginx/default.conf`.
2. Run the same image as the `app`, `horizon` (`php artisan horizon`), `scheduler` (`php artisan schedule:work`) and `reverb` (`php artisan reverb:start`) containers.
3. Set `RUN_MIGRATIONS=true` on exactly one container so it migrates on start.

Checklist:

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, new `APP_KEY`
- [ ] `SESSION_SECURE_COOKIE=true` behind HTTPS
- [ ] `REVERB_SCHEME=https` and `REVERB_HOST=` your public WebSocket host (proxy `wss://` to the reverb container)
- [ ] Real SMTP in `.env` or Settings › Mail
- [ ] `BACKUP_ARCHIVE_PASSWORD` set, and an off-server backup disk (S3) added in `config/backup.php`
- [ ] `ADMIN_PATH` changed from `admin` if you want a less obvious login URL
- [ ] Settings › Security › Require 2FA for admin roles
- [ ] Persistent volumes for `storage/app` (uploads and backups)

---

## 10. Packages

| Package | Used for |
|---|---|
| laravel/fortify | sign-in, 2FA, password reset |
| laravel/reverb | WebSockets |
| laravel/horizon | queue workers and dashboard |
| spatie/laravel-permission | roles and permissions |
| spatie/laravel-activitylog | audit trail |
| spatie/laravel-medialibrary | media library and image conversions |
| spatie/laravel-backup | database and file backups |
| spatie/laravel-sluggable | URL slugs |
| mews/purifier | rich text cleaning |
| Bootstrap 5, Bootstrap Icons, Trix, Chart.js, SortableJS | admin UI |
| Tailwind CSS 4 | website |

Front-end entry points: `resources/admin/admin.{css,js}` and `resources/web/web.{css,js}` (see `vite.config.js`).
