<?php

declare(strict_types=1);

namespace App\Core\Modules;

/**
 * One module folder under app/Modules, described by its module.json file.
 */
final class Module
{
    /**
     * @param  array<int, array{key: string, label: string, actions: array<int, string>}>  $permissions
     * @param  array<int, array<string, mixed>>  $menu
     */
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly string $path,
        public readonly string $description,
        public readonly string $version,
        public readonly bool $enabled,
        public readonly bool $core,
        public readonly int $order,
        public readonly array $permissions,
        public readonly array $menu,
        public readonly bool $central = false,
        public readonly bool $tenant = true,
        public readonly bool $saasOnly = false,
    ) {}

    public static function fromFile(string $jsonFile): self
    {
        $data = json_decode((string) file_get_contents($jsonFile), true, flags: JSON_THROW_ON_ERROR);
        $path = dirname($jsonFile);

        return new self(
            name: $data['name'] ?? basename($path),
            slug: $data['slug'] ?? strtolower(basename($path)),
            path: $path,
            description: $data['description'] ?? '',
            version: $data['version'] ?? '1.0.0',
            enabled: (bool) ($data['enabled'] ?? true),
            core: (bool) ($data['core'] ?? false),
            order: (int) ($data['order'] ?? 100),
            permissions: $data['permissions'] ?? [],
            menu: $data['menu'] ?? [],
            // SaaS mode only (STARTER_TENANCY=true), see SAAS.md:
            central: (bool) ($data['central'] ?? false),      // available in the central console
            tenant: (bool) ($data['tenant'] ?? true),         // available to tenants (core = always, others = if their plan includes it)
            saasOnly: (bool) ($data['saas_only'] ?? false),   // hidden in single-business mode
        );
    }

    public function namespace(): string
    {
        return 'App\\Modules\\'.basename($this->path);
    }

    public function file(string $relative): string
    {
        return $this->path.DIRECTORY_SEPARATOR.$relative;
    }

    /**
     * Can a plan switch this module on or off for a tenant? (Plans form.)
     */
    public function isPlanModule(): bool
    {
        return $this->tenant && ! $this->core && ! $this->saasOnly;
    }

    public function isLocked(): bool
    {
        return in_array($this->slug, config('starter.locked_modules', []), true);
    }

    /**
     * All permission names this module declares, e.g. ["blog.view", "blog.create"].
     *
     * @return array<int, string>
     */
    public function permissionNames(): array
    {
        $names = [];
        foreach ($this->permissions as $resource) {
            foreach ($resource['actions'] as $action) {
                $names[] = $resource['key'].'.'.$action;
            }
        }

        return $names;
    }
}
