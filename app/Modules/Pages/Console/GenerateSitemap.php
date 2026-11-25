<?php

declare(strict_types=1);

namespace App\Modules\Pages\Console;

use App\Modules\Pages\Controllers\Web\SeoController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Rebuilds the cached /sitemap.xml (runs daily from the scheduler).
 */
class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Rebuild the cached sitemap.xml';

    public function handle(): int
    {
        Cache::put('sitemap.xml', SeoController::buildSitemap(), now()->addDay());
        $this->components->info('Sitemap rebuilt: '.url('/sitemap.xml'));

        return self::SUCCESS;
    }
}
