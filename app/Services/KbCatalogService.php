<?php

namespace App\Services;

use App\Models\KbCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolves Windows KB article numbers to the human-readable titles the
 * Microsoft Update Catalog publishes.
 *
 * Wazuh's hotfix inventory only stores "KB5007651"; the title, product and
 * release date live on Microsoft's side, so they are fetched once and cached
 * in the local database. Nothing here runs on a schedule: the agent page asks
 * for a handful of missing titles at a time, and `kb:sync` is used for the
 * initial backfill.
 */
class KbCatalogService
{
    /** A resolved title is considered fresh for this long. */
    private const FRESH_DAYS = 30;

    /** Give up on an article that Microsoft does not list. */
    private const MAX_ATTEMPTS = 3;

    /** Politeness delay between two requests to Microsoft. */
    private const THROTTLE_US = 1_200_000;

    private const TIMEOUT = 8;

    private float $lastRequestAt = 0.0;

    /**
     * Cached rows for the given KBs, keyed by KB. No network access.
     *
     * When agent context is given, the stored catalog variants are re-scored so
     * the title shown belongs to the build that machine actually runs.
     *
     * @param  array{os_name?: ?string, arch?: ?string, package_version?: ?string}  $context
     * @return array<string, array{title: ?string, product: ?string, url: string, status: string, variant: ?array}>
     */
    public function titles(array $kbs, array $context = []): array
    {
        $rows = KbCatalog::forKeys($kbs);
        $out = [];

        foreach (array_values(array_unique(array_filter($kbs))) as $kb) {
            $row = $rows[$kb] ?? null;

            if ($row === null) {
                // Never looked up: the page shows the code and asks for it in
                // the background rather than blocking on Microsoft.
                $out[$kb] = [
                    'title' => null,
                    'product' => null,
                    'date' => null,
                    'url' => $this->supportUrl($kb),
                    'status' => 'pending',
                ];

                continue;
            }

            $presented = $this->present($row);

            if ($presented['title'] !== null && $context !== [] && is_array($row->variants) && $row->variants !== []) {
                $best = $this->pickVariant(
                    $row->variants,
                    $context['os_name'] ?? null,
                    $context['arch'] ?? null,
                    $context['package_version'] ?? null,
                );

                if ($best !== null && ! empty($best['title'])) {
                    $presented['title'] = $best['title'];
                    $presented['product'] = $best['product'] ?? $presented['product'];
                }
            }

            $out[$kb] = $presented;
        }

        return $out;
    }

    /**
     * KBs that still need a network lookup, in the order given.
     */
    public function pending(array $kbs): array
    {
        $rows = KbCatalog::forKeys($kbs);

        return collect($kbs)
            ->unique()
            ->filter(function ($kb) use ($rows) {
                $row = $rows[$kb] ?? null;

                if ($row === null) {
                    return true;
                }

                if ($row->status === 'ok') {
                    return $row->fetched_at === null
                        || $row->fetched_at->lt(now()->subDays(self::FRESH_DAYS));
                }

                // Not found: retry a couple of times, then leave it alone.
                return $row->attempts < self::MAX_ATTEMPTS
                    && ($row->fetched_at === null || $row->fetched_at->lt(now()->subDays(7)));
            })
            ->values()
            ->all();
    }

    /**
     * Fetch and cache up to $limit missing titles, then return everything that
     * is now known for the requested KBs.
     *
     * $context carries the agent's OS/arch so the catalog entry that actually
     * applies to that machine is the one stored as the display title.
     *
     * @param  array{os_name?: ?string, arch?: ?string, package_version?: ?string}  $context
     */
    public function resolve(array $kbs, int $limit = 5, array $context = []): array
    {
        $todo = array_slice($this->pending($kbs), 0, max(0, $limit));

        foreach ($todo as $kb) {
            $this->fetchOne($kb, $context);
        }

        return $this->titles($kbs, $context);
    }

    /**
     * Backfill every KB currently reported by the agents.
     *
     * @param  callable(string, array):void|null  $progress
     * @return array{fetched: int, skipped: int, failed: int}
     */
    public function syncAll(array $kbs, ?callable $progress = null, int $limit = 0): array
    {
        $todo = $this->pending($kbs);

        if ($limit > 0) {
            $todo = array_slice($todo, 0, $limit);
        }

        $stats = ['fetched' => 0, 'skipped' => count($kbs) - count($todo), 'failed' => 0];

        foreach ($todo as $kb) {
            $row = $this->fetchOne($kb);

            if ($row->status === 'ok') {
                $stats['fetched']++;
            } else {
                $stats['failed']++;
            }

            if ($progress !== null) {
                $progress($kb, $stats);
            }
        }

        return $stats;
    }

