<?php

namespace App\Support;

class BackendEndpoint
{
    /**
     * Trim, drop a trailing slash, and assume https for a bare host such as
     * an indexer address pasted straight from Wazuh.
     */
    public static function normalizeUrl(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $value)) {
            $value = 'https://'.$value;
        }

        return rtrim($value, '/');
    }

    /**
     * Split a "scheme://host:port" value into its host and optional port.
     *
     * @return array{0: string, 1: int|null}
     */
    public static function splitHostPort(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            return [$url, null];
        }

        $host = ($parts['scheme'] ?? 'https').'://'.$parts['host'];

        return [$host, isset($parts['port']) ? (int) $parts['port'] : null];
    }
}
