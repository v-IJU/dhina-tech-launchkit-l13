<?php

declare(strict_types=1);

namespace App\Core\Support;

/**
 * Small .env editor for the install / convert commands.
 *
 *   EnvFile::set(['STARTER_TENANCY' => 'true', 'CENTRAL_DOMAIN' => 'myapp.com']);
 *
 * Replaces the line when the key exists, appends it otherwise. Values with spaces are quoted.
 */
final class EnvFile
{
    /**
     * @param  array<string, string|int|bool|null>  $values
     */
    public static function set(array $values, ?string $path = null): bool
    {
        $path ??= app()->environmentFilePath();
        if (! is_file($path) || ! is_writable($path)) {
            return false;
        }

        $content = (string) file_get_contents($path);

        foreach ($values as $key => $value) {
            $value = match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                $value === null => 'null',
                default => (string) $value,
            };
            if (preg_match('/\s|#/', $value)) {
                $value = '"'.addcslashes($value, '"').'"';
            }

            $line = $key.'='.$value;
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $content = preg_match($pattern, $content)
                ? (string) preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $content)
                : rtrim($content).PHP_EOL.$line.PHP_EOL;
        }

        return file_put_contents($path, $content) !== false;
    }
}
