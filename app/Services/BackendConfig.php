<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class BackendConfig
{
    public const KEYS = [
        'opensearch.host',
        'opensearch.port',
        'opensearch.username',
        'opensearch.password',
        'opensearch.tls_verify',
    ];

    /**
     * Probe an arbitrary endpoint without persisting anything, so the setup and
     * configuration screens can refuse to save a backend that does not answer.
     *
     * @return array{ok: bool, message: string}
     */
    public function probe(string $host, int $port, ?string $username, ?string $password, bool $verifyTls): array
    {
        $url = rtrim($host, '/').':'.$port.'/_cluster/health';

        try {
            $request = Http::withBasicAuth((string) $username, (string) $password)
                ->timeout(10)
                ->acceptJson();

            if (! $verifyTls) {
                $request = $request->withOptions(['verify' => false]);
            }

            $response = $request->get($url);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $this->transportMessage($e)];
        }

        if ($response->status() === 401 || $response->status() === 403) {
            return ['ok' => false, 'message' => 'Credentials rejected (HTTP '.$response->status().'). Check the username and password.'];
        }

        if ($response->failed()) {
            return ['ok' => false, 'message' => 'Endpoint answered with HTTP '.$response->status().' for /_cluster/health.'];
        }

        $health = $response->json();

        if (! is_array($health) || ! isset($health['status'])) {
            return ['ok' => false, 'message' => 'The response is not an OpenSearch cluster health document. Is the URL pointing at the Wazuh indexer?'];
        }

        return ['ok' => true, 'message' => sprintf(
            'Connected to "%s": status %s, %d node(s).',
            $health['cluster_name'] ?? 'unnamed',
            $health['status'],
            $health['number_of_nodes'] ?? 0
        )];
    }

    /**
     * Turn the most common cURL failures into something an operator can act on
     * instead of a raw certificate path.
     */
    private function transportMessage(\Throwable $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'certificate verify') || str_contains($message, 'SSL certificate problem')) {
            return 'TLS verification failed. Use the CA bundle of the indexer, or turn off "Verify TLS certificate" for a self-signed certificate.';
        }

        if (str_contains($message, 'timed out') || str_contains($message, 'Connection timed out')) {
            return 'The endpoint did not answer in time. Check the host, port and firewall.';
        }

        if (str_contains($message, 'Could not resolve host')) {
            return 'The host name could not be resolved. Check the address.';
        }

        if (str_contains($message, 'Connection refused')) {
            return 'The connection was refused. Is the indexer listening on that port?';
        }

        return 'Cannot reach the endpoint: '.$message;
    }

    /**
     * Store the values that differ from .env. The password is encrypted with
     * the application key, never stored in clear text.
     */
    public function save(array $values, bool $replacePassword = true): void
    {
        $rows = [
            'opensearch.host' => (string) $values['opensearch_host'],
            'opensearch.port' => (string) $values['opensearch_port'],
            'opensearch.username' => (string) ($values['opensearch_username'] ?? ''),
            'opensearch.tls_verify' => $values['opensearch_tls_verify'] ? '1' : '0',
        ];

        if ($replacePassword && ! empty($values['opensearch_password'])) {
            $rows['opensearch.password'] = Crypt::encryptString($values['opensearch_password']);
        }

        Setting::putMany($rows);
    }

    public function forget(): void
    {
        Setting::query()->delete();
        Cache::forget(Setting::cacheKey());
    }

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        return [
            'opensearch_host' => (string) config('opensearch.host'),
            'opensearch_port' => (int) config('opensearch.port'),
            'opensearch_username' => (string) config('opensearch.username'),
            'opensearch_tls_verify' => (bool) config('opensearch.tls_verify'),
            'opensearch_password_set' => (string) config('opensearch.password') !== '',
        ];
    }

    /**
     * Keys that currently come from the database instead of .env.
     *
     * @return string[]
     */
    public function storedKeys(): array
    {
        return array_keys(Setting::values());
    }
}
