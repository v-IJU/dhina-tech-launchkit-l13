# Update v1.3: SaaS mode (optional), S3 uploads, Production deploy, Monitoring, Customer accounts, Trash, CSV import, REST API, Multi-language, Sign-in history, Passkeys, Team chat, Excel import/export

This update adds these features to an existing project made from the starter. It **includes everything from v1.1 and v1.2**, so apply it the same way whatever you applied before. Full explanations are in `README_DEVELOPER.md` sections 23–29 and `DEPLOY.md`; commands are in `README_PROJECT_CMD.md` sections 14 and 17–19.

| Feature                 | Where you see it                                                                         |
| ----------------------- | ---------------------------------------------------------------------------------------- |
| **Trash & restore**     | "All / Trash" tabs on Users, Blog posts, Sliders, Services, Testimonials, FAQs            |
| **CSV import**          | "Import" button on Users, Subscribers, Sliders, Services, Testimonials, FAQs              |
| **REST API**            | `/api/v1/...`, tokens in Profile → Security, docs at `/docs/api`, guide in `API_GUIDE.md` |
| **Multi-language site** | Settings → Languages, "+ TA" badges on Pages / Blog posts, language switcher on the website |
| **Sign-in history**     | Users → Sign-in history; Profile → Security → Recent sign-ins; new-device emails          |
| **Customer accounts**   | Website "Sign in" + `/account`; admin **Customers** menu; Settings → Customers; app API `/api/v1/shop` |
| **Extra permissions per user** | Users → key icon: give one person permissions on top of their role (`users.permissions`) |
| **Role sidebar preview** | Roles → Add/Edit: live "Sidebar for this role" card while ticking permissions |
| **Monitoring**          | System → Monitoring → **Error log**, **Failed jobs**, **Log files** (`/admin/logs`); Settings → Monitoring; `/up` and `php artisan health:check` |
| **Excel import & export** | Laravel Excel (maatwebsite/excel): Import accepts .xlsx/.csv, big files run in the background in chunks with a progress bar and notification; Export → Excel or CSV. Each module has `Imports/` and `Exports/` classes. Settings → Import & export |
| **Debugbar (local)** | Bar at the bottom of each page with the Blade views, queries and route used (dev dependency only) |
| **Team chat** | Main → **Team chat**, chat icon in the top bar. Staff 1-to-1 chat with emoji, photos, files, voice notes; realtime over Reverb. Settings → Team chat. Permissions `chat.view` / `chat.start` |
| **Passkeys** | "Sign in with a passkey" on the admin and customer sign-in pages; add them in Profile → Security / My account → Security. Settings → Security / Customers to turn off |
| **Request-forgery hardening** | Laravel 13 `PreventRequestForgery` set explicitly in `bootstrap/app.php`: other sites and sibling tenant subdomains can't post forms |
| **AI & MCP** | MCP server at `/mcp` for Claude / ChatGPT / Cursor + in-panel assistant (Laravel AI SDK). Off by default: Settings → AI. See **`AI.md`** |
| **SaaS mode (optional)** | Off by default, nothing changes until you turn it on. Plans, tenants with their own database, Razorpay + Stripe, signup, console: **`SAAS.md`**. Convert this install: `php artisan tenancy:convert` |
| **Uploads on S3 or local** | `.env` `UPLOADS_DISK=public` (this server) or `s3` (your bucket). All uploads use the new `uploads` disk |
| **Production deploy**   | `compose.prod.yaml`, `deploy.sh`, `.env.production.example`, `DEPLOY.md` (new files in the project root) |

---

## 1. Before you start

```bash
git add -A && git commit -m "Before starter update"     # or copy the project folder
docker compose exec app php artisan backup:run --only-db
```

**Did you change any of these files yourself?** The update replaces them. Compare first (for example with `git diff` after unzipping) and merge your changes back:

- `app/Modules/{Users,Blog,Pages,Faqs,Services,Sliders,Testimonials,Inbox,Settings,Menus,Modules}/...` (the files listed in this zip)
- `app/Providers/AppServiceProvider.php`, `bootstrap/app.php` (now `preventRequestForgery(...)` instead of `validateCsrfTokens(...)`), `config/auth.php`, `config/backup.php`, `config/starter.php`, `routes/console.php`
- `app/Http/Middleware/SecurityHeaders.php` (microphone allowed for chat voice notes), `config/filesystems.php` (new private `chat` disk)
- `config/queue.php` (new `redis-imports` connection), `config/horizon.php` (new `supervisor-imports`), `config/starter.php` (new `imports` block; `import_max_rows` / `import_max_kb` removed)
- `docker/php/Dockerfile` (new `web` target), `resources/admin/js/realtime.js`, `resources/views/layouts/admin.blade.php`
- `resources/views/layouts/web.blade.php`, `resources/views/components/admin/datatable.blade.php`
- `stubs/module/*.stub` (new: `import.stub`, `export.stub`, `view.import.stub`)
- `app/Core/DataTable/DataTable.php`, `resources/views/components/admin/datatable.blade.php` (Export → Excel / CSV)
- removed: `app/Core/Import/CsvImport.php`, `HandlesImport.php`, `ImportResult.php`, `resources/views/admin/import.blade.php` (replaced by Laravel Excel, see section 5)
- `composer.json` (adds `laravel/sanctum`, `stancl/tenancy`, `league/flysystem-aws-s3-v3`, `laravel/passkeys`; keep any packages you added)
- your own code that calls `Storage::disk('public')` for uploads: change it to `Storage::disk('uploads')` (section 5)

