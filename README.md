# Dhina Tech LaunchKit L13

Laravel 13 / PHP 8.4 starter that every Dhina Technologies client project begins from. One codebase gives you:

- **Admin panel** (Bootstrap 5): dashboard, users, roles and permissions, extra permissions per user, menu builder, settings, activity log, sign-in history, backups, module manager, monitoring
- **Business website** (Tailwind CSS 4): pages built from blocks, blog, FAQs, services, sliders, testimonials, contact form, newsletter, SEO, multi-language with RTL
- **Customer accounts**: website sign-up / sign-in / My account with two-factor, managed from the admin panel
- **AI & MCP**: an MCP server at `/mcp` so Claude, ChatGPT, Cursor… can work with the site's data, and an in-panel assistant on the Laravel AI SDK. Same tools, same permissions, off by default
- **REST API** with Sanctum tokens: staff API `/api/v1`, customer app API `/api/v1/shop`, interactive docs at `/docs/api`, versioning (`/api/v2`) built in
- **Modules**: every feature is a folder in `app/Modules`; `php artisan make:module` generates a full CRUD (list with search, filters, export, import, trash, permissions, menu, optional website pages and API)
- **Two modes, same code**: **single business** (default) or **SaaS** (many companies, a database each, plans, Razorpay + Stripe billing, signup with trial). One line in `.env` decides.
- **Ops**: Docker Compose for local, `compose.prod.yaml` + `./deploy.sh` (health check, automatic rollback) for servers, Horizon queues, Reverb WebSockets, scheduler, error log with alerts, log viewer, uploads on the server or S3

No Filament, Livewire or Nova: controllers, Blade views and form requests you can read top to bottom.

### Which document do I read?

| You want to… | Read |
| --- | --- |
| Start working on the project | **this file** |
| Every command for a one-company project | [README_SINGLE_BUSINESS.md](README_SINGLE_BUSINESS.md) |
| Every command for SaaS mode (tenants, plans, billing) | [README_SAAS.md](README_SAAS.md) |
| How the code works and how to change it (deep dive) | [README_DEVELOPER.md](README_DEVELOPER.md) |
| Full command reference with all options | [README_PROJECT_CMD.md](README_PROJECT_CMD.md) |
| Build a mobile app / frontend against the API | [API_GUIDE.md](API_GUIDE.md) + `/docs/api` |
| Connect AI clients (MCP), the AI assistant, add AI tools | [AI.md](AI.md) |
| How SaaS mode works (DNS, SSL, gateways, convert) | [SAAS.md](SAAS.md) |
| Put it on a server | [DEPLOY.md](DEPLOY.md) |
| Update an older copy of the starter | [UPDATE.md](UPDATE.md) |

---

## 1. Get it running (new developer)

You need: **Docker Desktop**, **Git**, and on Windows **Git Bash**. Nothing else (PHP, Composer, Node, MySQL all run in containers).

```bash
git config --global core.autocrlf false        # once per machine: the repo uses LF line endings
git clone https://github.com/v-IJU/dhina-tech-launchkit-l13.git launchkit
cd launchkit
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan starter:install          # asks: Single business or SaaS? + your admin email/password
docker compose run --rm node sh -c "npm install && npm run build"
```

Open http://localhost:8080/admin and sign in. Git Bash says *"the input device is not a TTY"*? Put `winpty` in front of the command.

**SaaS instead of single business:** first give the database user rights to create tenant databases, then install with the SaaS options:

```bash
docker compose exec mysql mysql -uroot -proot -e "GRANT ALL PRIVILEGES ON *.* TO 'starter'@'%'; FLUSH PRIVILEGES;"
docker compose exec app php artisan starter:install --mode=saas --central-domain=localhost --demo-tenant=demo
```

Then http://localhost:8080/admin is the SaaS console and http://demo.localhost:8080/admin is a tenant (`owner@demo.test` / `Demo@12345`). Everything else about SaaS: [README_SAAS.md](README_SAAS.md).

### Local URLs

