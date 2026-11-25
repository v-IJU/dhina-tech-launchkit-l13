# SaaS mode: commands

Many companies (tenants) on one install, each with its own database, users, settings and website, on plans you sell.
How it works and the production setup (DNS, SSL, gateways): **`SAAS.md`**. One business only? Use `README_SINGLE_BUSINESS.md`.

---

## 1. Shortcuts and URLs

```bash
alias art="docker compose exec app php artisan"
alias dc="docker compose"
alias comp="docker compose exec app composer"
```

Locally the main domain is `localhost`. Every tenant is a subdomain; `*.localhost` works in Chrome and Firefox without any hosts file.

| URL | What |
| --- | --- |
| http://localhost:8080 | Your SaaS website (`/pricing`, `/signup`) |
| http://localhost:8080/admin | **SaaS console**: tenants, plans, subscriptions, invoices, your own website content |
| http://acme.localhost:8080/admin | Tenant `acme`'s own admin panel |
| http://acme.localhost:8080 | Tenant `acme`'s website |
| http://localhost:8025 | Mailpit: all emails (trial reminders, welcome, invoices) |
| http://localhost:8082 | phpMyAdmin: `starter_central` + `tenant_*` databases |

`.env` (local):

```bash
STARTER_TENANCY=true
CENTRAL_DOMAIN=localhost
APP_URL=http://localhost:8080
CACHE_STORE=redis          # required
SESSION_DOMAIN=null        # required: each tenant keeps its own login
```

The database user must be able to create databases (local, once):

```bash
dc exec mysql mysql -uroot -proot -e "GRANT ALL PRIVILEGES ON *.* TO 'starter'@'%'; FLUSH PRIVILEGES;"
```

## 2. Install as SaaS (new project)

```bash
cp .env.example .env
dc up -d --build
comp install
art key:generate
art starter:install --mode=saas --central-domain=localhost --demo-tenant=demo
dc run --rm node sh -c "npm install && npm run build"
```

Result: the console admin you typed, the 3 default plans, and a demo tenant at http://demo.localhost:8080/admin (`owner@demo.test` / `Demo@12345`).

## 3. Convert an existing single-business install

The current database becomes the first tenant, untouched (same users and passwords).

```bash
art backup:run --only-db
art tenancy:convert --subdomain=laravelnews --central-domain=localhost --central-database=starter_central --plan=enterprise
dc restart app horizon scheduler reverb
```

| Option | Meaning |
| --- | --- |
| `--subdomain` | The business's address: `laravelnews.localhost` |
| `--central-domain` | Main SaaS domain (`localhost` locally, `yourapp.com` on the server) |
| `--central-database` | New, empty database for plans and tenants (created for you) |
| `--domain` | Its existing own domain, e.g. `www.laravelnews.com` (optional) |
| `--plan` | `starter`, `business` or `enterprise` |

Sign in to both with your old admin email and password. The converted tenant keeps its database name (`starter`); only new tenants get `tenant_<subdomain>`.

## 4. Where data lives

| Central (`starter_central`) | Tenant (`starter`, `tenant_acme`, …) |
| --- | --- |
| Plans, tenants, domains, subscriptions, invoices, usage | The company's users, roles, permissions, settings, menus |
| Your staff (console admins), Error log of all tenants | Pages, blog, FAQs, customers, media, inbox… |
| Your own website content (pages, blog, FAQs…) | Its own website content |

Settings work the same way: Settings in the console = your SaaS website + Billing tab; Settings in a tenant = that tenant only.

## 5. Tenants

### Create

- **Self-signup:** http://localhost:8080/signup (trial from the plan).
- **You onboard:** console → SaaS → Onboard tenant. Leave the password empty → the owner gets a "choose your password" email (see Mailpit).

Either way the tenant gets: a new database → all migrations → roles, permissions, menus, settings → owner account with super-admin. Takes a few seconds.

### Look and act

```bash
art tenants:list                                                    # id + domains
art tinker --execute="foreach (App\Modules\Saas\Models\Tenant::all() as \$t) echo \$t->id.' '.\$t->status.' '.\$t->plan?->name.' '.\$t->database()->getName().PHP_EOL;"

# run any command inside one tenant / all tenants
art tenants:run cache:clear --tenants=acme
art tenants:run module:sync

# code inside a tenant
art tinker --execute="tenancy()->initialize('acme'); echo App\Models\User::count();"

# reset a tenant user's password
art tinker --execute="tenancy()->initialize('acme'); App\Models\User::where('email','owner@acme.com')->first()->forceFill(['password'=>bcrypt('Secret#123')])->save();"
```

