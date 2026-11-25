<?php

declare(strict_types=1);

namespace App\Core\Security;

/**
 * Turns a browser user-agent string into "Chrome on Windows". Simple on purpose:
 * good enough for sign-in history and session lists, no package needed.
 */
final class UserAgent
{
    public function __construct(public readonly string $raw) {}

    public function browser(): string
    {
        foreach (['Edg' => 'Edge', 'OPR' => 'Opera', 'SamsungBrowser' => 'Samsung Internet', 'Chrome' => 'Chrome', 'CriOS' => 'Chrome', 'Firefox' => 'Firefox', 'FxiOS' => 'Firefox', 'Safari' => 'Safari'] as $needle => $name) {
            if (str_contains($this->raw, $needle)) {
                return $name;
            }
        }

        return $this->raw === '' ? 'Unknown' : 'Other browser';
    }

    public function platform(): string
    {
        foreach (['iPhone' => 'iPhone', 'iPad' => 'iPad', 'Android' => 'Android', 'Windows' => 'Windows', 'Mac OS' => 'macOS', 'CrOS' => 'ChromeOS', 'Linux' => 'Linux'] as $needle => $name) {
            if (str_contains($this->raw, $needle)) {
                return $name;
            }
        }

        return 'Unknown OS';
    }

    public function isMobile(): bool
    {
        return (bool) preg_match('/Mobile|Android|iPhone|iPad/i', $this->raw);
    }

    public function label(): string
    {
        return $this->browser().' on '.$this->platform();
    }
}