The ports are the defaults from `.env` (`APP_PORT`, `FORWARD_*`, `REVERB_PORT`, `VITE_PORT`). If you changed them, use yours.

| URL | What |
| --- | --- |
| http://localhost:8080 | Website |
| http://localhost:8080/admin | Admin panel (SaaS mode: the SaaS console) |
| http://*name*.localhost:8080/admin | SaaS mode: a tenant's admin panel |
| http://localhost:8080/docs/api | API documentation (try requests from the browser) |
| http://localhost:8080/admin/horizon | Queues (super admin) |
| http://localhost:8080/admin/logs | Log files |
| http://localhost:8025 | Mailpit: every email the app sends lands here |
| http://localhost:8082 | phpMyAdmin (root, every database) |
| `127.0.0.1:3307` | MySQL from your PC, user `starter` / `secret` |
| http://localhost:5173 | Vite dev server (only with `--profile dev`) |

### Two copies on one machine (e.g. one single business, one SaaS)

Clone the repo into a second folder and give that copy its own name and ports in **its** `.env`. `compose.yaml` stays untouched.

```dotenv
COMPOSE_PROJECT_NAME=launchkit-single
APP_PORT=8090
APP_URL=http://localhost:8090
REVERB_PORT=8091
FORWARD_DB_PORT=3308
FORWARD_PMA_PORT=8092
FORWARD_MAILPIT_PORT=8026
VITE_PORT=5174
```

Each copy then has its own containers, MySQL, Redis and data.

---

## 2. Everyday work

```bash
docker compose up -d                       # start
docker compose --profile dev up -d         # start + Vite hot reload while editing Blade / CSS / JS
docker compose down                        # stop (data is kept)
docker compose exec app bash               # shell in the PHP container
docker compose exec app php artisan ...    # any artisan command
docker compose logs -f app                 # logs of a container (app, nginx, horizon, reverb, scheduler)
docker compose restart horizon             # after changing a job, mail or notification class
docker compose exec app php artisan optimize:clear    # anything looks stale
```

Tip, add to `~/.bashrc`: `alias art="docker compose exec app php artisan"` and then use `art migrate`, `art test`, …

### Git workflow

```bash
git checkout main && git pull                       # start from the latest main
git checkout -b feature/short-name                  # one branch per task, never commit to main directly
# … code …
docker compose exec app php artisan test            # must stay green
docker compose exec app ./vendor/bin/pint           # code style
git add . && git commit -m "What changed"
git push -u origin feature/short-name               # open a Pull Request on GitHub → review → merge
```

**After every `git pull` that brought changes:**

```bash
docker compose exec app composer install
docker compose exec app php artisan migrate
docker compose exec app php artisan module:sync
docker compose exec app php artisan optimize:clear
docker compose run --rm node sh -c "npm install && npm run build"
# SaaS mode also:
docker compose exec app php artisan tenants:migrate
docker compose exec app php artisan tenants:run module:sync
```

**Never commit `.env`.** A new setting the team needs → add it to `.env.example` with a safe default and mention it in the PR.

GitHub Actions (`.github/workflows/ci.yml`) runs tests, Pint and Larastan on every push.

---

## 3. Folder structure

