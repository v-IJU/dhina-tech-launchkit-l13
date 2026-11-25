<?php

declare(strict_types=1);

namespace App\Core\Console;

use App\Core\Console\Generator\Field;
use App\Core\Menus\MenuService;
use App\Core\Modules\ModuleManager;
use App\Core\Permissions\PermissionSync;
use App\Core\Tenancy\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * php artisan make:module Product --fields="name:string,price:decimal,is_active:boolean"
 *
 * Creates app/Modules/Products with model, migration, admin controller,
 * requests, views, routes, permissions and a sidebar menu item.
 * The templates live in /stubs/module — edit them to change what gets generated.
 */
class MakeModuleCommand extends Command
{
    protected $signature = 'make:module
        {name : Singular name, e.g. Product or BlogPost}
        {--fields= : Comma separated name:type[:nullable][:unique] list}
        {--group=Modules : Sidebar group}
        {--icon=bi-grid : Bootstrap icon class}
        {--web : Also create public website pages}
        {--api : Also create a REST API (/api/v1/...) with a JSON resource}
        {--migrate : Run the migration after generating (SaaS mode: also in every tenant)}
        {--for=tenant : SaaS mode: tenant (a plan module, default), central (your console/website only) or both}
        {--force : Overwrite an existing module}';

    protected $description = 'Create a new CRUD module (model, migration, controllers, views, routes, permissions, menu)';

    /** @var array<int, string> */
    public array $created = [];

