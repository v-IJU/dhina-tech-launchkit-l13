<?php

use App\Modules\Pages\Controllers\Web\PageController;
use App\Modules\Pages\Controllers\Web\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [SeoController::class, 'robots'])->name('robots');

// Catch-all for CMS pages (/about, /services, …). Registered last because
// the Pages module has the highest "order" in module.json.
Route::get('{slug}', [PageController::class, 'show'])
    ->where('slug', '(?!(?:'.preg_quote(config('starter.admin_path'), '#').'|horizon|storage|build|up|broadcasting)$)[a-z0-9\-]+')
    ->name('pages.show');