```
app/
├── Core/                      The engine, shared by every module
│   ├── Console/               starter:install, make:module (+ Generator/), module:list|enable|disable|sync, health:check
│   ├── DataTable/             search / sort / filter / export engine behind every list page
│   ├── Import/                CSV import (check first, row-by-row errors)
│   ├── Menus/                 MenuItem model + MenuService (admin sidebar, website menus)
│   ├── Modules/               Module, ModuleManager (which modules are on, per tenant/plan), ModuleServiceProvider
│   ├── Monitoring/            ErrorRecorder (error log + alerts)
│   ├── Permissions/           PermissionSync (module.json → permissions table)
│   ├── Settings/              SettingsRepository + setting() helper
│   ├── Security/              LoginRecorder (sign-in history), UserAgent
│   ├── Tenancy/               SaaS helpers: Tenancy::enabled()/inTenant()/tenant()/central()
│   ├── Support/               EnvFile (.env editor for the install commands)
│   └── helpers.php            setting(), module_enabled(), …
├── Http/
│   ├── Controllers/Api/       API sign-in (token, me, revoke)
│   └── Middleware/            IdentifyTenant, EnsureModuleAvailable, ApiAccess, SecurityHeaders, EnforceTwoFactor, …
├── Models/                    User, Role
├── Notifications/             AdminAlert (bell + Reverb + email), WelcomeUser
├── Providers/                 AppServiceProvider (gates, settings, API docs), TenancyServiceProvider, …
└── Modules/                   ONE FOLDER PER FEATURE
    ├── Dashboard  Users  Roles  Settings  Menus  Activity  Media  Inbox      core: always on
    ├── Pages  Blog  Faqs  Services  Sliders  Testimonials                     website content
    ├── Customers                                                              customer accounts + shop API
    ├── Ai                                                                     MCP server + AI assistant (AI.md)
    ├── Monitoring  Backups  Modules                                           ops tools (SaaS: console only)
    └── Saas  Billing                                                          SaaS mode only

resources/
├── admin/                     admin.css (Bootstrap theme) + js/ (one file per feature)
├── web/                       web.css (Tailwind) + web.js
└── views/
    ├── layouts/               admin, auth, web
    ├── components/admin/      <x-admin.card>, <x-admin.form.input>, <x-admin.datatable>, … reusable UI
    ├── auth/                  sign-in, 2FA, password reset (Fortify)
    └── errors/                403, 404, 419, 500, 503, tenant-not-found, tenant-suspended

routes/                        web.php, api.php (/api/v1 sign-in), console.php (scheduler), channels.php
config/starter.php             starter settings: admin path, menu groups, API versions, SaaS options
config/tenancy.php             SaaS: central domains, tenant databases, what is isolated per tenant
database/                      core migrations + seeders (DatabaseSeeder, TenantDatabaseSeeder, DemoContentSeeder)
stubs/module/                  templates for make:module: edit them to change generated code
tests/Feature/                 154 tests (single business + SaaS + convert + AI)
docker/                        Dockerfile (dev + production targets), nginx, php.ini
compose.yaml                   local stack       compose.prod.yaml + deploy.sh   servers
```

### Anatomy of a module

```
app/Modules/Products/
├── module.json                name, permissions, sidebar menu, where it is available
├── Controllers/Admin/         admin pages (permission middleware per action)
├── Controllers/Web/           public website pages (--web)
├── Controllers/Api/           REST API (--api)
├── Models/   Requests/   Resources/ (API JSON)
├── Routes/admin.php           → /admin/products, names admin.products.*  (auth + permissions)
├── Routes/web.php             → /products, public
├── Routes/api.php             → /api/v1/products, names api.products.*   (Sanctum token)
├── Routes/api_v2.php          → /api/v2/products  (only if you add API v2, section 6)
├── Views/admin/  Views/web/   view('products::admin.index')
├── Database/Migrations/       run by php artisan migrate (SaaS: also tenants:migrate)
├── Database/Seeders/
└── Console/                   artisan commands, registered automatically
```

`module.json`:

```json
{
    "name": "Products",
    "slug": "products",
    "enabled": true,
    "core": false,
    "order": 200,
    "central": false,
    "tenant": true,
    "permissions": [
        { "key": "products", "label": "Products", "actions": ["view", "create", "edit", "delete", "export", "import"] }
    ],
    "menu": [
        { "key": "products", "group": "Catalog", "label": "Products", "icon": "bi-box-seam", "route": "admin.products.index", "permission": "products.view" }
    ]
}
```

`central` / `tenant` only matter in SaaS mode (who gets the module: your console, tenants on a plan with it, or both). In single-business mode every enabled module is on.

---

## 4. Build a feature

```bash
docker compose exec app php artisan make:module Product \
  --fields="name:string,sku:string:unique,product_category_id:foreignId:nullable,price:decimal,photo:image,description:editor:nullable,is_active:boolean" \
  --group="Catalog" --icon=bi-box-seam --web --api --migrate
```

