<?php

declare(strict_types=1);

namespace App\Modules\Pages\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Blog\Models\Post;
use App\Modules\Pages\Models\Page;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), fn () => self::buildSitemap());

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        $lines = setting('allow_indexing', true) && app()->isProduction()
            ? ['User-agent: *', 'Disallow: /'.config('starter.admin_path').'/', 'Allow: /', '', 'Sitemap: '.url('/sitemap.xml')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain']);
    }

    public static function buildSitemap(): string
    {
        $urls = Page::query()->originals()->where('status', 'published')->get()
            ->map(fn (Page $p) => ['loc' => $p->url(), 'lastmod' => $p->updated_at, 'priority' => $p->is_home ? '1.0' : '0.8']);

        if (module_enabled('blog')) {
            $urls->push(['loc' => url('/blog'), 'lastmod' => now(), 'priority' => '0.7']);
            Post::query()->published()->latest('published_at')->limit(5000)->get()
                ->each(fn (Post $post) => $urls->push(['loc' => route('blog.show', $post), 'lastmod' => $post->updated_at, 'priority' => '0.6']));
        }

        $body = $urls->map(fn ($u) => '  <url><loc>'.e($u['loc']).'</loc><lastmod>'.$u['lastmod']->toAtomString().'</lastmod><priority>'.$u['priority'].'</priority></url>')->join("\n");

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n".$body."\n</urlset>\n";
    }
}
