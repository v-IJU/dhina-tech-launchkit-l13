# Developer Guide

This guide explains how the whole project works: where everything lives, how each feature is built, and how to change it safely. Read it once from top to bottom, then use it as a reference.

> **Companion docs**
> - `README.md`: what the project is and how to install it.
> - `README_PROJECT_CMD.md`: every command (Docker, artisan, modules, migrations, deploy).
> - **This file**: how the code works and how to change it.

Examples use the `art` shortcut: `alias art="docker compose exec app php artisan"` (see `README_PROJECT_CMD.md` section 1).

---

## Contents

1. [Where is…? (quick index)](#1-where-is-quick-index)
2. [Tech stack](#2-tech-stack)
3. [Folder structure](#3-folder-structure)
4. [How a request travels through the app](#4-how-a-request-travels-through-the-app)
5. [The module system](#5-the-module-system)
6. [Users, roles and permissions (inch by inch)](#6-users-roles-and-permissions-inch-by-inch)
7. [Sidebar and website menus](#7-sidebar-and-website-menus)
8. [Login and security](#8-login-and-security)
9. [Settings](#9-settings)
10. [Admin UI: layout, components, forms](#10-admin-ui-layout-components-forms)
11. [Data tables (list pages)](#11-data-tables-list-pages)
12. [JavaScript and CSS: how to add and change](#12-javascript-and-css-how-to-add-and-change)
13. [Realtime with Reverb (inch by inch)](#13-realtime-with-reverb-inch-by-inch)
14. [Queues and Horizon](#14-queues-and-horizon)
15. [Scheduler](#15-scheduler)
16. [Notifications and mail](#16-notifications-and-mail)
17. [Website CMS: pages, blocks, blog, forms, SEO](#17-website-cms-pages-blocks-blog-forms-seo)
18. [Media library, activity log, backups](#18-media-library-activity-log-backups)
19. [Docker services](#19-docker-services)
20. [Tests and code quality](#20-tests-and-code-quality)
21. [Rules for working on this project](#21-rules-for-working-on-this-project)
22. [Recipes: common tasks start to finish](#22-recipes-common-tasks-start-to-finish)
23. [Trash and restore](#23-trash-and-restore)
24. [Import & export (Laravel Excel)](#24-import--export-laravel-excel) · [Debugbar](#24b-laravel-debugbar-local-only) · [Is the queue running?](#24c-is-the-queue-running-horizon-workers-logs)
25. [REST API (Sanctum)](#25-rest-api-sanctum)
26. [Multi-language website](#26-multi-language-website)
27. [Sign-in history and new-device alerts](#27-sign-in-history-and-new-device-alerts)
28. [Customer accounts (website + app)](#28-customer-accounts-website--app)
29. [Monitoring: errors, failed jobs, health checks](#29-monitoring-errors-failed-jobs-health-checks)

---

## 1. Where is…? (quick index)

| I want to change…                               | File                                                                         |
| ----------------------------------------------- | ---------------------------------------------------------------------------- |
| Admin URL (`/admin`), per-page, menu groups     | `config/starter.php`                                                         |
| Middleware groups, aliases, error pages         | `bootstrap/app.php`                                                          |
| Super-admin bypass, password rules, view data   | `app/Providers/AppServiceProvider.php`                                       |
| How modules are loaded                          | `app/Core/Modules/ModuleServiceProvider.php`                                 |
| Sidebar building                                | `app/Core/Menus/MenuService.php`                                             |
| Permission sync from `module.json`              | `app/Core/Permissions/PermissionSync.php`                                    |
| List pages (search/sort/filter/export)          | `app/Core/DataTable/DataTable.php`                                           |
| Module generator (`make:module`)                | `app/Core/Console/MakeModuleCommand.php` + `stubs/module/*.stub`             |
| Admin layout (sidebar, top bar, bell)           | `resources/views/layouts/admin.blade.php`                                    |
| Website layout (header, footer)                 | `resources/views/layouts/web.blade.php`                                      |
| Admin components (`<x-admin.*>`)                | `resources/views/components/admin/`                                          |
| Admin CSS / JS                                  | `resources/admin/admin.css`, `resources/admin/admin.js`, `resources/admin/js/*.js` |
| Website CSS / JS                                | `resources/web/web.css`, `resources/web/web.js`                              |
| Realtime (Reverb) in the browser                | `resources/admin/js/realtime.js`                                             |
| Who can listen to which realtime channel        | `routes/channels.php`                                                        |
| Scheduled tasks                                 | `routes/console.php`                                                         |
| Settings page fields                            | `app/Modules/Settings/Support/SettingDefinitions.php`                        |
| Page builder blocks                             | `app/Modules/Pages/Support/BlockRegistry.php` + `app/Modules/Pages/Views/web/blocks/` |
| Default roles and their permissions             | `database/seeders/DatabaseSeeder.php`                                        |
| Security headers / CSP                          | `app/Http/Middleware/SecurityHeaders.php`                                    |
| Docker                                          | `compose.yaml`, `docker/php/Dockerfile`, `docker/nginx/default.conf`         |
| Trash (soft delete) on list pages               | `DataTable::trash()` + module `restore` / `forceDelete` methods (section 23) |
| Import & export (Laravel Excel)                 | `app/Core/Import/`, `app/Core/Export/`, each module's `Imports/` `Exports/` (section 24) |
| API auth, token rules                           | `routes/api.php`, `app/Http/Controllers/Api/AuthController.php`, `app/Http/Middleware/ApiAccess.php` |
| A module's API                                  | `app/Modules/X/Routes/api.php`, `Controllers/Api/`, `Resources/`             |
| Website languages                               | `config/starter.php` → `locales`, `app/Core/Localization/`, `lang/*.json`    |
| Sign-in history                                 | `app/Core/Security/LoginRecorder.php`, `app/Models/LoginEvent.php`           |

---

## 2. Tech stack

| Layer          | What we use                                                                   |
| -------------- | ----------------------------------------------------------------------------- |
| Language       | PHP 8.4                                                                       |
| Framework      | Laravel 13                                                                    |
| Admin UI       | Bootstrap 5 + Bootstrap Icons, plain Blade (no Livewire, no Filament)         |
| Website UI     | Tailwind CSS 4                                                                |
| JS             | Plain JavaScript modules, built by Vite. No jQuery, no Vue/React              |
| Auth           | Laravel Fortify (login, password reset, 2FA). Registration is off             |
| Permissions    | spatie/laravel-permission                                                     |
| Activity log   | spatie/laravel-activitylog                                                    |
| Uploads        | spatie/laravel-medialibrary (Media library), Laravel Storage (module images)  |
| Backups        | spatie/laravel-backup                                                         |
| HTML cleaning  | mews/purifier (rich text)                                                     |
| Realtime       | Laravel Reverb (WebSockets) + Laravel Echo in the browser                     |
| Queues         | Redis + Laravel Horizon                                                       |
| Database       | MySQL 8.4 (tests use SQLite in memory)                                        |
| Mail (local)   | Mailpit                                                                       |
| Small JS libs  | Chart.js (dashboard), SortableJS (menu builder), Trix (rich text editor)      |

---

## 3. Folder structure

Only the folders you will touch are shown.

```
├── app/
│   ├── Actions/Fortify/            Password reset / update rules used by Fortify
│   ├── Core/                       ★ The "framework" of this starter. Shared by all modules.
│   │   ├── Console/                make:module, module:list, module:enable/disable, module:sync, starter:install
│   │   │   └── Generator/Field.php    Field types for make:module (string, decimal, image, foreignId…)
│   │   ├── DataTable/DataTable.php Server-side list tables (search, sort, filter, export, bulk)
│   │   ├── Http/StoresUploads.php  Trait: save/replace/delete uploaded images in controllers
│   │   ├── Menus/                  MenuItem model + MenuService (builds the sidebar and website menus)
│   │   ├── Modules/                Module, ModuleManager (reads module.json), ModuleServiceProvider (loads them)
│   │   ├── Permissions/            PermissionSync (creates permissions from module.json)
│   │   ├── Settings/               SettingsRepository (settings table, cached)
│   │   └── helpers.php             setting(), module_enabled(), admin_path(), initials(), avatar_color()
│   ├── Http/Middleware/            EnsureUserIsActive, ForcePasswordChange, EnforceTwoFactor,
│   │                               SecurityHeaders, WebsiteMaintenance
│   ├── Models/                     User, Role (extends Spatie's Role with display_name, color, description)
│   ├── Modules/                    ★ Every feature. One folder = one module. (see section 5)
│   │   ├── Dashboard/  Users/  Roles/  Menus/  Settings/  Activity/  Modules/  Backups/   ← admin/system
│   │   ├── Media/  Blog/  Pages/  Inbox/                                                ← content
│   │   └── Sliders/  Services/  Testimonials/  Faqs/                                    ← generated with make:module
│   ├── Notifications/              AdminAlert (bell + realtime), WelcomeUser (email)
│   └── Providers/                  AppServiceProvider, FortifyServiceProvider, HorizonServiceProvider
│
├── bootstrap/
│   ├── app.php                     Middleware groups/aliases, command folders, exception rendering
│   └── providers.php               Which service providers load
│
├── config/
│   ├── starter.php                 ★ Settings for this starter (admin path, groups, per page, CSP…)
│   ├── permission.php              Spatie config (points role model to App\Models\Role)
│   └── …                           Normal Laravel config files
│
├── database/
│   ├── migrations/                 Core tables: users, settings, menu_items, permissions, jobs…
│   └── seeders/                    DatabaseSeeder (roles, settings, menus), DemoContentSeeder (website demo)
│
├── docker/                         Dockerfile, php.ini, nginx config, entrypoint
├── resources/
│   ├── admin/                      ★ Admin panel assets (Bootstrap)
│   │   ├── admin.css               Theme + all admin styles
│   │   ├── admin.js                Entry file: imports every js/*.js
│   │   └── js/                     One file per feature (see section 12)
│   ├── web/                        ★ Website assets (Tailwind)
│   │   ├── web.css
│   │   └── web.js
│   └── views/
│       ├── layouts/                admin, auth (login pages), web (website)
│       ├── components/admin/       <x-admin.*> components (section 10)
│       ├── auth/                   Login, forgot/reset password, 2FA challenge
│       ├── errors/                 403, 404, 419, 500, 503
│       └── web/maintenance.blade.php
│
├── routes/
│   ├── web.php                     Almost empty: routes live inside modules
│   ├── channels.php                Realtime channel permissions (Reverb)
│   └── console.php                 Scheduled tasks
│
├── stubs/module/                   Templates used by make:module
├── tests/Feature/                  Feature tests (PHPUnit)
├── compose.yaml                    Docker services
├── vite.config.js                  Asset entry points
├── phpstan.neon / pint.json        Static analysis / code style
└── README*.md                      Docs
```

### Inside one module

```
app/Modules/Blog/
├── module.json                    ★ Name, slug, permissions, sidebar menu
├── Models/Post.php, Category.php
├── Controllers/Admin/PostController.php     Admin pages
├── Controllers/Web/BlogController.php       Public website pages
├── Requests/PostRequest.php                 Validation
├── Routes/admin.php                         Loaded at /admin/…, names admin.*
├── Routes/web.php                           Public routes
├── Views/admin/…                            view('blog::admin.posts.index')
├── Views/web/…                              view('blog::web.show')
├── Database/Migrations/                     Run by `art migrate`
├── Database/Seeders/
├── Console/PublishScheduledPosts.php        Artisan commands (auto-registered)
└── Providers/                               Optional service provider (auto-registered)
```

---

## 4. How a request travels through the app

### Admin page: `GET /admin/blog/posts`

```
Browser
  │
  ▼
nginx (laravel13-nginx)  ──▶  PHP-FPM (laravel13-app)  ──▶  public/index.php
  │
  ▼
bootstrap/app.php
  • service providers boot (bootstrap/providers.php)
      AppServiceProvider       → Gate::before (super-admin), password rules, settings → config
      ModuleServiceProvider    → for every ENABLED module: views, routes, migrations
  │
  ▼
Route found: app/Modules/Blog/Routes/admin.php
  registered by ModuleServiceProvider inside:
      Route::middleware('admin')->prefix('admin')->name('admin.')
  │
  ▼
Middleware group "admin"  (bootstrap/app.php)
  web            → session, cookies, CSRF, SecurityHeaders (CSP)
  auth           → logged in? otherwise redirect to /login
  active         → EnsureUserIsActive: account still active?
  force.password → ForcePasswordChange: must change password first?
  enforce.2fa    → EnforceTwoFactor: 2FA required but not set up?
  │
  ▼
Controller middleware  (PostController::middleware())
  permission:blog.view → user has it? otherwise 403
  │
  ▼
PostController@index → DataTable → view('blog::admin.posts.index')
  │
  ▼
Layout resources/views/layouts/admin.blade.php
  View composer (AppServiceProvider) adds $sidebar (MenuService) and $notifications (bell)
  │
  ▼
HTML back to the browser → loads resources/admin/admin.js (Vite) → JS features start
```

### Website page: `GET /about-us`

```
Routes from module Routes/web.php files, middleware: web + website
  website → WebsiteMaintenance: if maintenance mode is on and visitor is not logged in → "back soon" page
Pages module's catch-all route  /{slug}  (registered LAST because Pages has "order": 999)
  → PageController@show → finds page "about-us" → renders each block
Layout resources/views/layouts/web.blade.php (header/footer menus from MenuService)
```

---

## 5. The module system

A **module** is a folder in `app/Modules`. The starter finds it, reads its `module.json`, and wires it in. You never register routes or views by hand.

### What `ModuleServiceProvider` does for each enabled module

`app/Core/Modules/ModuleServiceProvider.php`

| Module has…                         | Becomes…                                                                                  |
| ----------------------------------- | ----------------------------------------------------------------------------------------- |
| `Routes/admin.php`                  | Routes under `/admin`, names prefixed `admin.`, middleware group `admin`                  |
| `Routes/web.php`                    | Public routes, middleware `web` + `website`                                               |
| `Views/`                            | View namespace = module **slug**: `view('blog::admin.posts.index')`                       |
| `Database/Migrations/`              | Included in `art migrate` (for **all** modules, even disabled ones)                       |
| `Providers/*ServiceProvider.php`    | Registered automatically                                                                  |
| `Console/*.php`                     | Artisan commands registered automatically (`bootstrap/app.php` → `withCommands`)           |

### `module.json`

```json
{
    "name": "Blog",
    "slug": "blog",
    "description": "Posts and categories.",
    "version": "1.0.0",
    "enabled": true,
    "core": false,
    "order": 30,
    "permissions": [
        { "key": "blog", "label": "Blog posts", "actions": ["view", "create", "edit", "delete", "publish", "export"] }
    ],
    "menu": [
        { "key": "blog", "group": "Content", "label": "Blog", "icon": "bi-journal-text", "permission": "blog.view",
          "children": [
            { "key": "blog.posts", "label": "All posts", "route": "admin.blog.posts.index", "permission": "blog.view" }
          ] }
    ]
}
```

- `slug`: the module's id, used as the view namespace and the folder name for uploads.
- `enabled`: `false` hides the module's routes, menus and role-matrix rows. Its data stays.
- `order`: load order. Lower loads first.
- `permissions`: turned into database permissions by `art module:sync`.
- `menu`: sidebar items.

Full key reference: `README_PROJECT_CMD.md` section 7.

### `ModuleManager` (`app/Core/Modules/ModuleManager.php`)

Use it when your code needs to know about modules:

```php
app(ModuleManager::class)->all();             // every module
app(ModuleManager::class)->enabled();         // enabled only
app(ModuleManager::class)->find('blog');      // one module
module_enabled('blog');                       // helper: true/false
```

```blade
@if (module_enabled('blog')) … @endif
```

### Required modules

`config/starter.php` → `locked_modules`: `dashboard`, `users`, `roles`, `menus`, `modules`, `settings`. These can't be disabled.

### Making a module

```bash
art make:module Product --fields="name:string,price:decimal,is_active:boolean" --group=Catalog --icon=bi-box --migrate
```

The full guide (fields, options, editing an existing module, removing one) is in `README_PROJECT_CMD.md` sections 4–8.

---

## 6. Users, roles and permissions (inch by inch)

### 6.1 The pieces

| Piece              | Where                                                    | What it is                                                     |
| ------------------ | -------------------------------------------------------- | -------------------------------------------------------------- |
| **Permission**     | table `permissions`                                      | One ability, named `resource.action`: `blog.publish`           |
| **Role**           | table `roles` (`App\Models\Role`)                        | A named group of permissions: Admin, Editor                    |
| Role ↔ permission  | table `role_has_permissions`                             | Which permissions a role has                                   |
| User ↔ role        | table `model_has_roles`                                  | Which role a user has                                          |
| User ↔ permission  | table `model_has_permissions`                            | Direct permissions (rarely used; prefer roles)                 |
| Spatie package     | `spatie/laravel-permission`                              | Provides the tables, `HasRoles` trait, `permission:` middleware |
| `User` model       | `app/Models/User.php` → `use HasRoles`                   | Gives `$user->can()`, `assignRole()`, `hasRole()`…             |
| Super-admin bypass | `AppServiceProvider` → `Gate::before(...)`               | super-admin passes every check, even ones that don't exist yet |

### 6.2 Permission naming

```
blog.publish
 │     └── action   (view, create, edit, delete, or anything else)
 └──────── resource (the "key" in module.json)
```

- **Standard actions**: `view`, `create`, `edit`, `delete` (`config/starter.php` → `standard_actions`). These are the columns of the role matrix.
- **Special permissions**: every other action, such as `blog.publish`, `users.impersonate` or `*.export`. These appear in a separate **Special permissions** box on the role screen.

The grouping is done in `app/Modules/Roles/Support/PermissionCatalog.php`.

### 6.3 Default roles

Created by `database/seeders/DatabaseSeeder.php`:

| Role          | Gets                                                                                           |
| ------------- | ---------------------------------------------------------------------------------------------- |
| `super-admin` | Everything (through `Gate::before`, not by ticking boxes)                                      |
| `admin`       | Everything except modules.*, deleting roles, creating/deleting permissions, deleting backups/activity |
| `editor`      | Dashboard, blog, categories, media (no delete), pages view/edit, Website CMS modules (no delete), inbox view |

`config/starter.php` → `system_roles` lists the roles that cannot be deleted.

> The seeder grants permissions **only when it creates a role**. Permissions added later (new modules, new actions) must be ticked in **Roles → Edit**, or given in code (6.7).

### 6.4 How a permission gets into the database

```
module.json  "actions": ["view", "create", "edit", "delete", "refund"]
     │
     │  art module:sync   (also runs at the end of make:module, starter:install, and the
     │                     "Sync permissions & menus" button on System → Modules)
     ▼
app/Core/Permissions/PermissionSync.php
     → creates missing permissions: orders.view, orders.create, … orders.refund
     → NEVER deletes any permission
```

### 6.5 Adding a special permission, end to end

Example: allow some roles to **refund orders**.

**Step 1. Declare it** in `app/Modules/Orders/module.json`:

```json
{ "key": "orders", "label": "Orders", "actions": ["view", "create", "edit", "delete", "export", "refund"] }
```

**Step 2. Create it in the database:**

```bash
art module:sync
```

**Step 3. Give it to a role:** in **Access control → Roles → Edit "Admin" → Special permissions**, tick `orders.refund` and save.

**Step 4. Protect the route/controller** (this is what actually blocks it):

```php
// app/Modules/Orders/Controllers/Admin/OrderController.php
public static function middleware(): array
{
    return [
        new Middleware('permission:orders.view', only: ['index', 'show']),
        new Middleware('permission:orders.edit', only: ['edit', 'update']),
        new Middleware('permission:orders.refund', only: ['refund']),
    ];
}

public function refund(Order $order): RedirectResponse
{
    $order->update(['order_status' => 'refunded']);

    return back()->with('success', 'Order refunded.');
}
```

```php
// app/Modules/Orders/Routes/admin.php
Route::post('orders/{order}/refund', [OrderController::class, 'refund'])->name('orders.refund');
```

**Step 5. Hide the button** from users who can't use it:

```blade
@can('orders.refund')
    <form method="POST" action="{{ route('admin.orders.refund', $order) }}" data-confirm="Refund this order?">
        @csrf
        <button class="btn btn-sm btn-outline-danger">Refund</button>
    </form>
@endcan
```

**Step 6 (optional): add it as a bulk action** on the list (section 11):

```php
->bulk(route('admin.orders.bulk'), ['refund' => 'orders.refund', 'delete' => 'orders.delete'])
```

> `@can` only hides the button. The middleware is what blocks a direct request (Postman, curl). Always do both.

### 6.6 Creating a role

**In the admin panel** (normal way): **Access control → Roles → Add role**:
1. **Name**: the machine name used in code, e.g. `manager` (lowercase, no spaces).
2. **Display name** (`Store manager`), **colour** and **description**: shown in the UI.
3. Tick permissions in the matrix (rows = modules, columns = view/create/edit/delete, plus the **Special permissions** box).
4. Save.

**In code**, for example in a seeder so every environment gets the role:

```php
use App\Models\Role;

$role = Role::findOrCreate('manager', 'web');
$role->update(['display_name' => 'Store manager', 'color' => '#0891b2', 'description' => 'Runs day-to-day orders.']);
$role->syncPermissions(['dashboard.view', 'orders.view', 'orders.edit', 'orders.refund', 'orders.export']);
```

Put it in `database/seeders/DatabaseSeeder.php` (roles list) or a module seeder, then run `art db:seed`.

**Sidebar preview:** the right-hand card **"Sidebar for this role"** shows the menu people with this role will see, and updates as you tick boxes (also when you use *Start from an existing role* or *Select all*). Clients can set up a role visually without knowing permission names. The data comes from `MenuService::adminTree()` (every menu item with the permission it needs); `resources/admin/js/role-matrix.js` applies the same show/hide rules as the real sidebar.

### 6.7 Assigning roles and permissions in code

```php
$user->assignRole('editor');                 // add a role
$user->syncRoles(['manager']);               // replace all roles (the Users form does this: one role per user)
$user->removeRole('editor');

$role->givePermissionTo('orders.refund');    // add to a role
$role->revokePermissionTo('orders.refund');

$user->givePermissionTo('orders.export');    // direct permission on one user (avoid; use roles)
```

After changing permissions **outside** the admin panel (tinker, seeders), clear Spatie's cache:

```php
app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
```

### 6.8 Checking permissions: every way

| Where                    | Code                                                                                   |
| ------------------------ | -------------------------------------------------------------------------------------- |
| Controller (recommended) | `new Middleware('permission:orders.view', only: ['index'])`                            |
| Route                    | `Route::get(...)->middleware('permission:orders.view')`                                |
| Inside a method          | `abort_unless($request->user()->can('orders.refund'), 403);`                           |
| Blade                    | `@can('orders.refund') … @endcan`, `@cannot(...)`, `@canany(['a', 'b'])`              |
| Any PHP                  | `$user->can('orders.refund')`, `$user->hasRole('admin')`, `$user->isSuperAdmin()`     |
| Sidebar item             | `"permission": "orders.view"` in `module.json`                                         |
| Data table               | `->export(..., 'orders.export')`, `->bulk(url, ['delete' => 'orders.delete'])`         |
| Realtime channel         | `routes/channels.php`: `fn ($user) => $user->can('orders.view')`                       |

**Check permissions, not roles.** `@can('orders.refund')` keeps working when someone creates a new role. `@role('admin')` doesn't.

### 6.9 Custom permissions from the admin panel

**Access control → Permissions → Add permission** creates a permission in that database only. Use it to try something quickly, then move it into `module.json`, so that local, staging and production all have it.

### 6.10 Extra permissions for one user

**The situation:** every Editor can't manage customers, but Priya, who is also an Editor, should. Don't create a new role for one person, and don't change the Editor role (that changes every editor).

**Users → key icon (or Edit user → Extra permissions)** → `/admin/users/{id}/permissions`:

- the same grid as Roles; boxes that come from a role show a 🔒 **Role** badge and can't be changed there
- tick extra boxes for **this user only**, then save
- the right-hand card shows **their sidebar** as it will look
- the users list shows **+2 extra** next to the role badges (hover to see which)

```
what Priya can do  =  permissions of her roles  +  her extra permissions
```

Spatie stores extras in `model_has_permissions`. `$user->can()`, `@can`, the `permission:` middleware and the **sidebar** all count them automatically, with no extra code. The menu cache is keyed by each user's full permission set, so the change shows on their next page load.

**Safety rules** (`UserPermissionController`):

| Rule | Why |
| --- | --- |
| Needs `users.permissions` | Separate from `users.edit`: editing a profile ≠ granting access |
| You can only grant permissions **you have yourself** (super-admin: all) | No one can hand out more than they have |
| Extras you don't have are kept, not removed, when you save | You can't take away what you can't give |
| You can't change your own extras | No self-promotion |
| Not for super-admins | They pass every check anyway |
| Every change is in the **Activity log** (added / removed) | Who gave what, when |

In code:

```php
$user->givePermissionTo('customers.view');            // extra
$user->revokePermissionTo('customers.view');
$user->getDirectPermissions();                         // extras only
$user->getPermissionsViaRoles();                       // from roles only
$user->getAllPermissions();                            // both = what counts
```

**Taking a permission away from one user** that their role gives isn't possible: permissions only add up. Make a narrower role (e.g. "Editor (no publish)") and give that user the narrower role instead.

**When to use which:** a permission that **several people** need belongs in a role. Extras are for the one-off exception. If you find yourself giving the same extra to a third person, make a role.

### 6.11 Users module features

`app/Modules/Users/`:

| Feature                    | Where                                                                       |
| -------------------------- | --------------------------------------------------------------------------- |
| List with filters/export   | `UserController@index` (data table)                                         |
| Full user form             | `Views/admin/form.blade.php`, `Requests/UserRequest.php`                    |
| Status active/inactive/suspended | `users.status`; `EnsureUserIsActive` logs out non-active users        |
| Force password change      | `users.force_password_reset` + `ForcePasswordChange` middleware             |
| Require 2FA per user       | `users.require_two_factor` + `EnforceTwoFactor` middleware                  |
| Welcome email              | `app/Notifications/WelcomeUser.php` (checkbox on create)                    |
| Impersonate ("Sign in as") | `ImpersonationController`; needs `users.impersonate`; orange bar to return  |
| Own profile, 2FA, sessions | `ProfileController` → `/admin/profile`                                      |
| Protection rules           | You can't delete or bulk-change yourself; non-super-admins can't touch super-admins |

---

## 7. Sidebar and website menus

### 7.1 Admin sidebar

```
module.json "menu" ──art module:sync──▶ table menu_items (location = admin)
                                              │
Appearance → Menu builder (rename, icon, reorder, hide)
                                              │
                                              ▼
                        MenuService::adminSidebar($user)   (app/Core/Menus/MenuService.php)
                          • skips items of disabled modules
                          • skips items the user lacks "permission" for
                          • skips items whose route doesn't exist
                          • groups by "group", ordered by config('starter.menu_groups')
                          • cached per permission set (cleared on any menu change)
                                              │
                                              ▼
                        resources/views/layouts/admin.blade.php  (@foreach ($sidebar …))
```

| Rule                                                       | Result                                                |
| ---------------------------------------------------------- | ----------------------------------------------------- |
| `route`, no children                                       | Plain link                                            |
| `children`                                                 | Dropdown                                              |
| `route` **and** visible children                           | Dropdown; the parent's page is the first link         |
| `route` and children, but none visible                     | Plain link to the parent's page                       |
| Nothing clickable                                          | Hidden                                                |

- **New sidebar group:** add its name to `menu_groups` in `config/starter.php` so it's placed correctly.
- **Sync never overwrites** the label, icon, group, order or visibility you set in the Menu builder.

### 7.2 Website menus

**Appearance → Menu builder → Header / Footer** tabs. Items are stored in `menu_items` with `location = header|footer`. They're passed to the website layout by the view composer in `AppServiceProvider` as `$headerMenu` and `$footerMenu`.

---

## 8. Login and security

### 8.1 Authentication (Fortify)

| Feature              | Where                                                                 |
| -------------------- | --------------------------------------------------------------------- |
| Login page           | `resources/views/auth/login.blade.php`                                |
| Who may log in       | `FortifyServiceProvider` → `authenticateUsing` (active users only)    |
| Login rate limit     | `FortifyServiceProvider` (per email + IP; attempts per minute from Settings → Security) |
| Forgot/reset password| `auth/forgot-password`, `auth/reset-password` views                   |
| Two-factor (TOTP)    | Set up in **Profile**; challenge page `auth/two-factor-challenge`     |
| Registration         | Disabled (`config/fortify.php` features)                              |
| Login/logout logging | `AppServiceProvider::registerAuthListeners()` → activity log + `last_login_at` |

### 8.2 Middleware (`app/Http/Middleware`)

| Middleware            | Alias            | Does                                                                     |
| --------------------- | ---------------- | ------------------------------------------------------------------------ |
| `EnsureUserIsActive`  | `active`         | Logs out users made inactive/suspended while signed in                    |
| `ForcePasswordChange` | `force.password` | Sends flagged users to their profile until they set a new password        |
| `EnforceTwoFactor`    | `enforce.2fa`    | With Settings → Security → "Require 2FA", admins must set up 2FA first    |
| `SecurityHeaders`     | (on every web request) | CSP with nonce, X-Frame-Options, nosniff, Referrer-Policy, HSTS on HTTPS |
| `WebsiteMaintenance`  | group `website`  | Settings → Maintenance: visitors see "back soon"; staff see the site      |

### 8.3 Other protections already in place

| Protection                    | How                                                                                                |
| ----------------------------- | -------------------------------------------------------------------------------------------------- |
| CSRF                          | `@csrf` in every form; `X-CSRF-TOKEN` header in JS `fetch` (`meta[name=csrf-token]`)               |
| XSS in rich text              | `Purifier::clean()` before saving `editor` fields                                                  |
| XSS in output                 | Always `{{ }}` in Blade. Use `{!! !!}` only for purified HTML                                      |
| Mass assignment               | `$fillable` on every model                                                                         |
| Strong passwords              | `AppServiceProvider`: min length (setting) + letters, mixed case, numbers, symbols                 |
| Session                       | Database driver, encrypted (`.env` `SESSION_ENCRYPT=true`)                                         |
| Public form spam              | Honeypot field + `throttle:forms` (5/min per IP) on contact/newsletter                             |
| Upload safety                 | Image MIME check, size limit `config('starter.max_upload_kb')`, no SVG in Media library            |
| CSV injection                 | DataTable export prefixes cells starting with `= + - @` with `'`                                   |
| Lazy loading                  | `Model::preventLazyLoading()` outside production: forgotten `->with()` throws an error locally     |
| Secrets in settings           | `mail_password` stored encrypted (`SettingsRepository::ENCRYPTED`)                                 |

### 8.4 Content Security Policy (CSP)

The CSP blocks scripts that aren't from our own files. This matters when you add JavaScript:

- **Inline `<script>`** must carry the nonce: `<script nonce="{{ Vite::cspNonce() }}">…</script>`.
- **External scripts, fonts or iframes** (Google Maps, YouTube, a CDN) must be allowed in `config/starter.php` → `csp` (`frame_src`, `img_src`), or in `SecurityHeaders::policy()` for other directives.
- **`onclick="…"` attributes don't run.** Use `data-*` attributes and a JS file instead (section 12).
- To debug, set `CSP_ENABLED=false` in `.env` temporarily.

---

## 9. Settings

**Admin → System → Settings**, with 8 tabs: General, Branding, SEO, Mail, Social, Security (also API and sign-in history options), Languages, Maintenance (also trash retention).

```
app/Modules/Settings/Support/SettingDefinitions.php   ← list of tabs and fields (type, label, rules, default)
app/Core/Settings/SettingsRepository.php              ← reads/writes table "settings", cached forever, flushed on save
app/Core/helpers.php → setting('key', $default)       ← read a setting anywhere
```

### Read a setting

```php
setting('site_name');                     // PHP
setting('contact_email', 'hi@site.com');  // with default
```

```blade
{{ setting('site_name') }}
```

### Add a new setting

Add one line to a tab in `SettingDefinitions.php`:

```php
'general' => [
    ...
    'fields' => [
        ...
        'invoice_prefix' => ['type' => 'text', 'label' => 'Invoice number prefix', 'rules' => ['required', 'string', 'max:10'], 'default' => 'INV-', 'hint' => 'Shown before every invoice number.'],
    ],
],
```

It appears on the Settings page, gets validated and saved, and you can read it with `setting('invoice_prefix')`. There's no migration: settings are key/value rows.

| Field type | Input                         |
| ---------- | ----------------------------- |
| `text` `email` `url` `number` | Normal input |
| `textarea` | Multi-line                    |
| `select`   | Dropdown (`'options' => [...]`) |
| `toggle`   | On/off switch                 |
| `color`    | Colour picker                 |
| `media`    | Pick from the Media library   |
| `password` | Hidden; add the key to `SettingsRepository::ENCRYPTED` to store it encrypted |

**Settings that change Laravel config** (site name → `app.name`, mail server, session lifetime) are applied in `AppServiceProvider::applySettings()`. Add yours there if a setting must override a config value.

---

## 10. Admin UI: layout, components, forms

### 10.1 Layout

`resources/views/layouts/admin.blade.php` contains:
- **Sidebar** (`$sidebar` from MenuService).
- **Top bar:** page search, dark mode, the notification bell (`$notifications`) and the user menu.
- **Flash messages:** `->with('success', '…')` and `->with('error', '…')` from controllers show as toasts.
- **Confirm modal:** used by any `data-confirm` form.
- **Live toast:** used by realtime notifications.
- **`@yield('content')`:** your page.
- **`@stack('head')` and `@stack('scripts')`:** for page-specific additions.

A page:

```blade
@extends('layouts.admin')

@section('title', 'Orders')

@section('content')
    <x-admin.page-header title="Orders" :breadcrumbs="['Sales', 'Orders']">
        <a href="{{ route('admin.orders.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add order</a>
    </x-admin.page-header>

    <x-admin.card title="Recent orders">
        …
    </x-admin.card>
@endsection
```

### 10.2 Components

Anonymous Blade components live in `resources/views/components/admin/`. A dot in the tag means a folder: `<x-admin.form.input>` → `components/admin/form/input.blade.php`.

| Component                            | Use                                                        |
| ------------------------------------ | ---------------------------------------------------------- |
| `<x-admin.page-header>`              | Title, breadcrumbs, buttons (slot)                         |
| `<x-admin.card>`                     | Card with `title`, optional `actions` slot, `:flush="true"` for tables |
| `<x-admin.datatable>` + `datatable.*`| List pages (section 11)                                    |
| `<x-admin.status-badge :status="…">` | Coloured badge (map at top of the file)                    |
| `<x-admin.delete-button :action="…">`| Delete form with confirm dialog                            |
| `<x-admin.empty>`                    | Empty state                                                |
| `<x-admin.avatar :user="…">`         | Photo or initials                                          |
| `<x-admin.search>`                   | Search input                                               |
| `<x-admin.form.input>`               | `name`, `label`, `type`, `:value`, `hint`, `prefix`, `required`. Shows validation errors itself |
| `<x-admin.form.textarea>`            | Multi-line                                                 |
| `<x-admin.form.select :options="[...]">` | Dropdown                                              |
| `<x-admin.form.checkbox>`            | Switch (always sends 0/1)                                  |
| `<x-admin.form.editor>`              | Trix rich text (purify in the controller)                  |
| `<x-admin.form.image :url="…">`      | Upload with preview (use `StoresUploads` trait)            |
| `<x-admin.form.media>`               | Pick from the Media library                                |

**Making a component:** create `components/admin/price.blade.php`:

```blade
@props(['amount', 'currency' => '₹'])
<span {{ $attributes->class(['fw-semibold']) }}>{{ $currency }}{{ number_format((float) $amount, 2) }}</span>
```

Use it as `<x-admin.price :amount="$order->total" />`. A leading `:` means the value is PHP.

### 10.3 Handy UI attributes (handled by JS, no code needed)

| Attribute                                 | Effect                                                           |
| ----------------------------------------- | ---------------------------------------------------------------- |
| `<form data-confirm="Delete this?">`      | Confirmation dialog before submit (`confirm.js`)                 |
| `data-confirm-body`, `data-confirm-button`| Dialog text and button label                                     |
| `data-bs-toggle="tooltip" title="…"`      | Tooltip                                                          |
| `<input data-slug-from="#title">`         | Slug auto-filled from the title (`forms.js`)                     |
| `<select data-autosubmit>`                | Submits its form on change                                       |

### 10.4 Flash messages from controllers

```php
return redirect()->route('admin.orders.index')->with('success', 'Order saved.');
return back()->with('error', 'Cannot delete a paid order.');
```

---

## 11. Data tables (list pages)

Every list page uses `app/Core/DataTable/DataTable.php`. One chain in the controller defines search, sorting, filters, date range, CSV export and bulk actions. A full walkthrough is in `README_PROJECT_CMD.md` section 4.7. In short:

```php
public function index(Request $request): Response
{
    return DataTable::of(Order::query()->with('customer'), $request)
        ->search(['order_number', 'customer_name'])                         // ?q=
        ->sortable(['order_number', 'total', 'created_at'], default: 'created_at')  // ?sort=&dir=
        ->status('order_status', Order::STATUSES)                           // ?status=
        ->filter('payment', 'Payment', ['cod' => 'COD', 'online' => 'Online'])   // ?payment=
        ->dateRange('created_at', 'Ordered')                                // ?from=&to=
        ->export('orders', ['Order #' => 'order_number', 'Customer' => 'customer.name'], 'orders.export')
        ->bulk(route('admin.orders.bulk'), ['delete' => 'orders.delete'])
        ->render('orders::admin.index', 'orders::admin._table');
}
```

| File                                         | Role                                                             |
| -------------------------------------------- | ---------------------------------------------------------------- |
| `app/Core/DataTable/DataTable.php`           | Reads the URL, builds the query, paginates, exports              |
| `components/admin/datatable.blade.php`       | Toolbar (search, filter dropdowns, dates, export) + bulk bar     |
| `components/admin/datatable/th.blade.php`    | Sortable column heading                                          |
| `components/admin/datatable/footer.blade.php`| "Show n", "Showing x–y of z", page links                         |
| `components/admin/datatable/check*.blade.php`| Row / select-all checkboxes                                      |
| `resources/admin/js/datatable.js`            | Reloads only the rows on search/filter/sort/page; keeps the URL in sync |
| Module `Views/admin/index.blade.php`         | Page + `<x-admin.datatable>`                                     |
| Module `Views/admin/_table.blade.php`        | The `<table>` + footer (returned alone for background reloads)   |

**How the background reload works:** `datatable.js` fetches the same URL with the header `X-DataTable: 1`. `render()` sees the header and returns only the `_table` partial. Without JS, every action still works as a normal link or form.

---

## 12. JavaScript and CSS: how to add and change

### 12.1 How assets are built

`vite.config.js` has 4 entry points:

| Entry                         | Used by                          | Loaded in                                   |
| ----------------------------- | -------------------------------- | ------------------------------------------- |
| `resources/admin/admin.css`   | Admin panel (Bootstrap 5)        | `layouts/admin.blade.php`, `layouts/auth`   |
| `resources/admin/admin.js`    | Admin panel                      | `@vite([...])` in the admin layout          |
| `resources/web/web.css`       | Website (Tailwind 4)             | `layouts/web.blade.php`                     |
| `resources/web/web.js`        | Website                          | `layouts/web.blade.php`                     |

| Command                                                | When                                                    |
| ------------------------------------------------------ | ------------------------------------------------------- |
| `docker compose --profile dev up -d`                   | While working: the Vite dev server reloads changes instantly |
| `docker compose run --rm node sh -c "npm install && npm run build"` | Before testing without the dev server, and before deploy |

> If `public/hot` exists, pages load JS from the dev server (`localhost:5173`). If the dev server isn't running, delete `public/hot` or run the build.

### 12.2 The admin JS files

`resources/admin/admin.js` only **imports** feature files. Each file is small and does one job:

| File                     | Does                                                                       | Hooks (HTML attributes)                           |
| ------------------------ | -------------------------------------------------------------------------- | ------------------------------------------------- |
| `js/layout.js`           | Sidebar collapse, mobile menu, dark mode, top-bar page search              | `data-sidebar-toggle`, `data-theme-toggle`        |
| `js/confirm.js`          | Confirm dialog for dangerous forms                                         | `data-confirm`                                    |
| `js/tables.js`           | Select-all checkboxes, bulk action bar, auto-submit selects                | `data-bulk`, `data-check-all`, `data-row-check`, `data-autosubmit` |
| `js/datatable.js`        | Background reload of list pages                                           | `data-datatable`, `data-datatable-*`              |
| `js/forms.js`            | Slug auto-fill, character counters, Google search preview                  | `data-slug-from`, `data-counter`, `data-preview-source` |
| `js/media-picker.js`     | "Choose from Media library" modal                                          | `<x-admin.form.media>`                            |
| `js/role-matrix.js`      | Row/column/select-all ticking on the role screen                           | `data-permission-matrix`                          |
| `js/page-builder.js`     | Add/move/duplicate/remove page blocks                                      | `data-blocks`                                     |
| `js/menu-builder.js`     | Drag-and-drop menu ordering (SortableJS), saved with fetch                 | `data-menu-sortable`                              |
| `js/dashboard.js`        | Dashboard charts (Chart.js) from `data-chart` / `data-values`              | `canvas[data-chart]`                              |
| `js/realtime.js`         | Reverb/Echo connection, live bell + toast (section 13)                     | `meta[name=user-id]`, `data-notif-*`, `#liveToast` |

Global objects: `window.bootstrap` (Bootstrap JS), and `window.Echo` (when realtime is on).

### 12.3 The pattern every JS file follows

1. Find elements by a `data-*` attribute.
2. If none are on the page, do nothing.
3. Read settings from `data-*` attributes rendered by Blade.

```js
// resources/admin/js/copy-button.js
// <button data-copy="text to copy">Copy</button>

document.addEventListener('click', async (e) => {
    const button = e.target.closest('[data-copy]');
    if (!button) return;

    await navigator.clipboard.writeText(button.dataset.copy);
    const old = button.textContent;
    button.textContent = 'Copied!';
    setTimeout(() => { button.textContent = old; }, 1500);
});
```

### 12.4 Add a new admin JS feature (step by step)

**1. Create the file**, for example `resources/admin/js/order-total.js`:

```js
// Live order total on the order form.
// <form data-order-form> with inputs [data-qty] and [data-price], output [data-total]

const form = document.querySelector('[data-order-form]');

if (form) {
    const update = () => {
        let total = 0;
        form.querySelectorAll('[data-line]').forEach((line) => {
            const qty = Number(line.querySelector('[data-qty]').value || 0);
            const price = Number(line.querySelector('[data-price]').value || 0);
            total += qty * price;
        });
        form.querySelector('[data-total]').textContent = total.toFixed(2);
    };

    form.addEventListener('input', update);
    update();
}
```

**2. Import it** in `resources/admin/admin.js`:

```js
import './js/order-total';
```

**3. Add the hooks** in the Blade view:

```blade
<form method="POST" action="…" data-order-form>
    <div data-line>
        <input type="number" name="qty[]" data-qty>
        <input type="number" name="price[]" data-price>
    </div>
    Total: <strong data-total>0.00</strong>
</form>
```

**4. Build:** `docker compose run --rm node sh -c "npm run build"`, or keep the dev server running.

> **Use one bundle, not one file per page.** Every feature is imported in `admin.js` and does nothing on pages without its `data-*` hook. That keeps a single cached file. Only add a new Vite entry for something very large that one page needs (step 12.6).

### 12.4b Where a module's JavaScript goes

| The script is…                                               | Put it in                                         | Loaded by                    |
| ------------------------------------------------------------ | ------------------------------------------------- | ---------------------------- |
| Used by **one module** (e.g. live order total, product variant picker) | `app/Modules/{Module}/Resources/js/admin/*.js` | `admin.js`, automatically (`import.meta.glob`) |
| A **website** script of one module                           | `app/Modules/{Module}/Resources/js/web/*.js`      | `web.js`, automatically      |
| **Shared** by several modules (confirm dialog, tables, forms, media picker) | `resources/admin/js/*.js` + `import` in `admin.js` | `admin.js`             |

Module files need no import line: create the file and build. Deleting the module folder removes its JavaScript too. The same `data-*` pattern applies, so the file does nothing on pages without its hook:

```js
// app/Modules/Orders/Resources/js/admin/order-total.js
const form = document.querySelector('[data-order-form]');
if (form) { /* … */ }
```

### 12.5 Using an npm package

```bash
docker compose run --rm node npm install flatpickr
```

```js
// resources/admin/js/date-picker.js
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

document.querySelectorAll('[data-datepicker]').forEach((el) => flatpickr(el, { dateFormat: 'Y-m-d' }));
```

Then add `import './js/date-picker';` to `admin.js` and build. Don't use CDN `<script src="https://…">` tags: the CSP blocks them.

### 12.6 Page-only scripts

**Small inline script:** it must have the nonce.

```blade
@push('scripts')
    <script nonce="{{ Vite::cspNonce() }}">
        document.querySelector('[data-order-form]')?.addEventListener('submit', () => console.log('saving'));
    </script>
@endpush
```

**Large page-only bundle:**
1. Add it to `vite.config.js` → `input`, e.g. `'resources/admin/reports.js'`.
2. Load it on that page only: `@push('scripts') @vite('resources/admin/reports.js') @endpush`.
3. Build.

### 12.7 Calling the server from JS

Always send the CSRF token for POST/PUT/DELETE:

```js
const response = await fetch(url, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
    },
    body: JSON.stringify({ ids: [1, 2, 3] }),
});
const data = await response.json();
```

The controller can `return response()->json([...])`. `menu-builder.js` and `media-picker.js` are working examples.

### 12.8 Admin CSS

- Everything is in `resources/admin/admin.css`. The colour/size variables are at the top (`--app-…`).
- Use Bootstrap classes first: `d-flex`, `gap-2`, `text-secondary`, `btn btn-outline-secondary`…
- Add custom classes at the bottom, in a commented block like `/* ---------- Orders ---------- */`.
- Dark mode: use Bootstrap variables (`var(--bs-body-bg)`, `var(--bs-border-color)`) instead of fixed colours, and both themes work.

### 12.9 Website CSS (Tailwind)

- `resources/web/web.css` uses Tailwind 4 with **explicit `@source` lines**. Tailwind only generates classes it finds in those files.
- Views covered: `resources/views/layouts/web.blade.php`, `resources/views/web/**`, `app/Modules/*/Views/web/**`.
- If you put Tailwind classes anywhere else, add an `@source` line at the top of `web.css`, or the classes won't exist.
- Brand colours (`brand-600` etc.) are defined in the `@theme` block of `web.css`.
- Website JS (mobile menu, slider) is in `resources/web/web.js`, following the same `data-*` pattern.

---

## 13. Realtime with Reverb (inch by inch)

### 13.1 What it does today

The admin **notification bell updates live**. When something happens, every admin who should know gets:
- a red badge count on the bell, without a page reload,
- a new item at the top of the bell dropdown,
- a toast popup.

It's also saved in the database, so it's still in the bell after a refresh.

**Where it's triggered:**

| Event                          | Code                                                         | Who gets it            |
| ------------------------------ | ------------------------------------------------------------ | ---------------------- |
| New contact form message       | `Inbox/Controllers/Web/FormController.php`                   | Users with `inbox.view` |
| New newsletter subscriber      | same file                                                    | `subscribers.view`     |
| New user added                 | `Users/Controllers/Admin/UserController.php` (`store`)       | `users.view`           |
| Backup finished / failed       | `Backups/Jobs/RunBackup.php`                                 | `backups.view`         |
| Too many failed sign-ins       | `app/Core/Security/LoginRecorder.php`                        | `users.view`           |

### 13.2 All the pieces and where they are

| #  | Piece                    | Location                                                     | Job                                                                 |
| -- | ------------------------ | ------------------------------------------------------------ | ------------------------------------------------------------------- |
| 1  | Reverb server            | Docker service `reverb` (`compose.yaml`), `php artisan reverb:start` | The WebSocket server browsers connect to                    |
| 2  | Server config            | `config/reverb.php`, `.env` `REVERB_*`                       | App id/key/secret, ports                                            |
| 3  | Broadcast driver         | `.env` `BROADCAST_CONNECTION=reverb`, `config/broadcasting.php` | Tells Laravel to send broadcasts to Reverb                       |
| 4  | Channel permissions      | `routes/channels.php`                                        | Who may listen to which private channel                             |
| 5  | Auth endpoint            | `/broadcasting/auth` (registered by `withRouting(channels: …)` in `bootstrap/app.php`) | Browser asks "may I join this channel?" |
| 6  | Notification             | `app/Notifications/AdminAlert.php`                           | Saved to `notifications` table + broadcast                          |
| 7  | Queue worker             | Docker service `horizon`                                     | `AdminAlert` is queued (`ShouldQueue`), so Horizon actually sends it |
| 8  | Layout meta tags         | `layouts/admin.blade.php`: `meta user-id`, `meta broadcast-auth` | Tell the JS who you are and where to authenticate               |
| 9  | Browser client           | `resources/admin/js/realtime.js` (Laravel Echo + pusher-js)  | Connects, joins your channel, updates the bell                      |
| 10 | Browser config           | `.env` `VITE_REVERB_*` (built into the JS by Vite)           | Where the browser connects                                          |
| 11 | CSP                      | `SecurityHeaders::policy()` adds `ws://host:port` to `connect-src` | Lets the browser open the WebSocket                           |
| 12 | Bell HTML                | `layouts/admin.blade.php`: `data-notif-count`, `data-notif-list`, `#liveToast` | What `realtime.js` updates                   |
| 13 | Open / mark read         | `Dashboard/Controllers/Admin/NotificationController.php`     | Clicking an item marks it read and redirects                        |

### 13.3 The flow, step by step

```
① Something happens (e.g. visitor submits the contact form)
   FormController:  AdminAlert::toAdmins("New enquiry from Anita", route(...), 'inbox.view', 'bi-inbox');
        │
        ▼
② AdminAlert::toAdmins()  picks active users who are super-admin OR can('inbox.view')
   Notification::send($users, new AdminAlert(...))
        │  AdminAlert implements ShouldQueue → pushed to Redis queue
        ▼
③ Horizon worker (laravel13-horizon) runs it. via() returns ['database', 'broadcast']:
     database  → row in "notifications" table (bell after refresh)
     broadcast → sent to Reverb on channel  private-App.Models.User.{id}
        │
        ▼
④ Reverb (laravel13-reverb) pushes the message to every browser connected to that channel
        │
        ▼
⑤ Browser: realtime.js
   window.Echo.private(`App.Models.User.${userId}`).notification((n) => {
       badge +1, prepend item to [data-notif-list], show #liveToast with n.message
   });
```

**How the browser joins the channel (once per page load):**

```
realtime.js reads <meta name="user-id"> and <meta name="broadcast-auth">
   → opens WebSocket ws://REVERB_HOST:REVERB_PORT
   → POST /broadcasting/auth  (session cookie + CSRF) "may user 5 join private-App.Models.User.5?"
   → routes/channels.php:  Broadcast::channel('App.Models.User.{id}', fn ($user, $id) => $user->id == $id)
   → yes → subscribed. Users can only ever join their OWN channel.
```

### 13.4 `.env` keys explained

```dotenv
BROADCAST_CONNECTION=reverb        # "log" or "null" turns realtime off (bell still works on refresh)

REVERB_APP_ID=starter              # any id
REVERB_APP_KEY=starter-key         # public key: the browser uses it
REVERB_APP_SECRET=starter-secret   # private: server only

REVERB_HOST=localhost              # address the BROWSER connects to
REVERB_PORT=8081                   # port the BROWSER connects to (compose maps 8081 → container 8080)
REVERB_SCHEME=http                 # https in production (wss://)

REVERB_SERVER_HOST=0.0.0.0         # where the reverb container listens
REVERB_SERVER_PORT=8080

REVERB_INTERNAL_HOST=reverb        # how the APP container reaches reverb inside Docker
REVERB_INTERNAL_PORT=8080

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"   # copied into the JS at build time
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

> `VITE_*` values are **built into the JS**. After changing them, rebuild the assets.

### 13.5 Send a live notification from your own code

```php
use App\Notifications\AdminAlert;

AdminAlert::toAdmins(
    "Order #{$order->number} was placed",          // message
    route('admin.orders.edit', $order),            // where clicking it goes
    'orders.view',                                 // who gets it (permission); super-admins always do
    'bi-cart-check',                               // Bootstrap icon
);
```

That's all: database + live bell + toast.

To send to **one user**:

```php
$user->notify(new AdminAlert('Your export is ready', route('admin.orders.index')));
```

### 13.6 Add a new realtime feature (your own event)

Example: refresh the Orders list for everyone viewing it when a new order comes in.

**1. The event class**: `app/Modules/Orders/Events/OrderPlaced.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Orders\Events;

use App\Modules\Orders\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPlaced implements ShouldBroadcast     // queued: Horizon sends it. Use ShouldBroadcastNow to send immediately.
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order) {}

    /** Channel name the browser listens to. */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('orders')];
    }

    /** Event name in JS (with a leading dot). */
    public function broadcastAs(): string
    {
        return 'order.placed';
    }

    /** Only send what the browser needs, never the whole model. */
    public function broadcastWith(): array
    {
        return ['id' => $this->order->id, 'number' => $this->order->number, 'total' => $this->order->total];
    }
}
```

**2. Who may listen**: `routes/channels.php`

```php
Broadcast::channel('orders', fn ($user) => $user->can('orders.view'));
```

**3. Fire it** wherever an order is created:

```php
use App\Modules\Orders\Events\OrderPlaced;

OrderPlaced::dispatch($order);
```

**4. Listen in the browser**: `resources/admin/js/orders-live.js`

```js
// Reload the orders table when a new order arrives (only on pages with the orders table).
const table = document.querySelector('[data-orders-live]');

if (table && window.Echo) {
    window.Echo.private('orders').listen('.order.placed', (e) => {
        console.log('New order', e.number);
        // simplest: click the current page link to reload rows through datatable.js
        table.querySelector('[data-datatable-reset]')?.click();
    });
}
```

Import it in `admin.js` (`import './js/orders-live';`). It must come **after** `import './js/realtime';`, which creates `window.Echo`. Then add `data-orders-live` to the page (for example on `<x-admin.datatable … data-orders-live>`) and build.

### 13.7 Debugging realtime

| Check                                  | How                                                                              |
| -------------------------------------- | -------------------------------------------------------------------------------- |
| Is Reverb running?                     | `docker compose ps reverb`, `docker compose logs -f reverb`                      |
| Is the queue running?                  | `docker compose logs -f horizon`, or open `/admin/horizon`                       |
| Does the browser connect?              | F12 → Network → **WS** → a connection to `localhost:8081` with status 101        |
| Channel auth OK?                       | F12 → Network → `broadcasting/auth` should be 200 (403 = rule in `channels.php` said no) |
| Is `window.Echo` defined?              | F12 → Console → `window.Echo`. If undefined: meta tags missing (BROADCAST_CONNECTION not `reverb`) or VITE_* empty → rebuild |
| CSP blocking?                          | Console error "Refused to connect to ws://…" → check `REVERB_HOST/PORT/SCHEME` |
| Send a test                            | `art tinker --execute="App\Notifications\AdminAlert::toAdmins('Hello from tinker');"` |

---

## 14. Queues and Horizon

- **Driver:** Redis (`QUEUE_CONNECTION=redis`).
- **Worker:** the `horizon` container runs `php artisan horizon`.
- **Dashboard:** `/admin/horizon` (super-admin only, `HorizonServiceProvider`).

What goes on the queue today: `AdminAlert` notifications, `WelcomeUser` emails, the contact-reply email, and `RunBackup` jobs.

**Make your own job:**

```bash
art make:job App/Modules/Orders/Jobs/SendInvoice
```

```php
class SendInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Order $order) {}

    public function handle(): void
    {
        Mail::to($this->order->email)->send(new InvoiceMail($this->order));
    }
}

// anywhere:
SendInvoice::dispatch($order);
```

> **After changing a Job, Mail or Notification class, run `docker compose restart horizon`.** Workers keep old code in memory.

Failed jobs: `art queue:failed`, `art queue:retry all`, or the Horizon dashboard.

---

## 15. Scheduler

- **Runner:** the `scheduler` container runs `php artisan schedule:work`.
- **Tasks:** listed in `routes/console.php`.

| When            | Task                                                    |
| --------------- | ------------------------------------------------------- |
| every minute    | Heartbeat (dashboard "System status"), `blog:publish-scheduled` |
| every 5 min     | `horizon:snapshot` (Horizon graphs)                     |
| every 15 min    | `auth:clear-resets`                                     |
| daily           | `queue:prune-failed` (older than 7 days)                |
| 01:30 / 02:00   | `backup:clean` / `backup:run`                           |
| 03:00 / 03:15   | `activitylog:clean` / `sitemap:generate`                |
| 03:30           | `trash:empty` (Settings → Maintenance → days)           |
| 03:45           | `model:prune` for sign-in history (Settings → Security → days) |
| 09:00           | `backup:monitor`                                        |

**Add a scheduled task:**

```php
// routes/console.php
Schedule::command('orders:cancel-unpaid')->hourly()->withoutOverlapping();
```

with the command in `app/Modules/Orders/Console/CancelUnpaidOrders.php` (auto-registered):

```bash
art make:command App/Modules/Orders/Console/CancelUnpaidOrders
```

Check it: `art schedule:list`, and run once with `art schedule:run`.

---

## 16. Notifications and mail

| Class                                           | Channel                     | Used for                                       |
| ----------------------------------------------- | --------------------------- | ---------------------------------------------- |
| `app/Notifications/AdminAlert.php`              | database + broadcast        | Admin bell (section 13)                        |
| `app/Notifications/WelcomeUser.php`             | mail                        | New user email with a "set your password" link |
| `app/Notifications/NewDeviceSignIn.php`         | mail                        | "New sign-in to your account" (section 27)     |
| `app/Modules/Inbox/Mail/ContactReplyMail.php`   | mail                        | Reply to a contact message from the Inbox      |

- **Local mail:** everything goes to **Mailpit** at http://localhost:8025.
- **Mail server:** set in `.env` (`MAIL_*`), or overridden from **Settings → Mail** (with a "Send test email" button).

---

## 17. Website CMS: pages, blocks, blog, forms, SEO

### 17.1 Pages and the page builder

- **Admin:** Website CMS → Pages → Open builder.
- A page is a row in `pages` with a JSON `blocks` column: `[{type: "hero", data: {...}}, …]`.
- The website renders each block with `pages::web.blocks.{type}` (`app/Modules/Pages/Views/web/blocks/{type}.blade.php`).
- The page marked **Home page** is served at `/`. Other pages are served at `/{slug}` by the catch-all route, which is registered last.

**12 built-in blocks:** hero, text, image_text, features, stats, testimonials, faq, latest_posts, cta, contact_form, map, gallery.

**Add a new block (2 steps):**

1. Register it in `app/Modules/Pages/Support/BlockRegistry.php`:

```php
'pricing' => [
    'label' => 'Pricing table', 'icon' => 'bi-currency-rupee', 'description' => 'Three plans side by side.',
    'fields' => [
        'heading' => ['type' => 'text', 'label' => 'Heading'],
        'plans' => ['type' => 'textarea', 'label' => 'Plans', 'hint' => 'One per line: Name | Price | Feature, Feature'],
        'button_url' => ['type' => 'url', 'label' => 'Button link'],
    ],
],
```

   Field types: `text`, `textarea`, `editor`, `url`, `media`, `select` (with `options`), `toggle`, `number`. The admin form for the block is generated from this list.

2. Create `app/Modules/Pages/Views/web/blocks/pricing.blade.php`. The block's values arrive as `$data`:

```blade
<section class="py-16">
    <div class="container-site">
        @if (! empty($data['heading']))<h2 class="text-3xl font-bold">{{ $data['heading'] }}</h2>@endif
        <div class="mt-8 grid gap-6 md:grid-cols-3">
            @foreach (array_filter(explode("\n", $data['plans'] ?? '')) as $line)
                @php [$name, $price, $features] = array_pad(array_map('trim', explode('|', $line)), 3, ''); @endphp
                <div class="rounded-2xl border p-6">
                    <h3 class="font-semibold">{{ $name }}</h3>
                    <p class="mt-2 text-3xl font-bold">{{ $price }}</p>
                    <p class="mt-4 text-slate-600">{{ $features }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
```

The new file is under `app/Modules/*/Views/web`, which Tailwind already scans, so no `@source` change is needed. Rebuild the CSS.

### 17.2 Blog

| Part                          | Where                                                                      |
| ----------------------------- | -------------------------------------------------------------------------- |
| Posts, categories (admin)     | `Blog/Controllers/Admin/PostController.php`, `CategoryController.php`      |
| Statuses                      | `Post::STATUSES`: draft, published, scheduled                              |
| Publishing                    | Needs `blog.publish`; others can only save drafts                          |
| Scheduled posts               | `blog:publish-scheduled` runs every minute (`Blog/Console/`)               |
| Public pages                  | `/blog`, `/blog/category/{slug}`, `/blog/{slug}` (`Controllers/Web/BlogController.php`) |
| Soft delete                   | Deleted posts go to trash (`SoftDeletes`)                                  |
| Test data                     | `art db:seed --class="App\Modules\Blog\Database\Seeders\BlogPostSeeder"`  |

### 17.3 Contact form and newsletter (Inbox module)

- **Public routes:** `POST /contact-submit` and `POST /newsletter`, with `throttle:forms` (5 per minute per IP) and a honeypot field.
- **On submit:** a `ContactMessage` or `Subscriber` row is saved, and the admins get an `AdminAlert` (live bell).
- **Admin Inbox:** a two-pane mailbox with read/unread, star, reply by email (`ContactReplyMail`) and delete.
- **Admin Subscribers:** a data table with CSV export.

### 17.4 SEO

- **Per page/post:** meta title and description fields.
- **Defaults:** Settings → SEO.
- **`/sitemap.xml`:** cached, rebuilt daily by `sitemap:generate` (`Pages/Console/GenerateSitemap.php`).
- **`/robots.txt`:** generated by `Pages/Controllers/Web/SeoController.php`.

---

## 18. Media library, activity log, backups

### Media library (`app/Modules/Media`)

- Upload, search, edit title/alt text and delete images. Uses `spatie/laravel-medialibrary`; `MediaItem` holds each file, and a thumbnail and a WebP copy are generated on upload.
- `<x-admin.form.media name="image" />` in any form lets the user pick an image from the library, and stores its URL.
- **Module image fields** (`image` type from `make:module`) are different: they upload straight to `storage/app/public/<slug>` through the `StoresUploads` trait. Run `art storage:link` once so they show.

### Activity log (`app/Modules/Activity`)

- Records logins/logouts and every create/update/delete on logged models, with who did it and what changed.
- **Log your own model:**

```php
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Order extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('orders')->logOnly(['order_status', 'total'])->logOnlyDirty();
    }
}
```

- **Log something by hand:** `activity('orders')->performedOn($order)->causedBy(auth()->user())->log('Refunded');`
- **Old entries:** removed daily (`activitylog:clean`), or with "Clean up" on the Activity page.

### Backups (`app/Modules/Backups`)

- **Page:** System → Backups. Lists backups, with "Back up now" (full or database only), download and delete.
- **Runs in the background:** as the `RunBackup` job on the queue, then notifies admins through `AdminAlert`.
- **Tools section:** one-click maintenance commands (clear cache, rebuild caches, sync modules, regenerate sitemap, restart queue workers). Only the commands listed in `BackupController::TOOLS` can run.
- **Config:** `config/backup.php`. Files go to `storage/app/private/<app name>/`.

---

## 19. Docker services

`compose.yaml`, project name `laravel13`:

| Service     | Container             | Job                                                     | Port (your machine) |
| ----------- | --------------------- | ------------------------------------------------------- | ------------------- |
| `app`       | `laravel13-app`       | PHP-FPM: runs Laravel. **The only service that builds the image** | –         |
| `nginx`     | `laravel13-nginx`     | Web server → app                                        | 8080                |
| `horizon`   | `laravel13-horizon`   | Queue worker (same image as app)                        | –                   |
| `scheduler` | `laravel13-scheduler` | `schedule:work` (same image)                            | –                   |
| `reverb`    | `laravel13-reverb`    | WebSocket server (same image)                           | 8081                |
| `mysql`     | `laravel13-mysql`     | Database                                                | 3307                |
| `redis`     | `laravel13-redis`     | Cache, queue                                            | –                   |
| `mailpit`   | `laravel13-mailpit`   | Catches emails                                          | 8025                |
| `node`      | `laravel13-node`      | Vite dev server (only with `--profile dev`)             | 5173                |

- **Code is shared, not copied:** the project folder is mounted into `app`, `horizon`, `scheduler` and `reverb`. PHP changes show up immediately, except in **horizon** (restart it) and **reverb** (restart it if you change channels or config).
- **Production:** uses the `production` target in `docker/php/Dockerfile`, where code is copied into the image and assets are built. See `README_PROJECT_CMD.md` section 14.

---

## 20. Tests and code quality

```bash
art test                                   # all feature tests (SQLite in memory, seeded roles/permissions)
art test --filter=DataTableTest            # one class
docker compose exec app ./vendor/bin/pint  # fix code style
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G   # static analysis (level 5)
```

| Test file                       | Covers                                                           |
| ------------------------------- | ---------------------------------------------------------------- |
| `AuthTest`                      | Login, wrong password, inactive users, forced password change, registration off |
| `PermissionTest`                | Every admin page opens for super-admin; editor/admin limits; sidebar shows only permitted items |
| `UserManagementTest`            | Create/edit/delete users, bulk, export, impersonation            |
| `RoleTest`                      | Roles CRUD, system roles protected, custom permissions           |
| `ContentTest`                   | Blog publishing rules, page builder, drafts, sitemap/robots      |
| `WebsiteFormsTest`              | Contact form, honeypot, rate limit, newsletter, inbox reply, maintenance mode, security headers |
| `AdminToolsTest`                | Settings, media uploads, menus, activity log, backup tools       |
| `ModuleGeneratorTest`           | `make:module` output                                             |
| `DataTableTest`                 | Search, sort, filters, per-page, AJAX, export, bulk, permissions |
| `TrashAndImportTest`            | Trash tabs, restore, delete forever, `trash:empty`, Excel/CSV import (create/update/errors/dry run/background chunks), user and subscriber import |
| `ApiTest`                       | Token sign-in, `/me`, revoke, 401/403/422 JSON, filters, CRUD, read-only tokens, API switch, blog API, profile tokens |
| `LanguagesTest`                 | Switcher, hreflang, RTL, page/post translations, menu labels, single-language site |
| `LoginHistoryTest`              | Sign-ins and failures recorded, new-device email, lockout alert, history page, profile |

**Test helpers** (`tests/TestCase.php`):

```php
$this->actingAs($this->superAdmin());
$editor = $this->userWithRole('editor');
$this->get($this->admin('orders'))->assertOk();      // → /admin/orders
```

**Writing a test for your module:**

```php
public function test_editor_cannot_refund(): void
{
    $this->actingAs($this->userWithRole('editor'));
    $order = Order::query()->create([...]);

    $this->post(route('admin.orders.refund', $order))->assertForbidden();
}
```

CI (`.github/workflows/ci.yml`) runs Pint, Larastan and the tests on every push.

---

## 21. Rules for working on this project

1. **New feature = new module.** Use `art make:module`. Don't put feature code in `app/Http/Controllers` or `routes/web.php`.
2. **Every admin controller method has a permission** in `middleware()`. List every method.
3. **Hide UI with `@can`, block with middleware.** Do both.
4. **Permissions your code checks go in `module.json`**, then `art module:sync`.
5. **Never edit a migration that already ran on a server.** Add a new one (`--path=app/Modules/X/Database/Migrations`).
6. **Validate with Form Requests** and keep `$fillable` on models.
7. **Eager-load relations** (`->with('category')`). Lazy loading throws an error locally on purpose.
8. **Output with `{{ }}`.** Use `{!! !!}` only for HTML cleaned with `Purifier::clean()`.
9. **JS:** one feature per file in `resources/admin/js/`, hooked by `data-*` attributes, imported in `admin.js`. No inline `onclick`, no CDN scripts.
10. **Inline `<script>` needs `nonce="{{ Vite::cspNonce() }}"`.**
11. **Restart Horizon** after changing jobs, mails or notifications.
12. **Before pushing:** `art test`, Pint, Larastan.
13. **Read settings with `setting()`**, config with `config()`. Never call `env()` outside `config/*.php`.
14. **Don't change `app/Core` casually.** Every module depends on it. Add a test when you do.

---

## 22. Recipes: common tasks start to finish

### A. New module with a list, form, permissions and menu

```bash
art make:module Order --fields="order_number:string:unique,customer_name:string,total:decimal,order_status:string,is_active:boolean" --group=Sales --icon=bi-cart --migrate
```

Then:
- add `'Sales'` to `menu_groups` in `config/starter.php`,
- tick `orders.*` for other roles in **Roles**,
- adjust the list (section 11) and the form (`Views/admin/_form.blade.php`).

### B. Add a column to an existing module

`README_PROJECT_CMD.md` section 5: migration with `--table --path`, then model `$fillable`, request rules, form component, and the list column.

### C. Add a "special" ability

Section 6.5: `module.json` → `module:sync` → tick in Roles → middleware → `@can`.

### D. New role for a client

Section 6.6: in the admin panel, or `Role::findOrCreate()` + `syncPermissions()` in a seeder.

### E. Show something live to admins

Section 13.5: one line, `AdminAlert::toAdmins('…', $url, 'orders.view')`.

### F. New JS behaviour on a page

Section 12.4: `resources/admin/js/feature.js` → import in `admin.js` → `data-*` hooks in Blade → build.

### G. New setting

Section 9: one line in `SettingDefinitions.php`, then read it with `setting('key')`.

### H. New website block

Section 17.1: an entry in `BlockRegistry.php` plus a Blade file in `Pages/Views/web/blocks/`.

### I. New nightly task

Section 15: a command in `Modules/X/Console/` plus one line in `routes/console.php`.

### J. Something broke

| Symptom                                   | First thing to check                                                      |
| ----------------------------------------- | ------------------------------------------------------------------------- |
| 500 error                                 | `docker compose exec app tail -50 storage/logs/laravel.log`               |
| 403 on a page                             | Your role lacks the permission → Roles; or `module:sync` not run          |
| Page/menu missing                         | `art module:sync && art optimize:clear`; module `enabled` in `module.json` |
| JS does nothing                           | F12 Console errors; rebuild assets; `public/hot` left over                |
| Tailwind class has no effect              | Missing `@source` in `resources/web/web.css`; rebuild                     |
| Job/email not sent                        | `docker compose logs -f horizon`; restart horizon                         |
| Live bell not updating                    | Section 13.7                                                              |
| "Lazy loading … not allowed"              | Add `->with('relation')` to the query                                     |
| Script blocked by CSP                     | Add the nonce, or allow the domain in `config/starter.php` → `csp`        |

---

## 23. Trash and restore

Deleting a record moves it to the **Trash** (soft delete). Every list with trash shows two tabs: **All** and **Trash (n)**. In the trash, rows have **Restore** and **Delete forever** buttons, and the bulk bar has the same two actions.

| Where it's on                         | Notes                                                              |
| ------------------------------------- | ------------------------------------------------------------------ |
| Users, Blog posts                     | Built in                                                           |
| Sliders, Services, Testimonials, FAQs | `deleted_at` added by `2026_11_18_100000_add_soft_deletes_…`       |
| Every module made with `make:module`  | Generated: `SoftDeletes` on the model, `softDeletes()` in the migration |

**Permissions:** Restore and Delete forever use the module's `delete` permission. Users without it don't see the Trash tab.

**Files:** a soft delete keeps uploaded files, so the row can be restored. **Delete forever** removes them: the model's `forceDeleted` hook deletes every field listed in `UPLOADS`.

**Automatic emptying:** `trash:empty` runs daily at 03:30. It deletes forever any module row that has been in the trash longer than **Settings → Maintenance → Empty the trash after (days)** (default 30, 0 = never). Users are never removed automatically.

```bash
art trash:empty --dry-run        # what would be deleted
art trash:empty --days=7         # run now with another limit
```

**Adding trash to your own list** (`make:module` already does all of this):

```php
// model
use SoftDeletes;

// migration
$table->softDeletes();

// controller index()
->trash('orders.delete')

// controller
public function restore(Order $order): RedirectResponse { $order->restore(); return back()->with('success', 'Restored.'); }
public function forceDelete(Order $order): RedirectResponse { $order->forceDelete(); return back()->with('success', 'Deleted forever.'); }

// bulk(): accept 'restore' and 'force-delete'; load them with ->onlyTrashed()
```

```php
// Routes/admin.php: withTrashed() lets {order} find deleted rows
Route::patch('orders/{order}/restore', [OrderController::class, 'restore'])->name('orders.restore')->withTrashed();
Route::delete('orders/{order}/force', [OrderController::class, 'forceDelete'])->name('orders.force-delete')->withTrashed();
```

```blade
{{-- _table.blade.php: Edit/Delete normally, Restore/Delete forever in the trash --}}
<x-admin.datatable.row-actions :table="$table" slug="orders" :record="$order" label="order" />
```

> **Unique columns:** a trashed row still holds its unique value (an email or SKU). Restore it or delete it forever before reusing that value.

---

## 24. Import & export (Laravel Excel)

Import and export use **maatwebsite/excel** (Laravel Excel 3.1), the package you know. Every module keeps its own classes, next to its controller:

```
app/Modules/Faqs/
├── Controllers/Admin/FaqController.php   importForm() · import() · importTemplate()   (plain, readable)
├── Imports/FaqsImport.php                columns, rules, how one row is saved
├── Exports/FaqsExport.php                headings + one row → one line
└── Views/admin/import.blade.php          the module's own import page
```

Modules that have it: Users, Customers, Subscribers, Sliders, Services, Testimonials, FAQs, and every module from `make:module`.

### How an import runs

```
Upload (.xlsx / .xls / .csv)
  └─ FaqController::import()
       ├─ ImportRun::start('faqs', $file, $user)   saves the file (private disk), counts the rows, row in import_runs
       └─ $run->process(new FaqsImport($run))
            ├─ wrong headings?            → stops with "None of the column headings match…"
            ├─ ≤ 1,000 rows               → Excel::import() now, in this request (no queue), result on the page
            └─ > 1,000 rows               → RunImport job on the "imports" queue (config/starter.php → imports)
                                              the job reads the file chunk by chunk (500 rows, in order),
                                              progress bar on the import page, bell notification when done
       per row (FaqsImport): find existing → validate rules() → saveRow() in its own savepoint
       bad rows are skipped and kept in import_run_errors → "Skipped rows" downloads them as Excel with the reason
```

- Thresholds: **Settings → Import & export** (background above N rows, rows per chunk, largest upload).
- **Only check the file** validates and saves every row inside a transaction that is rolled back: you see exactly what would happen, nothing changes.
- Headings are matched loosely (Laravel Excel "slug"): `Group name` = `group_name`. Unknown columns are ignored, so **Export → edit in Excel → Import** works.
- `Yes/No`, `1/0`, `true/false`, `active/inactive` work for yes/no columns; Excel date cells become dates; numbers in text columns (phones) stay text.
- When **updating**, columns that aren't in the file are left as they are.
- Each chunk is one database transaction; each row is a savepoint, so one bad row never undoes the others.
- The queue config is never changed: small files don't use the queue at all; big files are one job on the **imports** queue. Anything a row dispatches (welcome emails, notifications) goes to your normal queue as usual.
- **Why one job per file, not one job per chunk:** chunks run in order on one worker, so two workers can never import the same email/SKU at the same moment, and updates happen in file order. Memory stays low because only one chunk is loaded at a time.
- **Own queue for long jobs:** `RunImport::$timeout = 3600`, `$tries = 1`. A queue must let a job run that long, otherwise a second worker picks the same import up again:
  - **Redis + Horizon (local / Redis servers):** `.env` `IMPORT_QUEUE_CONNECTION=redis-imports`. That connection (`config/queue.php`) has `retry_after` 3700, and Horizon runs it with the `supervisor-imports` supervisor (`config/horizon.php`: 1 process, timeout 3600). Nothing else to start.
  - **SQS (production):** leave `IMPORT_QUEUE_CONNECTION` empty (= `QUEUE_CONNECTION=sqs`), create an SQS queue named **imports** with **visibility timeout 3600 s or more**, and run a worker for it: `php artisan queue:work sqs --queue=imports --timeout=3600 --tries=1`.
  - A failed import is never re-run automatically; the import page shows where it stopped.
- Results: the module's import page shows **Recent imports** (status, progress, counts, skipped rows); `/admin/imports/{id}` is the full result (the bell links there). Kept 30 days (`model:prune`).
- Permission: `<slug>.import`.

### The import class

```php
// app/Modules/Faqs/Imports/FaqsImport.php
class FaqsImport extends ModuleImport
{
    protected ?string $model = Faq::class;
    protected string $matchBy = 'id';          // a row with a known id updates; "email", "sku"… also fine

    public function columns(): array           // headings + example (template and help table)
    {
        return ['id' => '', 'question' => 'How long does a project take?', 'answer' => 'Usually 4 to 8 weeks.', 'is_active' => 'Yes'];
    }

    public function rules(?Model $existing): array   // normal validation; $existing = record being updated
    {
        return ['question' => ['required', 'string', 'max:255'], 'answer' => ['required', 'string'], 'is_active' => ['nullable', 'boolean']];
    }

    public function saveRow(array $data, ?Model $existing): void   // your saving code
    {
        $faq = $existing ?? new Faq;
        $faq->fill($data)->save();
    }
}
```

`ModuleImport` (`app/Core/Import/ModuleImport.php`) is a normal Laravel Excel import (`OnEachRow`, `WithHeadingRow`, `WithChunkReading`, `ShouldQueue`, `WithEvents`). It does the headings, cleaning, validation, counting, errors and progress, so the module class stays short.

**Customising** (all in the module's import class):

| Need | Do |
|---|---|
| Unique column | `Rule::unique('products', 'sku')->ignore($existing?->getKey())` in `rules()` |
| Match on another column | `protected string $matchBy = 'sku';` or override `find(array $row): ?Model` |
| Relations, stock, several tables | Write it in `saveRow()`. It runs inside a savepoint, so an exception skips only that row |
| Skip emails/side effects during "only check" | `if (! $this->dryRun()) { … }` |
| Who uploaded (also in queued jobs) | `$this->importer()` |
| Bigger/smaller chunks for this module | `public function chunkSize(): int { return 2000; }` |
| Another queue for one module | `public ?string $queue = 'imports-heavy';` (and a worker / Horizon supervisor for it) |
| Any other Laravel Excel feature | Add the concern to the class: `WithMultipleSheets`, `WithStartRow`, `WithCalculatedFormulas`, `WithCustomCsvSettings`… |
| Completely different import | Write a plain Laravel Excel import and call `Excel::import()` / `Excel::queueImport()` yourself in the controller |

### Two kinds of import: row by row, or a chunk at a time

| | `ModuleImport` (row by row) | `ChunkImport` (chunk at a time) |
|---|---|---|
| You write | `rules()` + `saveRow($data, $existing)` for **one** row | `importChunk(Collection $rows)` for **500–1,000** rows |
| Validation | Laravel rules per row, automatic | Yours: check rows, `$this->skip($line, 'reason', $row)` |
| Queries | a few per row | yours: `whereIn()`, one `update()` / `insertOrIgnore()` / `upsert()` for many rows |
| Best for | CRUD: FAQs, users, customers, products with a lookup or two | Big lists, matching against other tables: tags by product name, price lists, stock sync, subscribers |
| In LaunchKit | FAQs, Sliders, Services, Testimonials, Users, Customers, generated modules | **Subscribers** (`app/Modules/Inbox/Imports/SubscribersImport.php`) |

Both share the rest: progress bar, Recent imports, result page, skipped rows download, notification, "only check", background job for big files, the same controller code.

```php
// app/Modules/Inbox/Imports/SubscribersImport.php (shortened)
class SubscribersImport extends ChunkImport
{
    public function columns(): array { return ['email' => 'reader@example.com']; }

    /** $rows keyed by the real Excel row number; values trimmed, empty = null, empty rows removed. */
    public function importChunk(Collection $rows): void
    {
        $emails = [];
        foreach ($rows as $line => $row) {
            $email = strtolower((string) $row->get('email'));
            match (true) {
                ! filter_var($email, FILTER_VALIDATE_EMAIL) => $this->skip($line, "\"{$email}\" is not a valid email address.", $row),
                isset($emails[$email]) => $this->skip($line, "Same email as row {$emails[$email]}.", $row),
                default => $emails[$email] = $line,
            };
        }

        foreach (array_chunk(array_keys($emails), 500) as $batch) {
            $known = DB::table('newsletter_subscribers')->whereIn('email', $batch)->pluck('email')->all();   // 1 query
            $new = array_diff($batch, $known);
            DB::table('newsletter_subscribers')->insertOrIgnore(/* rows for $new */);                         // 1 query
            $this->created(count($new));
            $this->updated(count($known));
        }
    }
}
```

`ChunkImport` helpers: `$this->created($n)`, `$this->updated($n)`, `$this->skip($line, 'reason', $row)`, `$this->dryRun()`, `$this->importer()`. The whole chunk runs in one savepoint: if `importChunk()` throws, that chunk is undone and listed as one error ("Rows 1002–2001 were not imported: …"), the other chunks stay. Extra values for the import (branch, tags…) go in the constructor and travel with the queued job:

```php
class FloristTagsImport extends ChunkImport
{
    public function __construct(?ImportRun $run = null, private int $branchId = 0, private array $tags = []) { parent::__construct($run); }
    …
}
// controller
$run->process(new FloristTagsImport($run, $request->integer('branch_id'), $request->input('tags', [])));
```

Note: a chunk only sees its own rows. "Duplicate of row 12" works inside one chunk; across chunks, check the database (like `$known` above) or keep a set in `Cache::` keyed by the import id.

### The controller (same in every module)

```php
public function importForm(): View
{
    return view('faqs::admin.import', ['columns' => (new FaqsImport)->describe(), 'runs' => ImportRun::recent('faqs')]);
}

public function import(Request $request): RedirectResponse
{
    $request->validate(['file' => ImportRun::fileRules(), 'dry_run' => ['nullable', 'boolean']]);
    $run = ImportRun::start('faqs', $request->file('file'), $request->user(), dryRun: $request->boolean('dry_run'));
    $run->process(new FaqsImport($run));    // small: now · big: queued in chunks
    return redirect()->route('admin.faqs.import')->with('success', 'Import finished: '.$run->summary().'.');
}

public function importTemplate(): BinaryFileResponse
{
    return Excel::download(new TemplateExport(new FaqsImport), 'faqs-import-template.xlsx');
}
```

Routes (`Routes/admin.php`, before `Route::resource`): `faqs/import` (GET, POST) and `faqs/import/template`. Button on the list: `@can('faqs.import') <a href="{{ route('admin.faqs.import') }}">Import</a> @endcan`.

### Export

The list's **Export** button offers **Excel (.xlsx)** and **CSV**, with exactly what the list shows (search, filters, sorting, all pages). In the controller:

```php
->export('faqs', FaqsExport::class, 'faqs.export')       // the module's export class (round-trips with the import)
->export('activity', ['When' => 'created_at', 'User' => 'causer.name'])   // or a quick column list (reports)
```

```php
// app/Modules/Faqs/Exports/FaqsExport.php
class FaqsExport extends ModuleExport
{
    public function headings(): array { return ['ID', 'Question', 'Answer', 'Is active', 'Created']; }

    /** @param  Faq  $faq */
    public function map($faq): array
    {
        return [$faq->id, $faq->question, $faq->answer, $this->yesNo($faq->is_active), $this->date($faq->created_at)];
    }
}
```

`ModuleExport` is `FromQuery` + `WithHeadings` + `WithMapping` + `ShouldAutoSize`: rows are read in chunks, so big tables don't fill memory. Text starting with `=`, `+`, `-` or `@` is never written as a formula (CSV/Excel injection). Add any Laravel Excel concern for styling (`WithStyles`, `WithColumnFormatting`, `WithColumnWidths`…).

**Very large exports** (100k+ rows): queue them instead of downloading in the request:

```php
(new FaqsExport($query))->queue("exports/faqs-{$user->id}.xlsx", 'local')
    ->chain([new NotifyExportReady($user, "exports/faqs-{$user->id}.xlsx")]);   // your job: AdminAlert with a download link
```

### Files

| File | Job |
|---|---|
| `app/Core/Import/BaseImport.php` | What both kinds share: headings, chunk size, counts, skipped rows, progress events |
| `app/Core/Import/ModuleImport.php` | Row-by-row import (`rules()` + `saveRow()`) |
| `app/Core/Import/ChunkImport.php` | Chunk import (`importChunk($rows)`) |
| `app/Core/Import/ImportRun.php` + `ImportRunError.php` | One import: file, status, counts, skipped rows; start / process |
| `app/Core/Import/RunImport.php` | The queued job for big files (timeout, tries, queue name) |
| `app/Core/Export/ModuleExport.php` | Base for module exports |
| `app/Core/Export/TableExport.php` | Quick export from a column list |
| `app/Core/Export/TemplateExport.php`, `FailedRowsExport.php` | Template download, skipped rows download |
| `resources/views/components/admin/import-history.blade.php` | "Recent imports" table (live progress via `resources/admin/js/imports.js`) |
| `app/Modules/Dashboard/Controllers/Admin/ImportRunController.php` | `/admin/imports/{id}` result page + progress JSON |
| `config/excel.php` (package defaults) | Publish with `art vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider" --tag=config` to change CSV delimiter, temp files… |

**Several servers (Auto Scaling):** the queued job may run on another server than the upload. Put the import files on shared storage: set `excel.temporary_files.remote_disk` to `s3` (publish the config) and change `ImportRun::DISK` to an S3 disk.

---

## 24b. Laravel Debugbar (local only)

`fruitcake/laravel-debugbar` is installed as a **dev** dependency: a bar at the bottom of every page while `APP_DEBUG=true`.

| Tab | Shows |
|---|---|
| **Views** | Every Blade file used for this page, e.g. `faqs::admin.import`, `components.admin.card`, `layouts.admin`. Click one to open it in your editor |
| Queries | Every SQL query with time and bindings (spot N+1) |
| Route | Controller + method, middleware, route name |
| Models, Gate, Cache, Timeline | Models loaded, permission checks, cache hits, where time went |

- `.env`: `DEBUGBAR_ENABLED=` (empty = follows APP_DEBUG; `false` hides it), `DEBUGBAR_EDITOR=vscode`.
- Docker: to open files from the bar, set `DEBUGBAR_REMOTE_SITES_PATH=/var/www/html` and `DEBUGBAR_LOCAL_SITES_PATH=D:/launchkit-single/launchkit-single` (your project folder).
- Not on `/api/*`, `/mcp`, `/horizon`, `/docs` (`config/debugbar.php` → `except`).
- Production never has it: the Docker image runs `composer install --no-dev`. Never set `APP_DEBUG=true` on a live server.

## 24c. Is the queue running? (Horizon, workers, logs)

Imports above 1,000 rows, emails, notifications and chat broadcasts all need a running worker. How to check:

| Question | Where |
|---|---|
| Are workers running? | **`/admin/horizon`** (super admin): status *Active* / *Inactive*, supervisors `supervisor-1` and `supervisor-imports`, processes |
| What is waiting / running now? | Horizon → **Pending jobs**, **Completed jobs** (each job's time, queue, payload) |
| What failed and why? | Horizon → **Failed jobs** (full exception, Retry button), or **Admin → System → Monitoring → Failed jobs** |
| Queue size per queue | Horizon → **Metrics** / dashboard "Jobs per minute", "Max wait time" |
| From the terminal | `art horizon:status` (running / paused / inactive), `art queue:monitor redis:default,redis-imports:imports --max=100` |
| Health check | `art health:check` → "Queue workers (Horizon): running" (also in Monitoring) |

Worker logs:

```bash
docker compose ps horizon                      # Up? (restarts automatically: restart: unless-stopped)
docker compose logs -f horizon                 # live: "Processing App\Core\Import\RunImport", "Processed", errors
docker compose logs --since 1h horizon         # last hour
docker compose restart horizon                 # after changing job code (workers keep old code in memory!)
docker compose exec app php artisan horizon:terminate   # graceful restart (finishes the current job first)
```

Application errors from jobs also go to `storage/logs/laravel.log` and **Monitoring → Error log** (`/admin/logs` for the raw files).

**SQS (production):** Horizon only works with Redis, so on SQS there is no Horizon dashboard:
- workers are `php artisan queue:work sqs --queue=default` and `--queue=imports --timeout=3600`, kept alive by Supervisor / systemd (or one container each);
- logs: `journalctl -u laravel-worker -f` or the container logs; failed jobs: Monitoring → Failed jobs;
- queue length: AWS console → SQS → "Messages available / in flight", or a CloudWatch alarm on `ApproximateAgeOfOldestMessage`.

**Remember:** after deploying new code, restart workers (`art queue:restart` or `art horizon:terminate`), otherwise they keep running the old code.

---

## 25. REST API (Sanctum)

`/api/v1/...` serves mobile apps, React/Vue frontends and other systems. It uses the **same permissions** as the admin panel.

**For app developers:** hand them `API_GUIDE.md`. It covers the sign-in flow, permissions per role, every endpoint, error handling, React Native examples, and how to add customers (e-commerce) separately from staff. Interactive docs are at **`/docs/api`**, and the OpenAPI file for Postman is at **`/docs/api.json`**. Both are generated from the code by `dedoc/scramble` (`config/scramble.php`).

### 25.1 Getting a token

**Option A: from a script or app (email + password)**

```bash
curl -X POST http://localhost:8080/api/v1/auth/token \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"Admin@12345","device_name":"My phone"}'
# → {"token":"3|abc…","token_type":"Bearer","expires_at":null,"user":{…,"permissions":[…]}}
```

- If the account has 2FA, also send `"code": "123456"` (or a recovery code).
- If Settings → Security requires 2FA for admins and it isn't set up yet, there's no token.
- This sign-in is recorded in the sign-in history and can trigger the new-device email.
- Limited to 5 attempts per minute.

**Option B: from the admin panel.** Go to **Profile → Security → API tokens**, give the token a name, choose **Full access** or **Read only** and an expiry, and copy the token. It's shown once.

### 25.2 Using it

```bash
TOKEN="3|abc…"
curl http://localhost:8080/api/v1/me -H "Authorization: Bearer $TOKEN"
curl "http://localhost:8080/api/v1/faqs?status=1&sort=question&dir=asc&per_page=20" -H "Authorization: Bearer $TOKEN"
curl -X POST http://localhost:8080/api/v1/faqs -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"question":"From the app?","answer":"Yes","is_active":true}'
curl -X DELETE http://localhost:8080/api/v1/auth/token -H "Authorization: Bearer $TOKEN"   # sign out (revoke)
```

### 25.3 Endpoints

| Method + URL                                  | Permission         |
| --------------------------------------------- | ------------------ |
| `POST /api/v1/auth/token`                     | none (credentials) |
| `GET /api/v1/me`, `DELETE /api/v1/auth/token` | any token          |
| `GET/POST /api/v1/faqs`, `GET/PUT/DELETE /api/v1/faqs/{id}` | `faqs.view/create/edit/delete` |
| Same for `sliders`, `services`, `testimonials` | that module's permissions |
| `GET/POST /api/v1/blog/posts`, `GET/PUT/DELETE /api/v1/blog/posts/{id}` (`?lang=ta` for the translation) | `blog.*` (publishing needs `blog.publish`) |
| `GET /api/v1/blog/categories`                 | `blog.view`        |
| `GET /api/v1/media`, `POST /api/v1/media` (multipart `file`) | `media.view` / `media.create` |

**Lists** take the same query string as the admin tables: `q`, `sort`, `dir`, `per_page`, `page`, `status`, filters, `from`, `to`. The response looks like this:

```json
{ "data": [ { "id": 1, "question": "…", "created_at": "2026-11-18T10:00:00+05:30" } ],
  "links": { "first": "…", "last": "…", "prev": null, "next": "…" },
  "meta": { "current_page": 1, "per_page": 15, "total": 42, "last_page": 3 } }
```

**Errors** are always JSON:

| Status | Meaning                                                            |
| ------ | ------------------------------------------------------------------ |
| `401`  | No token, or the token is invalid                                  |
| `403`  | Missing permission, or a read-only token tried to change something |
| `422`  | Validation failed: `{"message": "…", "errors": {"question": ["…"]}}` |
| `429`  | Too many requests (Settings → Security → API requests per minute)  |
| `503`  | The API is turned off in Settings                                  |

### 25.4 How it's wired

| Piece                                         | Job                                                              |
| --------------------------------------------- | ---------------------------------------------------------------- |
| `laravel/sanctum`, `HasApiTokens` on `User`   | Personal access tokens (`personal_access_tokens` table)          |
| `routes/api.php`                              | `/api/v1/auth/token`, `/api/v1/me`                               |
| Module `Routes/api.php`                       | Loaded by `ModuleServiceProvider` at `/api/v1`, names `api.*`, middleware group `api.staff` |
| Group `api.staff` (`bootstrap/app.php`)          | `api` → `auth:sanctum` → `api.access` → `throttle:api`           |
| `ForceJsonResponse`                           | API errors are JSON even without an `Accept` header              |
| `ApiAccess`                                   | API on/off switch, inactive users blocked, read-only tokens can only GET |
| `DataTable::json(Resource::class)`            | Same filters as the admin list, paginated JSON                   |
| Module `Resources/XResource.php`              | The JSON shape of one record. Edit it to rename or hide fields   |

### 25.5 API for a new module

```bash
art make:module Product --fields="name:string,price:decimal,is_active:boolean" --api --migrate
```

This creates `Controllers/Api/ProductController.php`, `Resources/ProductResource.php` and `Routes/api.php` (`Route::apiResource`). Create and update reuse the admin `Store/UpdateProductRequest`, so validation is identical. Use **PUT with every required field**: partial updates aren't supported by default.

**API for an existing module:** copy those three files from a generated module, such as `app/Modules/Faqs`, and rename them.

### 25.6 Settings (Security tab)

| Setting                                 | Default |
| --------------------------------------- | ------- |
| Allow API access                        | On      |
| API requests per minute (per user)      | 60      |
| App sign-in tokens expire after (days)  | 0 = never (profile tokens choose their own expiry) |

---

## 26. Multi-language website

The **public website** can be shown in several languages. The admin panel stays in English.

### 26.1 Turning it on

1. Go to **Settings → Languages**. Set the **main language** (what your content is written in) and tick the extra ones, for example Tamil, Hindi or Arabic.
2. A language switcher appears in the website header. Visitors' choice goes into the URL as `?lang=ta` and is remembered in a cookie. First-time visitors get their browser's language if it's enabled.
3. Arabic (and any language with `'dir' => 'rtl'`) turns the layout right-to-left.

The available languages are listed in `config/starter.php` → `locales`. Add any language there with `name`, `native` and `dir`.

### 26.2 What gets translated, and how

| Content                   | How                                                                                                   |
| ------------------------- | ----------------------------------------------------------------------------------------------------- |
| **Pages**                 | Pages list → **+ TA** badge → a copy of the page is created as a **draft translation** → translate the blocks in the builder → publish |
| **Blog posts**            | Same: **+ TA** on the posts list                                                                      |
| **Website menu labels**   | Menu builder → edit item → "Label in Tamil" fields                                                    |
| **Fixed website text** ("Get in touch", "Read more", "Subscribe"…) | `lang/ta.json`, `lang/hi.json`, `lang/ar.json`                 |
| **Settings text** (tagline, footer text, address) | Not translated: one value for all languages                                   |

**Rules:**
- A translation is shown **only when it's published**. Otherwise visitors see the main-language version.
- Translations have **no URL of their own**. `/about-us?lang=ta` shows the Tamil version of `/about-us`. Old links to a post translation redirect there.
- The **lists** in the admin show only main-language records, with language badges: green = published, grey = draft, **+** = missing.
- **Deleting** the original deletes its translations; restoring it from the trash restores them.
- The website `<head>` gets `hreflang` links for search engines.

### 26.3 Adding fixed text in a new language

Create `lang/ml.json` (copy `lang/ta.json` and translate the values):

```json
{
    "Get in touch": "ബന്ധപ്പെടുക",
    "Read more": "കൂടുതൽ വായിക്കുക"
}
```

In Blade, wrap any website text in `__()`:

```blade
<a class="btn-brand">{{ __('Get in touch') }}</a>
{{ __(':minutes min read', ['minutes' => $post->readingMinutes()]) }}
```

A string with no translation shows in English.

### 26.4 Translations for your own model

```php
use App\Core\Localization\HasTranslations;

class Product extends Model
{
    use HasTranslations;   // needs columns: locale (nullable string), translation_of (nullable FK to same table)
}
```

```php
// migration
$table->string('locale', 10)->nullable();
$table->foreignId('translation_of')->nullable()->constrained('products')->cascadeOnDelete();

// website controller
$product = Product::query()->originals()->where('slug', $slug)->firstOrFail()->localized();

// admin: create a translation
$translation = $product->makeTranslation('ta');
```

**Pieces:**

| File                                         | Job                                                           |
| -------------------------------------------- | ------------------------------------------------------------- |
| `app/Core/Localization/Locales.php`          | Enabled languages, default, direction, switch URLs            |
| `app/Http/Middleware/SetWebsiteLocale.php`   | Picks the language for each website request (`website` middleware group) |
| `app/Core/Localization/HasTranslations.php`  | `translations`, `localized()`, `makeTranslation()`, `originals()` |
| `resources/views/components/admin/translation-links.blade.php` | The language badges in admin lists        |
| `resources/views/components/admin/translation-banner.blade.php` | "Tamil translation of …" on edit forms   |
| `lang/*.json`                                | Fixed website text                                            |

---

## 27. Sign-in history and new-device alerts

Every sign-in, failed attempt, sign-out and lockout is recorded in the `login_events` table, from both the website login and API tokens.

| Where                                   | What you see                                                                                   |
| --------------------------------------- | ---------------------------------------------------------------------------------------------- |
| **Users → Sign-in history** (`users.view`) | Data table: when, event, account, IP, device. Filters for event, "new device only" and dates. CSV export |
| **Profile → Security → Recent sign-ins** | The user's last 10 sign-ins and failed attempts                                               |
| **Profile → Security → Active sessions** | Devices signed in now, with "Sign out other sessions"                                         |

**New device:** a successful sign-in from a browser, operating system and network (first 3 parts of the IP) that the account hasn't used before is marked **New device**. The user gets an email (`NewDeviceSignIn`). An account's very first sign-in doesn't count.

**Lockout:** when an email reaches the sign-in limit (Settings → Security → attempts per minute), a **lockout** is recorded and admins with `users.view` get a live bell alert.

**Settings → Security:**

| Setting                                         | Default |
| ----------------------------------------------- | ------- |
| Email users about sign-ins from a new device    | On      |
| Keep sign-in history for (days)                 | 180 (pruned daily by `model:prune`) |

**Pieces:** `app/Core/Security/LoginRecorder.php` (listens to Laravel's `Login`, `Failed`, `Logout` and `Lockout` events, registered in `AppServiceProvider`), `app/Core/Security/UserAgent.php` (turns a browser string into "Chrome on Windows"), `app/Models/LoginEvent.php`, `app/Notifications/NewDeviceSignIn.php`, `app/Modules/Users/Controllers/Admin/LoginHistoryController.php`.

---

## 28. Customer accounts (website + app)

**Module:** `app/Modules/Customers`. Customers are the people who buy, book or join: they are **not staff**. They have their own table (`customers`), their own sign-in (guard `customer`) and their own API (`/api/v1/shop`). A customer can never open the admin panel, and staff never show up as customers.

### 28.1 What each side gets

| Who | Where | What |
| --- | --- | --- |
| **Customer, website** | `/account/login`, `/account/register` | Sign up, sign in, forgot password, two-factor code |
| | `/account` | Profile details, change password, delete account |
| | `/account/addresses` | Saved addresses, default address |
| | `/account/security` | Two-factor login (QR code + recovery codes), signed-in apps, recent sign-ins |
| **Customer, mobile app** | `/api/v1/shop/...` | The same features as JSON. See `API_GUIDE.md` section 9 |
| **Staff, admin panel** | Customers → All customers | Data table: search, status / email / 2FA / marketing filters, dates, CSV export, trash, bulk block/activate/delete, CSV import |
| | Customer profile page | Details, addresses (add/edit/remove), recent sign-ins, signed-in apps; buttons: **send password reset link**, **resend / mark email confirmed**, **reset 2FA**, **block / unblock**, **sign out of the app**, **sign in as customer** |
| | Customers → Sign-in history | Every customer sign-in and failed attempt |
| | Settings → Customers | On/off switches (below) |

The website header shows **Sign in** / **My account** automatically.

### 28.2 Permissions

`customers.view`, `create`, `edit`, `delete`, `export`, `import`, `impersonate`. Only super-admin has them after install. Tick them for other roles in **Roles**. `impersonate` ("Sign in as customer") is the sensitive one: give it to support staff only.

### 28.3 Settings → Customers

| Setting | Default | Effect |
| --- | --- | --- |
| Customer accounts | On | Off = `/account/*` returns 404, `/api/v1/shop` returns 503, header link hidden |
| Allow customers to sign up | On | Off = only staff add customers |
| Customers must confirm their email | On | Unconfirmed customers only see "Check your inbox" (website) / 403 on addresses (API) |
| Let customers turn on two-factor login | On | Optional per customer. Staff can always reset it |
| Send a welcome email after sign up | On | `CustomerWelcome` |
| Email customers about sign-ins from a new device | On | Same `NewDeviceSignIn` email as staff, linking to `/account/security` |
| Notify staff about new sign ups | Off | Bell alert for everyone with `customers.view` |
| App sign-in lasts (days) | 365 | Expiry of app tokens. 0 = never |

### 28.4 How it is built

| Piece | File |
| --- | --- |
| Guard `customer`, provider and password broker `customers` | `config/auth.php` |
| Model (2FA, tokens, soft deletes, activity log) | `Models/Customer.php`, `Models/CustomerAddress.php` |
| Tables `customers`, `customer_addresses`, `customer_password_reset_tokens`, `login_events.customer_id` | `Database/Migrations/2026_11_19_100000_create_customers_tables.php` |
| Shared rules: sign up, 2FA code check, token expiry | `Support/CustomerAccounts.php` |
| Website controllers | `Controllers/Web/{Auth,Password,Verification,Account,Address,Security}Controller.php` |
| API controllers | `Controllers/Api/{Auth,Profile,TwoFactor,Address}Controller.php` |
| Admin controllers | `Controllers/Admin/{Customer,CustomerAction,CustomerAddress,CustomerLogin}Controller.php` |
| Middleware | `Http/Middleware/`: `customer.enabled`, `customer.active`, `customer.verified`, `customer.api` |
| Emails | `Notifications/CustomerVerifyEmail`, `CustomerResetPassword`, `CustomerWelcome` |
| Website views | `Views/web/auth/*`, `Views/web/account/*` (Tailwind, every string in `__()` for translations) |
| Module JS (`data-confirm`, copy recovery codes) | `Resources/js/web/customers.js`, loaded by `resources/web/web.js` |
| Demo data | `Database/Seeders/CustomerSeeder.php` (40 customers, `demo@customer.test` / `Secret#123`) |

**Two-factor** uses Fortify's actions (`EnableTwoFactorAuthentication`, `ConfirmTwoFactorAuthentication`…) on the `Customer` model, exactly like staff, but with the customer's own pages and routes. The sign-in check is `CustomerAccounts::validTwoFactorCode()`: a 6-digit code or a one-time recovery code.

**Sign-in history** shares the `login_events` table with staff: `guard = 'customer'` and `customer_id` set. `LoginEvent::staff()` and `LoginEvent::customers()` keep the two lists apart.

**Sign in as customer:** staff click the button and the website opens in a new tab as that customer. It uses the `customer` guard, so the admin session stays signed in. It's logged in the activity log, not in the customer's sign-in history, and the amber bar on the website ends it.

**Same browser, two accounts:** a staff member can be signed in to the admin panel and as a customer on the website at the same time. Customer sign-out only ends the customer session.

### 28.5 Using customers in your own modules

```php
// The signed-in customer on a website page (route inside the customer group or any web route):
$customer = auth('customer')->user();          // null when signed out

// Protect your own website pages:
Route::middleware(['customer.enabled', 'auth:customer', 'customer.active', 'customer.verified'])
    ->prefix('account')->name('customer.')->group(function () {
        Route::get('orders', [MyOrdersController::class, 'index'])->name('orders'); // /account/orders
    });

// Link a model to a customer:
$table->foreignId('customer_id')->constrained()->cascadeOnDelete();
public function orders(): HasMany { return $this->hasMany(Order::class); }   // add to Customer
```

Add the new page to the account menu in `Views/web/account/layout.blade.php` (`$nav`). For the app, add `Routes/shop.php` to your module (`API_GUIDE.md` section 9.3).

### 28.6 Try it

```bash
docker compose exec app php artisan db:seed --class="App\Modules\Customers\Database\Seeders\CustomerSeeder"
```

- Website: `http://localhost:8080/account/login`, `demo@customer.test` / `Secret#123`
- Admin: **Customers → All customers**
- Emails (sign up, confirm email, reset password): Mailpit `http://localhost:8025`
- API: `/docs/api` → "Shop · Sign in" → `POST /shop/auth/token`

---

## 29. Monitoring: errors, failed jobs, health checks

Production setup and deploys are in **`DEPLOY.md`**. This section is how the monitoring parts work in the code.

### 29.1 Error log

| Piece | File |
| --- | --- |
| Hook: every reported exception | `bootstrap/app.php` → `$exceptions->report(... ErrorRecorder::record($e))` |
| Saves, counts, alerts | `app/Core/Monitoring/ErrorRecorder.php` |
| Table / model | `error_logs` / `app/Models/ErrorLog.php` (pruned daily by `model:prune`) |
| Email | `app/Notifications/ErrorOccurred.php` |
| Admin pages | `app/Modules/Monitoring` (Error log, Failed jobs), permissions `monitoring.view`, `monitoring.manage` |
| Settings | **Settings → Monitoring**: on/off, bell, email address, keep days |

**One row per distinct error.** The fingerprint is *exception class + the first file:line in your code* (outside `vendor/`). The message is not part of it, so "Order 15 not found" and "Order 16 not found" are the same row with a counter. Database errors, which Laravel always throws from the same line in `vendor/`, are told apart by the line in `app/` that ran the query. New and reopened errors alert at most once an hour each.

**What counts as an error:** whatever Laravel *reports*. Not reported, never listed: 404, 403, validation errors, `AuthenticationException`, CSRF expiry (419), and anything you add to `$exceptions->dontReport([...])`.

**Report something yourself without crashing the page:**

```php
try {
    $gateway->charge($order);
} catch (GatewayException $e) {
    report($e);                       // → error log + alert, and the code carries on
    return back()->with('error', 'Payment failed, please try again.');
}
```

**Sentry:** `composer require sentry/sentry-laravel` + `SENTRY_LARAVEL_DSN`. `bootstrap/app.php` detects the package and hands errors to it too (`DEPLOY.md` 7.3).

### 29.2 Failed jobs

Laravel stores jobs that failed after all `$tries` in `failed_jobs`. **System → Monitoring → Failed jobs** lists them (model `app/Modules/Monitoring/Models/FailedJob.php`), with the error and payload, **Retry** (`queue:retry`) and **Delete** (`queue:forget`). Pruned after 7 days (`queue:prune-failed` in `routes/console.php`). Horizon (`/admin/horizon`) shows the same jobs with Redis metrics.

### 29.3 Health checks

`app/Core/Health/HealthCheck.php`:

| Check | Critical (fails `/up`) |
| --- | --- |
| Database (`select 1`) | Yes |
| Cache (write + read) | Yes |
| Storage folders writable | Yes |
| Redis ping | Warning only |
| Horizon running | Warning only |
| Scheduler ran in the last 5 minutes | Warning only |
| Disk space ≥ 10 % free | Warning only |

- `/up` is Laravel's health route. `AppServiceProvider` listens to its `DiagnosingHealth` event and throws when a critical check fails, so it answers 500.
- `php artisan health:check` prints every check (`app/Core/Console/HealthCheckCommand.php`).

**Add a check:**

```php
self::check('Payment gateway', false, fn () => Http::timeout(3)->get('https://api.gateway.com/ping')->ok() ? 'reachable' : throw new \RuntimeException('Not reachable')),
```

Make it critical (`true`) only if the website is useless without it.

### 29.4 Log files (opcodesio/log-viewer)

**System → Monitoring → Log files** (`/admin/logs`, follows `ADMIN_PATH`) shows everything in `storage/logs`:

- every daily file (`laravel-2026-11-19.log`), plus sub-folders for extra channels
- each entry with its level (error, warning, info…), time, message and context
- stack traces as a readable list; vendor frames are hidden by default (toggle in ⚙)
- filter by level, search one file or **all files**, link to one entry
- download or delete files

| Permission | Allows |
| --- | --- |
| `logs.view` | Open the viewer |
| `logs.download` | Download a file or a folder (zip) |
| `logs.delete` | Delete files |

Wiring: `config/log-viewer.php` (path, `admin` middleware group, back link), `AppServiceProvider` (`LogViewer::auth` and the download/delete gates), and `SecurityHeaders` (no CSP on `/admin/logs`, because the viewer uses an inline script). The assets are in `public/vendor/log-viewer`; `composer update` republishes them (`post-update-cmd`).

**A separate log per topic (channel)** shows up in the viewer as its own folder automatically:

```php
// config/logging.php → 'channels'
'payments' => [
    'driver' => 'daily',
    'path' => storage_path('logs/payments/payments.log'),   // → storage/logs/payments/payments-2026-11-19.log
    'level' => 'debug',
    'days' => 30,
],

// anywhere
Log::channel('payments')->info('Razorpay webhook received', ['order' => $order->id, 'event' => $event]);
Log::channel('payments')->error('Signature mismatch', ['order' => $order->id]);
```

### 29.5 Error log or log files?

| | Error log (built in) | Log files (viewer) |
| --- | --- | --- |
| Source | Saved to the database **at the moment** an exception happens (`report()`), never read from files | Reads the `.log` files in `storage/logs` |
| Contains | Exceptions only: unhandled 500s and `report($e)`, from pages, API, queue jobs and artisan | Everything written to a log: exceptions **and** `Log::error/warning/info/debug(...)`, deprecations, every channel |
| Grouping | One row per distinct error, with a counter | One line per occurrence |
| Alerts | Bell + email on new and returning errors | None |
| Workflow | Open → fix → **Mark as fixed** → reopens if it comes back | Read, search, download |
| Use it for | "What is broken right now?" | "What happened around 10:42?", your own `Log::` messages, debugging one request |

The error page has **Find in log files**: it opens the viewer already searching for that message, so you can see the log lines around it.

### 29.6 Scheduled housekeeping (`routes/console.php`)

| Command | When | What |
| --- | --- | --- |
| `model:prune` (`LoginEvent`, `ErrorLog`) | 03:45 | Old sign-in history and old errors |
| `sanctum:prune-expired --hours=24` | 04:00 | Expired API / app tokens |
| `queue:prune-failed --hours=168` | daily | Failed jobs older than 7 days |

## 30. Uploads disk (local or S3) and SaaS mode

**Uploads.** Everything users upload goes to the `uploads` disk (`config/filesystems.php`): `UPLOADS_DISK=public` keeps files in `storage/app/public` (URL `/storage/...`), `UPLOADS_DISK=s3` puts them in your bucket under `AWS_UPLOADS_PREFIX`. The media library uses it too (`MEDIA_DISK`, default `uploads`). In your own code write `Storage::disk('uploads')`, never `public`, so switching to S3 is one `.env` line. Tests: `Storage::fake('uploads')`.

**SaaS mode** is off unless `STARTER_TENANCY=true`. Everything about it is in `SAAS.md`. What a developer needs to know:

- `App\Core\Tenancy\Tenancy` helpers: `enabled()`, `inTenant()`, `tenant()`, `central(fn () => ...)`.
- `app/Http/Middleware/IdentifyTenant.php` runs first in the `web` and `api` groups and picks the tenant from the host name.
- `module.json` keys `central`, `tenant`, `saas_only` decide where a module exists; `ModuleManager::isEnabled()` also checks the tenant's plan. Use `module_enabled('slug')` in Blade instead of `Route::has()`.
- Central-only models (`Plan`, `Subscription`, `Invoice`, `TenantUsage`) use stancl's `CentralConnection` trait, so they read the central database even inside a tenant.
- Tenant databases are migrated with every module's `Database/Migrations` except Saas (`config/tenancy.php` → `migration_parameters`). Run `php artisan tenants:migrate` after adding a migration.
- Scheduled tasks that touch tenant data go through `$perTenant(...)` in `routes/console.php`.
- Tests: `tests/Feature/SaasTest.php` (central domain `saas.test`, tenant databases as SQLite files) and `ConvertToTenancyTest.php`.

## 31. AI & MCP

Module `app/Modules/Ai`, full guide in `AI.md`. In short:

- `POST /mcp` is a Laravel MCP server (`Mcp/SiteServer.php`) behind `api` + `auth:sanctum` + `module:ai` + `McpAccess` (Settings > AI switch, staff, active, `ai.mcp`). SaaS: per tenant host.
- Tools extend `Mcp/Tools/SiteTool.php` (`$module`, `$permission`, `$writes`, `run()`) and are listed in `SiteServer::TOOLS`. The same classes feed the Laravel AI SDK assistant (`Agents/SiteAssistant.php` through `Agents/AgentTool.php`).
- Provider, model and key: Settings > AI (key encrypted, per tenant) or `.env` / `config/ai.php`. Call `AiSettings::apply()` before using an agent in your own code.
- Tests: `SiteServer::actingAs($user)->tool(Tool::class, [...])`, `SiteAssistant::fake([...])`.

---

## 32. Passkeys and request-forgery protection

### Passkeys (fingerprint, Face ID, Windows Hello, phone)

Staff and customers can sign in with one tap instead of a password. A passkey is a key pair: the private half never leaves the device and is unlocked with the fingerprint / face / PIN, so there is nothing to phish or leak. It counts as two-factor: with *Settings → Security → Require two-factor* on, a user with a passkey is not forced to set up an authenticator app.

| | Staff (admin panel) | Customers (website) |
| --- | --- | --- |
| Sign in | "Sign in with a passkey" on `/admin/login` | same on `/account/login` |
| Add / remove | Profile → Security → Passkeys | My account → Security → Passkeys |
| Table | `passkeys` | `customer_passkeys` (model `CustomerPasskey`) |
| Switch | Settings → Security → *Passkey sign-in for staff* | Settings → Customers → *Passkey sign-in for customers* |
| Routes | `passkey.login-options`, `passkey.login`, `admin.profile.passkeys.*` | `customer.passkey.*`, `customer.passkeys.*` |

How it fits together:

1. **`app/Core/Security/PasskeyService.php`** wraps laravel/passkeys. `for(kind, request)` picks the tables and binds passkeys to the **current host** (each tenant's own address in SaaS mode). The WebAuthn challenge is kept in the session between the two steps.
2. **Controllers**: `app/Http/Controllers/Auth/PasskeyController.php` (staff) and `app/Modules/Customers/Controllers/Web/PasskeyController.php`. Sign-in refuses inactive staff and blocked customers. Adding a passkey asks for the account password first.
3. **Browser**: `resources/shared/passkeys.js`, no npm package. It uses the browser's own `PublicKeyCredential.parseRequestOptionsFromJSON()` and `credential.toJSON()`. Markup hooks: `data-passkey-login`, `data-passkey-register`, `data-passkey-supported` (hidden on old browsers).

Rules worth knowing:
- Passkeys need **HTTPS**, or `localhost` / `*.localhost` while developing.
- A passkey works only on the domain it was added on. If you move a site to a new domain, users add their passkey again (the password still works).
- Passwords stay. Passkeys are an extra, faster way in, never the only one.

### PreventRequestForgery (Laravel 13)

`bootstrap/app.php`:

```php
$middleware->preventRequestForgery(except: ['billing/webhooks/*'], originOnly: false, allowSameSite: false);
```

- Modern browsers send `Sec-Fetch-Site`. A POST/PUT/DELETE is accepted when it says `same-origin`.
- No header (old browser, curl): the CSRF token is checked as before, so keep `@csrf` in forms.
- `allowSameSite: false`: `acme.example.com` can't post to `laravelnews.example.com`, even though they share a parent domain.
- Payment webhooks are excluded because Razorpay / Stripe sign them.
- To exclude another path (an incoming webhook from a service), add it to `except` and verify that service's signature in the controller.

### Test it by hand

**A fingerprint on any PC:** use Windows Hello (Chrome / Edge pick it up on localhost), or Chrome's virtual authenticator: F12 → ⋮ → More tools → **WebAuthn** → *Enable virtual authenticator environment* → Add (`ctap2`, `internal`, resident keys ✓, user verification ✓). Keep DevTools open.

| # | Do | Expected |
|---|---|---|
| 1 | Profile → Security → Passkeys, wrong password → Add passkey | "The password is incorrect" |
| 2 | Right password, name `My laptop`, approve | "Passkey added", listed with "Last used never" |
| 3 | Sign out → `/admin/login` → Sign in with a passkey | Dashboard without typing; Activity log "Signed in with a passkey"; Sign-in history row |
| 4 | Click the button, then Cancel the prompt | No error, button works again |
| 5 | Settings → Security → Require two-factor on; a user with a passkey but no 2FA app signs in | Not forced to 2FA setup |
| 6 | Suspend that user, passkey sign-in | "This account cannot sign in" |
| 7 | Settings → Security → Passkey sign-in for staff off | Button and Passkeys card gone |
| 8 | Remove the passkey, try it | Refused; password still works |
| 9 | Customer: `/account/login` → My account → Security → Add passkey → sign out → passkey sign-in | Lands on My account |
| 10 | Customer passkey on `/admin/login` | Not accepted (separate tables) |
| 11 | Admin → Customers → block that customer, passkey sign-in | Refused |
| 12 | SaaS: passkey added on `laravelnews.localhost`, open `acme.localhost/admin` | Browser doesn't offer it (passkeys are per address) |

**Button not showing?** Use `localhost`, not `127.0.0.1` (passkeys need localhost, `*.localhost` or HTTPS); `npm run build` + Ctrl+F5; the page must have `<meta name="csrf-token">`; browser Chrome/Edge 129+ or Safari 18+ (F12 console: `PublicKeyCredential.parseRequestOptionsFromJSON` must exist).

**Request forgery by hand:**
```bash
curl -i -X POST http://localhost:8090/admin/login -d "email=a@b.c&password=x"                                   # 419
curl -i -X POST http://localhost:8090/admin/login -H "Sec-Fetch-Site: cross-site" -d "email=a@b.c&password=x"   # 419
```

Automated tests: `tests/Feature/PasskeyTest.php`, `tests/Feature/RequestForgeryTest.php`.

---

## 33. Team chat (staff, realtime)

One-to-one chat between staff users inside the admin panel: text, emoji, photos (also paste a screenshot), documents and voice notes, with typing indicator, read ticks and an unread badge in the top bar. Module `app/Modules/Chat`, menu **Main → Team chat**.

### Who can chat

| Permission | Lets the user |
|---|---|
| `chat.view` | Use chat: see their conversations, reply, send photos / files / voice notes. Others can find them. |
| `chat.start` | Also search all staff (name or email) and **start** a new conversation with anyone |

- Super admin and Admin have both. New installs give Editor `chat.view` (reply only).
- To let one editor start chats: Users → key icon → tick `chat.start` (or tick it on the role for all editors).
- Only **active** users with `chat.view` appear in search; you can't message someone who couldn't read it.
- Someone without `chat.start` can still reopen an existing conversation with the same person.

### Settings → Team chat

Allow images · Allow voice notes · Allow documents · Largest file (MB, default 10) · Delete messages after (days, 0 = keep forever; pruned nightly with `model:prune`).

### Files: private, local or S3

Chat files go on their own **private** disk `chat` (`config/filesystems.php`), never on the public `/storage` folder:

```dotenv
CHAT_DISK=           # empty: s3 when UPLOADS_DISK=s3, otherwise local
CHAT_DISK=local      # storage/app/private/chat/…
CHAT_DISK=s3         # your bucket (AWS_* keys) under AWS_CHAT_PREFIX=chat
```

Paths: `central/2026/11/<random>.png` or, in SaaS mode, `tenantacme/2026/11/…`. Files are served by `GET /admin/chat/attachments/{message}` only to the two people in the conversation. Photos and voice notes open inline; documents always download (nothing else is rendered on your domain). Allowed types: `ChatSettings::IMAGE`, `AUDIO`, `FILE`. Moving from local to S3 later: `aws s3 sync storage/app/private/chat s3://BUCKET/chat`, then set `CHAT_DISK=s3`.

### Realtime

- With Reverb (`BROADCAST_CONNECTION=reverb`): events `chat.message`, `chat.read`, `chat.typing`, `chat.deleted` go to each person's private user channel (the same one as bell notifications, tenant-safe). They are sent immediately, not queued. If Reverb is down, the message is still saved.
- Without Reverb (`log`): the chat page polls every 4 s, so it still works, just not instantly.
- Other admin pages: the top-bar chat icon counts up and a toast shows "Name: message" with a soft sound.

### Voice notes

The mic button uses the browser's MediaRecorder (Chrome/Edge/Firefox: webm, Safari: m4a), max 5 minutes. Needs **HTTPS or localhost**, and the browser asks for microphone permission once. The `Permissions-Policy` header allows `microphone=(self)` and CSP allows `media-src 'self' blob:`.

### Code

| What | Where |
|---|---|
| Routes (`admin.chat.*`) | `app/Modules/Chat/Routes/admin.php` |
| Controller | `Controllers/Admin/ChatController.php` |
| Models | `Conversation` (direct now, participants table ready for groups), `Participant` (last read), `Message` (soft delete = "This message was deleted") |
| Realtime event | `Events/ChatEvent.php` |
| Files / settings | `Support/ChatFiles.php`, `Support/ChatSettings.php` |
| Page + JS + CSS | `Views/admin/index.blade.php`, `Resources/js/admin/chat.js`, `chat.css` |

### Test it by hand

Two browsers (or Chrome + an incognito window), two users: Super admin and an Editor.

| # | Do | Expected |
|---|---|---|
| 1 | Super admin → Team chat, type the editor's name | "Start a chat with" lists them (role + email) |
| 2 | Click them, type `Hi 👋` + Enter | Message on the right with ✓ |
| 3 | Editor (other browser), any admin page | Top-bar chat icon shows **1**, toast "Bala: Hi 👋" (with Reverb) |
| 4 | Editor opens the chat | Message there; the sender's ✓ turns into blue ✓✓ |
| 5 | Editor types | Sender sees "typing…" under the name (with Reverb) |
| 6 | Emoji button, 📎 a photo, a PDF, paste a screenshot (Ctrl+V) | Photo inline, PDF as a download card |
| 7 | Mic → speak → Send | Voice note with a player on both sides |
| 8 | Hover your own message → trash | "This message was deleted" for both; the file is removed |
| 9 | Editor searches a name | Not possible ("Search chats…" only filters their chats) |
| 10 | Users → key icon on the editor → tick `chat.start` | Editor can now start chats |
| 11 | Settings → Team chat → Allow images off, send a photo | "This kind of file can't be sent in chat." |
| 12 | Copy a photo's address, open it in a browser signed in as a third user | 404 |
| 13 | `BROADCAST_CONNECTION=log`, `art optimize:clear` | Chat still works, new messages appear within ~4 s |

Automated tests: `tests/Feature/ChatTest.php` (11) and `SaasTest::test_team_chat_inside_a_tenant`.