    public function handle(Filesystem $files): int
    {
        try {
            $fields = Field::parseList((string) ($this->option('fields') ?: 'name:string'));
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if (! in_array($this->option('for'), ['tenant', 'central', 'both'], true)) {
            $this->components->error('--for must be tenant, central or both.');

            return self::FAILURE;
        }

        $model = Str::studly(Str::singular((string) $this->argument('name')));
        if (! preg_match('/^[A-Z][A-Za-z0-9]*$/', $model)) {
            $this->components->error('The name must start with a letter and contain only letters and numbers.');

            return self::FAILURE;
        }

        $module = Str::plural($model);
        $path = config('starter.modules_path').'/'.$module;
        $slug = Str::kebab($module);

        if ($files->isDirectory($path) && ! $this->option('force')) {
            $this->components->error("Module [{$module}] already exists. Use --force to overwrite.");

            return self::FAILURE;
        }
        if (app(ModuleManager::class)->find($slug) && ! $this->option('force')) {
            $this->components->error("A module with slug [{$slug}] already exists.");

            return self::FAILURE;
        }

        $replace = $this->replacements($model, $module, $slug, $fields);

        $targets = [
            'module.json.stub' => 'module.json',
            'model.stub' => "Models/{$model}.php",
            'migration.stub' => 'Database/Migrations/'.date('Y_m_d_His')."_create_{$replace['__table__']}_table.php",
            'seeder.stub' => "Database/Seeders/{$model}Seeder.php",
            'controller.admin.stub' => "Controllers/Admin/{$model}Controller.php",
            'request.store.stub' => "Requests/Store{$model}Request.php",
            'request.update.stub' => "Requests/Update{$model}Request.php",
            'routes.admin.stub' => 'Routes/admin.php',
            'view.index.stub' => 'Views/admin/index.blade.php',
            'view.create.stub' => 'Views/admin/create.blade.php',
            'view.edit.stub' => 'Views/admin/edit.blade.php',
            'view.form.stub' => 'Views/admin/_form.blade.php',
            'view.table.stub' => 'Views/admin/_table.blade.php',
        ];

        if ($this->option('api')) {
            $targets += [
                'controller.api.stub' => "Controllers/Api/{$model}Controller.php",
                'resource.stub' => "Resources/{$model}Resource.php",
                'routes.api.stub' => 'Routes/api.php',
            ];
        }

        if ($this->option('web')) {
            $targets += [
                'controller.web.stub' => "Controllers/Web/{$model}Controller.php",
                'routes.web.stub' => 'Routes/web.php',
                'view.web.index.stub' => 'Views/web/index.blade.php',
                'view.web.show.stub' => 'Views/web/show.blade.php',
            ];
        }

        foreach ($targets as $stub => $target) {
            $content = strtr($files->get(base_path('stubs/module/'.$stub)), $replace);
            $files->ensureDirectoryExists(dirname("{$path}/{$target}"));
            $files->put("{$path}/{$target}", $content);
            $this->created[] = "app/Modules/{$module}/{$target}";
        }

        $this->components->info("Module [{$module}] created.");
        $this->components->bulletList($this->created);

        // Tidy the generated code to the project style (same as running Pint yourself).
        if (is_file(base_path('vendor/bin/pint'))) {
            Process::path(base_path())->run(['vendor/bin/pint', '--quiet', "app/Modules/{$module}"]);
        }

        $this->registerModule($module);
        $this->registerWithLarastan($files, $module);

        $this->newLine();
        $this->line('  Next steps:');
        if (! $this->option('migrate')) {
            $this->line('   • php artisan migrate');
        }
        $this->line('   • Open /'.config('starter.admin_path')."/{$slug} (super-admin sees it right away; grant other roles {$slug}.* under Roles)");
        if ($this->option('api')) {
            $this->line("   • API: /api/v1/{$slug} with a token from your profile page (README_DEVELOPER.md, section API)");
        }

        return self::SUCCESS;
    }

    /**
     * Larastan reads migrations to know model columns; add the new folder to phpstan.neon.
     */
    private function registerWithLarastan(Filesystem $files, string $module): void
    {
        $neon = base_path('phpstan.neon');
        $line = "        - app/Modules/{$module}/Database/Migrations";

        if ($files->exists($neon) && ! str_contains($files->get($neon), $line) && str_contains($files->get($neon), 'databaseMigrationsPath:')) {
            $files->append($neon, $line."\n");
        }
    }

    /**
     * Make the new module live: migrate, create permissions and the menu item.
     */
    private function registerModule(string $module): void
    {
        app(ModuleManager::class)->refresh();

        try {
            if ($this->option('migrate')) {
                // The module folder is new, so point migrate at it directly.
                $this->call('migrate', ['--force' => true, '--path' => "app/Modules/{$module}/Database/Migrations"]);
            }
            if (Schema::hasTable('permissions') && Schema::hasTable('menu_items')) {
                app(PermissionSync::class)->run();
                app(MenuService::class)->syncFromModules();
                $this->components->info('Permissions and sidebar menu item created.');
            }

            // SaaS mode: the same table, permissions and menu item in every tenant database.
            if ($this->option('migrate') && Tenancy::enabled() && Schema::hasTable('tenants')) {
                $this->call('tenants:migrate', ['--force' => true, '--path' => ["app/Modules/{$module}/Database/Migrations"]]);
                $this->call('tenants:run', ['commandname' => 'module:sync']);
                $this->components->info($this->option('for') === 'central'
                    ? 'Tenants have the table too (unused: this module is central only).'
                    : 'Done in every tenant. Tick it on a plan under SaaS > Plans to give it to tenants.');
            }
        } catch (Throwable $e) {
            $this->components->warn('Database not ready ('.$e->getMessage().'). Run: php artisan migrate && php artisan module:sync');
        }
    }

    /**
     * @param  array<int, Field>  $fields
     * @return array<string, string>
     */
    private function replacements(string $model, string $module, string $slug, array $fields): array
    {
        $var = Str::camel($model);
        $vars = Str::camel($module);
        $table = Str::snake($module);
        $label = Str::headline($module);
        $singular = Str::lower(Str::headline($model));
        $indent = fn (array $lines, int $spaces) => implode("\n", array_map(fn ($l) => str_repeat(' ', $spaces).$l, $lines));

        $active = collect($fields)->first(fn (Field $f) => $f->isActiveFlag());
        $images = collect($fields)->filter(fn (Field $f) => $f->type === 'image')->values();
        $title = collect($fields)->first(fn (Field $f) => in_array($f->type, ['string', 'email'], true));
        $titleColumn = $title instanceof Field ? $title->name : 'id';
        $searchable = collect($fields)->filter(fn (Field $f) => in_array($f->type, ['string', 'email', 'text'], true));
        $listed = collect($fields)->reject(fn (Field $f) => in_array($f->type, ['text', 'editor'], true) || $f === $active)->take(4);

        // ----- List page (DataTable) -----
        $searchColumns = $searchable->map(fn (Field $f) => "'{$f->name}'")->implode(', ');
        $sortKeys = $listed->reject(fn (Field $f) => $f->type === 'image')->map(fn (Field $f) => $f->name)->values()->all();
        if ($active) {
            $sortKeys[] = $active->name;
        }
        $sortKeys[] = 'created_at';
        $hasSortOrder = collect($fields)->contains(fn (Field $f) => $f->name === 'sort_order');

        $filters = [];
        if ($active) {
            $filters[] = "->status('{$active->name}')";
        }
        foreach (collect($fields)->filter(fn (Field $f) => $f->type === 'foreignId') as $f) {
            $filters[] = "->filter('{$f->name}', '{$f->label()}', \$this->options()['{$f->name}'])";
        }
        foreach (collect($fields)->filter(fn (Field $f) => $f->type === 'boolean' && $f !== $active) as $f) {
            $filters[] = "->filter('{$f->name}', '{$f->label()}', ['1' => 'Yes', '0' => 'No'])";
        }

        $export = ["'ID' => 'id',"];
        foreach ($fields as $f) {
            if (in_array($f->type, ['editor', 'image'], true)) {
                continue;
            }
            $export[] = "'{$f->label()}' => '{$f->name}',";
        }
        $export[] = "'Created' => 'created_at',";

        $bulk = $active
            ? ['activate' => "'{$slug}.edit'", 'deactivate' => "'{$slug}.edit'", 'delete' => "'{$slug}.delete'"]
            : ['delete' => "'{$slug}.delete'"];
        $bulkUpdate = $active
            ? "        if (in_array(\$data['action'], ['activate', 'deactivate'], true)) {\n            \$items->each->update(['{$active->name}' => \$data['action'] === 'activate']);\n        }\n"
            : '';

        $sortable = fn (Field $f) => in_array($f->name, $sortKeys, true);
        $heads = $listed->map(fn (Field $f) => $sortable($f)
            ? "<x-admin.datatable.th :table=\"\$table\" sort=\"{$f->name}\">{$f->label()}</x-admin.datatable.th>"
            : "<th>{$f->label()}</th>")->all();
        $cells = $listed->map(fn (Field $f) => '<td'.($f === $title ? ' class="fw-semibold"' : '').'>'.$f->cell($var).'</td>')->all();
        if ($active) {
            $heads[] = "<x-admin.datatable.th :table=\"\$table\" sort=\"{$active->name}\">Status</x-admin.datatable.th>";
            $cells[] = '<td>@can(\''.$slug.'.edit\')<form method="POST" action="{{ route(\'admin.'.$slug.'.toggle\', $'.$var.') }}">@csrf @method(\'PATCH\')<button class="btn p-0 border-0" title="Click to change"><x-admin.status-badge :status="(bool) $'.$var.'->'.$active->name.'" /></button></form>@else<x-admin.status-badge :status="(bool) $'.$var.'->'.$active->name.'" />@endcan</td>';
        }
        $heads[] = '<x-admin.datatable.th :table="$table" sort="created_at">Created</x-admin.datatable.th>';
        $cells[] = '<td class="text-secondary text-nowrap">{{ $'.$var.'->created_at?->format(\'d M Y\') }}</td>';

        $toggle = $active ? <<<PHP

    public function toggle({$model} \${$var}): RedirectResponse
    {
        \${$var}->update(['{$active->name}' => ! \${$var}->{$active->name}]);

        return back()->with('success', '{$model} '.(\${$var}->{$active->name} ? 'activated' : 'deactivated').'.');
    }

PHP : '';

        $options = collect($fields)->filter(fn (Field $f) => $f->type === 'foreignId')->map(function (Field $f) {
            $t = $f->relatedTable();

            return "'{$f->name}' => \\Illuminate\\Support\\Facades\\Schema::hasTable('{$t}') ? DB::table('{$t}')->orderBy('id')->pluck(\\Illuminate\\Support\\Facades\\Schema::hasColumn('{$t}', 'name') ? 'name' : (\\Illuminate\\Support\\Facades\\Schema::hasColumn('{$t}', 'title') ? 'title' : 'id'), 'id')->all() : [],";
        })->all();

        $webFields = collect($fields)->reject(fn (Field $f) => $f === $title || $f->type === 'image')
            ->map(fn (Field $f) => '<div class="grid grid-cols-3 gap-4 px-6 py-4"><dt class="text-sm font-semibold text-slate-500">'.$f->label().'</dt><dd class="col-span-2 text-slate-800">'.($f->type === 'editor' ? '{!! $'.$var.'->'.$f->name.' !!}' : $f->cell($var)).'</dd></div>')->all();

        return [
            '__Module__' => $module,
            '__Model__' => $model,
            '__models__' => $vars,
            '__model__' => $var,
            '__slug__' => $slug,
            '__table__' => $table,
            '__Label__' => $label,
            '__label__' => Str::lower($label),
            '__Singular__' => Str::ucfirst($singular),
            '__singular__' => $singular,
            '__group__' => (string) $this->option('group'),
            '__icon__' => (string) $this->option('icon'),
            '__central__' => in_array($this->option('for'), ['central', 'both'], true) ? 'true' : 'false',
            '__tenant__' => $this->option('for') === 'central' ? 'false' : 'true',
            '__titleColumn__' => $titleColumn,
            '__fillable__' => $indent(array_map(fn (Field $f) => "'{$f->name}',", $fields), 8),
            '__casts__' => $indent(array_values(array_filter(array_map(fn (Field $f) => $f->cast(), $fields))), 12),
            '__columns__' => $indent(array_map(fn (Field $f) => $f->migration(), $fields), 12),
            '__rules__' => $indent(array_map(fn (Field $f) => $f->rules($var, $table), $fields), 12),
            '__formFields__' => $indent(array_map(fn (Field $f) => $f->input($var), $fields), 16),
            '__tableHeads__' => $indent($heads, 12),
            '__tableCells__' => $indent($cells, 16),
            '__searchColumns__' => $searchColumns,
            '__sortColumns__' => implode(', ', array_map(fn ($k) => "'{$k}'", $sortKeys)),
            '__defaultSort__' => $hasSortOrder ? 'sort_order' : 'created_at',
            '__defaultDir__' => $hasSortOrder ? 'asc' : 'desc',
            '__filters__' => $filters ? $indent($filters, 12)."\n" : '',
            '__exportColumns__' => $indent($export, 16),
            '__bulkPermissions__' => collect($bulk)->map(fn ($perm, $action) => "'{$action}' => {$perm}")->implode(', '),
            '__bulkActionList__' => collect(array_keys($bulk))->map(fn ($a) => "'{$a}'")->implode(', '),
            '__bulkList__' => implode(', ', array_keys($bulk)),
            '__uploadsConst__' => $images->isEmpty() ? '' : "\n    /** @var array<int, string> Uploaded files removed from storage when a row is deleted forever */\n    public const UPLOADS = [".$images->map(fn (Field $f) => "'{$f->name}'")->implode(', ')."];\n",
            '__uploadsHook__' => $images->isEmpty() ? '' : <<<'PHP'
    protected static function booted(): void
    {
        // Soft delete keeps files (the row can be restored); delete forever removes them.
        static::forceDeleted(function (self $item) {
            foreach (self::UPLOADS as $field) {
                if ($item->{$field}) {
                    Storage::disk('uploads')->delete($item->{$field});
                }
            }
        });
    }


PHP,
            '__resourceFields__' => $indent(array_map(fn (Field $f) => $f->type === 'image'
                ? "'{$f->name}' => \$this->{$f->name} ? Storage::disk('uploads')->url(\$this->{$f->name}) : null,"
                : "'{$f->name}' => \$this->{$f->name},", $fields), 12),
            '__apiQueryParams__' => $indent(array_values(array_filter([
                "#[QueryParameter('q', description: 'Search text', type: 'string')]",
                "#[QueryParameter('sort', description: 'Sort by: ".implode(', ', $sortKeys)."', type: 'string')]",
                "#[QueryParameter('dir', description: 'asc or desc', type: 'string')]",
                "#[QueryParameter('per_page', description: '10, 15, 25, 50 or 100', type: 'integer')]",
                "#[QueryParameter('page', type: 'integer')]",
                $active ? "#[QueryParameter('status', description: '1 = active, 0 = inactive', type: 'string')]" : null,
                "#[QueryParameter('from', description: 'Created from (YYYY-MM-DD)', type: 'string', format: 'date')]",
                "#[QueryParameter('to', description: 'Created to (YYYY-MM-DD)', type: 'string', format: 'date')]",
            ])), 4)."\n",
            '__apiOptions__' => $options ? "\n    /**\n     * Options for filters on related records.\n     *\n     * @return array<string, mixed>\n     */\n    private function options(): array\n    {\n        return [\n".$indent($options, 12)."\n        ];\n    }\n" : '',
            '__importColumns__' => $indent(array_values(array_filter(array_map(fn (Field $f) => $f->importColumn($table, $model), $fields))), 12),
            '__orderBy__' => collect($fields)->contains(fn (Field $f) => $f->name === 'sort_order') ? "->orderBy('sort_order')->latest()" : '->latest()',
            '__bulkUpdate__' => $bulkUpdate,
            '__uploads__' => implode(', ', collect($fields)->filter(fn (Field $f) => $f->type === 'image')->map(fn (Field $f) => "'{$f->name}'")->all()),
            '__richText__' => implode(', ', collect($fields)->filter(fn (Field $f) => $f->type === 'editor')->map(fn (Field $f) => "'{$f->name}'")->all()),
            '__options__' => $indent($options, 12),
            '__toggle__' => $toggle,
            '__toggleRoute__' => $active ? "Route::patch('{$slug}/{{$var}}/toggle', [{$model}Controller::class, 'toggle'])->name('{$slug}.toggle');\n" : '',
            '__activeScope__' => $active ? "->where('{$active->name}', true)" : '',
            '__activeGuard__' => $active ? "        abort_unless(\${$var}->{$active->name}, 404);\n" : '',
            '__webFields__' => $indent($webFields, 16),
            '__attributes__' => $active ? "\n    protected \$attributes = ['{$active->name}' => true];\n" : '',
        ];
    }
}
