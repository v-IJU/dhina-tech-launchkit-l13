# Update v1.3: SaaS mode (optional), S3 uploads, Production deploy, Monitoring, Customer accounts, Trash, CSV import, REST API, Multi-language, Sign-in history

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
- `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`, `config/auth.php`, `config/backup.php`, `config/starter.php`, `routes/console.php`
- `docker/php/Dockerfile` (new `web` target), `resources/admin/js/realtime.js`, `resources/views/layouts/admin.blade.php`
- `resources/views/layouts/web.blade.php`, `resources/views/components/admin/datatable.blade.php`
- `stubs/module/*.stub`
- `composer.json` (adds `laravel/sanctum`, `stancl/tenancy`, `league/flysystem-aws-s3-v3`; keep any packages you added)
- your own code that calls `Storage::disk('public')` for uploads: change it to `Storage::disk('uploads')` (section 5)

Modules **you generated yourself** are not touched. Section 5 shows how to give them the new features.

## 2. Copy the files

Unzip `update-v1.3.zip` **into your project folder**, so the `app/`, `config/`… folders in the zip land on top of yours, and allow it to replace files. Your `.env`, `compose.yaml`, database and own modules are not in the zip. Add the new `.env` keys from `.env.example`: `BACKUP_DISKS=local`, `UPLOADS_DISK=public`, `AWS_UPLOADS_PREFIX=uploads`, `STARTER_TENANCY=false` (and the `CENTRAL_DOMAIN`, `TENANT_DB_PREFIX`, `SAAS_*`, `RAZORPAY_*`, `STRIPE_*` lines, empty until you use SaaS).

## 3. Run these commands

From the project root (Git Bash):

```bash
# 1. install the new packages (Sanctum: API tokens, Scramble: API docs at /docs/api, Log Viewer: /admin/logs,
#    Tenancy: SaaS mode, S3 driver: uploads/backups on S3)
docker compose exec app composer require laravel/sanctum:^4.5 dedoc/scramble:^0.13 opcodesio/log-viewer:^3.24 stancl/tenancy:^3.10 league/flysystem-aws-s3-v3:^3.0 laravel/mcp:^1.3 laravel/ai:^1.4

# 2. new tables and columns: error log, customers, trash (deleted_at), API tokens, sign-in history, translations
docker compose exec app php artisan migrate

# 3. new permissions (monitoring.*, customers.*, *.import) and menu items (Monitoring, Customers, Sign-in history)
docker compose exec app php artisan module:sync

# 4. clear caches
docker compose exec app php artisan optimize:clear

# 5. rebuild CSS/JS (language switcher, trash tabs, etc.)
docker compose run --rm node sh -c "npm install && npm run build"

# 6. restart the background containers so they load the new code
docker compose restart horizon scheduler reverb

# 7. check everything: expect "154 passed"
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
10. Going live? Follow `DEPLOY.md` (first install, HTTPS, backups to S3, launch checklist).
11. Optional demo customers: `docker compose exec app php artisan db:seed --class="App\Modules\Customers\Database\Seeders\CustomerSeeder"`, then sign in at `/account/login` with `demo@customer.test` / `Secret#123`.
12. Try the API:
   ```bash
   curl -X POST http://localhost:8080/api/v1/auth/token -H "Content-Type: application/json" \
     -d '{"email":"YOUR_ADMIN_EMAIL","password":"YOUR_PASSWORD","device_name":"test"}'
   ```

## 5. Your own modules (generated before this update)

New modules from `make:module` get trash and import automatically, and an API with `--api`. To add them to a module you generated earlier, compare it with a fresh one:

```bash
docker compose exec app php artisan make:module Sample --fields="name:string,is_active:boolean" --api
```

Then copy the patterns from `app/Modules/Samples/`:
- the `trash()`, `restore()`, `forceDelete()` and `importer()` code in the controller
- the routes in `Routes/admin.php`
- `<x-admin.datatable.row-actions>` in `_table.blade.php`
- `SoftDeletes` in the model, plus a migration with `$table->softDeletes()`

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
