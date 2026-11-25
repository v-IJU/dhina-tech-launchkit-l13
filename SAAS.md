# SaaS mode: many companies on one install

SaaS mode is **optional**. Without it the starter is a single-business app, exactly as before. With it, one install serves many companies (tenants). Each tenant gets:

- its own database (`tenant_acme`), its own users, roles, permissions, settings, menus and website
- its own address: `acme.yourapp.com` and, if wanted, its own domain (`www.acme.com`)
- a plan that decides which modules it has and how many users, customers and how much storage it may use

The central part (`yourapp.com`) has your marketing website, `/pricing`, `/signup` and the **SaaS console** in the admin panel, where you see every subscriber, their usage, payments and invoices.

| | Single business | SaaS |
| --- | --- | --- |
| `.env` | `STARTER_TENANCY=false` | `STARTER_TENANCY=true`, `CENTRAL_DOMAIN=yourapp.com` |
| Databases | one | one central + one per tenant |
| Cache | any | **Redis** (`CACHE_STORE=redis`); tenants' cache is kept apart with tags |
| DB user | normal rights | also `CREATE` and `DROP` DATABASE |
| DNS | `yourapp.com` | `yourapp.com` **and** `*.yourapp.com` |

All commands in one place: **`README_SAAS.md`**.

Package: [stancl/tenancy](https://tenancyforlaravel.com) v3 (database per tenant).

---

## 1. Turn it on

**New install**

```bash
docker compose exec app php artisan starter:install --mode=saas --central-domain=yourapp.com --demo-tenant=demo
```

Without options it asks "Single business or SaaS?". It writes `STARTER_TENANCY` and `CENTRAL_DOMAIN` to `.env`, seeds the three default plans, and makes you super admin of the console.

**Existing single-business install** (your current data becomes the first tenant): see section 8.

**Locally** `*.localhost` works in Chrome and Firefox without DNS: use `CENTRAL_DOMAIN=localhost`, open `http://localhost:8080` for the console and `http://acme.localhost:8080` for tenant "acme".

## 2. What lives where

| Central database (`yourapp.com`) | Tenant database (`acme.yourapp.com`) |
| --- | --- |
| Plans, tenants, domains, subscriptions, invoices, usage | Users, roles, permissions, settings, menus |
| Your own staff (console admins), Error log for everybody | Pages, blog, customers, FAQs, services, media, inbox… |
| Your own website content: pages, blog, FAQs, services, sliders, testimonials | |
| Modules, Monitoring, Backups, Horizon, Log files | Billing page (their plan, usage, invoices, pay / change plan) |

Each module's `module.json` says where it is available:

```json
"central": true,     // shown on yourapp.com (console)
"tenant": true,      // shown inside tenants
"saas_only": true    // only exists in SaaS mode (Saas, Billing)
```

Blog, FAQs, Services, Sliders and Testimonials have both flags on: they run your own SaaS website on `yourapp.com`, and each tenant's website when its plan includes them. Customers is tenant-only.

Core modules (`"core": true`: Dashboard, Users, Roles, Settings, Menus, Activity, Media, Pages, Inbox, Billing) are in **every** plan. The others (Blog, Customers, FAQs, Services, Sliders, Testimonials, and any module you generate) are **plan modules**: a tenant has them only when its plan includes them. Routes of a module the tenant doesn't have return 404, and the menu entry is hidden.

Files: every disk gets a tenant folder, `storage/app/public/tenant{id}/…` or `uploads/tenant{id}/…` on S3, and file URLs become `/storage/tenant{id}/…`.

## 3. Plans

**SaaS → Plans.** The seeder (`PlanSeeder`) creates:

| Plan | Price / month | Modules on top of core | Limits | Trial |
| --- | --- | --- | --- | --- |
| Starter | ₹999 | FAQs, services, sliders, testimonials | 3 users, 1 GB | 14 days |
| Business (featured) | ₹2,499 | + Blog, Customers | 10 users, 5,000 customers, 5 GB | 14 days |
| Enterprise | ₹6,999 | everything | unlimited | 30 days |

Yearly = 10 × monthly. Prices are before tax; the tax rate is in Settings → Billing (18 % GST by default).

**Add a plan:** SaaS → Plans → Add plan. Tick its modules, fill the limits (empty = unlimited), the pricing-page bullet points and the gateway plan ids (section 5). "Show on the pricing page" off = a private plan you assign by hand (custom deals). Changing a plan's modules applies to its tenants straight away.

**One tenant needs more:** SaaS → Tenants → Edit → *Extra modules* and *Limit overrides* change one tenant without a new plan.

**A new module you generated** (`php artisan make:module Invoices`) is a plan module automatically. Tick it on the plans that should have it. Its migrations run in every tenant database with `php artisan tenants:migrate`.

Limits are checked when a user or customer is created (`PlanLimits::ensureCanAdd()`); storage and sign-ins are measured nightly (`saas:collect-usage`) and shown on the tenant's Billing page and in the console.

## 4. Getting tenants

**Self-signup:** `yourapp.com/pricing` → *Start free trial* → `/signup` (company, subdomain with live "is it free?" check, owner name, email, password). A few seconds later the tenant has its own database with roles, menus, settings and the owner as super admin, and is sent to `acme.yourapp.com/admin/login`. Turn signup off under Settings → Billing.

**You onboard them:** SaaS → Onboard tenant. Choose the plan, trial days (0 = active now), optional demo content and custom domain. The owner gets a "choose your password" email that links to their own domain. Leave the password empty for that, or type one to hand over yourself.

**Open as owner:** on the tenant page, *Open as owner* signs you in to their panel in a new tab (one-time link, not recorded in their sign-in history).

Tenant life cycle:

```
trial ──paid──▶ active ──payment fails──▶ past_due ──grace days──▶ suspended
  │                │                                                  ▲
  └─trial ends + grace days (no payment)──────────────────────────────┘
                   └─cancelled: runs to end of paid period, then suspended
```

`php artisan saas:check-subscriptions` runs hourly: trial-ending reminder 3 days before, trial-ended email, suspension after `SAAS_GRACE_DAYS` (7). A suspended tenant's website shows "temporarily unavailable"; its owner can still sign in and pay under Billing, which reactivates it immediately. You can also Suspend / Reactivate / Extend trial by hand.

## 5. Payments: Razorpay and Stripe

Both work at the same time; the tenant picks one on its Billing → Choose a plan page. Turn each on/off under Settings → Billing (company, GSTIN, address, tax label and rate, invoice prefix are there too).

**Razorpay** (subscriptions, UPI autopay and cards)

1. Dashboard → Subscriptions → Plans: create one plan per price and interval, **including tax** (₹2,499 + 18 % = ₹2,948.82). Paste each `plan_…` id into SaaS → Plans → Edit.
2. Settings → API keys → `.env`: `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET`.
3. Settings → Webhooks → Add: URL `https://yourapp.com/billing/webhooks/razorpay`, a secret → `RAZORPAY_WEBHOOK_SECRET`, events `subscription.activated`, `subscription.charged`, `subscription.pending`, `subscription.halted`, `subscription.cancelled`, `subscription.completed`.

**Stripe** (cards, international)

1. Products → add a product per plan with a monthly and a yearly recurring price (tax included, or use Stripe Tax). Paste the `price_…` ids into the plan.
2. Developers → API keys → `STRIPE_SECRET`.
3. Developers → Webhooks → endpoint `https://yourapp.com/billing/webhooks/stripe`, events `checkout.session.completed`, `invoice.paid`, `invoice.payment_failed`, `customer.subscription.updated`, `customer.subscription.deleted` → signing secret → `STRIPE_WEBHOOK_SECRET`.

Flow: tenant clicks *Pay* → gateway's hosted page → webhook (signature checked, otherwise 400) → subscription active, tenant active on the new plan, numbered invoice `INV-2026-00042` created as paid. Renewals arrive the same way every month or year.

**Bank transfer / cheque / UPI to your account:** tenant page → *Record a payment*. Same result: plan active, paid invoice.

Invoices are printable (browser → Save as PDF) from SaaS → Invoices and from the tenant's Billing page.

## 6. The console

| Page | What you see |
| --- | --- |
| SaaS → Overview | MRR, active / trial / suspended counts, revenue chart (6 months), trials ending this week, tenants needing attention, newest signups |
| Tenants | Search, filter by status and plan, export CSV |
| Tenant page | Plan, status, owner and last sign-in, usage vs limits, subscriptions, invoices, domains, modules, record payment, extend trial, suspend, open as owner, delete (drops its database, type the id to confirm) |
| Subscriptions, Invoices | Every tenant's, searchable; mark an open invoice paid, void it |
| System → Monitoring → Error log | Errors from every tenant, with the tenant id |

Console staff need the `saas.*` and `plans.*` permissions (the seeded *Admin* role has them).

## 7. Production

1. **DNS:** `A yourapp.com` and `A *.yourapp.com` → your server or load balancer. Custom tenant domains: the tenant points a CNAME to `yourapp.com`, you add it on the tenant page.
2. **HTTPS:** a wildcard certificate `*.yourapp.com`.
   - AWS: ACM certificate for `yourapp.com` + `*.yourapp.com` on the ALB (recommended). For tenants' own domains add their names to the certificate, or put Cloudflare (SSL for SaaS) in front.
   - Cloudflare proxy: wildcard DNS record proxied, SSL mode Full.
   - Caddy on the server: on-demand TLS, `ask` pointing at your app so only known domains get certificates.
3. **`.env`:** `STARTER_TENANCY=true`, `CENTRAL_DOMAIN=yourapp.com`, `APP_URL=https://yourapp.com`, `CACHE_STORE=redis`, `SESSION_DOMAIN=null` (each tenant keeps its own sign-in cookie), payment keys.
4. **Database user** with `CREATE`, `DROP` on `tenant_%` (MySQL: `GRANT ALL ON \`tenant\_%\`.* TO 'starter'@'%'; GRANT CREATE ON *.* TO 'starter'@'%';`).
5. **Deploys:** `deploy.sh` runs `php artisan migrate --force` (central) **and** `php artisan tenants:migrate --force` (every tenant).
6. **Scheduler:** already in `routes/console.php`; daily jobs that touch tenant data (trash, tokens, activity pruning) run once per tenant via `tenants:run`.
7. **Backups:** the built-in backup covers the central database. Use RDS automated snapshots (whole instance, all tenant databases) or a nightly `mysqldump` loop over `tenant_*`. Uploaded files: S3 versioning, or include `storage/app/public` in your server backup.
8. **Queues:** jobs remember their tenant; nothing to change.

## 8. Convert your existing single-business install

Your current database becomes tenant number one, untouched: same users, passwords, roles, content and settings. A new central database holds plans and tenants.

```bash
# 0. backup first, and make sure CACHE_STORE=redis
docker compose exec app php artisan backup:run --only-db

# 1. convert (example: business "acme", SaaS domain yourapp.com)
docker compose exec app php artisan tenancy:convert \
    --subdomain=acme \
    --central-domain=yourapp.com \
    --central-database=starter_central \
    --domain=www.acme.com \
    --plan=enterprise

# 2. restart so workers pick up the new .env
docker compose restart app queue scheduler   # or: php artisan horizon:terminate
```

It:

1. creates `starter_central` and sets `DB_DATABASE`, `STARTER_TENANCY=true`, `CENTRAL_DOMAIN` in `.env`
2. migrates and seeds the central database (roles, menus, settings, 3 plans)
3. copies your **super admins** (same email and password) to the console; other staff stay only in the tenant
4. registers the old database as tenant `acme` (`acme.yourapp.com` + `www.acme.com`), status active, on the plan you chose
5. moves `storage/app/public/*` into `storage/app/public/tenantacme/` and leaves links behind, so old `/storage/…` image URLs inside page or post content keep working. On S3 it copies `uploads/…` to `uploads/tenantacme/…` and keeps the originals.

Then add the DNS and certificate from section 7. Running it twice is refused. There is no automatic way back to single business: restore the backup if needed.

## 9. Commands

```bash
php artisan tenants:list                          # all tenants
php artisan tenants:migrate --force               # new migrations in every tenant database
php artisan tenants:migrate --tenants=acme        # one tenant
php artisan tenants:seed --class=YourSeeder       # seed every tenant
php artisan tenants:run cache:clear --tenants=acme  # any command inside a tenant
php artisan saas:check-subscriptions              # trials, suspensions (hourly)
php artisan saas:collect-usage --tenant=acme      # measure usage now (nightly)
```

In code:

```php
use App\Core\Tenancy\Tenancy;

Tenancy::enabled();          // SaaS mode on?
Tenancy::inTenant();         // inside acme.yourapp.com (or tenants:run)?
Tenancy::tenant()?->plan;    // current tenant and its plan
Tenancy::central(fn () => Plan::all());   // read central data from inside a tenant
$tenant->run(fn () => User::count());     // run code inside a tenant from the console
module_enabled('blog');      // does the current tenant's plan include Blog?
```
