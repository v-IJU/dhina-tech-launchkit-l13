# Single-business mode: commands

One company, one database, one admin panel and website. This is the default (`STARTER_TENANCY=false`).
Running many companies on one install? Use **`README_SAAS.md`** instead.

Detailed explanations: `README_PROJECT_CMD.md` (every option), `README_DEVELOPER.md` (how the code works), `DEPLOY.md` (servers).

---

## 1. Shortcuts

Add to `~/.bashrc` (Git Bash on Windows, Linux, macOS), then open a new terminal:

```bash
alias art="docker compose exec app php artisan"
alias dc="docker compose"
alias comp="docker compose exec app composer"
```

Git Bash says *"the input device is not a TTY"*? Put `winpty` in front of the command.

## 2. URLs (local)

These are the default ports. Each copy of the project reads its ports from its own `.env`; if you changed them (e.g. a second copy next to a SaaS one), use yours.

| URL (default) | `.env` key | What |
| --- | --- | --- |
| http://localhost:8080 | `APP_PORT` (and `APP_URL`) | Website |
| http://localhost:8080/admin | | Admin panel |
| http://localhost:8080/docs/api | | API docs |
| http://localhost:8080/admin/horizon | | Queues (super admin) |
| http://localhost:8080/admin/logs | | Log files |
| http://localhost:8025 | `FORWARD_MAILPIT_PORT` | Mailpit: every email the app sends |
| http://localhost:8082 | `FORWARD_PMA_PORT` | phpMyAdmin |
| `127.0.0.1:3307` | `FORWARD_DB_PORT` | MySQL from your PC (user `starter`, password `secret`) |
| `localhost:8081` | `REVERB_PORT` | WebSockets (used by the browser automatically) |
| http://localhost:5173 | `VITE_PORT` | Vite dev server (`--profile dev`) |

Example second copy (`.env`): `COMPOSE_PROJECT_NAME=launchkit-single`, `APP_PORT=8090`, `APP_URL=http://localhost:8090`, `REVERB_PORT=8091`, `FORWARD_DB_PORT=3308`, `FORWARD_PMA_PORT=8092`, `FORWARD_MAILPIT_PORT=8026`, `VITE_PORT=5174` → website on http://localhost:8090.

## 3. First install

```bash
cp .env.example .env
dc up -d --build
comp install
art key:generate
art starter:install                 # asks "Single business or SaaS?" → choose Single business
dc run --rm node sh -c "npm install && npm run build"
```

```bash
art starter:install --mode=single --email=me@site.com --password='Secret#123' --name="Viju" -n   # no questions
art starter:install --fresh          # wipe the database and install again (local only)
art starter:install --no-demo        # without demo pages, posts, services
```

## 4. Every day

```bash
dc up -d                            # start
dc --profile dev up -d              # start + Vite live reload while editing Blade/CSS/JS
dc down                             # stop (data kept)
dc ps                               # what is running
dc logs -f app                      # follow a container log (app, nginx, horizon, reverb, scheduler)
dc restart horizon                  # after changing a job, mail or notification class
dc exec app bash                    # shell inside PHP
art optimize:clear                  # something looks stale
```

After `git pull` or an update zip:

```bash
comp install
art migrate
art module:sync
art optimize:clear
dc run --rm node sh -c "npm install && npm run build"
dc restart horizon scheduler reverb
```

## 5. Modules

### Create one

```bash
art make:module Product \
  --fields="name:string,sku:string:unique,product_category_id:foreignId:nullable,price:decimal,photo:image,description:editor:nullable,is_active:boolean" \
  --group="Catalog" --icon=bi-box-seam --migrate
```

| Option | Meaning |
| --- | --- |
| `--fields=` | `name:type[:nullable][:unique]`, comma separated |
| `--group=` | Sidebar section (`config/starter.php` → `menu_groups`) |
| `--icon=` | Bootstrap icon, e.g. `bi-people` |
| `--web` | Public pages `/products` and `/products/{id}` |
| `--api` | REST API `/api/v1/products` |
| `--migrate` | Create the table, permissions and menu item now |
| `--force` | Regenerate an existing module (delete its old migration first) |

Field types: `string`, `email`, `url`, `text`, `editor`, `integer`, `decimal`, `boolean`, `date`, `datetime`, `image`, `foreignId`.

