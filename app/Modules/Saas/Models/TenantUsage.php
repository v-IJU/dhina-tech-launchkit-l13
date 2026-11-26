<?php

declare(strict_types=1);

namespace App\Modules\Saas\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One row per tenant per day (php artisan saas:collect-usage, scheduled nightly).
 *
 * @property string $tenant_id
 * @property Carbon $date
 * @property int $users
 * @property int $customers
 * @property int $storage_mb
 * @property int $logins_30d
 * @property int $ai_tokens
 */
class TenantUsage extends Model
{
    use CentralConnection;

    protected $table = 'tenant_usage';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
