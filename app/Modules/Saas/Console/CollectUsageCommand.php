<?php

declare(strict_types=1);

namespace App\Modules\Saas\Console;

use App\Modules\Saas\Models\Tenant;
use App\Modules\Saas\Support\UsageCollector;
use Illuminate\Console\Command;
use Throwable;

/**
 * Nightly: users, customers, storage and sign-ins of every tenant → tenant_usage (SaaS console).
 */
class CollectUsageCommand extends Command
{
    protected $signature = 'saas:collect-usage {--tenant= : Only this tenant id}';

    protected $description = 'SaaS: measure every tenant (users, customers, storage, sign-ins)';

    public function handle(UsageCollector $collector): int
    {
        $query = Tenant::query()->when($this->option('tenant'), fn ($q, $id) => $q->whereKey($id));

        foreach ($query->cursor() as $tenant) {
            try {
                $usage = $collector->collect($tenant);
                $this->line("{$tenant->id}: {$usage->users} users, {$usage->customers} customers, {$usage->storage_mb} MB");
            } catch (Throwable $e) {
                report($e);
                $this->components->error("{$tenant->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
