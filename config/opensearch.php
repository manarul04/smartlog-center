<?php

return [
    /*
     * An empty host means the console has never been set up: every page,
     * including the sign-in screen, is redirected to /setup until it is filled
     * in from the setup wizard (or .env).
     */
    'host' => env('OPENSEARCH_HOST', ''),
    'port' => (int) env('OPENSEARCH_PORT', 9200),
    'username' => env('OPENSEARCH_USERNAME', 'dashboard'),
    'password' => env('OPENSEARCH_PASSWORD', ''),
    'tls_verify' => filter_var(env('OPENSEARCH_TLS_VERIFY', false), FILTER_VALIDATE_BOOLEAN),
    'timeout' => (int) env('OPENSEARCH_TIMEOUT', 30),
    'cache_ttl' => (int) env('OPENSEARCH_CACHE_TTL', 45),

    'indices' => [
        'alerts' => env('OPENSEARCH_INDEX_ALERTS', 'wazuh-alerts-*'),
        'monitoring' => env('OPENSEARCH_INDEX_MONITORING', 'wazuh-monitoring-*'),
        'vulnerabilities' => env('OPENSEARCH_INDEX_VULNERABILITIES', 'wazuh-states-vulnerabilities-*'),
    ],

    /**
     * Severity bands derived from Wazuh rule.level (0-15).
     */
    'severity_bands' => [
        'critical' => ['min' => 12, 'max' => 15, 'label' => 'Critical', 'color' => '#f43f5e'],
        'high' => ['min' => 10, 'max' => 11, 'label' => 'High', 'color' => '#fb923c'],
        'medium' => ['min' => 7, 'max' => 9, 'label' => 'Medium', 'color' => '#fbbf24'],
        'low' => ['min' => 4, 'max' => 6, 'label' => 'Low', 'color' => '#38bdf8'],
        'info' => ['min' => 0, 'max' => 3, 'label' => 'Info', 'color' => '#64748b'],
    ],

    'compliance_frameworks' => [
        'pci_dss' => ['label' => 'PCI DSS', 'field' => 'rule.pci_dss'],
        'hipaa' => ['label' => 'HIPAA', 'field' => 'rule.hipaa'],
        'gdpr' => ['label' => 'GDPR', 'field' => 'rule.gdpr'],
        'nist_800_53' => ['label' => 'NIST 800-53', 'field' => 'rule.nist_800_53'],
        'tsc' => ['label' => 'TSC (SOC 2)', 'field' => 'rule.tsc'],
    ],

    'time_ranges' => [
        '15m' => ['label' => 'Last 15 minutes', 'from' => 'now-15m', 'interval' => '1m'],
        '1h' => ['label' => 'Last 1 hour', 'from' => 'now-1h', 'interval' => '5m'],
        '24h' => ['label' => 'Last 24 hours', 'from' => 'now-24h', 'interval' => '1h'],
        '7d' => ['label' => 'Last 7 days', 'from' => 'now-7d', 'interval' => '6h'],
        '30d' => ['label' => 'Last 30 days', 'from' => 'now-30d', 'interval' => '1d'],
        '90d' => ['label' => 'Last 90 days', 'from' => 'now-90d', 'interval' => '1d'],
    ],
];
