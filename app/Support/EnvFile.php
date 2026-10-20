<?php

namespace App\Support;

class EnvFile
{
    /**
     * Parse .env into a plain map. Laravel's Dotenv is immutable, so a value
     * injected into the process environment (for example by `artisan serve`
     * before the installer rewrote .env) wins over the file. Reading the file
     * here lets the installer's changes apply without a restart.
     *
     * @return array<string, string>
     */
    public static function values(): array
    {
        $path = app()->environmentFilePath();

        if (! is_file($path)) {
            return [];
        }

        $values = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);

            if (preg_match('/^[A-Z0-9_]+$/i', $key) !== 1) {
                continue;
            }

            $values[$key] = self::unquote(trim($value));
        }

        return $values;
    }

    private static function unquote(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $first = $value[0];

        if (($first === '"' || $first === "'") && str_ends_with($value, $first)) {
            $inner = substr($value, 1, -1);

            return $first === '"' ? stripcslashes($inner) : $inner;
        }

        // Drop a trailing " # comment" from unquoted values.
        $comment = strpos($value, ' #');

        return $comment === false ? $value : rtrim(substr($value, 0, $comment));
    }
}