Console → SaaS → Tenants → a tenant: usage, subscriptions, invoices, domains, **Record a payment**, **Extend trial**, **Suspend / Reactivate**, **Open as owner**, **Delete** (drops its database; type the id to confirm).

### Test the trial life cycle without waiting

```bash
# reminder 3 days before the end
art tinker --execute="App\Modules\Saas\Models\Tenant::find('acme')->update(['trial_ends_at' => now()->addDays(2)->addHours(12)]);"
art saas:check-subscriptions

# trial over + 7 grace days → suspended
art tinker --execute="App\Modules\Saas\Models\Tenant::find('acme')->update(['trial_ends_at' => now()->subDays(8)]);"
art saas:check-subscriptions
```

Suspended: the website shows "Temporarily unavailable"; the owner can still sign in and is sent to Billing to pay. Unlock from the console with **Record a payment** or **Reactivate**. Grace days: `SAAS_GRACE_DAYS` in `.env`.

## 6. Migrations

| Command | Runs in |
| --- | --- |
| `art migrate` | central |
| `art tenants:migrate` | every tenant |
| `art tenants:migrate --tenants=acme` | one tenant |
| `art tenants:run migrate:status --tenants=acme` | what ran in a tenant |
| `art tenants:rollback --tenants=acme` | undo the last batch in a tenant |

Where a new migration goes:

| Table for… | Folder | Runs in |
| --- | --- | --- |
| A feature tenants use | `app/Modules/<Module>/Database/Migrations` | central **and** every tenant |
| Your SaaS business only (e.g. coupons) | `app/Modules/Saas/Database/Migrations` (+ model with the `CentralConnection` trait) | central only |

Every tenant database has the tables of **every** module, whatever its plan. Plans only decide what a tenant can use, so upgrading never needs a migration.

After writing a migration:

```bash
art migrate && art tenants:migrate
```

## 7. Modules

### Create

```bash
art make:module Invoice --fields="title:string,amount:decimal,due_date:date,is_paid:boolean" --group="Sales" --icon=bi-receipt --migrate
```

In SaaS mode `--migrate` does everything: the table in central **and** every tenant, permissions and menu in all of them.

| `--for=` | Who gets it | Use for |
| --- | --- | --- |
| `tenant` (default) | Tenants whose plan includes it | Features you sell |
| `central` | Only your console / SaaS website | Your own internal tools |
| `both` | You and tenants on a plan with it | Website content (like Blog, FAQs) |

Then **sell it**: console → SaaS → Plans → edit a plan → tick the module → Save. Every tenant on that plan has it on the next page load. One tenant only: SaaS → Tenants → Edit → *Extra modules*.

The tenant's super admin sees it at once; other roles need its permissions ticked under the tenant's Roles page.

### Fields, options

Same as single business: `--fields`, `--group`, `--icon`, `--web`, `--api`, `--force`. Field types: `string`, `email`, `url`, `text`, `editor`, `integer`, `decimal`, `boolean`, `date`, `datetime`, `image`, `foreignId`. Details: `README_SINGLE_BUSINESS.md` section 5.

### Change an existing module

```bash
# add a column
art make:migration add_tax_to_invoices_table --table=invoices --path=app/Modules/Invoices/Database/Migrations
art migrate && art tenants:migrate

# new permission or menu item in module.json
art module:sync && art tenants:run module:sync
art optimize:clear
```

Who gets a module is the `central` / `tenant` pair in its `module.json` (`"saas_only": true` = never in single-business mode). After changing them: `art optimize:clear`.

### Switch off / remove

```bash
art module:disable invoices      # hidden for everyone (console and all tenants), data stays
art module:enable invoices
```

Hide it from some tenants only: untick it on their plan (data stays, ticking again brings it back).

Remove completely:

```bash
art tenants:run migrate:reset --option="path=app/Modules/Invoices/Database/Migrations" --option="force=1"
art migrate:reset --path=app/Modules/Invoices/Database/Migrations --force
rm -rf app/Modules/Invoices
art module:sync && art tenants:run module:sync
art optimize:clear
```

