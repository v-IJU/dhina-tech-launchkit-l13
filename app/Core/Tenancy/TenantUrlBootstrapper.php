<?php

declare(strict_types=1);

namespace App\Core\Tenancy;

use App\Modules\Saas\Models\Tenant as SaasTenant;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Makes URLs point at the tenant's own domain:
 *
 *  - Queued jobs and artisan commands (emails: password reset, welcome, customer confirm links)
 *    would otherwise use APP_URL = the central domain. Here route()/url() use https://acme.yourapp.com.
 *  - Files on a LOCAL uploads disk are saved in storage/app/public/tenant{id}/ (FilesystemTenancyBootstrapper);
 *    their URLs become …/storage/tenant{id}/… (public/storage already links to storage/app/public).
 *    S3 URLs already include the prefix.
 */
class TenantUrlBootstrapper implements TenancyBootstrapper
{
    /** @var array<string, string|null> */
    private array $originalDiskUrls = [];

    private ?string $originalAppUrl = null;

    public function __construct(private Application $app) {}

    public function bootstrap(Tenant $tenant): void
    {
        // On the tenant's own host the request already has the right root. Anywhere else (queue, artisan,
        // or the central signup page creating the tenant) force the tenant's domain.
        $inRequest = ! $this->app->runningInConsole() && $this->app->bound('request')
            && ! Tenancy::isCentralHost($this->app['request']->getHost());
        $base = $inRequest
            ? $this->app['request']->getSchemeAndHttpHost()
            : ($tenant instanceof SaasTenant ? rtrim($tenant->url(), '/') : rtrim((string) $this->app['config']['app.url'], '/'));

        if (! $inRequest) {
            $this->originalAppUrl = $this->app['config']['app.url'];
            $this->app['config']['app.url'] = $base;
            URL::forceRootUrl($base);
        }

        foreach (['public', 'uploads'] as $disk) {
            if ($this->app['config']["filesystems.disks.{$disk}.driver"] !== 'local') {
                continue;
            }
            $this->originalDiskUrls[$disk] = $this->app['config']["filesystems.disks.{$disk}.url"];
            $this->app['config']["filesystems.disks.{$disk}.url"] = $base.'/storage/'.$this->app['config']['tenancy.filesystem.suffix_base'].$tenant->getTenantKey();
        }

        Storage::forgetDisk(array_keys($this->originalDiskUrls));
    }

    public function revert(): void
    {
        if ($this->originalAppUrl !== null) {
            $this->app['config']['app.url'] = $this->originalAppUrl;
            URL::forceRootUrl(null);
            $this->originalAppUrl = null;
        }

        foreach ($this->originalDiskUrls as $disk => $url) {
            $this->app['config']["filesystems.disks.{$disk}.url"] = $url;
        }
        Storage::forgetDisk(array_keys($this->originalDiskUrls));
        $this->originalDiskUrls = [];
    }
}
