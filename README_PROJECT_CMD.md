# Project Command Guide

Every command you need day to day: running the stack, creating modules, changing existing modules, migrations, `module.json` and sync, queues, backups, tests and deployment.

> All commands run **inside the `app` container**. Examples use the `art` shortcut set up in section 1.
> Without Docker, replace `art` with `php artisan`.

---

## Contents

1. [Shortcuts](#1-shortcuts)
2. [Start, stop and first install](#2-start-stop-and-first-install)
3. [How a module works](#3-how-a-module-works)
4. [Create a new module](#4-create-a-new-module)
5. [Add a field to an existing module](#5-add-a-field-to-an-existing-module)
6. [Add a new table, model or controller to an existing module](#6-add-a-new-table-model-or-controller-to-an-existing-module)
7. [module.json and module:sync](#7-modulejson-and-modulesync)
8. [Enable, disable or remove a module](#8-enable-disable-or-remove-a-module)
9. [Database commands](#9-database-commands)
10. [Queues, scheduler, realtime, backups](#10-queues-scheduler-realtime-backups)
11. [Front-end assets](#11-front-end-assets)
12. [Tests and code quality](#12-tests-and-code-quality)
13. [Cache and optimisation](#13-cache-and-optimisation)
14. [Deploying to a server](#14-deploying-to-a-server)
15. [Troubleshooting](#15-troubleshooting)
16. [Command reference (one page)](#16-command-reference-one-page)
17. [Trash, import, API, languages, sign-in history](#17-trash-import-api-languages-sign-in-history)
18. [Customer accounts](#18-customer-accounts)
19. [Monitoring and health](#19-monitoring-and-health)

---

## 1. Shortcuts

Add these to `~/.bashrc` (Git Bash / Linux / macOS) so you can type `art migrate` instead of the full Docker command:

```bash
alias art="docker compose exec app php artisan"
alias dc="docker compose"
alias comp="docker compose exec app composer"
```

On **Git Bash for Windows**, if a command says _"the input device is not a TTY"_, put `winpty` in front:

```bash
winpty docker compose exec app php artisan starter:install
```

Everything runs through Docker Compose. There is no Makefile; these are the commands you use:

| Command                                                         | What it does                                                         |
| --------------------------------------------------------------- | -------------------------------------------------------------------- |
| `dc up -d`                                                      | Start all containers                                                 |
| `dc --profile dev up -d`                                        | Start all containers plus the Vite dev server (live reload)          |
| `dc --profile dev down`                                         | Stop all containers (including the Vite one)                         |
| `dc up -d --build`                                              | Rebuild the PHP image (after changing the Dockerfile) and restart    |
| `dc exec app bash`                                              | Open a bash shell inside the `app` container                         |
| `dc logs -f app`                                                | Follow logs of one container (`app`, `horizon`, `reverb`, `nginx`…)  |
| `art route:list`                                                | Run any artisan command                                              |
| `comp require vendor/package`                                   | Run any composer command                                             |
| `dc run --rm node sh -c "npm install && npm run build"`         | Build CSS/JS once                                                    |
| `art test`                                                      | Run the test suite                                                   |
| `dc exec app ./vendor/bin/pint`                                 | Fix code style                                                       |
| `dc exec app ./vendor/bin/phpstan analyse --memory-limit=1G`    | Static analysis (Larastan)                                           |

Container names all start with `laravel13-` (`laravel13-app`, `laravel13-mysql`, …), so `docker ps` shows clearly which ones belong to this project.

---

## 2. Start, stop and first install

```bash
dc up -d                      # start everything in the background
dc ps                         # see which containers are running
dc down                       # stop everything (data is kept in volumes)
dc down -v                    # stop AND delete database + redis data (careful)
dc logs -f app                # follow one container's log
dc restart horizon            # restart one container (e.g. after changing a job class)
dc exec app bash              # shell inside the PHP container
```

| URL                                 | What                                                        |
| ----------------------------------- | ----------------------------------------------------------- |
| http://localhost:8080               | Website                                                     |
| http://localhost:8080/admin         | Admin panel                                                 |
| http://localhost:8080/admin/horizon | Queue dashboard (super admin only)                          |
| http://localhost:8025               | Mailpit, catches every email the app sends                  |
| http://localhost:8082               | phpMyAdmin, every database (root, signed in automatically)  |
| `127.0.0.1:3307`                    | MySQL from your machine (user `starter`, password `secret`) |

### First install (once per machine)

```bash
cp .env.example .env
dc up -d --build
comp install
art key:generate
art starter:install
dc run --rm node sh -c "npm install && npm run build"
```

> Do **not** add `env_file: .env` to `compose.yaml`. Laravel reads `.env` from the mounted project folder; the container-level copy overrides it with stale values (for example an empty `APP_KEY`).

### starter:install

```bash
art starter:install                                   # migrate + seed + ask for first admin
art starter:install --fresh                           # DROP all tables first, then install
art starter:install --no-demo                         # skip demo pages, posts, services
art starter:install --email=me@site.com --password='Secret#123' --name="Viju" -n   # no questions
```

It is safe to run again: seeders never duplicate data and never overwrite changes you made in the admin panel.

---

## 3. How a module works

Each feature is one folder in `app/Modules`. Everything the feature needs lives inside it:

```
app/Modules/Products/
├── module.json                        ← name, permissions, sidebar menu
├── Models/Product.php
├── Controllers/Admin/ProductController.php
├── Controllers/Web/ProductController.php      (only with --web)
├── Requests/StoreProductRequest.php
├── Requests/UpdateProductRequest.php
├── Routes/admin.php                   ← loaded at /admin/..., names "admin.*", login + active user checked
├── Routes/web.php                     ← public website routes
├── Views/admin/index|create|edit|_form.blade.php
├── Views/web/...
├── Database/Migrations/               ← run by "art migrate" automatically
├── Database/Seeders/ProductSeeder.php
├── Console/                           ← artisan commands here are registered automatically
└── Providers/*ServiceProvider.php     ← optional, registered automatically
```

| Thing           | How you refer to it                                                         |
| --------------- | --------------------------------------------------------------------------- |
| View            | `view('products::admin.index')`, where the namespace is the module **slug** |
| Route name      | `route('admin.products.index')`                                             |
| Permission      | `products.view`, `products.create`, `products.edit`, `products.delete`      |
| Class namespace | `App\Modules\Products\Models\Product`                                       |

Naming rule used by the generator, for `make:module Product`:

| Piece                                       | Value                                                           |
| ------------------------------------------- | --------------------------------------------------------------- |
| Folder                                      | `Products` (plural)                                             |
| Model                                       | `Product` (singular)                                            |
| Table                                       | `products`                                                      |
| Slug / permission key / URL                 | `products`                                                      |
| Multi-word example: `make:module CaseStudy` | folder `CaseStudies`, table `case_studies`, slug `case-studies` |

---

## 4. Create a new module

### 4.1 The command

```bash
art make:module Product \
  --fields="name:string,sku:string:unique,product_category_id:foreignId:nullable,price:decimal,stock:integer,photo:image,description:editor:nullable,is_active:boolean" \
  --group="Catalog" \
  --icon=bi-box-seam \
  --migrate
```

| Option      | Meaning                                                                |
| ----------- | ---------------------------------------------------------------------- |
| `name`      | Singular name: `Product`, `Testimonial`, `CaseStudy`                   |
| `--fields=` | Comma separated `name:type[:nullable][:unique]`                        |
| `--group=`  | Sidebar section (see `menu_groups` in `config/starter.php`)            |
| `--icon=`   | Any [Bootstrap icon](https://icons.getbootstrap.com), e.g. `bi-people` |
| `--web`     | Also create public pages at `/products` and `/products/{id}`           |
| `--api`     | Also create a REST API at `/api/v1/products` (controller, JSON resource, routes) |
| `--migrate` | Create the table straight away                                         |
| `--force`   | Overwrite an existing module folder (see the note below)               |

> **`--force` note:** it rewrites the files but keeps the old migration file next to a new one. Delete the old migration (and roll it back if it already ran) before regenerating, otherwise the table is created twice.

### 4.2 Field types

| Type        | Database column          | Form input          | Validation                                                                                     |
| ----------- | ------------------------ | ------------------- | ---------------------------------------------------------------------------------------------- |
| `string`    | varchar(255)             | text                | `required\|string\|max:255`                                                                    |
| `email`     | varchar                  | email               | `email`                                                                                        |
| `url`       | varchar                  | url                 | `url`                                                                                          |
| `text`      | text                     | textarea            | `string`                                                                                       |
| `editor`    | longText                 | rich text editor    | `string`, HTML cleaned with HTML Purifier on save                                              |
| `integer`   | int                      | number              | `integer`                                                                                      |
| `decimal`   | decimal(12,2)            | number (step 0.01)  | `numeric`                                                                                      |
| `boolean`   | boolean                  | switch              | `boolean`; `is_active` also gets a status toggle in the list                                   |
| `date`      | date                     | date picker         | `date`                                                                                         |
| `datetime`  | datetime                 | date + time picker  | `date`                                                                                         |
| `image`     | path (nullable)          | upload with preview | `image\|max:4096`, stored in `storage/app/public/<slug>`                                       |
| `foreignId` | unsigned big int + index | dropdown            | `exists:<table>,id`, table guessed from the name: `product_category_id` → `product_categories` |

Modifiers:

| Modifier    | Effect                                              |
| ----------- | --------------------------------------------------- |
| `:nullable` | Column can be empty, field is optional              |
| `:unique`   | Unique index + `Rule::unique(...)->ignore($record)` |

### 4.3 What happens when you run it

1. All files above are created from the templates in `stubs/module/`.
2. With `--migrate`: the new table is created.
3. Permissions `products.view|create|edit|delete|export` are added.
4. The sidebar menu item is added.
5. The module's migration folder is added to `phpstan.neon`.

Open **/admin/products**. A super admin sees it immediately. For anyone else: **Access control → Roles → Edit**, tick the **Products** row, save.

### 4.4 A new sidebar group

If you use a new `--group` name (like `Catalog`), add it to `config/starter.php` so it sits in the right place:

```php
'menu_groups' => ['Main', 'Access control', 'Catalog', 'Content', 'Website CMS', 'Appearance', 'Modules', 'System'],
```

### 4.5 Two linked modules (parent first)

```bash
art make:module ProductCategory --fields="name:string,is_active:boolean" --group=Catalog --icon=bi-tags --migrate
art make:module Product --fields="name:string,product_category_id:foreignId:nullable,price:decimal" --group=Catalog --migrate
```

Then add the relation by hand in `app/Modules/Products/Models/Product.php`:

```php
public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(\App\Modules\ProductCategories\Models\ProductCategory::class, 'product_category_id');
}
```

…and show it in the list (`Views/admin/_table.blade.php`): `{{ $product->category?->name ?? '—' }}`, eager-loaded by changing `Product::query()` to `Product::query()->with('category')` in the controller's `index()`. The generator already adds a **Product category** dropdown filter to the list for every `foreignId` field.

### 4.6 Change what every new module looks like

The generator copies the files in `stubs/module/`. Edit them once and every module generated afterwards includes the change. Placeholders look like `__Model__`, `__slug__`, `__table__`.

### 4.7 The list page (data table)

Every list page (generated modules, Users, Blog posts, Pages, Subscribers, Activity log) is a server-side data table:

| Feature             | How it works                                                                                          |
| ------------------- | ----------------------------------------------------------------------------------------------------- |
| Search              | Types as you go (debounced); searches the columns listed in `->search([...])`                         |
| Sort                | Click a column heading; click again to reverse                                                        |
| Filters             | Status dropdown, one dropdown per `foreignId`/boolean field, any custom `->filter()`                  |
| Date range          | "Created from / to"                                                                                   |
| Per page            | "Show 10 / 15 / 25 / 50 / 100" in the footer                                                          |
| Export CSV          | Exports **what you are looking at**: same search, filters and sort (all pages). Needs `<slug>.export` |
| Bulk actions        | Tick rows → Activate / Deactivate / Delete (each checked against `<slug>.edit` / `<slug>.delete`)     |
| No page reloads     | Only the rows are fetched; the URL updates, so refresh, back button and shared links all work         |

Everything is plain query string, so links like `/admin/products?status=1&sort=price&dir=desc&per_page=50` work directly.

**Three files make a list page:**

| File                                   | What it holds                                                                          |
| -------------------------------------- | -------------------------------------------------------------------------------------- |
| `Controllers/Admin/XController@index`  | The `DataTable` definition: what's searchable, sortable, filterable, exported          |
| `Views/admin/index.blade.php`          | Page header + `<x-admin.datatable>` (toolbar, bulk bar)                                |
| `Views/admin/_table.blade.php`         | The `<table>` and footer: the part reloaded when you search, sort or change page       |

**The controller** (generated for you; edit freely):

```php
public function index(Request $request): Response
{
    return DataTable::of(Product::query()->with('category'), $request)
        ->search(['name', 'sku'])                                   // ?q=
        ->sortable(['name', 'price', 'is_active', 'created_at'], default: 'created_at', direction: 'desc')
        ->status('is_active')                                       // ?status=1|0
        ->filter('product_category_id', 'Category', ProductCategory::pluck('name', 'id')->all())
        ->dateRange('created_at')                                   // ?from=&to=
        ->export('products', [
            'ID' => 'id',
            'Name' => 'name',
            'Category' => 'category.name',                          // dot notation = relation
            'Price' => fn (Product $p) => number_format($p->price, 2), // or a closure
        ], 'products.export')
        ->bulk(route('admin.products.bulk'), [
            'activate' => 'products.edit',
            'deactivate' => 'products.edit',
            'delete' => 'products.delete',
        ])
        ->render('products::admin.index', 'products::admin._table');
}
```

All builder methods:

| Method                                                    | Use                                                                                          |
| --------------------------------------------------------- | -------------------------------------------------------------------------------------------- |
| `->search(['col', ...])`                                  | LIKE search on these columns                                                                 |
| `->search(fn ($q, $term) => $q->whereHas(...))`           | Custom search, e.g. on a relation                                                            |
| `->sortable(['col', 'key' => fn ($q, $dir) => ...], default:, direction:)` | Sortable columns; a closure for computed sorting (e.g. by a relation)                       |
| `->status('is_active')`                                   | Active/Inactive dropdown on a boolean column                                                 |
| `->status('status', ['draft' => 'Draft', ...])`           | Status dropdown on a text column                                                             |
| `->filter('name', 'Label', [value => label], ?closure)`   | Any dropdown filter; without a closure it filters `where name = value`                       |
| `->dateRange('created_at', 'Created')`                    | From/to date filter on any date column                                                       |
| `->perPage(25)`                                           | Default rows per page for this list (otherwise `per_page` in `config/starter.php`)          |
| `->export('file-name', [heading => column\|closure], 'perm')` | CSV export; the button only shows with that permission                                   |
| `->bulk(url, [action => permission])`                     | Bulk bar. Built-in labels: `activate`, `deactivate`, `publish`, `draft`, `delete`; any other name gets a headline label |
| `->render('view', 'partial', [extra data])`               | Returns the page, the partial (AJAX) or the CSV                                              |

**The partial** uses these components:

```blade
<x-admin.datatable.check-all :table="$table" />                       {{-- header checkbox (only if bulk actions) --}}
<x-admin.datatable.th :table="$table" sort="price">Price</x-admin.datatable.th>   {{-- sortable heading --}}
<th>Not sortable</th>
@forelse ($table->rows as $product)                                   {{-- the paginated rows --}}
    <x-admin.datatable.check :table="$table" :value="$product->id" />  {{-- row checkbox --}}
    ...
@endforelse
<x-admin.datatable.footer :table="$table" label="products" />         {{-- show n / showing x–y of z / pages --}}
```

**The bulk action** in the controller receives `action` and `ids[]`:

```php
public function bulk(Request $request): RedirectResponse
{
    $data = $request->validate([
        'action' => ['required', Rule::in(['activate', 'deactivate', 'delete', 'feature'])],
        'ids' => ['required', 'array'],
        'ids.*' => ['integer'],
    ]);
    abort_unless($request->user()->can($data['action'] === 'delete' ? 'products.delete' : 'products.edit'), 403);

    $items = Product::query()->whereKey($data['ids'])->get();
    // ... do the work ...
    return back()->with('success', $items->count().' item(s) updated.');
}
```

Route (already generated): `Route::post('products/bulk', [ProductController::class, 'bulk'])->name('products.bulk');`

> **Add a custom bulk action** (e.g. "Mark featured"): add `'feature' => 'products.edit'` to `->bulk([...])`, add `'feature'` to the `Rule::in([...])` list and handle it in `bulk()`.
>
> **Extra buttons or filters in the toolbar**: use the slots, e.g. `<x-admin.datatable :table="$table"><x-slot:actions><a class="btn btn-outline-secondary" href="...">Import</a></x-slot:actions> @include(...) </x-admin.datatable>`.

---

## 5. Add a field to an existing module

Example: add `barcode` (text, optional, unique) and `weight` (decimal) to **Products**.

### Step 1: migration that changes the existing table

```bash
art make:migration add_barcode_and_weight_to_products_table \
  --table=products \
  --path=app/Modules/Products/Database/Migrations
```

Edit the new file:

```php
public function up(): void
{
    Schema::table('products', function (Blueprint $table) {
        $table->string('barcode')->nullable()->unique()->after('sku');
        $table->decimal('weight', 8, 2)->nullable()->after('price');
    });
}

public function down(): void
{
    Schema::table('products', function (Blueprint $table) {
        $table->dropUnique(['barcode']);
        $table->dropColumn(['barcode', 'weight']);
    });
}
```

```bash
art migrate
```

> Never edit a migration that has already run on a server. Always add a new one.

### Step 2: model (`Models/Product.php`)

```php
protected $fillable = [
    'name', 'sku', 'barcode', 'price', 'weight', /* … */
];

protected function casts(): array
{
    return [
        'price' => 'decimal:2',
        'weight' => 'decimal:2',
        // …
    ];
}
```

### Step 3: validation (`Requests/StoreProductRequest.php`)

```php
'barcode' => ['nullable', 'string', 'max:64', Rule::unique('products', 'barcode')->ignore($this->route('product'))],
'weight'  => ['nullable', 'numeric', 'min:0'],
```

`UpdateProductRequest` extends `StoreProductRequest`, so it picks this up automatically.

### Step 4: form (`Views/admin/_form.blade.php`)

```blade
<x-admin.form.input name="barcode" label="Barcode" :value="$product->barcode" />
<x-admin.form.input type="number" step="0.01" name="weight" label="Weight (kg)" :value="$product->weight" />
```

Available form components:

| Component                                                                                                          | Use                                                             |
| ------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------- |
| `<x-admin.form.input name="" label="" type="text\|email\|number\|date\|…" :value="" required hint="" prefix="" />` | Text-like inputs                                                |
| `<x-admin.form.textarea name="" label="" :value="" rows="4" />`                                                    | Multi-line text                                                 |
| `<x-admin.form.select name="" label="" :options="[value => label]" :value="" placeholder="Select…" />`             | Dropdown                                                        |
| `<x-admin.form.checkbox name="" label="" :checked="" hint="" />`                                                   | On/off switch (always sends 0 or 1)                             |
| `<x-admin.form.editor name="" label="" :value="" />`                                                               | Rich text (clean it with `Purifier::clean()` in the controller) |
| `<x-admin.form.image name="" label="" :url="" />`                                                                  | File upload with preview                                        |
| `<x-admin.form.media name="" label="" :value="" />`                                                                | Pick an image from the Media library (stores the URL)           |

### Step 5: list (`Views/admin/_table.blade.php`), optional

```blade
<x-admin.datatable.th :table="$table" sort="barcode">Barcode</x-admin.datatable.th>   {{-- in <thead> --}}
<td>{{ $product->barcode ?? '—' }}</td>                                                {{-- in the row --}}
```

Then in the controller's `index()` (see 4.7):

```php
->search(['name', 'sku', 'barcode'])                      // searchable
->sortable(['name', 'barcode', 'weight', 'created_at'])   // sortable
->export('products', [..., 'Barcode' => 'barcode', 'Weight' => 'weight'], 'products.export')   // in the CSV
```

### Step 6: check

```bash
art test
```

---

## 6. Add a new table, model or controller to an existing module

Laravel's own `make:*` commands work for modules. **Give the full class path starting with `App/` and use forward slashes**, and the file lands in the right folder with the right namespace.

| Need                    | Command                                                                                                                                                     |
| ----------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Model                   | `art make:model App/Modules/Products/Models/Variant`                                                                                                        |
| Migration, new table    | `art make:migration create_product_variants_table --path=app/Modules/Products/Database/Migrations`                                                          |
| Migration, change table | `art make:migration add_x_to_products_table --table=products --path=app/Modules/Products/Database/Migrations`                                               |
| Admin controller        | `art make:controller App/Modules/Products/Controllers/Admin/VariantController --resource`                                                                   |
| Website controller      | `art make:controller App/Modules/Products/Controllers/Web/VariantController`                                                                                |
| Form request            | `art make:request App/Modules/Products/Requests/VariantRequest`                                                                                             |
| Policy                  | `art make:policy App/Modules/Products/Policies/VariantPolicy --model=App/Modules/Products/Models/Variant`                                                   |
| Job                     | `art make:job App/Modules/Products/Jobs/SyncStock`                                                                                                          |
| Event                   | `art make:event App/Modules/Products/Events/StockLow`                                                                                                       |
| Listener                | `art make:listener App/Modules/Products/Listeners/NotifyStockLow`                                                                                           |
| Mail                    | `art make:mail App/Modules/Products/Mail/LowStockMail`                                                                                                      |
| Notification            | `art make:notification App/Modules/Products/Notifications/StockAlert`                                                                                       |
| Artisan command         | `art make:command App/Modules/Products/Console/ImportProducts` (auto-registered)                                                                            |
| Seeder                  | `art make:seeder ProductVariantSeeder`, then move it to `app/Modules/Products/Database/Seeders/` and set `namespace App\Modules\Products\Database\Seeders;` |
| Views                   | Create files by hand in `app/Modules/Products/Views/admin/variants/`, used as `view('products::admin.variants.index')`                                      |

> Don't use `make:model -m`: it puts the migration in `database/migrations` instead of the module.

### Full example: Variants inside Products

**1. Migration → migrate**

```php
Schema::create('product_variants', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('sku')->unique();
    $table->decimal('price', 12, 2);
    $table->integer('stock')->default(0);
    $table->timestamps();
});
```

```bash
art migrate
```

**2. Model + relation**

```php
// Models/Variant.php
class Variant extends Model
{
    protected $table = 'product_variants';
    protected $fillable = ['product_id', 'name', 'sku', 'price', 'stock'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

// Models/Product.php
public function variants(): HasMany
{
    return $this->hasMany(Variant::class);
}
```

**3. Controller.** The quickest way is to copy `ProductController.php`, rename `Product` → `Variant` and `products` → `variants` (its `index()` and `bulk()` are the data table; see 4.7). Every admin controller checks permissions like this:

```php
class VariantController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:variants.view', only: ['index']),
            new Middleware('permission:variants.create', only: ['create', 'store']),
            new Middleware('permission:variants.edit', only: ['edit', 'update']),
            new Middleware('permission:variants.delete', only: ['destroy']),
        ];
    }
    // index / create / store / edit / update / destroy …
}
```

**4. Routes**, added to the module's existing `Routes/admin.php`:

```php
use App\Modules\Products\Controllers\Admin\VariantController;

Route::resource('variants', VariantController::class)->except('show');
// → /admin/variants, route names admin.variants.index / create / store / edit / update / destroy
```

Don't add the `admin` prefix, the `admin.` name prefix or the `auth` middleware yourself; the module loader does that.

**5. Views.** Copy `Views/admin/index|_table|create|edit|_form.blade.php` into `Views/admin/variants/` and update names (in `render()` too: `'products::admin.variants.index', 'products::admin.variants._table'`). Use `@can('variants.edit') … @endcan` around buttons.

**6. Permissions + menu.** Add them to `module.json` (see section 7), then:

```bash
art module:sync
```

**7. Static analysis.** If this module's migration folder is not yet in `phpstan.neon`, add it:

```neon
    databaseMigrationsPath:
        - app/Modules/Products/Database/Migrations
```

---

## 7. module.json and module:sync

### 7.1 Full example

```json
{
    "name": "Products",
    "slug": "products",
    "description": "Product catalogue with variants.",
    "version": "1.0.0",
    "enabled": true,
    "core": false,
    "order": 50,
    "permissions": [
        {
            "key": "products",
            "label": "Products",
            "actions": ["view", "create", "edit", "delete", "export"]
        },
        {
            "key": "variants",
            "label": "Product variants",
            "actions": ["view", "create", "edit", "delete"]
        }
    ],
    "menu": [
        {
            "key": "products",
            "group": "Catalog",
            "label": "Products",
            "icon": "bi-box-seam",
            "permission": "products.view",
            "children": [
                {
                    "key": "products.all",
                    "label": "All products",
                    "route": "admin.products.index",
                    "permission": "products.view"
                },
                {
                    "key": "products.add",
                    "label": "Add product",
                    "route": "admin.products.create",
                    "permission": "products.create"
                },
                {
                    "key": "products.variants",
                    "label": "Variants",
                    "route": "admin.variants.index",
                    "permission": "variants.view"
                }
            ]
        }
    ]
}
```

| Key                     | Meaning                                                                                                                                              |
| ----------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| `name`                  | Shown on the Modules page                                                                                                                            |
| `slug`                  | Unique id: view namespace, used by `module:enable/disable`                                                                                           |
| `enabled`               | `false` hides routes, menus and the module's rows in the role matrix (data is kept)                                                                  |
| `core`                  | `true` = part of the starter                                                                                                                         |
| `order`                 | Load order. Lower loads first; Pages uses 999 so its catch-all `/{slug}` route is last                                                               |
| `permissions[].key`     | First part of the permission name (`products` → `products.view`)                                                                                     |
| `permissions[].actions` | `view` `create` `edit` `delete` appear as columns in the role matrix; anything else (e.g. `export`, `publish`) appears under **Special permissions** |
| `menu[]`                | Sidebar items. Use `route` for a link, `children` for a dropdown, or both (see 7.2 for how they are shown)                                           |
| `menu[].key`            | Unique id across all modules. **Don't change it** after syncing, or a duplicate item is created                                                      |
| `menu[].group`          | Sidebar section; should match `menu_groups` in `config/starter.php`                                                                                  |
| `menu[].icon`           | Bootstrap icon class                                                                                                                                 |
| `menu[].permission`     | Hide the item from roles without this permission                                                                                                     |
| `menu[].order`          | Optional position number (used only when the item is first created)                                                                                  |

### 7.2 How the sidebar shows an item

| `module.json` item                                     | Sidebar shows                                                                       |
| ------------------------------------------------------ | ----------------------------------------------------------------------------------- |
| `route`, no `children`                                 | A plain link                                                                        |
| `children`, no `route`                                 | A dropdown with the children                                                        |
| `route` **and** `children`                             | A dropdown; the parent's own page is the first link, then the children              |
| `route` and `children`, but no child can be shown      | A plain link to the parent's page                                                   |
| Nothing clickable                                      | Hidden                                                                              |

A child is hidden when its route doesn't exist yet (module not generated) or the user lacks its `permission`. So it's fine to add a child for a module you'll build later: it appears by itself once the route exists.

> When a child points to another module (e.g. Attributes under Product Categories), remove the `menu` block from that other module's own `module.json`, otherwise it also shows as a separate top-level item.

### 7.3 Sync it

```bash
art module:sync          # alias: art module:sync-permissions
```

| Sync **does**                                                                                      | Sync **does not**                                                                              |
| -------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| Create permissions that are new in any `module.json`                                               | Delete any permission (yours or custom ones)                                                   |
| Create sidebar items that are new                                                                  | Overwrite label, icon, group, order or visibility you changed in **Appearance → Menu builder** |
| Update each item's `route`, `permission` and parent                                                | Touch the website header/footer menus                                                          |
| Remove sidebar items whose `key` no longer exists in any module (e.g. you deleted a module folder) | Give new permissions to roles. Do that in **Roles**                                            |
| Clear the menu and permission caches                                                               |                                                                                                |

Also available as a button: **System → Modules → Sync permissions & menus**.

Permissions and menu items you create in the **admin panel** are never removed by sync. They live only in that database, though, so anything your code checks (`@can`, `permission:` middleware) belongs in `module.json` so every environment gets it.

### 7.4 Common edits

| You want to…                   | Do this                                                                                                                                              |
| ------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| Add an action like `import`    | Add it to `actions`, `art module:sync`, guard it with `new Middleware('permission:products.import', only: ['import'])` and `@can('products.import')`  |
| Add a sidebar child            | Add it to `children` with a new `key`, `art module:sync`. It shows once its route exists                                                          |
| Remove a sidebar item          | Remove it from `module.json`, `art module:sync` (it is deleted). To keep it but hide it, use the toggle in **Menu builder**                          |
| Rename a sidebar label or icon | Use **Menu builder** (sync never overwrites it). Editing `module.json` only affects new installs                                                     |
| Move an item to another group  | Change `group` in `module.json`, then delete the row from `menu_items` (or reinstall) so sync recreates it; or just leave it                         |
| Remove a permission            | Remove it from `module.json`, then delete it on **Access control → Permissions** (it now shows as custom). Sync alone never deletes it             |

---

## 8. Enable, disable or remove a module

```bash
art module:list                 # all modules, version, core/custom, enabled/disabled, folder
art module:disable products     # hide routes + menus + role rows; data and tables stay
art module:enable products
```

Or use the switches on **System → Modules**. Required modules (`dashboard`, `users`, `roles`, `menus`, `modules`, `settings`) cannot be disabled.

### Remove a module completely

Order matters: drop the tables **before** deleting the folder (the migration files are needed). Example for `Products`:

```bash
# 1. drop its tables (data is lost) — only this module's migrations, whatever batch they ran in
art migrate:reset --path=app/Modules/Products/Database/Migrations

# 2. delete the folder
rm -rf app/Modules/Products

# 3. remove its sidebar items
art module:sync

# 4. delete its permissions (sync never deletes permissions)
art tinker --execute="Spatie\Permission\Models\Permission::where('name','like','products.%')->delete(); app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();"

# 5. only if it had image fields: delete its media records
art tinker --execute="Spatie\MediaLibrary\MediaCollections\Models\Media::where('model_type','like','%Product%')->delete();"

# 6. clear caches and check
art optimize:clear
art module:list
```

Then clean up by hand:

- Remove its line from `phpstan.neon` (`- app/Modules/Products/Database/Migrations`).
- Remove anything in **other** modules that points to it: `children` entries in their `module.json`, `foreignId` columns (`product_id`), relation methods, `@can('products...')` checks.

> Want it gone only for now? Use `art module:disable products` instead: code, tables and data stay, and `art module:enable products` brings it back.

---

## 9. Database commands

| Command                                                                     | What it does                                                               |
| --------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| `art migrate`                                                               | Run new migrations (core + every module)                                   |
| `art migrate:status`                                                        | Which migrations ran                                                       |
| `art migrate:rollback`                                                      | Undo the last batch                                                        |
| `art migrate:rollback --step=1`                                             | Undo the last single migration                                             |
| `art migrate:reset --path=app/Modules/Products/Database/Migrations`         | Undo all of one module's migrations (drops its tables)                     |
| `art migrate:fresh`                                                         | Drop all tables and migrate (no seed)                                      |
| `art starter:install --fresh`                                               | Drop all tables, migrate, seed, create admin (best for a full local reset) |
| `art db:seed`                                                               | Base data: roles, permissions, menus, settings (safe to repeat)            |
| `art db:seed --class="Database\Seeders\DemoContentSeeder"`                  | Demo website content (skipped if pages exist)                              |
| `art db:seed --class="App\Modules\Products\Database\Seeders\ProductSeeder"` | A module's own seeder                                                      |
| `art tinker`                                                                | PHP console with your models, e.g. `Product::count()`                      |
| `art db`                                                                    | Open a MySQL prompt for the app database                                   |

Connect a GUI (TablePlus, HeidiSQL, DBeaver) to `127.0.0.1:3307`, user `starter`, password `secret`, database `starter`.

---

## 10. Queues, scheduler, realtime, backups

These run in their own containers, `laravel13-horizon`, `laravel13-scheduler` and `laravel13-reverb`, so normally you don't start them by hand.

| Command                      | What it does                                                              |
| ---------------------------- | ------------------------------------------------------------------------- |
| `dc restart horizon`         | Reload queue workers **after changing a Job, Mail or Notification class** |
| `art horizon:status`         | Is Horizon running                                                        |
| `art queue:failed`           | List failed jobs                                                          |
| `art queue:retry all`        | Retry failed jobs                                                         |
| `art queue:flush`            | Delete all failed jobs                                                    |
| `art schedule:list`          | Show every scheduled task and its next run                                |
| `art schedule:run`           | Run due tasks once (testing)                                              |
| `art backup:run`             | Full backup (database + files) now                                        |
| `art backup:run --only-db`   | Database only                                                             |
| `art backup:list`            | Backups and their health                                                  |
| `art backup:clean`           | Delete old backups by the retention rules                                 |
| `art blog:publish-scheduled` | Publish scheduled posts whose time has come                               |
| `art trash:empty --dry-run`  | Count module rows that have been in the trash longer than the setting     |
| `art trash:empty --days=7`   | Delete forever trash older than 7 days (runs daily at 03:30)               |
| `art model:prune --model="App\Models\LoginEvent"` | Remove old sign-in history (runs daily at 03:45)  |
| `art sitemap:generate`       | Rebuild the cached `/sitemap.xml`                                         |
| `art activitylog:clean`      | Delete activity log entries older than 365 days                           |

What the scheduler runs (`routes/console.php`):

| When          | Task                                         |
| ------------- | -------------------------------------------- |
| every minute  | publish scheduled posts, dashboard heartbeat |
| every 5 min   | Horizon metrics                              |
| every 15 min  | clear expired password reset tokens          |
| 01:30 / 02:00 | clean old backups / new backup               |
| 03:00 / 03:15 | prune activity log / rebuild sitemap         |
| 09:00         | backup health check                          |

Send a live notification to the admin bell from anywhere:

```php
\App\Notifications\AdminAlert::toAdmins('Stock is low for SKU-123', route('admin.products.index'), 'products.view', 'bi-exclamation-triangle');
```

---

## 11. Front-end assets

| Command                                                                    | When                                                                      |
| -------------------------------------------------------------------------- | ------------------------------------------------------------------------- |
| `dc --profile dev up -d`                                                   | While editing Blade, CSS or JS: changes appear instantly                  |
| `dc run --rm node sh -c "npm install && npm run build"`                    | After `git pull`, before testing without the dev server, before deploying |

Files:

| Path                                | What                                                                |
| ----------------------------------- | ------------------------------------------------------------------- |
| `resources/admin/admin.css`         | Admin theme (Bootstrap 5 + variables at the top)                    |
| `resources/admin/js/*.js`           | One file per admin feature (tables, forms, page builder, realtime…) |
| `resources/web/web.css`             | Website (Tailwind 4)                                                |
| `resources/views/components/admin/` | Reusable admin Blade components                                     |

> If you add Tailwind classes in a **new folder** outside `resources/views/web` or `app/Modules/*/Views/web`, add an `@source` line at the top of `resources/web/web.css`, or the classes won't be compiled.

---

## 12. Tests and code quality

```bash
art test                                  # all tests
art test --filter=UserManagementTest      # one test class
art test --filter=test_create_role        # one test method
dc exec app ./vendor/bin/pint            # fix code style automatically
dc exec app ./vendor/bin/pint --test      # only check style (what CI runs)
dc exec app ./vendor/bin/phpstan analyse --memory-limit=1G   # Larastan (level 5)
```

Tests live in `tests/Feature`. Each test gets a fresh in-memory database with roles, permissions and menus seeded. Helpers in `tests/TestCase.php`:

```php
$this->actingAs($this->superAdmin());
$editor = $this->userWithRole('editor');
$this->get($this->admin('products'))->assertOk();       // → /admin/products
```

CI (`.github/workflows/ci.yml`) runs Pint, Larastan and the tests on every push.

---

## 13. Cache and optimisation

| Command                                | When                                                            |
| -------------------------------------- | --------------------------------------------------------------- |
| `art optimize:clear`                   | Something looks stale (config, routes, views): clear everything |
| `art optimize`                         | On a server after deploy: cache config, routes, views, events   |
| `art route:list --path=admin`          | See every admin route and its name                              |
| `art route:list --path=admin/products` | Routes of one module                                            |
| `art config:show starter`              | See current starter settings                                    |
| `art about`                            | Versions, drivers, cache status, app key check                  |

> After `art optimize`, changes to `.env` or `config/*.php` are ignored until you run `art optimize` (or `art optimize:clear`) again.

---

## 14. Deploying to a server

Production uses Docker too: **`DEPLOY.md`** has the full guide (first install, HTTPS, RDS, backups and restore, Auto Scaling). Short version, on the server:

```bash
cp .env.production.example .env        # fill in every CHANGE_ME (APP_KEY: DEPLOY.md 2.3)
./deploy.sh                            # build, migrate, start, health check, auto-rollback
docker compose -f compose.prod.yaml exec app php artisan starter:install --no-demo --email=you@company.com --password='…'
```

| Command (on the server) | What it does |
| --- | --- |
| `./deploy.sh` | Pull, build, migrate, restart, check `/up`; rolls back by itself if the check fails |
| `./deploy.sh --down` | Same, with the maintenance page during migrations |
| `./deploy.sh rollback` | Previous images (code only, not the database) |
| `./deploy.sh restart` | After editing `.env` |
| `./deploy.sh status` | Containers, version, health |
| `docker compose -f compose.prod.yaml exec app php artisan health:check` | Database, cache, storage, Redis, Horizon, scheduler, disk |

Without Docker (plain server): `composer install --no-dev -o`, `npm ci && npm run build`, `php artisan migrate --force`, `php artisan module:sync`, `php artisan optimize`, `php artisan horizon:terminate`, `php artisan reverb:restart`.

---

## 15. Troubleshooting

| Problem                                    | Fix                                                                                                                                   |
| ------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------- |
| `No application encryption key`            | Make sure `compose.yaml` has **no** `env_file: .env` line, then `dc up -d --force-recreate` and `art key:generate`                    |
| `the input device is not a TTY` (Git Bash) | Put `winpty` in front of the command                                                                                                  |
| New module page gives 404                  | `art optimize:clear`; check `"enabled": true` in its `module.json`                                                                    |
| New module not in sidebar                  | `art module:sync`, `art optimize:clear`; make sure your role has `<slug>.view`                                                        |
| Sidebar group vanished after adding a child | The child's route doesn't exist yet: it's hidden until you create that module. The parent stays as a plain link                    |
| `image ... already exists` on build        | Only `app` may have a `build:` section in `compose.yaml`; the others use `image: laravel13-app:dev` + `pull_policy: never`            |
| 403 on a page                              | Give the role the permission under **Access control → Roles**                                                                         |
| Migration not found for a module           | It must be in `app/Modules/<Module>/Database/Migrations/`                                                                             |
| Page has no styling                        | `dc run --rm node sh -c "npm install && npm run build"` (or `dc --profile dev up -d`)                                                |
| Tailwind class doesn't work on the website | Add the folder as `@source` in `resources/web/web.css`, rebuild                                                                       |
| Emails not arriving                        | Locally they go to Mailpit: http://localhost:8025                                                                                     |
| Job/email changes not picked up            | `dc restart horizon`                                                                                                                  |
| Live notifications not showing             | `dc logs reverb`; `.env` needs `BROADCAST_CONNECTION=reverb` and the `REVERB_*` values; rebuild assets after changing `VITE_*` values |
| Port already in use                        | Change `APP_PORT`, `REVERB_PORT` or `FORWARD_DB_PORT` in `.env`, then `dc up -d`                                                      |
| Uploaded images don't show                 | `art storage:link`                                                                                                                    |
| Something else                             | `dc logs -f app` and `storage/logs/laravel-*.log`                                                                                     |

---

## 16. Command reference (one page)

```bash
# --- Stack ---------------------------------------------------------------
dc up -d | dc --profile dev up -d | dc --profile dev down | dc ps | dc up -d --build
dc exec app bash | dc logs -f app | dc restart horizon

# --- Install / reset -----------------------------------------------------
art starter:install [--fresh] [--no-demo] [--email= --password= --name=]

# --- Modules -------------------------------------------------------------
art make:module Product --fields="name:string,price:decimal,is_active:boolean" --group=Catalog --icon=bi-box --migrate [--web] [--force]
art module:list
art module:enable products | art module:disable products
art module:sync
# remove: art migrate:reset --path=app/Modules/X/Database/Migrations && rm -rf app/Modules/X && art module:sync

# --- Inside an existing module ------------------------------------------
art make:migration add_x_to_products_table --table=products --path=app/Modules/Products/Database/Migrations
art make:migration create_things_table --path=app/Modules/Products/Database/Migrations
art make:model      App/Modules/Products/Models/Thing
art make:controller App/Modules/Products/Controllers/Admin/ThingController --resource
art make:request    App/Modules/Products/Requests/ThingRequest
art make:job        App/Modules/Products/Jobs/DoSomething
art make:command    App/Modules/Products/Console/DoSomething

# --- Database ------------------------------------------------------------
art migrate | art migrate:status | art migrate:rollback [--step=1]
art migrate:reset --path=app/Modules/X/Database/Migrations     # drop one module's tables
art db:seed [--class=...] | art tinker | art db

# --- Trash / API ----------------------------------------------------------
art trash:empty [--days=30] [--dry-run]
art make:module Product --fields="..." --api --migrate
art route:list --path=api/v1

# --- Queue / schedule / backups -----------------------------------------
art queue:failed | art queue:retry all | art schedule:list
art backup:run [--only-db] | art backup:list | art sitemap:generate

# --- Quality -------------------------------------------------------------
art test [--filter=Name]
dc exec app ./vendor/bin/pint | dc exec app ./vendor/bin/phpstan analyse --memory-limit=1G

# --- Assets --------------------------------------------------------------
dc run --rm node sh -c "npm install && npm run build" | dc --profile dev up -d

# --- Cache ---------------------------------------------------------------
art optimize:clear | art optimize | art route:list --path=admin | art about
```

---

## 17. Trash, import, API, languages, sign-in history

Full explanations are in `README_DEVELOPER.md` sections 23–27. These are the commands and URLs you need.

### Trash

| Do                                    | How                                                                         |
| ------------------------------------- | --------------------------------------------------------------------------- |
| See deleted rows                      | **Trash** tab on any list, or `?trashed=1`                                  |
| Restore / delete forever              | Row buttons or the bulk bar in the Trash tab (needs `<slug>.delete`)        |
| Change auto-empty                     | Settings → Maintenance → *Empty the trash after (days)* (0 = keep forever)   |
| Empty now                             | `art trash:empty --days=1` deletes forever everything trashed more than a day ago |

### CSV import

| Do                                    | How                                                                         |
| ------------------------------------- | --------------------------------------------------------------------------- |
| Import                                | **Import** button on the list → upload → keep *Only check* ticked first     |
| Get the right headings                | **Download template**, or use **Export CSV** and edit that file             |
| Permission                            | `<slug>.import`: tick it under Roles → Special permissions                  |
| Limits                                | `config/starter.php` → `import_max_rows` (5000), `import_max_kb` (5120)     |

### API

```bash
# get a token
curl -X POST http://localhost:8080/api/v1/auth/token -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"Admin@12345","device_name":"curl"}'

# use it
curl http://localhost:8080/api/v1/me -H "Authorization: Bearer <token>"
curl "http://localhost:8080/api/v1/blog/posts?status=published&per_page=5" -H "Authorization: Bearer <token>"

# list every API route
art route:list --path=api/v1
```

Tokens can also be created in **Profile → Security → API tokens** (full access or read-only, with an expiry). Switch the API off, or change the rate limit, in **Settings → Security**.

### Languages

| Do                                    | How                                                                         |
| ------------------------------------- | --------------------------------------------------------------------------- |
| Turn on languages                     | Settings → Languages → tick e.g. Tamil, Arabic                              |
| Translate a page / post               | **+ TA** badge on the Pages / Blog posts list → edit → publish              |
| Translate a menu label                | Appearance → Menu builder → edit item → *Label in Tamil*                    |
| Translate fixed website text          | `lang/ta.json` (copy it to `lang/xx.json` for a new language)               |
| Add a language to the list            | `config/starter.php` → `locales` (`name`, `native`, `dir`)                  |
| Preview                               | `http://localhost:8080/?lang=ta`                                            |

### Sign-in history

| Do                                    | How                                                                         |
| ------------------------------------- | --------------------------------------------------------------------------- |
| See all sign-ins / failures           | Users → **Sign-in history** (filters, CSV export)                           |
| See your own                          | Profile → Security → Recent sign-ins                                        |
| New-device emails, how long to keep   | Settings → Security                                                         |

---

## 18. Customer accounts

| Command | What it does |
| --- | --- |
| `art db:seed --class="App\Modules\Customers\Database\Seeders\CustomerSeeder"` | 40 demo customers; sign in as `demo@customer.test` / `Secret#123` |
| `art route:list --name=customer` | Website account routes (`/account/...`) |
| `art route:list --path=api/v1/shop` | Customer app API |
| `art tinker --execute="App\Modules\Customers\Models\Customer::where('email','x@y.com')->first()?->forceFill(['two_factor_secret'=>null,'two_factor_recovery_codes'=>null,'two_factor_confirmed_at'=>null])->save();"` | Reset a customer's 2FA from the command line (or use the button on their profile) |
| `art test --filter=Customer` | Customer tests (website, API, admin) |

Website: `http://localhost:8080/account/login`. Admin: **Customers**. Settings: **Settings → Customers**. Full guide: `README_DEVELOPER.md` section 28; app developers: `API_GUIDE.md` section 9.

---

## 19. Monitoring and health

| Command | What it does |
| --- | --- |
| `art health:check` | Database, cache, storage (critical) + Redis, Horizon, scheduler, disk space (warnings). Exit code 1 if a critical check fails |
| `curl -i http://localhost:8080/up` | What the load balancer sees: 200, or 500 when the database, cache or storage is broken |
| `art queue:failed` / `art queue:retry all` | Failed jobs (also **Admin → System → Monitoring → Failed jobs**) |
| `art model:prune --model="App\Models\ErrorLog"` | Delete old errors now (runs daily) |
| `art sanctum:prune-expired --hours=24` | Delete expired API/app tokens (runs daily) |

| `art log-viewer:publish` | Re-copy the log viewer's CSS/JS to `public/vendor/log-viewer` (runs on `composer update`) |

Error log: **Admin → System → Monitoring → Error log**. Log files: **Admin → System → Monitoring → Log files** (`/admin/logs`). Alerts: **Settings → Monitoring**. Guide: `README_DEVELOPER.md` section 29, `DEPLOY.md` sections 6–7.


## 20. SaaS mode (optional, see SAAS.md and README_SAAS.md)

```bash
# new install as SaaS (asks if you leave the options out)
docker compose exec app php artisan starter:install --mode=saas --central-domain=yourapp.com --demo-tenant=demo

# turn this single-business install into SaaS: current data becomes tenant "acme"
docker compose exec app php artisan tenancy:convert --subdomain=acme --central-domain=yourapp.com --central-database=starter_central

docker compose exec app php artisan tenants:list
docker compose exec app php artisan tenants:migrate --force              # after adding migrations
docker compose exec app php artisan tenants:run cache:clear --tenants=acme
docker compose exec app php artisan saas:check-subscriptions             # hourly: trials, suspensions
docker compose exec app php artisan saas:collect-usage --tenant=acme     # nightly: usage numbers
docker compose exec app php artisan db:seed --class="App\Modules\Saas\Database\Seeders\PlanSeeder"   # default plans again (never overwrites)

# new module in SaaS mode: --migrate also creates it in every tenant; --for=tenant (default, plan module) | central | both
docker compose exec app php artisan make:module Invoice --fields="title:string,amount:decimal" --migrate
```
