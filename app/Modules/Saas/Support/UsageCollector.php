<?php

declare(strict_types=1);

namespace App\Modules\Saas\Support;

use App\Models\LoginEvent;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Saas\Models\Tenant;
use App\Modules\Saas\Models\TenantUsage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Measures one tenant (users, customers, storage, sign-ins) and saves today's row in tenant_usage.
 * Run for everyone nightly by saas:collect-usage; the console shows the latest numbers and history.
 */
class UsageCollector
{
    public function collect(Tenant $tenant): TenantUsage
    {
        $numbers = $tenant->run(fn () => [
            'users' => User::query()->count(),
            'customers' => Schema::hasTable('customers') ? Customer::query()->count() : 0,
            'logins_30d' => LoginEvent::query()->where('event', 'login')->where('created_at', '>=', now()->subDays(30))->count(),
            'storage_mb' => $this->storageMb(),
        ]);

        // whereDate: a "date" column comes back as "2026-11-20 00:00:00" on SQLite, "2026-11-20" on MySQL.
        $usage = TenantUsage::query()->where('tenant_id', $tenant->id)->whereDate('date', today())->first()
            ?? new TenantUsage(['tenant_id' => $tenant->id, 'date' => today()]);
        $usage->fill($numbers)->save();

        return $usage;
    }

    /**
     * Everything on the uploads disk under this tenant's folder (local or S3).
     */
    private function storageMb(): int
    {
        try {
            $bytes = collect(Storage::disk('uploads')->allFiles())->sum(fn (string $f) => Storage::disk('uploads')->size($f));
        } catch (Throwable) {
            $bytes = 0;
        }

        return (int) ceil($bytes / 1_048_576);
    }
}