| Option | Meaning |
| --- | --- |
| `--fields=` | `name:type[:nullable][:unique]`, comma separated |
| `--group=` / `--icon=` | Sidebar section (`config/starter.php` → `menu_groups`) / Bootstrap icon |
| `--web` | Public pages `/products` and `/products/{id}` |
| `--api` | REST API `/api/v1/products` + JSON resource, appears in `/docs/api` |
| `--migrate` | Create the table, permissions and menu item now (SaaS: in every tenant too) |
| `--for=` | SaaS only: `tenant` (default, sold on plans), `central` (your console), `both` |
| `--force` | Regenerate an existing module |

| Field type | Column | Form input |
| --- | --- | --- |
| `string` `email` `url` | varchar | text / email / url |
| `text` | text | textarea |
| `editor` | longText | rich text (cleaned with HTML Purifier) |
| `integer` `decimal` | int / decimal(12,2) | number |
| `boolean` | boolean | switch (`is_active` also gets a status toggle in the list) |
| `date` `datetime` | date / datetime | date picker |
| `image` | path | upload with preview, stored on the `uploads` disk (server or S3) |
| `foreignId` | foreign key | select from the related table (`product_category_id` → `product_categories`) |

You get: model with activity log, migration, admin list (search, sort, filters, export, import, bulk actions, trash), create/edit forms, form requests, routes, permissions `products.view|create|edit|delete|export|import` and a sidebar item. Super admins see it at once; give other roles access under **Access control → Roles**.

Then, by hand, whatever the generator can't guess: relations, extra validation, business rules. Add a test in `tests/Feature/`.

Changing a module later:

```bash
art make:migration add_brand_to_products_table --table=products --path=app/Modules/Products/Database/Migrations
art migrate                         # SaaS: + art tenants:migrate
art module:sync                     # after editing module.json (new permission / menu item); SaaS: + art tenants:run module:sync
```

More (extra tables, controllers, removing a module): [README_SINGLE_BUSINESS.md](README_SINGLE_BUSINESS.md) section 5.

---

## 5. Roles, permissions and menus

- Permission names are `{resource}.{action}`: `products.edit`, `users.impersonate`.
- `super-admin` passes every check. Seeded roles: Super Admin, Admin, Editor.
- Roles: **Access control → Roles** (matrix of modules × actions, live sidebar preview). One person needs more than their role: **Users → key icon** (extra permissions, audit logged).
- New permission names come from `module.json` (`art module:sync`). In SaaS mode tenants can assign permissions but not invent new names.

```php
// Controller (every generated controller already does this)
public static function middleware(): array
{
    return [new Middleware('permission:products.edit', only: ['edit', 'update'])];
}

// Blade
@can('products.export') <a href="…">Export</a> @endcan

// Anywhere
$request->user()->can('products.edit');
module_enabled('products');      // is the module on (SaaS: in this tenant's plan)?
setting('contact_email');        // any value from Settings
```

The sidebar is built from `menu_items` (filled from `module.json`), filtered by permission and module, editable under **Appearance → Menu builder**.

---

## 6. API

### URLs

| What | URL | Who signs in |
| --- | --- | --- |
| Staff API (admin users) | `/api/v1/...` | users of the admin panel |
| Customer app API | `/api/v1/shop/...` | customers (Customers module) |
| Docs (v1, staff + shop) | **`/docs/api`** (JSON: `/docs/api.json`) | |
| Docs (v2, once added) | `/docs/api/v2` | |

SaaS mode: call the tenant's host, e.g. `http://acme.localhost:8080/api/v1/...`. Tokens belong to one tenant.

The docs are generated from the code by **Scramble**, so they never go out of date. Access: open to everyone on your local machine (`APP_ENV=local`); on servers only to signed-in super admins and users with `settings.view` (sign in to `/admin` first, then open `/docs/api`).

### Get a token

