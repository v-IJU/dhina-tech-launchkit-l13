# Deploying to production

How to put a project made from this starter on a server, update it, watch it and restore it.
Local development stays the same (`compose.yaml`, `README_PROJECT_CMD.md`).

| File | What it is |
| --- | --- |
| `compose.prod.yaml` | Production stack: `web` (nginx), `app` (PHP-FPM), `horizon`, `scheduler`, `reverb`, plus optional `mysql` / `redis` |
| `docker/php/Dockerfile` | Targets `production` (code + vendor + built CSS/JS baked in) and `web` (nginx + `public/`) |
| `docker/nginx/production.conf` | nginx for production: caching, `/up`, websockets for Reverb |
| `.env.production.example` | Every production setting, with `CHANGE_ME` where you must fill in |
| `deploy.sh` | Pull, build, migrate, restart, health check, automatic rollback |

**Contents**

1. [How it fits together](#1-how-it-fits-together)
2. [First install on a new server](#2-first-install-on-a-new-server)
3. [Every update](#3-every-update)
4. [Domain and HTTPS](#4-domain-and-https)
5. [Database and Redis: on the server or managed (RDS, ElastiCache)](#5-database-and-redis)
6. [Health checks](#6-health-checks)
7. [Errors and failed jobs](#7-errors-and-failed-jobs)
8. [Several servers, Auto Scaling, golden AMI](#8-several-servers-auto-scaling-golden-ami)
9. [Backups and restore](#9-backups-and-restore)
10. [Logs and day-to-day commands](#10-logs-and-day-to-day-commands)
11. [Launch checklist](#11-launch-checklist)
12. [Troubleshooting](#12-troubleshooting)

---

## 1. How it fits together

```
Browser ──HTTPS──▶ Load balancer / Cloudflare / host Caddy   (TLS certificate lives here)
                        │ HTTP :80 (WEB_PORT)
                        ▼
                 ┌─────────────┐  /app, /apps (websockets) ┌──────────┐
                 │ web (nginx) │──────────────────────────▶│  reverb  │
                 └─────────────┘                           └──────────┘
                        │ PHP (FastCGI :9000)
                        ▼
                 ┌─────────────┐   ┌─────────┐   ┌───────────┐
                 │ app (FPM)   │   │ horizon │   │ scheduler │   ← same image, different command
                 └─────────────┘   └─────────┘   └───────────┘
                        │                │             │
                        ▼                ▼             ▼
               MySQL (container or RDS)     Redis (container or ElastiCache)

  Volume "storage": uploads, logs, cache files. Shared by app, horizon, scheduler, reverb and web (read-only).
```

**What's different from local:**

| | Local (`compose.yaml`) | Production (`compose.prod.yaml`) |
| --- | --- | --- |
| Code | Mounted from your folder | Baked into the image (rebuild to change) |
| Assets | Vite dev server or `npm run build` | Built inside the image |
| `.env` | Your folder | Mounted read-only; read when containers start |
| Config / routes / views | Not cached | Cached on every start (`php artisan optimize`) |
| OPcache | Re-checks files | Never re-checks files (fast) |
| Mailpit, MySQL port, Vite | Yes | No |
| Logs | Files | Files + `docker compose logs`, rotated at 10 MB × 5 |

---

## 2. First install on a new server

Tested shape: **Ubuntu 24.04 on EC2** (t3.small or bigger). Open ports 22 (your IP only) and 80 (or only the load balancer's security group).

### 2.1 Install Docker

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER     # then log out and back in
docker compose version            # must be v2.20 or newer
```

### 2.2 Get the code

Use a **read-only deploy key** for the repository (GitHub/GitLab → Deploy keys):

```bash
ssh-keygen -t ed25519 -f ~/.ssh/deploy_key -N ""     # add ~/.ssh/deploy_key.pub to the repo as a deploy key
printf 'Host github.com\n  IdentityFile ~/.ssh/deploy_key\n' >> ~/.ssh/config
git clone git@github.com:your-org/client-project.git /srv/app
cd /srv/app
```

### 2.3 Settings

```bash
cp .env.production.example .env
nano .env                         # fill in every CHANGE_ME
```

Create the app key and paste it into `APP_KEY=` in `.env`:

```bash
docker compose -f compose.prod.yaml build app
docker compose -f compose.prod.yaml run --rm --no-deps app php artisan key:generate --show
```

> **Never change `APP_KEY` later.** It encrypts 2FA secrets, sessions and encrypted settings. A new key = every 2FA user locked out ("The payload is invalid").

Random values for Reverb:

```bash
openssl rand -hex 16    # REVERB_APP_KEY
openssl rand -hex 32    # REVERB_APP_SECRET
```

### 2.4 Start, install and create the first admin

```bash
chmod +x deploy.sh
./deploy.sh                                                   # builds, migrates, starts, checks /up

docker compose -f compose.prod.yaml exec app php artisan starter:install --no-demo \
  --email=you@company.com --password='A-Strong#Passw0rd'      # roles, permissions, menus, settings, first admin
```

Leave out `--no-demo` if you want the placeholder website pages to edit.

Open `http://SERVER-IP/admin`, sign in, then do **section 11 (launch checklist)**.

---

## 3. Every update

On your machine: commit and push (to `main`, or the branch the server uses). On the server:

```bash
cd /srv/app
./deploy.sh
```

What it does, in order:

1. `git pull` (fast-forward only: it stops if the server has local changes)
2. Builds both images and tags them with the commit, e.g. `laravel13-app:3f2a9c1`
3. `php artisan migrate --force` and `module:sync` (in a one-off container)
4. Restarts every container with the new images and waits until they're healthy
5. Checks `http://127.0.0.1/up`. **If it fails, it switches back to the previous images automatically**
6. Keeps the last 3 versions, deletes older images

The website is unavailable for a few seconds while containers restart. For true zero downtime, run two or more servers behind a load balancer (section 8).

| Command | When |
| --- | --- |
| `./deploy.sh` | Normal update |
| `./deploy.sh --down` | Big migrations (e.g. changing a large table): visitors see the maintenance page while migrations run |
| `./deploy.sh rollback` | The new version has a bug: back to the previous images in ~20 seconds. **The database is not rolled back**, so write migrations that the previous code can still live with (add columns; drop them in a later release) |
| `./deploy.sh restart` | After editing `.env` |
| `./deploy.sh status` | Containers, current version, `/up` and `health:check` |

`DEPLOY_BRANCH=release ./deploy.sh` deploys another branch. `.deploy/history` lists every deploy.

---

## 4. Domain and HTTPS

Containers speak plain HTTP on `WEB_PORT` (80). TLS goes in front. Pick one:

| Option | Use when | Steps |
| --- | --- | --- |
| **AWS Application Load Balancer** (recommended on AWS) | One or more EC2 servers | ACM certificate for the domain → ALB with HTTPS :443 listener (+ :80 redirect to 443) → target group HTTP :80, **health check path `/up`** → DNS CNAME/alias to the ALB. Set the ALB **idle timeout to 120 s** for websockets |
| **Cloudflare** | Single server, quick | DNS record proxied (orange cloud), SSL mode **Full**. Allow port 80 only from Cloudflare IPs |
| **Caddy on the server** | Single server, no ALB | Set `WEB_PORT=8080` in `.env`, install Caddy, `/etc/caddy/Caddyfile`: `example.com { reverse_proxy 127.0.0.1:8080 }`. Caddy gets the certificate itself |

Then in `.env`:

```env
APP_URL=https://example.com
SESSION_SECURE_COOKIE=true
REVERB_HOST=example.com
REVERB_PORT=443
REVERB_SCHEME=https
```

and `./deploy.sh restart`. Laravel trusts the proxy's `X-Forwarded-Proto` header (`trustProxies` in `bootstrap/app.php`), so links and redirects use https.

**Websockets (live notifications)** go through the same domain: nginx forwards `/app/...` and `/apps/...` to the `reverb` container. No extra port to open. The admin panel reads the Reverb settings from the page (`<meta name="reverb">`), so the same image works on any domain.

---

## 5. Database and Redis

| | On the same server | Managed (AWS) |
| --- | --- | --- |
| `.env` | `COMPOSE_PROFILES=mysql,redis`, `DB_HOST=mysql`, `REDIS_HOST=redis`, set `DB_PASSWORD` and `DB_ROOT_PASSWORD` | Remove them from `COMPOSE_PROFILES`; `DB_HOST=xxx.rds.amazonaws.com`, `REDIS_HOST=xxx.cache.amazonaws.com` |
| Good for | Small sites, staging | Anything that matters, and every multi-server setup |
| Backups | `backup:run` (section 9) | RDS automated snapshots **plus** `backup:run` |
| Data lives in | Docker volume `laravel13_mysql-data` | AWS |

Mixing is fine, e.g. RDS + Redis container: `COMPOSE_PROFILES=redis`.

RDS: MySQL 8.4, same VPC, security group allowing 3306 from the app servers only.

---

## 6. Health checks

| Check | Used by | Fails when |
| --- | --- | --- |
| `GET /up` | Load balancer, the `web` container, `deploy.sh` | **Database, cache or storage** is broken (answers 500) |
| `php artisan health:check` | You, `./deploy.sh status`, monitoring scripts | Same critical checks → exit code 1. Also **warns** about Redis, queue workers (Horizon), scheduler and disk space (< 10 % free) |

Queue workers and the scheduler only warn, on purpose: a stopped Horizon must not take the website out of the load balancer.

```bash
docker compose -f compose.prod.yaml exec app php artisan health:check
```

The checks are in `app/Core/Health/HealthCheck.php`. Add your own (e.g. a payment gateway) there.

The **dashboard's System status card** shows the same picture, plus open errors and failed jobs.

---

## 7. Errors and failed jobs

### 7.1 Built-in error log (always on)

**Admin → System → Monitoring → Error log.**

- Every server error is saved **once**, with a counter: "PDOException in OrderController.php:42, 37 times".
- Each row shows the message, file and line, the stack trace, the URL, who hit it (staff, customer, API, console / job) and the IP address.
- **Alerts** the first time an error appears, and again if one you marked as fixed comes back (at most once an hour per error):
  - bell notification for staff with the `monitoring.view` permission
  - email to **Settings → Monitoring → Email new errors to**
- **Mark as fixed** when you've deployed a fix. Fixed errors are deleted with **Delete fixed errors**, and everything is pruned after **Keep errors for (days)** (default 30).
- Visitors' 404s, validation errors and failed sign-ins are not errors, so they're never listed.

Saving is wrapped in try/catch: if the database itself is down, errors still go to the log files.

### 7.2 Failed jobs

**Admin → System → Monitoring → Failed jobs**: emails, notifications or backups that failed after every try. Open one to see the error and its data, fix the cause (often the mail settings), then **Retry** (or **Retry all**). Same as `queue:retry` / `queue:forget` without a terminal. Deleted automatically after 7 days.

### 7.3 Log files

**Admin → System → Monitoring → Log files** (`/admin/logs`): the daily `laravel-*.log` files in the `storage` volume, with level filters, search across files and readable stack traces. Permissions: `logs.view`, `logs.download`, `logs.delete`. In production, `LOG_STACK=daily,stderr` writes to both the files (viewer) and `docker compose logs`. Files older than `LOG_DAILY_DAYS` (14) are deleted by Laravel.

### 7.4 Sentry (optional, as well)

Useful for bigger projects: release tracking, performance and grouping across several apps.

```bash
composer require sentry/sentry-laravel        # on your machine, then commit composer.json + composer.lock
```

In the server `.env`:

```env
SENTRY_LARAVEL_DSN=https://xxxx@o0.ingest.sentry.io/0
SENTRY_TRACES_SAMPLE_RATE=0.1
```

`./deploy.sh`. Nothing else: `bootstrap/app.php` hands errors to Sentry automatically when the package is installed. The built-in error log keeps working alongside it.

### 7.5 Permissions

`monitoring.view` (see errors and failed jobs, get alerts), `monitoring.manage` (mark fixed, delete, retry), and `logs.view` / `logs.download` / `logs.delete` (log files). Only super-admin has them after install.

---

## 8. Several servers, Auto Scaling, golden AMI

Once you run more than one app server, three things must be shared:

| What | How |
| --- | --- |
| Database, Redis | RDS + ElastiCache (`COMPOSE_PROFILES=` empty) |
| Uploaded files | S3: `composer require league/flysystem-aws-s3-v3`, then `.env`: `FILESYSTEM_DISK=s3`, `MEDIA_DISK=s3`, `AWS_*` values. (The `storage` volume is per server) |
| Maintenance mode | Already shared: `APP_MAINTENANCE_DRIVER=cache` + `APP_MAINTENANCE_STORE=redis` |

The scheduler runs on every server, but the jobs that matter use `->onOneServer()`, which takes a Redis lock, so they run once.

### Build once, run anywhere (ECR)

Build the images in CI (or on one build server) and let every instance pull them, instead of building on each server:

```bash
# build server / CI
aws ecr get-login-password --region ap-south-1 | docker login --username AWS --password-stdin 123456789012.dkr.ecr.ap-south-1.amazonaws.com
export APP_IMAGE=123456789012.dkr.ecr.ap-south-1.amazonaws.com/client-app
export APP_TAG=$(git rev-parse --short HEAD)
docker compose -f compose.prod.yaml build app web
docker compose -f compose.prod.yaml push app web
docker compose -f compose.prod.yaml run --rm app php artisan migrate --force    # once per release, from one place
```

On each instance, the EC2 **user data**, or your golden-AMI boot script, with `.env` from SSM Parameter Store or Secrets Manager:

```bash
cd /srv/app
aws ecr get-login-password --region ap-south-1 | docker login --username AWS --password-stdin 123456789012.dkr.ecr.ap-south-1.amazonaws.com
APP_IMAGE=123456789012.dkr.ecr.ap-south-1.amazonaws.com/client-app APP_TAG=3f2a9c1 \
  docker compose -f compose.prod.yaml up -d --wait
```

The instance needs only `compose.prod.yaml`, `.env` and Docker: no source code. A new release is a new `APP_TAG` plus an **ASG instance refresh**; the target group's `/up` check keeps traffic away from instances that aren't ready, which gives zero downtime.

---

## 9. Backups and restore

### 9.1 What is backed up

`spatie/laravel-backup`, scheduled in `routes/console.php`:

| Time | Command |
| --- | --- |
| 01:30 | `backup:clean`: keeps every backup for 7 days, then one a day for 16 days, one a week for 8 weeks, one a month for 4 months (`config/backup.php`) |
| 02:00 | `backup:run`: database dump + project files including uploads |
| 09:00 | `backup:monitor`: email to `BACKUP_NOTIFY_EMAIL` if the newest backup is too old or too big |

Each backup is a zip, password-protected with `BACKUP_ARCHIVE_PASSWORD`. Manage them under **Admin → System → Backups & system**: run now, download, delete.

**Keep a copy off the server.** By default backups are stored in the `storage` volume, on the same disk as the site. For real projects:

```bash
composer require league/flysystem-aws-s3-v3    # commit composer.json + composer.lock
```

```env
BACKUP_DISKS=local,s3
AWS_ACCESS_KEY_ID=...        # IAM user/role with access to this bucket only
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=ap-south-1
AWS_BUCKET=client-app-backups
```

Turn on S3 bucket versioning and a lifecycle rule (e.g. delete after 90 days).

### 9.2 Restore the database

```bash
cd /srv/app
# 1. Get the zip: download it from Admin → Backups, or from S3:
aws s3 cp s3://client-app-backups/Your-Business/2026-11-19-02-00-02.zip backup.zip

# 2. Unzip (asks for BACKUP_ARCHIVE_PASSWORD)
sudo apt-get install -y unzip && unzip backup.zip -d restore
ls restore/db-dumps/                     # mysql-starter.sql

# 3. Safety net: back up what's there now, and stop writes
docker compose -f compose.prod.yaml exec app php artisan backup:run --only-db
docker compose -f compose.prod.yaml exec app php artisan down

# 4a. Bundled MySQL container
docker compose -f compose.prod.yaml exec -T mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < restore/db-dumps/mysql-starter.sql

# 4b. RDS (the app image has the mysql client)
docker compose -f compose.prod.yaml run --rm -T app sh -c 'mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE"' < restore/db-dumps/mysql-starter.sql

# 5. Bring it back
docker compose -f compose.prod.yaml exec app php artisan migrate --force   # if the backup is older than the code
docker compose -f compose.prod.yaml exec app php artisan optimize:clear
docker compose -f compose.prod.yaml exec app php artisan up
rm -rf restore backup.zip
```

> `4b` uses `run --rm` with the `.env` mounted, so `$DB_HOST` and the other values come from your `.env`. If the shell can't see them, type the values in.

### 9.3 Restore uploaded files

```bash
# Uploads are in the zip under .../storage/app/public (full server path inside the zip)
SRC=$(find restore -type d -path '*/storage/app/public' | head -1); echo "$SRC"
docker run --rm -v laravel13_storage:/storage -v "$PWD/$SRC":/restore:ro alpine \
  sh -c 'cp -a /restore/. /storage/app/public/ && chown -R 33:33 /storage/app/public'
```

(`laravel13` is `COMPOSE_PROJECT_NAME`; `docker volume ls` shows the exact name. User 33 = www-data.)

### 9.4 Test it

A backup you have never restored is a hope, not a backup. Once a month, restore the newest backup into a **staging** server or locally (`compose.yaml` + step 4a) and sign in.

---

## 10. Logs and day-to-day commands

Short alias on the server: `echo "alias dc='docker compose -f compose.prod.yaml'" >> ~/.bashrc`

| Task | Command |
| --- | --- |
| Live logs | `dc logs -f app web horizon` |
| Laravel log file | `dc exec app tail -f storage/logs/laravel-$(date +%F).log` |
| Artisan | `dc exec app php artisan <command>` |
| Tinker | `dc exec app php artisan tinker` |
| Queue status | `dc exec app php artisan horizon:status`, or `/admin/horizon` |
| Restart workers only | `dc restart horizon` |
| Maintenance mode | `dc exec app php artisan down` / `up` |
| Disk usage | `docker system df`, `df -h` |
| Clean old images | `docker image prune -a` (deploy.sh does this) |
| Shell inside | `dc exec app sh` |

---

## 11. Launch checklist

**`.env`**
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`
- [ ] `APP_KEY` set **and saved somewhere safe** (password manager)
- [ ] `SESSION_SECURE_COOKIE=true`, `LOG_LEVEL=warning`
- [ ] Real mail (SES/SMTP); send a test from **Settings → Mail**

**Admin panel**
- [ ] **Settings → Security:** enforce 2FA for admins
- [ ] **Settings → Monitoring:** error email set
- [ ] **Settings → Customers:** off if the project has no customer accounts
- [ ] **Settings → General / Branding / SEO:** site name, logo, brand colour, contact details, meta tags

**Backups and checks**
- [ ] `BACKUP_DISKS=local,s3` and a backup run from **Admin → Backups** that shows up in S3
- [ ] `./deploy.sh status`: all OK; Horizon and scheduler running

**Infrastructure**
- [ ] HTTPS works, and http redirects to https
- [ ] Load balancer health check on `/up`
- [ ] Port 22 limited to your IP or VPN; MySQL / Redis not reachable from the internet
- [ ] Domain email: SPF, DKIM (SES) and DMARC records

---

## 12. Troubleshooting

| Problem | Fix |
| --- | --- |
| `deploy.sh`: "Not possible to fast-forward" | Someone edited files on the server. `git status`, then `git stash` or `git reset --hard origin/main` |
| Health check failed, rolled back | `dc logs --tail=100 app web`. Usually a wrong `.env` value (DB host or password) or a migration error |
| 502 Bad Gateway | `app` is not running: `dc ps`, `dc logs app` |
| Changed `.env` but nothing changed | Config is cached at start: `./deploy.sh restart` |
| CSS/JS missing (unstyled pages) | The `web` image is older than `app`: `./deploy.sh` builds both together; don't build only one |
| Uploaded images 404 | Check the `storage` volume is mounted on `web` (`dc exec web ls /var/www/html/storage/app/public`) |
| Live notifications don't arrive | `REVERB_HOST`/`PORT`/`SCHEME` must be the public domain, 443, https; ALB idle timeout ≥ 120 s; `dc logs reverb` |
| Emails stuck | **Monitoring → Failed jobs**; `dc logs horizon`; check **Settings → Mail** |
| "The payload is invalid" after deploy | `APP_KEY` changed. Put the old key back |
| Disk full | `docker system prune`, `docker image prune -a`, check `storage/logs`, check backups are going to S3 |
| Enabled/disabled a module in the admin panel and it came back after deploy | Module on/off is stored in `module.json` inside the image. Change it locally (`php artisan module:disable …`), commit, deploy |
| `required: false` / unknown option in compose | Docker Compose older than 2.20: `sudo apt-get install docker-compose-plugin` |