    /**
     * Look one article up: catalog first, then the support article as a
     * fallback. Both misses are recorded so they are not retried forever.
     */
    public function fetchOne(string $kb, array $context = []): KbCatalog
    {
        $kb = strtoupper(trim($kb));

        $row = KbCatalog::firstOrNew(['kb' => $kb]);
        $row->attempts = (int) $row->attempts + 1;

        [$variants, $source] = $this->fromCatalog($kb);

        if ($variants === []) {
            $fallback = $this->fromSupport($kb);

            if ($fallback !== null) {
                $variants = [[
                    'title' => $fallback,
                    'product' => null,
                    'classification' => null,
                    'last_updated' => null,
                    'update_id' => null,
                    'arch' => null,
                ]];
                $source = 'support';
            }
        }

        if ($variants === []) {
            $row->fill([
                'status' => 'not_found',
                'source' => 'none',
                'fetched_at' => now(),
            ])->save();

            return $row;
        }

        $best = $this->pickVariant(
            $variants,
            $context['os_name'] ?? null,
            $context['arch'] ?? null,
            $context['package_version'] ?? null,
        );

        $row->fill([
            'title' => $best['title'] ?? null,
            'product' => $best['product'] ?? null,
            'classification' => $best['classification'] ?? null,
            'variants' => $variants,
            'source' => $source,
            'status' => 'ok',
            'fetched_at' => now(),
        ])->save();

        return $row;
    }

    /**
     * Choose the catalog entry that best fits an agent.
     *
     * A single article can ship as x64, arm64 and Server builds with different
     * version numbers, so the package version and the host OS are used to pick
     * the entry that actually applies. Without context the newest wins.
     */
    public function pickVariant(
        array $variants,
        ?string $osName = null,
        ?string $arch = null,
        ?string $packageVersion = null,
    ): ?array {
        if ($variants === []) {
            return null;
        }

        $wantedArch = $this->normaliseArch($arch);
        $wantsServer = $osName !== null && stripos($osName, 'server') !== false;

        $scored = [];

        foreach ($variants as $index => $variant) {
            $score = 0;
            $title = (string) ($variant['title'] ?? '');
            $variantArch = $this->normaliseArch($variant['arch'] ?? $title);

            if ($packageVersion !== null && $packageVersion !== '') {
                $catalogVersion = $this->versionFromTitle($title);

                if ($catalogVersion !== null && $this->versionsOverlap($packageVersion, $catalogVersion)) {
                    // Version correlation is the strongest signal available.
                    $score += 100;
                }
            }

            if ($wantedArch !== null && $variantArch === $wantedArch) {
                $score += 20;
            } elseif ($wantedArch !== null && $variantArch !== null) {
                $score -= 10;
            }

            $isServer = stripos($title, 'server') !== false;

            if ($wantsServer && $isServer) {
                $score += 10;
            } elseif (! $wantsServer && $isServer) {
                $score -= 5;
            }

            $scored[] = [
                'score' => $score,
                'date' => $this->parseDate($variant['last_updated'] ?? null),
                'index' => $index,
                'variant' => $variant,
            ];
        }

        usort($scored, function ($a, $b) {
            return $b['score'] <=> $a['score']
                ?: $b['date'] <=> $a['date']
                ?: $a['index'] <=> $b['index'];
        });

        return $scored[0]['variant'];
    }

    /**
     * Shape a stored row for the view.
     */
    public function present(KbCatalog $row): array
    {
        $date = null;

        foreach ((array) $row->variants as $variant) {
            $parsed = $this->parseDate($variant['last_updated'] ?? null);

            if ($parsed > 0 && ($date === null || $parsed > $date)) {
                $date = $parsed;
            }
        }

        return [
            'title' => $row->status === 'ok' ? $row->title : null,
            'product' => $row->product,
            'date' => $date,
            'url' => $this->supportUrl($row->kb),
            'status' => $row->status,
        ];
    }

    public function supportUrl(string $kb): string
    {
        return 'https://support.microsoft.com/help/' . preg_replace('/\D+/', '', $kb);
    }

    /**
     * @return array{0: array<int, array>, 1: string}
     */
    private function fromCatalog(string $kb): array
    {
        $base = rtrim((string) config('kb.catalog_url', 'https://www.catalog.update.microsoft.com/Search.aspx'), '?');
        $html = $this->get($base . '?q=' . urlencode($kb));

        if ($html === null) {
            return [[], 'none'];
        }

        return [$this->parseCatalog($html), 'catalog'];
    }