Then untick it from plans (it no longer shows there anyway).

## 8. Plans and billing

```bash
art db:seed --class="App\Modules\Saas\Database\Seeders\PlanSeeder"   # Starter, Business, Enterprise (never overwrites)
```

Console → SaaS → Plans: price (before tax), trial days, modules, limits (empty = unlimited), pricing-page bullets, gateway plan ids, public/hidden. Tax, GSTIN, invoice prefix, gateways on/off, signup on/off: console → Settings → **Billing**.

Payment gateways (`.env`, then `art optimize:clear`):

```bash
RAZORPAY_KEY_ID=rzp_test_xxx
RAZORPAY_KEY_SECRET=xxx
RAZORPAY_WEBHOOK_SECRET=your-secret
STRIPE_SECRET=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
```

Webhook URLs: `https://yourapp.com/billing/webhooks/razorpay` and `/billing/webhooks/stripe`. Locally they need a tunnel (`cloudflared tunnel --url http://localhost:8080` or `ngrok http 8080`). Without gateways: **Record a payment** on the tenant page (bank transfer, UPI, cheque).

## 9. Scheduled jobs

| Command | When | What |
| --- | --- | --- |
| `art saas:check-subscriptions` | hourly | Trial reminders, trial ended, suspend after grace |
| `art saas:collect-usage [--tenant=acme]` | 04:30 | Users, customers, storage, sign-ins per tenant |
| `tenants:run …` jobs | daily | Trash, activity log, tokens, sitemap: per tenant |

```bash
art schedule:list                 # see them all
dc ps scheduler                   # the scheduler container must be running
```

## 10. Server

`.env` on the server:

```bash
STARTER_TENANCY=true
CENTRAL_DOMAIN=yourapp.com
APP_URL=https://yourapp.com
CACHE_STORE=redis
SESSION_DOMAIN=null
```

DNS: `yourapp.com` **and** `*.yourapp.com` → server / load balancer, with a wildcard SSL certificate (AWS: ACM on the ALB). Database user: `CREATE` / `DROP` database rights.

```bash
./deploy.sh        # migrate (central) + module:sync + tenants:migrate + tenants:run module:sync, health check, auto-rollback
```

Backups: the built-in backup covers the central database; use RDS automated snapshots for all tenant databases. Details: `SAAS.md` section 7.

## 11. Troubleshooting

| Problem | Fix |
| --- | --- |
| "This site doesn't exist" | Wrong subdomain, or `CENTRAL_DOMAIN` doesn't match the address you use |
| `This cache store does not support tagging` | `CACHE_STORE=redis` |
| `Access denied … CREATE DATABASE` when creating a tenant | GRANT rights to the DB user (section 1) |
| Tenant login loops back to login | Delete cookies for that `*.localhost` address; update to the latest starter |
| Billing or a new module missing in a tenant's sidebar | `art tenants:run module:sync`, `art optimize:clear` |
| Tenant shows the console's settings | Update to the latest starter (settings are per tenant) |
| Tenant can't reach a page, gets sent to Billing | It is suspended: Record a payment / Reactivate in the console |
| Changed `.env` not picked up | `art optimize:clear`, `dc restart app horizon scheduler reverb` |
| Error in one tenant | Console → System → Monitoring → Error log (shows the tenant id) |

## 12. One page

```bash
# setup
art starter:install --mode=saas --central-domain=localhost --demo-tenant=demo
art tenancy:convert --subdomain=X --central-domain=localhost --central-database=starter_central --plan=enterprise

# tenants
art tenants:list
art tenants:run <command> [--tenants=acme] [--option="key=value"]
art tinker --execute="tenancy()->initialize('acme'); …"

# migrations
art migrate && art tenants:migrate [--tenants=acme]

# modules
art make:module Name --fields="…" [--for=tenant|central|both] [--web] [--api] --migrate
art module:sync && art tenants:run module:sync
art module:enable x | art module:disable x

# billing / life cycle
art saas:check-subscriptions
art saas:collect-usage [--tenant=acme]
art db:seed --class="App\Modules\Saas\Database\Seeders\PlanSeeder"

# always after changes
art optimize:clear
dc restart app horizon scheduler reverb
```
