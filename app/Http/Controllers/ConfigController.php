<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\BackendConfig;
use App\Services\OpenSearch\OpenSearchClient;
use App\Support\BackendEndpoint;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function index(OpenSearchClient $client, BackendConfig $backend)
    {
        return view('config.index', [
            'settings' => $backend->current(),
            'status' => $this->probe($client),
            'source' => $backend->storedKeys(),
        ]);
    }

    public function update(Request $request, BackendConfig $backend)
    {
        $request->merge([
            'opensearch_host' => BackendEndpoint::normalizeUrl($request->input('opensearch_host')),
        ]);

        // Accept "host:port" in the host field and move the port to its own input.
        if ($request->filled('opensearch_host')) {
            [$host, $port] = BackendEndpoint::splitHostPort((string) $request->input('opensearch_host'));

            $request->merge([
                'opensearch_host' => $host,
                'opensearch_port' => $port ?? $request->input('opensearch_port'),
            ]);
        }

        $data = $request->validate([
            'opensearch_host' => ['required', 'string', 'max:255', 'url'],
            'opensearch_port' => ['required', 'integer', 'between:1,65535'],
            'opensearch_username' => ['nullable', 'string', 'max:120'],
            'opensearch_password' => ['nullable', 'string', 'max:255'],
            'opensearch_tls_verify' => ['boolean'],
        ]);

        $verifyTls = $request->boolean('opensearch_tls_verify');
        $password = $data['opensearch_password'] ?? null;

        // An empty password field means "keep the stored one".
        if ($password === null || $password === '') {
            $password = (string) config('opensearch.password');
        }

        $result = $backend->probe(
            $data['opensearch_host'],
            (int) $data['opensearch_port'],
            $data['opensearch_username'] ?? null,
            $password,
            $verifyTls
        );

        if (! $result['ok']) {
            return back()
                ->withInput($request->except('opensearch_password'))
                ->withErrors(['opensearch_host' => $result['message']]);
        }

        $backend->save([
            'opensearch_host' => $data['opensearch_host'],
            'opensearch_port' => (int) $data['opensearch_port'],
            'opensearch_username' => $data['opensearch_username'] ?? '',
            'opensearch_password' => $data['opensearch_password'] ?? '',
            'opensearch_tls_verify' => $verifyTls,
        ]);

        AuditLog::record('config.updated', 'backend', [
            'opensearch_host' => $data['opensearch_host'],
            'opensearch_port' => (int) $data['opensearch_port'],
            'opensearch_username' => $data['opensearch_username'] ?: null,
            'tls_verify' => $verifyTls,
            'password_changed' => ! empty($data['opensearch_password']),
        ]);

        return back()->with('status', 'Backend configuration saved. New requests now use the updated endpoint.');
    }

    public function test(OpenSearchClient $client)
    {
        $status = $this->probe($client);

        return back()->with(
            str_starts_with($status, 'Unreachable') ? 'error' : 'status',
            $status
        );
    }

    /**
     * Reset every override and fall back to the values in .env.
     */
    public function reset(BackendConfig $backend)
    {
        $backend->forget();

        AuditLog::record('config.reset', 'backend');

        return back()->with('status', 'Overrides removed. The backend now uses the .env values.');
    }

    private function probe(OpenSearchClient $client): string
    {
        try {
            $health = $client->clusterHealth();

            return sprintf(
                'OpenSearch %s | cluster "%s" | %d nodes | %s',
                $health['status'] ?? 'unknown',
                $health['cluster_name'] ?? 'unnamed',
                $health['number_of_nodes'] ?? 0,
                config('opensearch.host') . ':' . config('opensearch.port')
            );
        } catch (\Throwable $e) {
            return 'Unreachable: ' . $e->getMessage();
        }
    }
}
