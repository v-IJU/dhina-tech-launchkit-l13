<?php

declare(strict_types=1);

namespace App\Core\Settings;

use App\Core\Tenancy\Tenancy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Key/value site settings stored in the "settings" table and cached forever
 * until something changes. Use the helper: setting('site_name').
 */
class SettingsRepository
{
    private const CACHE_KEY = 'settings.all';

    /** Keys stored encrypted in the database. */
    private const ENCRYPTED = ['mail_password'];

    /** @var array<string, mixed>|null */
    private ?array $loaded = null;

    /** Which database the loaded values came from (SaaS mode: a tenant id, or "central"). */
    private ?string $loadedFor = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '' ? $all[$key] : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        // SaaS mode: settings are read while the app boots (central), then again inside the tenant.
        // Keyed by the database in use (while switching back, tenancy still names the old tenant).
        $connection = DB::getDefaultConnection();
        $context = $connection === 'tenant' ? 'tenant:'.Tenancy::tenant()?->getTenantKey() : $connection;
        if ($this->loaded !== null && $this->loadedFor === (string) $context) {
            return $this->loaded;
        }
        $this->loadedFor = (string) $context;

        try {
            $this->loaded = Cache::rememberForever(self::CACHE_KEY, function () {
                if (! Schema::hasTable('settings')) {
                    return [];
                }

                return DB::table('settings')->pluck('value', 'key')
                    ->map(fn ($value, $key) => $this->decode((string) $key, $value))
                    ->all();
            });
        } catch (Throwable) {
            $this->loaded = []; // database not ready yet (fresh install)
        }

        return $this->loaded;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            DB::table('settings')->updateOrInsert(['key' => $key], [
                'value' => $this->encode($key, $value),
                'updated_at' => now(),
            ]);
        }

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->loaded = null;
        $this->loadedFor = null;
    }

    private function encode(string $key, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }
        if (is_array($value)) {
            $value = json_encode($value);
        }

        return in_array($key, self::ENCRYPTED, true) && $value !== '' ? Crypt::encryptString((string) $value) : (string) $value;
    }

    private function decode(string $key, mixed $value): mixed
    {
        if ($value !== null && in_array($key, self::ENCRYPTED, true)) {
            try {
                return Crypt::decryptString($value);
            } catch (Throwable) {
                return null;
            }
        }

        return $value;
    }
}