Modules **you generated yourself** are not touched. Section 5 shows how to give them the new features.

## 2. Copy the files

Unzip `update-v1.3.zip` **into your project folder**, so the `app/`, `config/`… folders in the zip land on top of yours, and allow it to replace files. Your `.env`, `compose.yaml`, database and own modules are not in the zip. Add the new `.env` keys from `.env.example`: `BACKUP_DISKS=local`, `UPLOADS_DISK=public`, `AWS_UPLOADS_PREFIX=uploads`, `STARTER_TENANCY=false` (and the `CENTRAL_DOMAIN`, `TENANT_DB_PREFIX`, `SAAS_*`, `RAZORPAY_*`, `STRIPE_*` lines, empty until you use SaaS).

Then delete the old CSV import files (replaced by Laravel Excel):

```bash
rm -f app/Core/Import/CsvImport.php app/Core/Import/HandlesImport.php app/Core/Import/ImportResult.php resources/views/admin/import.blade.php
```

## 3. Run these commands

From the project root (Git Bash):

```bash
# 1. install the new packages (Sanctum: API tokens, Scramble: API docs at /docs/api, Log Viewer: /admin/logs,
#    Tenancy: SaaS mode, S3 driver: uploads/backups on S3, Passkeys: fingerprint / Face ID sign-in, Laravel Excel: import/export)
docker compose exec app composer require laravel/sanctum:^4.5 dedoc/scramble:^0.13 opcodesio/log-viewer:^3.24 stancl/tenancy:^3.10 league/flysystem-aws-s3-v3:^3.0 laravel/mcp:^1.3 laravel/ai:^1.4 laravel/fortify:^1.44 laravel/passkeys:^0.3 maatwebsite/excel:^3.1
docker compose exec app composer require --dev fruitcake/laravel-debugbar:^4.4

# 2. new tables and columns: error log, customers, trash (deleted_at), API tokens, sign-in history, translations, passkeys, chat, import runs
docker compose exec app php artisan migrate
#    SaaS mode: also the tenant databases (adds passkeys + customer_passkeys to each)
#    docker compose exec app php artisan tenants:migrate

# 3. new permissions (monitoring.*, customers.*, *.import, chat.*) and menu items (Monitoring, Customers, Sign-in history, Team chat)
#    SaaS mode: also inside every tenant:  docker compose exec app php artisan tenants:run module:sync
docker compose exec app php artisan module:sync

# 4. clear caches
docker compose exec app php artisan optimize:clear

# 5. rebuild CSS/JS (language switcher, trash tabs, etc.)
docker compose run --rm node sh -c "npm install && npm run build"

# 6. restart the background containers so they load the new code
docker compose restart horizon scheduler reverb

# 7. check everything: expect "192 passed"
docker compose exec app php artisan test
```

## 4. After the update

