<?php

declare(strict_types=1);

namespace App\Core\Localization;

use Illuminate\Support\Facades\App;

/**
 * Website languages.
 *  - available(): every language in config('starter.locales')
 *  - enabled():   the default language + the ones ticked in Settings > Languages
 *  - current():   the language of this request (set by SetWebsiteLocale middleware)
 */
final class Locales
{
    /**
     * @return array<string, array{name: string, native: string, dir: string}>
     */
    public static function available(): array
    {
        return config('starter.locales', []);
    }

    public static function default(): string
    {
        // Not config('app.locale'): that changes to the visitor's language during the request.
        $default = (string) (setting('default_locale') ?: 'en');

        return array_key_exists($default, self::available()) ? $default : 'en';
    }

    /**
     * @return array<int, string> default first
     */
    public static function enabled(): array
    {
        $extra = array_filter(array_map('trim', explode(',', (string) setting('website_locales', ''))));
        $extra = array_values(array_intersect($extra, array_keys(self::available())));

        return array_values(array_unique([self::default(), ...$extra]));
    }

    /**
     * Enabled languages other than the default (the ones content can be translated into).
     *
     * @return array<int, string>
     */
    public static function translatable(): array
    {
        return array_values(array_diff(self::enabled(), [self::default()]));
    }

    public static function isMultilingual(): bool
    {
        return count(self::enabled()) > 1;
    }

    public static function isEnabled(?string $code): bool
    {
        return $code !== null && in_array($code, self::enabled(), true);
    }

    public static function current(): string
    {
        return App::getLocale();
    }

    public static function isDefault(?string $code = null): bool
    {
        return ($code ?? self::current()) === self::default();
    }

    public static function name(string $code, bool $native = false): string
    {
        return self::available()[$code][$native ? 'native' : 'name'] ?? strtoupper($code);
    }

    public static function dir(?string $code = null): string
    {
        return self::available()[$code ?? self::current()]['dir'] ?? 'ltr';
    }

    /**
     * The current page in another language: same URL with ?lang=xx.
     */
    public static function switchUrl(string $code): string
    {
        $query = array_merge(request()->query(), ['lang' => $code]);

        return request()->url().'?'.http_build_query($query);
    }
}
