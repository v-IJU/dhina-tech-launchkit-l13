<?php

declare(strict_types=1);

namespace App\Core\Modules;

use App\Core\Tenancy\Tenancy;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Finds and manages modules in app/Modules. Each module is a plain folder
 * with a module.json file, so there is nothing hidden: open the folder and
 * you can see everything the module does.
 */
class ModuleManager
{
    /** @var Collection<string, Module>|null */
    private ?Collection $modules = null;

    public function __construct(private readonly string $basePath) {}

    /**
     * @return Collection<string, Module> keyed by slug, sorted by "order"
     */
    public function all(): Collection
    {
        if ($this->modules === null) {
            $files = glob($this->basePath.'/*/module.json') ?: [];

            $this->modules = collect($files)
                ->map(fn (string $file) => Module::fromFile($file))
                ->sortBy([['order', 'asc'], ['name', 'asc']])
                ->keyBy('slug');
        }

        return $this->modules;
    }

    /**
     * Switched on in module.json. These modules are loaded (routes, views, providers) in every context;
     * isEnabled() then decides per request whether the current context may use them.
     *
     * @return Collection<string, Module>
     */
    public function enabled(): Collection
    {
        return $this->all()->filter(fn (Module $m) => $m->enabled);
    }

    public function find(string $slug): ?Module
    {
        return $this->all()->get($slug);
    }

    /**
     * Can the current request use this module?
     *
     *  - single business:  switched on in module.json (and not a SaaS-only module)
     *  - SaaS, central:    switched on + "central": true
     *  - SaaS, tenant:     switched on + "tenant": true + (core module, or included in the tenant's plan)
     */
    public function isEnabled(string $slug): bool
    {
        $module = $this->find($slug);

        if (! $module?->enabled) {
            return false;
        }

        if (! Tenancy::enabled()) {
            return ! $module->saasOnly;
        }

        $tenant = Tenancy::tenant();

        if ($tenant === null) {
            return $module->central;
        }

        return $module->tenant && ($module->core || in_array($slug, $tenant->moduleSlugs(), true));
    }

    /**
     * Modules a plan can include (Plans form): tenant modules that are not core.
     *
     * @return Collection<string, Module>
     */
    public function planModules(): Collection
    {
        return $this->enabled()->filter(fn (Module $m) => $m->isPlanModule());
    }

    public function setEnabled(string $slug, bool $enabled): void
    {
        $module = $this->find($slug) ?? throw new RuntimeException("Module [{$slug}] not found.");

        if (! $enabled && $module->isLocked()) {
            throw new RuntimeException("Module [{$slug}] is required and cannot be disabled.");
        }

        $file = $module->file('module.json');
        $data = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        $data['enabled'] = $enabled;
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->refresh();
    }

    public function refresh(): void
    {
        $this->modules = null;
    }

    /**
     * Every permission resource across the modules this context can use, for the role matrix.
     *
     * @return Collection<int, array{module: string, key: string, label: string, actions: array<int, string>}>
     */
    public function permissionResources(): Collection
    {
        return $this->enabled()->filter(fn (Module $m) => $this->isEnabled($m->slug))->flatMap(fn (Module $m) => collect($m->permissions)
            ->map(fn (array $r) => $r + ['module' => $m->slug]))
            ->values();
    }
}
