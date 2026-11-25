<?php

declare(strict_types=1);

namespace App\Modules\Saas\Models;

use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

/**
 * A host name that opens a tenant: "acme" (= acme.CENTRAL_DOMAIN) or a full custom domain.
 *
 * @property int $id
 * @property string $domain
 * @property string $tenant_id
 */
class Domain extends BaseDomain
{
    public function isSubdomain(): bool
    {
        return ! str_contains($this->domain, '.');
    }

    public function host(): string
    {
        return $this->isSubdomain() ? $this->domain.'.'.config('tenancy.central_domains.0') : $this->domain;
    }
}