1. **Ctrl+F5** in the browser.
2. **Roles → Admin (and others):** tick the new `*.import` permissions under *Special permissions* for the roles that should import. Super-admin already has everything.
3. **Settings → Security:** check the new options (API on/off, API rate limit, new-device emails, history retention).
4. **Settings → Maintenance:** *Empty the trash after (days)*, default 30.
5. **Settings → Languages:** tick extra languages only if the website needs them. Nothing changes until you do.
6. **Settings → Customers:** customer accounts are **on** by default (a "Sign in" link appears in the website header). Turn *Customer accounts* off if this project has no customer logins. Tick the `customers.*` permissions for roles that manage customers.
7. **Settings → Monitoring:** put a developer email in *Email new errors to*. Tick `monitoring.*` and `logs.*` for roles that should see errors and log files.
8. **Uploads to S3?** Set `UPLOADS_DISK=s3` and the `AWS_*` keys, then copy what is already in `storage/app/public` to the bucket under `uploads/` (`aws s3 sync storage/app/public s3://YOUR_BUCKET/uploads`). Existing media items remember their old disk and keep working either way.
9. **SaaS?** Read `SAAS.md`. To turn *this* install into SaaS (its data becomes the first tenant): `SAAS.md` section 8.
10. **Import & export:** try it on FAQs → Import with an .xlsx file. Big files (over 1,000 rows) run on their own **imports** queue: add `IMPORT_QUEUE_CONNECTION=redis-imports` and `IMPORT_QUEUE=imports` from `.env.example` and `docker compose restart horizon` (the new `supervisor-imports` runs them). On SQS: leave `IMPORT_QUEUE_CONNECTION` empty, create an SQS queue **imports** with visibility timeout ≥ 3600 s and run `php artisan queue:work sqs --queue=imports --timeout=3600 --tries=1`. Two import styles: row by row (`ModuleImport`, e.g. FAQs) and chunk (`ChunkImport`, e.g. Subscribers), see README_DEVELOPER §24. Settings → Import & export has the thresholds. Locally, the **Debugbar** appears at the bottom of every page (Views tab = which Blade files the page uses); add the `DEBUGBAR_*` lines from `.env.example`.
11. **Team chat:** Roles → tick `chat.view` for every role that should chat (Editor: also tick it, existing roles don't get it automatically) and `chat.start` for roles that may start conversations. Add `CHAT_DISK=` and `AWS_CHAT_PREFIX=chat` from `.env.example` (empty = private local folder, or S3 when your uploads are on S3). SaaS: add **Team chat** to plans under Console → Plans (new installs: Business and Enterprise). Test list: `README_DEVELOPER.md` §33.
12. **Passkeys:** on by default. Sign in, open Profile → Security → Passkeys, enter your password and tap *Add passkey*. Passkeys need **HTTPS** (or `localhost` / `*.localhost` while developing) and are tied to the address you add them on: a passkey added on `laravelnews.localhost` only works there. Turn off in Settings → Security (*Passkey sign-in for staff*) or Settings → Customers.
13. Going live? Follow `DEPLOY.md` (first install, HTTPS, backups to S3, launch checklist).
14. Optional demo customers: `docker compose exec app php artisan db:seed --class="App\Modules\Customers\Database\Seeders\CustomerSeeder"`, then sign in at `/account/login` with `demo@customer.test` / `Secret#123`.
15. Try the API:
   ```bash
   curl -X POST http://localhost:8080/api/v1/auth/token -H "Content-Type: application/json" \
     -d '{"email":"YOUR_ADMIN_EMAIL","password":"YOUR_PASSWORD","device_name":"test"}'
   ```

## 5. Your own modules (generated before this update)

**Import / export changed engine.** The old `CsvImport` / `HandlesImport` classes are removed, so a module you generated yourself with an `importer()` method must be switched over (10 minutes per module). The quickest way is to copy from a fresh module:

```bash
docker compose exec app php artisan make:module Sample --fields="name:string,is_active:boolean"
```

Then, in **your** module (example: Orders):

1. Copy `app/Modules/Samples/Imports/SampleImport.php` → `app/Modules/Orders/Imports/OrderImport.php`. Change the namespace/model, move your old `->column(...)` lines into `columns()` (heading => example) and `rules()`, and your old `->using()` / `->prepare()` code into `saveRow()`.
2. Copy `Exports/SampleExport.php` → `Exports/OrderExport.php`: put your old `->export('orders', [...])` headings in `headings()` and the values in `map()`.
3. Copy `Views/admin/import.blade.php` and change `samples` → `orders` and the titles.
4. Controller: remove `use HandlesImport`, `importer()` and `importPage()`; copy `importForm()`, `import()` and `importTemplate()` from `SampleController` (change the names); change `->export('orders', [...], 'orders.export')` to `->export('orders', OrderExport::class, 'orders.export')`.
5. Routes stay the same (`orders.import`, `orders.import.store`, `orders.import.template`).

Lists that only use `->export('name', ['Heading' => 'column'])` keep working unchanged and now also export to Excel.

Other new features for your own modules:
- uploads: `Storage::disk('public')` → `Storage::disk('uploads')` (S3 or local, and the right tenant folder in SaaS mode)
- SaaS mode: in its `module.json` add `"central": false, "tenant": true` for a tenant feature (a plan module), or `"central": true` for a console feature

When you're done, remove the sample module as `README_PROJECT_CMD.md` section 8 describes.

## 6. If something goes wrong

| Problem                                               | Fix                                                                  |
| ----------------------------------------------------- | -------------------------------------------------------------------- |
| `Class "Laravel\Sanctum\..."`, `"Dedoc\Scramble\..."` or `"Opcodes\LogViewer\..."` not found | Step 3.1 didn't run: `composer require laravel/sanctum:^4.5 dedoc/scramble:^0.13 opcodesio/log-viewer:^3.24` |
| `Column not found: deleted_at` / `translation_of`     | Step 3.2: `php artisan migrate`                                      |
| No Import button                                      | Step 3.3 (`module:sync`), then tick `<module>.import` for your role   |
| Trash tab / language switcher missing or unstyled     | Step 3.5 (build), then Ctrl+F5                                       |
| API returns HTML instead of JSON                      | `php artisan optimize:clear`; URLs start with `/api/v1/`             |
| `Route [customer.login] not defined` / no Customers menu | Step 3.3 (`module:sync`), then `php artisan optimize:clear`         |
| `Class "Stancl\Tenancy\..."` not found               | Step 3.1: `composer require stancl/tenancy:^3.10 league/flysystem-aws-s3-v3:^3.0` |
| `This cache store does not support tagging` (SaaS)    | `CACHE_STORE=redis` in `.env`                                        |
| Tenant site shows "This site doesn't exist"           | Check `CENTRAL_DOMAIN`, the wildcard DNS, and the tenant's domains in SaaS → Tenants |
| Undo everything                                       | `php artisan migrate:rollback` (the new migrations ran as one batch), then `git checkout .` or restore your copy |
