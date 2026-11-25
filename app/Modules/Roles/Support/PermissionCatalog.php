<?php

declare(strict_types=1);

namespace App\Modules\Roles\Support;

use App\Core\Modules\ModuleManager;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

/**
 * Groups permissions for the role matrix:
 *  - rows: resources from module.json (Users, Blog posts, …)
 *  - columns: the standard actions view / create / edit / delete
 *  - "special": every other permission (blog.publish, users.impersonate, custom ones)
 */
class PermissionCatalog
{
    public function __construct(private readonly ModuleManager $modules) {}

    /**
     * @return Collection<int, array{module: string, key: string, label: string, actions: array<int, string>}>
     */
    public function resources(): Collection
    {
        return $this->modules->permissionResources()->unique('key')->values();
    }

    /**
     * @return array<int, string>
     */
    public function standardActions(): array
    {
        return config('starter.standard_actions');
    }

    /**
     * Permissions that are not a standard action of a resource row.
     *
     * @return Collection<int, string>
     */
    public function special(): Collection
    {
        $standard = $this->resources()->flatMap(fn ($r) => collect($r['actions'])
            ->intersect($this->standardActions())->map(fn ($a) => $r['key'].'.'.$a));

        $enabledKeys = $this->resources()->pluck('key')->all();

        return Permission::query()->orderBy('name')->pluck('name')
            ->reject(fn ($name) => $standard->contains($name))
            ->filter(fn ($name) => in_array(strtok($name, '.'), $enabledKeys, true) || ! $this->declaredAnywhere($name))
            ->values();
    }

    /**
     * Every permission name shown in the matrix (used for counts and "select all").
     *
     * @return Collection<int, string>
     */
    public function all(): Collection
    {
        return $this->resources()->flatMap(fn ($r) => collect($r['actions'])->map(fn ($a) => $r['key'].'.'.$a))
            ->merge($this->special())->unique()->values();
    }

    /**
     * True when a permission is declared in some module.json (custom ones are not).
     */
    public function declaredAnywhere(string $name): bool
    {
        return $this->modules->all()->contains(fn ($m) => in_array($name, $m->permissionNames(), true));
    }
}