    private function fromSupport(string $kb): ?string
    {
        $html = $this->get($this->supportUrl($kb));

        if ($html === null) {
            return null;
        }

        if (! preg_match('/<title>(.*?)<\/title>/is', $html, $m)) {
            return null;
        }

        $title = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = preg_replace('/\s*\|\s*Microsoft Support\s*$/i', '', $title) ?? $title;

        // The support site answers unknown articles with a generic error page.
        if ($title === '' || stripos($title, 'Error -') === 0 || stripos($title, 'Office.com') !== false) {
            return null;
        }

        return $title;
    }

    /**
     * Pull one entry per result row out of the catalog search page.
     */
    public function parseCatalog(string $html): array
    {
        $html = $this->toUtf8($html);

        if (! preg_match_all('/<tr id="([0-9a-f\-]{36})_R(\d+)"[^>]*>(.*?)<\/tr>/is', $html, $rows, PREG_SET_ORDER)) {
            return [];
        }

        $out = [];

        foreach ($rows as $row) {
            $updateId = $row[1];
            $body = $row[3];

            if (! preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $body, $cells)) {
                continue;
            }

            $text = array_map(fn ($cell) => $this->clean($cell), $cells[1]);

            // 0 icon, 1 title, 2 products, 3 classification, 4 date, 5 version, 6 size, 7 download
            $title = $text[1] ?? '';

            if ($title === '') {
                continue;
            }

            $out[] = [
                'title' => $title,
                'product' => $text[2] ?? null,
                'classification' => $text[3] ?? null,
                'last_updated' => $text[4] ?? null,
                'version' => $text[5] ?? null,
                'size' => $text[6] ?? null,
                'update_id' => $updateId,
                'arch' => $this->archFromTitle($title),
            ];
        }

        return $out;
    }

    private function get(string $url): ?string
    {
        $wait = self::THROTTLE_US - (int) ((microtime(true) - $this->lastRequestAt) * 1_000_000);

        if ($wait > 0) {
            usleep($wait);
        }

        $this->lastRequestAt = microtime(true);

        try {
            $request = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])->timeout((int) config('kb.timeout', self::TIMEOUT));

            if (! config('kb.tls_verify', true)) {
                $request = $request->withOptions(['verify' => false]);
            }

            $response = $request->get($url);
        } catch (\Throwable $e) {
            Log::warning('KB lookup failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('KB lookup returned an error', ['url' => $url, 'status' => $response->status()]);

            return null;
        }

        return $response->body();
    }

    /**
     * Catalog rows for some languages come back as GB18030 despite the UTF-8
     * content type, which turns the titles into mojibake.
     */
    private function toUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'GB18030');
    }

    private function clean(string $cell): string
    {
        $text = preg_replace('/<[^>]*>/', ' ', $cell) ?? '';
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private function archFromTitle(string $title): ?string
    {
        if (preg_match('/\b(arm64|x64|x86|itanium)\b/i', $title, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }

    private function normaliseArch(?string $arch): ?string
    {
        if ($arch === null || $arch === '') {
            return null;
        }

        $arch = strtolower($arch);

        return match (true) {
            str_contains($arch, 'arm64'), str_contains($arch, 'aarch64') => 'arm64',
            str_contains($arch, 'x86_64'), str_contains($arch, 'amd64'), $arch === 'x64' => 'x64',
            str_contains($arch, 'x86'), str_contains($arch, 'i386'), str_contains($arch, 'i686') => 'x86',
            str_contains($arch, 'ia64'), str_contains($arch, 'itanium') => 'itanium',
            default => null,
        };
    }

    private function versionFromTitle(string $title): ?string
    {
        if (preg_match('/\(Version\s+(\d+(?:\.\d+){2,3})\)/i', $title, $m)) {
            return $m[1];
        }

        if (preg_match('/\((\d{4,5}\.\d{2,6}\.\d{2,6})\)/', $title, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Package versions and catalog versions do not always share a prefix
     * ("1000.29628.1000.0" vs "10.0.29628.1000"), so any run of two consecutive
     * segments is treated as a match.
     */
    private function versionsOverlap(string $packageVersion, string $catalogVersion): bool
    {
        $package = array_values(array_filter(explode('.', $packageVersion), fn ($s) => $s !== ''));

        if (count($package) < 3) {
            return false;
        }

        for ($i = 0; $i < count($package) - 1; $i++) {
            $window = $package[$i] . '.' . $package[$i + 1];

            if (str_contains($catalogVersion, $window)) {
                return true;
            }
        }

        return false;
    }

    private function parseDate(?string $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        try {
            return Carbon::parse($value)->getTimestamp();
        } catch (\Throwable) {
            return 0;
        }
    }
}
