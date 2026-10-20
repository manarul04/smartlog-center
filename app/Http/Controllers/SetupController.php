<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\BackendConfig;
use App\Services\DatabaseProbe;
use App\Support\AppKey;
use App\Support\BackendEndpoint;
use App\Support\EnvWriter;
use App\Support\Setup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SetupController extends Controller
{
    /**
     * Three steps: database, Wazuh indexer, administrator. Each one is served on
     * its own so the operator finishes a step before seeing the next.
     */
    public function show(BackendConfig $backend)
    {
        if ($guard = $this->guard()) {
            return $guard;
        }

        $databaseReady = Setup::databaseReady();
        $indexerReady = $databaseReady && trim((string) config('opensearch.host')) !== '';

        $step = match (true) {
            ! $databaseReady => 1,
            ! $indexerReady => 2,
            default => 3,
        };

        return view('setup.index', [
            'step' => $step,
            'values' => $this->defaults($backend),
            'databaseReady' => $databaseReady,
        ]);
    }

    /**
     * The wizard is open to anonymous visitors only on a fresh install. Once an
     * account exists, changing the backend requires an administrator session,
     * so an exposed /setup cannot be used to re-point the console.
     */
    private function guard()
    {
        if (! Setup::required()) {
            return redirect()->route('login');
        }

        if (! Setup::administratorMissing() && ! auth()->user()?->isAdmin()) {
            return redirect()->route('login')
                ->with('error', 'Sign in as an administrator to change the backend.');
        }

        return null;
    }

    /**
     * Step 1: verify the database, build the schema, then persist the
     * credentials. .env is written last so a dev server that restarts on an
     * .env change never interrupts a half-finished migration.
     */
    public function storeDatabase(Request $request, DatabaseProbe $probe)
    {
        if ($guard = $this->guard()) {
            return $guard;
        }

        $data = $this->validateDatabase($request);

        $result = $probe->probe(
            $data['db_connection'],
            $data['db_host'],
            (int) $data['db_port'],
            $data['db_database'],
            $data['db_username'],
            (string) ($data['db_password'] ?? '')
        );

        if (! $result['ok']) {
            return $this->backWithErrors(['db_host' => $result['message']]);
        }

        $this->applyDatabaseConfig($data);

        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {
            report($e);

            return $this->backWithErrors([
                'db_host' => 'The database is reachable but the schema could not be created: '.$e->getMessage(),
            ]);
        }

        // Default triage labels; harmless when they already exist.
        try {
            Artisan::call('db:seed', ['--class' => \Database\Seeders\LabelSeeder::class, '--force' => true]);
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            EnvWriter::setAfterResponse([
                'DB_CONNECTION' => $data['db_connection'],
                'DB_HOST' => $data['db_host'],
                'DB_PORT' => (string) $data['db_port'],
                'DB_DATABASE' => $data['db_database'],
                'DB_USERNAME' => $data['db_username'],
                'DB_PASSWORD' => (string) ($data['db_password'] ?? ''),
            ]);
        } catch (\RuntimeException $e) {
            return $this->backWithErrors(['db_host' => $e->getMessage()]);
        }

        Setup::refresh();

        return redirect()->route('setup')
            ->with('status', 'Database ready. Next, connect the Wazuh indexer.');
    }

    /**
     * Probe the database without writing anything, so a typo does not leave a
     * half-configured .env behind.
     */
    public function testDatabase(Request $request, DatabaseProbe $probe)
    {
        if ($guard = $this->guard()) {
            return $guard;
        }

        $data = $this->validateDatabase($request);

        return response()->json($probe->probe(
            $data['db_connection'],
            $data['db_host'],
            (int) $data['db_port'],
            $data['db_database'],
            $data['db_username'],
            (string) ($data['db_password'] ?? '')
        ));
    }

    /**
     * Probe the indexer without saving, so credentials can be fixed before
     * committing them.
     */
    public function testConnection(Request $request, BackendConfig $backend)
    {
        if ($guard = $this->guard()) {
            return $guard;
        }

        $data = $this->validateEndpoint($request);

        return response()->json($backend->probe(
            $data['opensearch_host'],
            (int) $data['opensearch_port'],
            $data['opensearch_username'] ?? null,
            $data['opensearch_password'] ?? null,
            $data['opensearch_tls_verify']
        ));
    }

    /**
     * Step 2: save the indexer credentials.
     */
    public function storeConnection(Request $request, BackendConfig $backend)
    {
        if ($guard = $this->guard()) {
            return $guard;
        }

        if (! Setup::databaseReady()) {
            return redirect()->route('setup');
        }

        $endpoint = $this->validateEndpoint($request);

        $result = $backend->probe(
            $endpoint['opensearch_host'],
            (int) $endpoint['opensearch_port'],
            $endpoint['opensearch_username'] ?? null,
            $endpoint['opensearch_password'] ?? null,
            $endpoint['opensearch_tls_verify']
        );

        if (! $result['ok']) {
            return $this->backWithErrors(['opensearch_host' => $result['message']]);
        }

        try {
            $backend->save($endpoint);
        } catch (\Throwable $e) {
            report($e);

            return $this->backWithErrors([
                'opensearch_host' => 'The endpoint answered, but the configuration could not be saved: '.$e->getMessage(),
            ]);
        }

        Setup::refresh();

        return redirect()->route('setup')
            ->with('status', 'Indexer connected. Last step: create the administrator account.');
    }

    /**
     * Step 3: create the first administrator. Skipped when one already exists.
     */
    public function storeAdmin(Request $request)
    {
        if ($guard = $this->guard()) {
            return $guard;
        }

        if (! Setup::administratorMissing()) {
            return redirect()->route('login');
        }

        $account = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:60', 'alpha_dash', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()->symbols()],
        ]);

        try {
            User::create([
                'name' => $account['name'],
                'username' => $account['username'],
                'email' => ($account['email'] ?? null) ?: null,
                'password' => bcrypt($account['password']),
                'role' => 'admin',
                'is_active' => true,
                'theme' => 'dark',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return $this->backWithErrors([
                'username' => 'The administrator account could not be created: '.$e->getMessage(),
            ]);
        }

        Setup::refresh();

        // A session that survived from a previous install points at a user id
        // that may not exist in the new database, so start clean.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', 'Setup complete. Sign in with your new administrator account.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateDatabase(Request $request): array
    {
        return $request->validate([
            'db_connection' => ['required', Rule::in(['pgsql', 'mysql'])],
            'db_host' => ['required', 'string', 'max:190'],
            'db_port' => ['required', 'integer', 'between:1,65535'],
            'db_database' => ['required', 'string', 'max:120'],
            'db_username' => ['required', 'string', 'max:120'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEndpoint(Request $request): array
    {
        $request->merge([
            'opensearch_host' => BackendEndpoint::normalizeUrl($request->input('opensearch_host')),
        ]);

        if ($request->filled('opensearch_host')) {
            [$host, $port] = BackendEndpoint::splitHostPort((string) $request->input('opensearch_host'));

            $request->merge([
                'opensearch_host' => $host,
                'opensearch_port' => $port ?? $request->input('opensearch_port', 9200),
            ]);
        }

        $data = $request->validate([
            'opensearch_host' => ['required', 'string', 'max:255', 'url'],
            'opensearch_port' => ['required', 'integer', 'between:1,65535'],
            'opensearch_username' => ['nullable', 'string', 'max:120'],
            'opensearch_password' => ['nullable', 'string', 'max:255'],
            'opensearch_tls_verify' => ['boolean'],
        ]);

        $data['opensearch_tls_verify'] = $request->boolean('opensearch_tls_verify');

        return $data;
    }

    /**
     * Encrypted secrets need an application key. AppServiceProvider generates
     * one on boot when .env has none; this only persists it for deployments
     * that keep configuration in .env.
     */
    private function ensureAppKey(): void
    {
        if ((string) config('app.key') === '') {
            AppKey::ensure();
        }
    }

    private function applyDatabaseConfig(array $database): void
    {
        config([
            'database.default' => $database['db_connection'],
            "database.connections.{$database['db_connection']}.host" => $database['db_host'],
            "database.connections.{$database['db_connection']}.port" => $database['db_port'],
            "database.connections.{$database['db_connection']}.database" => $database['db_database'],
            "database.connections.{$database['db_connection']}.username" => $database['db_username'],
            "database.connections.{$database['db_connection']}.password" => (string) ($database['db_password'] ?? ''),
        ]);

        DB::purge($database['db_connection']);
    }

    private function backWithErrors(array $errors)
    {
        return back()
            ->withInput(request()->except(['password', 'password_confirmation', 'db_password', 'opensearch_password']))
            ->withErrors($errors);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(BackendConfig $backend): array
    {
        $connection = config('database.default') ?: 'pgsql';
        $database = config("database.connections.{$connection}");
        $current = $backend->current();

        return [
            'db_connection' => old('db_connection', $connection),
            'db_host' => old('db_host', $database['host'] ?? ''),
            'db_port' => old('db_port', $database['port'] ?? 5432),
            'db_database' => old('db_database', $database['database'] ?? ''),
            'db_username' => old('db_username', $database['username'] ?? ''),
            'opensearch_host' => old('opensearch_host', $current['opensearch_host']),
            'opensearch_port' => old('opensearch_port', $current['opensearch_port'] ?: 9200),
            'opensearch_username' => old('opensearch_username'),
            'opensearch_tls_verify' => $current['opensearch_tls_verify'],
        ];
    }
}
