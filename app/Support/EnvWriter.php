<?php

namespace App\Support;

class EnvWriter
{
    /**
     * Update keys in .env in place, keeping comments and ordering intact. The
     * file is backed up once before the first write so an operator can always
     * recover the previous configuration.
     */
    public static function set(array $values): void
    {
        $path = self::writablePath();

        $contents = (string) file_get_contents($path);

        $backup = $path.'.setup-backup';
        if (! is_file($backup)) {
            @copy($path, $backup);
        }

        foreach ($values as $key => $value) {
            $line = $key.'='.self::escape((string) $value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $contents) === 1) {
                $contents = (string) preg_replace($pattern, $line, $contents, 1);
            } else {
                $contents = rtrim($contents, "\r\n").PHP_EOL.$line.PHP_EOL;
            }
        }

        file_put_contents($path, $contents, LOCK_EX);
    }

    /**
     * Write once the response is flushed. `php artisan serve` restarts itself
     * when .env changes, so writing mid-request would kill the redirect the
     * browser is waiting for.
     */
    public static function setAfterResponse(array $values): void
    {
        // Fail before the response is built, while the error can still be shown.
        self::writablePath();

        app()->terminating(function () use ($values) {
            try {
                self::set($values);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    private static function writablePath(): string
    {
        $path = app()->environmentFilePath();

        // A fresh clone may not have .env yet (the composer create-project hook
        // does not run on a plain clone), so create it from the example instead
        // of failing the installer.
        if (! is_file($path)) {
            $example = base_path('.env.example');

            if (is_file($example)) {
                @copy($example, $path);
            } else {
                @file_put_contents($path, '');
            }
        }

        if (! is_file($path) || ! is_writable($path)) {
            throw new \RuntimeException('The .env file is not writable. Fix its permissions and try again.');
        }

        return $path;
    }

    /**
     * Values with spaces, quotes or comment characters are quoted; empty values
     * stay bare so env() keeps returning an empty string.
     */
    private static function escape(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/[\s#"\'\\\\]/', $value) === 1) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return $value;
    }
}
