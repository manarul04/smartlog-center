<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * All stored overrides as a key => value map, cached until the next write.
     *
     * The cache key carries the active database identity: switching the
     * database (the setup wizard, or a different .env) must never serve the
     * previous install's settings.
     */
    public static function values(): array
    {
        try {
            return Cache::rememberForever(self::cacheKey(), fn () => self::query()->pluck('value', 'key')->all());
        } catch (\Throwable) {
            // The settings table may not exist yet (fresh install, migrations).
            return [];
        }
    }

    public static function cacheKey(): string
    {
        $connection = (string) config('database.default');
        $database = (array) config("database.connections.{$connection}", []);

        return 'settings:values:'.sha1(implode('|', [
            $connection,
            $database['host'] ?? '',
            $database['port'] ?? '',
            $database['database'] ?? '',
            $database['username'] ?? '',
        ]));
    }

    public static function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::cacheKey());
    }
}
