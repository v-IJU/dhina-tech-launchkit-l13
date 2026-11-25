<?php

declare(strict_types=1);

namespace App\Core\Tenancy;

use Illuminate\Database\Query\Builder;
use Illuminate\Session\DatabaseSessionHandler;

/**
 * SaaS mode, SESSION_DRIVER=database: always use the CURRENT default connection.
 *
 * Laravel's handler keeps the connection it was created with. Fortify's login controller asks for the
 * auth guard (and so the session) while routes are being matched, before IdentifyTenant has switched
 * to the tenant database, so login sessions were saved centrally and the tenant never saw the sign-in
 * (endless /admin ⇄ /admin/login redirect).
 */
class TenantAwareDatabaseSessionHandler extends DatabaseSessionHandler
{
    protected function getQuery(): Builder
    {
        // SESSION_CONNECTION set on purpose → keep using that one.
        $name = $this->container->make('config')->get('session.connection');

        return $this->container->make('db')->connection($name)->table($this->table)->useWritePdo();
    }
}
