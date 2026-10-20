<?php

namespace App\Providers;

use App\Models\Setting;
use App\Support\AppKey;
use App\Support\EnvFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        AppKey::ensure();
        $this->applyEnvFile();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->applyStoredBackendSettings();
    }

    /**
     * `php artisan serve` passes .env values into the PHP process once, at
     * startup, and Laravel's env repository is immutable. The installer rewrites
     * .env mid-request, so without this overlay the running server would keep
     * querying the previous database until it is restarted.
     */
    private function applyEnvFile(): void
    {
        if (app()->runningUnitTests() || env('APP_ENV') === 'testing') {
            return;
        }

        $values = EnvFile::values();

        if ($values === []) {
            return;
        }

        if (! empty($values['APP_KEY']) && (string) config('app.key') !== $values['APP_KEY']) {
            config(['app.key' => $values['APP_KEY']]);
        }

        $connection = $values['DB_CONNECTION'] ?? null;

        if ($connection === null) {
            return;
        }

        config(['database.default' => $connection]);

        $map = [
            'DB_HOST' => 'host',
            'DB_PORT' => 'port',
            'DB_DATABASE' => 'database',
            'DB_USERNAME' => 'username',
            'DB_PASSWORD' => 'password',
        ];

        foreach ($map as $envKey => $configKey) {
            if (array_key_exists($envKey, $values)) {
                config(["database.connections.{$connection}.{$configKey}" => $values[$envKey]]);
            }
        }
    }

    /**
     * Settings saved from the Configuration page take precedence over .env so
     * the backend can be re-pointed without editing files or clearing config.
     */
    private function applyStoredBackendSettings(): void
    {
        $stored = Setting::values();

        if ($stored === []) {
            return;
        }

        foreach (['opensearch.host', 'opensearch.username'] as $key) {
            if (array_key_exists($key, $stored) && $stored[$key] !== null) {
                config([$key => $stored[$key]]);
            }
        }

        if (! empty($stored['opensearch.password'])) {
            config(['opensearch.password' => $this->decryptSecret($stored['opensearch.password'])]);
        }

        if (! empty($stored['opensearch.port'])) {
            config(['opensearch.port' => (int) $stored['opensearch.port']]);
        }

        if (array_key_exists('opensearch.tls_verify', $stored)) {
            config(['opensearch.tls_verify' => (bool) (int) $stored['opensearch.tls_verify']]);
        }
    }

    /**
     * The stored password is encrypted with the app key. Values written before
     * encryption was introduced, or unreadable after a key rotation, are used
     * as-is so a bad row never locks the console out of its backend.
     */
    private function decryptSecret(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }
}
