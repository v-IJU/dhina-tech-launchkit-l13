<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Localization\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the website language for this visit:
 *   ?lang=ta in the URL  →  remembered in a cookie for next visits
 *   otherwise the cookie, otherwise the browser's language, otherwise the default.
 */
class SetWebsiteLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $fromUrl = $request->query('lang');
        $locale = match (true) {
            is_string($fromUrl) && Locales::isEnabled($fromUrl) => $fromUrl,
            Locales::isEnabled($request->cookie('locale')) => $request->cookie('locale'),
            default => $this->fromBrowser($request) ?? Locales::default(),
        };

        App::setLocale($locale);
        Carbon::setLocale($locale);

        $response = $next($request);

        if (is_string($fromUrl) && Locales::isEnabled($fromUrl) && $request->cookie('locale') !== $fromUrl) {
            Cookie::queue('locale', $fromUrl, 60 * 24 * 365);
        }

        return $response;
    }

    private function fromBrowser(Request $request): ?string
    {
        if (! Locales::isMultilingual()) {
            return null;
        }

        foreach ($request->getLanguages() as $language) {
            $code = strtolower(substr($language, 0, 2));
            if (Locales::isEnabled($code)) {
                return $code;
            }
        }

        return null;
    }
}