What you get: model, migration, admin list/create/edit with search, filters, export, import and trash, permissions `products.view|create|edit|delete|export|import`, sidebar item. Super admin sees it at once; tick `products.*` for other roles under **Access control → Roles**.

`--for=` only matters in SaaS mode; ignore it here.

### Add a field to an existing module

```bash
art make:migration add_brand_to_products_table --table=products --path=app/Modules/Products/Database/Migrations
# edit the migration, then add the field to: Models (fillable), Requests (rules), Views/admin/_form, optionally _table
art migrate
```

### Add a table / model / controller inside a module

```bash
art make:migration create_variants_table --path=app/Modules/Products/Database/Migrations
art make:model      App/Modules/Products/Models/Variant
art make:controller App/Modules/Products/Controllers/Admin/VariantController --resource
art make:request    App/Modules/Products/Requests/VariantRequest
```

Routes go in `app/Modules/Products/Routes/admin.php`, views in `Views/admin/`.

### module.json changed (new permission, menu item)

```bash
art module:sync             # adds missing permissions and menu items, never deletes
art optimize:clear
```

### Switch on / off / remove

```bash
art module:list
art module:disable products     # hidden, tables and data stay
art module:enable products

# remove completely (order matters)
art migrate:reset --path=app/Modules/Products/Database/Migrations
rm -rf app/Modules/Products
art module:sync
art tinker --execute="Spatie\Permission\Models\Permission::where('name','like','products.%')->delete(); app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();"
art optimize:clear
```

## 6. Database

```bash
art migrate                     # new migrations
art migrate:status
art migrate:rollback [--step=1]
art db:seed                     # roles, permissions, menus, settings (safe to repeat)
art db:seed --class="Database\Seeders\DemoContentSeeder"
art tinker                      # PHP console: Product::count()
art backup:run --only-db        # backup now (download under Admin → System → Backups)
```

## 7. Files and uploads

`.env`:

```bash
UPLOADS_DISK=public     # storage/app/public on this server (default)
UPLOADS_DISK=s3         # your S3 bucket: also fill AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION, AWS_BUCKET
```

In your own code always use `Storage::disk('uploads')`. Images not showing locally: `art storage:link`.

## 8. Queues, schedule, backups

| Command | What |
| --- | --- |
| `art schedule:list` | Every scheduled job and when it runs |
| `art queue:failed` / `art queue:retry all` | Failed jobs (also Admin → System → Monitoring → Failed jobs) |
| `art backup:run` / `art backup:list` | Backups |
| `art trash:empty --days=30` | Empty the trash |
| `art health:check` | Database, cache, storage, Redis, Horizon, scheduler, disk |

Horizon, the scheduler and Reverb run in their own containers; you don't start them by hand.

## 9. Tests and code quality

```bash
art test                                    # all tests
art test --filter=UserManagementTest
dc exec app ./vendor/bin/pint               # fix code style
dc exec app ./vendor/bin/phpstan analyse --memory-limit=1G
```

## 10. Server

```bash
cp .env.production.example .env            # fill every CHANGE_ME
./deploy.sh                                 # build, migrate, module:sync, start, health check, auto-rollback
./deploy.sh status | ./deploy.sh restart | ./deploy.sh rollback
```

Full guide: `DEPLOY.md` (HTTPS, RDS, S3 backups, Auto Scaling).

## 11. Troubleshooting

| Problem | Fix |
| --- | --- |
| New module 404 / not in sidebar | `art module:sync`, `art optimize:clear`, check the role has `<slug>.view` |
| 403 on a page | Tick the permission under Access control → Roles (or Users → key icon for one user) |
| No styles | `dc run --rm node sh -c "npm install && npm run build"` |
| Emails not arriving | Locally: http://localhost:8025 (Mailpit) |
| Job / email change ignored | `dc restart horizon` |
| Errors | Admin → System → Monitoring → Error log, or Log files |
| Port in use | Change `APP_PORT`, `REVERB_PORT`, `FORWARD_DB_PORT`, `FORWARD_PMA_PORT` in `.env`, `dc up -d` |

## 12. Later: turn this into a SaaS

```bash
art tenancy:convert --subdomain=yourbusiness --central-domain=yourapp.com --central-database=starter_central
```

Your database becomes the first tenant, untouched. See `README_SAAS.md` section 3.
