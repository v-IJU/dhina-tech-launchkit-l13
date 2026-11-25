<?php

declare(strict_types=1);

namespace App\Modules\Saas\Support;

/**
 * Minor units ↔ display. 49900 INR → "₹499", 129900 → "₹1,299", 4999 USD → "$49.99".
 */
final class Money
{
    private const SYMBOLS = ['INR' => '₹', 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'AED' => 'AED ', 'SAR' => 'SAR '];

    public static function format(int $minor, string $currency = 'INR'): string
    {
        $major = $minor / 100;
        $decimals = fmod($major, 1.0) === 0.0 ? 0 : 2;
        $symbol = self::SYMBOLS[strtoupper($currency)] ?? strtoupper($currency).' ';

        if (strtoupper($currency) === 'INR') {
            return $symbol.self::indian($major, $decimals);
        }

        return $symbol.number_format($major, $decimals);
    }

    /**
     * Form input "499" or "499.50" → 49900 / 49950.
     */
    public static function toMinor(string|int|float|null $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    public static function toMajor(int $minor): string
    {
        $major = $minor / 100;

        return fmod($major, 1.0) === 0.0 ? (string) (int) $major : number_format($major, 2, '.', '');
    }

    /**
     * 1234567 → "12,34,567" (Indian grouping).
     */
    private static function indian(float $amount, int $decimals): string
    {
        $parts = explode('.', number_format($amount, $decimals, '.', ''));
        $int = $parts[0];
        $last3 = substr($int, -3);
        $rest = substr($int, 0, -3);
        $grouped = $rest !== '' ? preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$last3 : $last3;

        return $grouped.(isset($parts[1]) ? '.'.$parts[1] : '');
    }
}