| Way | How |
| --- | --- |
| From the admin panel | Top-right menu → **My profile** → *API tokens* → name it, choose *Full access* or *Read only* → copy (shown once) |
| From an app (staff) | `POST /api/v1/auth/token` with `email`, `password`, `device_name` (+ `code` when 2FA is on) |
| From an app (customer) | `POST /api/v1/shop/auth/register` or `POST /api/v1/shop/auth/token` |

Every call sends `Authorization: Bearer <token>` and `Accept: application/json`. A token works on every API version. **Settings → Security → Allow API access** switches all staff tokens off at once.

### Try it in the docs

1. Get a token (above).
2. Open http://localhost:8080/docs/api, pick an endpoint, e.g. `GET /me`.
3. In the request panel, open **Auth** → *Bearer token* → paste the token (without the word `Bearer`).
4. **Send API Request**: you see the real response.

Command line:

```bash
curl -s http://localhost:8080/api/v1/auth/token -H "Accept: application/json" \
  -d email=admin@example.com -d password='Secret#123' -d device_name=curl
curl -s http://localhost:8080/api/v1/me -H "Accept: application/json" -H "Authorization: Bearer 1|xxxxxxxx"
```

Permissions are the same as in the panel: a user who can't edit posts gets `403` from the API too. Read-only tokens can only `GET`.

### Add endpoints

| Want | Do |
| --- | --- |
| API for a new module | `make:module Product … --api` |
| API for an existing module | Add `Controllers/Api/XController.php` + `Routes/api.php` in the module (copy from a module made with `--api`) |
| Change the JSON | `app/Modules/X/Resources/XResource.php` |
| Show it nicely in the docs | PHPDoc summary on the method, `#[Group('Products')]` on the controller, `#[QueryParameter(...)]` for query params; request bodies come from the FormRequest |
| Customer endpoints | `Routes/shop.php` (signed-in customers) / `Routes/shop_guest.php` (public) in the module |

### Add API v2 (when v1 must stay for old app versions)

1. `config/starter.php`: uncomment the v2 line.
   ```php
   'api_versions' => [
       'v1' => 'Routes/api.php',
       'v2' => 'Routes/api_v2.php',
   ],
   ```
2. In each module that changes, add `Routes/api_v2.php` (copy `api.php`) pointing at new controllers, e.g. `Controllers/Api/V2/ProductController.php` with a new `Resources/V2/ProductResource.php`. Modules without `api_v2.php` simply have no v2 endpoints.
3. `art optimize:clear`. You now have `/api/v2/products` (route names `api.v2.products.*`) and docs at **`/docs/api/v2`**. v1 keeps working unchanged.
4. Customer API v2: same idea with `shop_api_versions` and `Routes/shop_v2.php`.

Rule of thumb: add fields freely in v1 (old apps ignore them); make v2 only for breaking changes (renamed/removed fields, different behaviour).

Full guide for app developers (sign-in flow, errors, pagination, React Native examples): [API_GUIDE.md](API_GUIDE.md).

---

## 7. Website, settings, realtime, queues

- **Pages** are built from blocks (hero, rich text, services, FAQ, latest posts, contact form, gallery…). New block: `app/Modules/Pages/Support/BlockRegistry.php` + `app/Modules/Pages/Views/web/blocks/{type}.blade.php`.
- **Settings**: read with `setting('key')`; add new ones in `app/Modules/Settings/Support/SettingDefinitions.php`. SaaS: every tenant has its own settings.
- **Uploads**: always `Storage::disk('uploads')`; `.env` `UPLOADS_DISK=public` (server) or `s3`.
- **Live notifications**: `AdminAlert::toAdmins('message', $url, 'permission.name')` → bell + Reverb + optional email. Own events: broadcast on `App.Models.User.{id}` and listen in `resources/admin/js/realtime.js`.
- **Queues**: Redis + Horizon (`/admin/horizon`). Mails, notifications, image conversions and backups are queued; restart Horizon after changing job code.
- **Scheduler**: `routes/console.php` (`art schedule:list`). Runs in the `scheduler` container.
- **Errors**: Admin → System → Monitoring → Error log (with alerts), Failed jobs, Log files.

