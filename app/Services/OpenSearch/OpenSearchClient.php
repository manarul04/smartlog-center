<?php

namespace App\Services\OpenSearch;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OpenSearchClient
{
    private string $baseUrl;

    public function __construct()
    {
        $config = config('opensearch');
        $this->baseUrl = rtrim($config['host'], '/') . ':' . $config['port'];
    }

    /**
     * Cache keys carry a fingerprint of the active endpoint, so switching the
     * backend from the Configuration page never serves documents from the old
     * cluster, and no global cache flush is needed.
     */
    private function key(string $name): string
    {
        $config = config('opensearch');
        $fingerprint = substr(sha1(implode('|', [
            $this->baseUrl,
            (string) $config['username'],
            sha1((string) $config['password']),
            $config['tls_verify'] ? 'verify' : 'insecure',
        ])), 0, 10);

        return "os:{$fingerprint}:{$name}";
    }

    public function search(string $index, array $body, ?string $cacheKey = null, ?int $ttl = null): array
    {
        $ttl = $ttl ?? (int) config('opensearch.cache_ttl');

        if ($cacheKey !== null && $ttl > 0) {
            return Cache::remember($this->key($cacheKey), $ttl, fn () => $this->request('POST', "{$index}/_search", $body));
        }

        return $this->request('POST', "{$index}/_search", $body);
    }

    public function searchUncached(string $index, array $body): array
    {
        return $this->request('POST', "{$index}/_search", $body);
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    public function clusterHealth(): array
    {
        return Cache::remember($this->key('cluster-health'), 10, fn () => $this->request('GET', '_cluster/health'));
    }

    public function indices(): array
    {
        return Cache::remember($this->key('indices'), 60, fn () => $this->request('GET', '_cat/indices?format=json'));
    }

    public function isHealthy(): bool
    {
        try {
            $health = $this->clusterHealth();

            return isset($health['status']) && in_array($health['status'], ['green', 'yellow'], true);
        } catch (RuntimeException) {
            return false;
        }
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        $config = config('opensearch');

        $request = Http::withBasicAuth($config['username'], $config['password'])
            ->timeout($config['timeout'])
            ->acceptJson()
            ->asJson();

        if (! $config['tls_verify']) {
            $request = $request->withOptions(['verify' => false]);
        }

        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $started = microtime(true);

        try {
            $response = $method === 'GET'
                ? $request->get($url)
                : $request->send($method, $url, ['json' => $body ?? []]);
        } catch (\Throwable $e) {
            Log::error('OpenSearch request failed', ['path' => $path, 'error' => $e->getMessage()]);
            throw new RuntimeException('Unable to reach the OpenSearch backend: ' . $e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            Log::warning('OpenSearch returned an error', [
                'path' => $path,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);
            throw new RuntimeException(
                "OpenSearch responded with HTTP {$response->status()} for {$path}",
                $response->status()
            );
        }

        Log::debug('OpenSearch query', [
            'path' => $path,
            'ms' => round((microtime(true) - $started) * 1000, 1),
        ]);

        return $response->json() ?? [];
    }
}
