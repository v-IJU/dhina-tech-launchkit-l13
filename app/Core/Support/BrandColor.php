<?php

declare(strict_types=1);

namespace App\Core\Support;

/**
 * Settings > Branding > Primary colour → CSS variables for the admin panel and the website.
 */
final class BrandColor
{
    public const DEFAULT = '#2563eb';

    public static function current(): string
    {
        $hex = (string) setting('brand_color', self::DEFAULT);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? strtolower($hex) : self::DEFAULT;
    }

    public static function isDefault(): bool
    {
        return self::current() === self::DEFAULT;
    }

    /**
     * Mix with black (negative) or white (positive): -0.15 = 15% darker, 0.9 = very light tint.
     */
    public static function shade(string $hex, float $amount): string
    {
        [$r, $g, $b] = self::rgb($hex);
        $target = $amount < 0 ? 0 : 255;
        $amount = abs($amount);
        $mix = fn (int $c) => (int) round($c + ($target - $c) * $amount);

        return sprintf('#%02x%02x%02x', $mix($r), $mix($g), $mix($b));
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    /**
     * CSS for the admin panel (Bootstrap). "html:root" outranks admin.css's
     * ":root, [data-bs-theme=light]" even when Vite's dev server injects CSS later.
     */
    public static function adminCss(): string
    {
        $c = self::current();

        return sprintf('html:root{--app-brand:%s;--app-brand-dark:%s;--app-brand-darker:%s;--app-brand-rgb:%s}',
            $c, self::shade($c, -0.15), self::shade($c, -0.3), implode(', ', self::rgb($c)));
    }

    /** CSS for the website (Tailwind brand-* colours). */
    public static function websiteCss(): string
    {
        $c = self::current();

        return sprintf('html:root{--color-brand-50:%s;--color-brand-100:%s;--color-brand-500:%s;--color-brand-600:%s;--color-brand-700:%s;--color-brand-900:%s}',
            self::shade($c, 0.93), self::shade($c, 0.85), self::shade($c, 0.2), $c, self::shade($c, -0.15), self::shade($c, -0.45));
    }
}