### AI & MCP

Off by default. Turn on under **Settings → AI**, connect clients from **Admin → AI & MCP** (it shows the `/mcp` address and ready-to-paste config for Claude, Cursor, VS Code). Every AI request runs as the user whose token it uses.

To let AI do something new in your module, write one tool class (extend `App\Modules\Ai\Mcp\Tools\SiteTool`, add it to `SiteServer::TOOLS`): it works in MCP clients and the assistant at once. Details and an example: [AI.md](AI.md).

---

## 8. Security (already done, keep it that way)

- Fortify sign-in with rate limiting, optional or enforced 2FA, recovery codes, password confirmation for sensitive actions, strong passwords.
- Inactive users are signed out on their next request; sign-in history with new-device emails.
- CSP with a nonce on every page: inline scripts need `nonce="{{ Vite::cspNonce() }}"`.
- Rich text cleaned with HTML Purifier; uploads allow-listed by type, names randomised.
- Activity log for sign-ins, changes, impersonation and settings.
- Change the admin URL with `ADMIN_PATH=control-room`.

---

## 9. Tests and code quality

```bash
art test                                          # 154 tests
art test --filter=SaasTest                        # one class
docker compose exec app ./vendor/bin/pint         # code style (fixes files)
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G    # Larastan
```

Every PR must keep all three green (CI checks them).

---

## 10. Servers

```bash
cp .env.production.example .env      # fill every CHANGE_ME
./deploy.sh                          # build, migrate, module:sync (SaaS: tenants too), health check, auto-rollback
./deploy.sh status | restart | rollback
```

HTTPS, RDS, S3, Auto Scaling: [DEPLOY.md](DEPLOY.md). SaaS DNS (wildcard) and gateways: [SAAS.md](SAAS.md).

---

## 11. Troubleshooting

| Problem | Fix |
| --- | --- |
| Port already in use | Change `APP_PORT` / `FORWARD_*` / `REVERB_PORT` / `VITE_PORT` in `.env`, `docker compose up -d` |
| `deploy.sh` or entrypoint fails with `\r` errors | You cloned with CRLF: `git config --global core.autocrlf false`, delete the folder, clone again |
| No styles / old JS | `docker compose run --rm node sh -c "npm install && npm run build"` |
| New module not in sidebar / 404 | `art module:sync`, `art optimize:clear`, role has `<slug>.view` |
| 403 | Tick the permission under Access control → Roles |
| Emails not arriving | Locally they are in Mailpit: http://localhost:8025 |
| Job / email change ignored | `docker compose restart horizon` |
| `/docs/api` says 403 on a server | Sign in to `/admin` first as super admin or a user with `settings.view` |
| API returns 401 | Missing/expired token or `Accept: application/json` header missing; check Settings → Security → Allow API access is on |
| SaaS problems | [README_SAAS.md](README_SAAS.md) section 11 |

---

## 12. Packages

| Package | Used for |
| --- | --- |
| laravel/fortify | sign-in, 2FA, password reset |
| laravel/sanctum | API tokens |
| dedoc/scramble | API docs at `/docs/api` |
| laravel/horizon · laravel/reverb | queues · WebSockets |
| laravel/mcp · laravel/ai | MCP server at `/mcp` · AI SDK (assistant, agents) |
| stancl/tenancy | SaaS mode (database per tenant) |
| spatie/laravel-permission · activitylog · medialibrary · backup · sluggable | roles, audit trail, media, backups, slugs |
| opcodesio/log-viewer | log files at `/admin/logs` |
| league/flysystem-aws-s3-v3 | uploads and backups on S3 |
| mews/purifier | rich text cleaning |
| Bootstrap 5, Bootstrap Icons, Trix, Chart.js, SortableJS · Tailwind CSS 4 | admin UI · website |

Front-end entry points: `resources/admin/admin.{css,js}` and `resources/web/web.{css,js}` (`vite.config.js`).
