<?php

use App\Core\Modules\ModuleManager;
use App\Core\Settings\SettingsRepository;

if (! function_exists('setting')) {
    /**
     * Read a site setting: setting('site_name', 'Default').
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingsRepository::class)->get($key, $default);
    }
}

if (! function_exists('module_enabled')) {
    function module_enabled(string $slug): bool
    {
        return app(ModuleManager::class)->isEnabled($slug);
    }
}

if (! function_exists('admin_path')) {
    /**
     * Build a URL path inside the admin panel: admin_path('users') -> /admin/users
     */
    function admin_path(string $path = ''): string
    {
        return '/'.trim(config('starter.admin_path').'/'.ltrim($path, '/'), '/');
    }
}

if (! function_exists('initials')) {
    function initials(?string $name): string
    {
        $parts = preg_split('/\s+/', trim(preg_replace('/[^\pL\pN\s]/u', '', (string) $name))) ?: [];
        $letters = array_map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice(array_filter($parts), 0, 2));

        return implode('', $letters) ?: '?';
    }
}

if (! function_exists('avatar_color')) {
    /**
     * Stable colour for a name, used for initials avatars.
     */
    function avatar_color(?string $name): string
    {
        $colors = ['#2563eb', '#7c3aed', '#db2777', '#ea580c', '#16a34a', '#0891b2', '#b45309', '#4f46e5'];

        return $colors[abs(crc32((string) $name)) % count($colors)];
    }
}
