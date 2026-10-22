<?php

namespace App\Services\OpenSearch;

class AgentRepository
{
    public function __construct(private OpenSearchClient $client) {}

    public function latestStatus(): array
    {
        $index = config('opensearch.indices.monitoring');

        $body = [
            'size' => 0,
            'query' => ['range' => ['timestamp' => ['gte' => 'now-30m']]],
            'aggs' => [
                'agents' => [
                    'terms' => ['field' => 'id', 'size' => 500],
                    'aggs' => [
                        'latest' => [
                            'top_hits' => [
                                'size' => 1,
                                'sort' => [['timestamp' => ['order' => 'desc']]],
                                '_source' => [
                                    'includes' => [
                                        'id', 'name', 'ip', 'status', 'status_code', 'version',
                                        'os', 'group', 'lastKeepAlive', 'dateAdd', 'node_name',
                                        'registerIP', 'manager', 'host', 'group_config_status',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        try {
            $raw = $this->client->search($index, $body, 'agents:latest', 30);
        } catch (\RuntimeException) {
            return [];
        }

        $agents = [];
        foreach ($raw['aggregations']['agents']['buckets'] ?? [] as $bucket) {
            $hit = $bucket['latest']['hits']['hits'][0]['_source'] ?? null;
            if ($hit === null) {
                continue;
            }
            $agents[] = [
                'id' => $hit['id'] ?? $bucket['key'],
                'name' => $hit['name'] ?? 'unknown',
                'ip' => $hit['ip'] ?? null,
                'status' => $hit['status'] ?? 'unknown',
                'version' => $hit['version'] ?? null,
                'os' => $hit['os']['name'] ?? null,
                'os_platform' => $hit['os']['platform'] ?? null,
                'os_version' => $hit['os']['version'] ?? null,
                'group' => $hit['group'] ?? [],
                'node' => $hit['node_name'] ?? null,
                'last_keepalive' => $hit['lastKeepAlive'] ?? null,
                'added' => $hit['dateAdd'] ?? null,
                'timestamp' => $hit['timestamp'] ?? null,
                'host' => $hit['host'] ?? null,
            ];
        }

        usort($agents, fn ($a, $b) => strcmp((string) $a['name'], (string) $b['name']));

        return $agents;
    }

    public function statusHistory(string $agentId, string $from = 'now-7d'): array
    {
        $index = config('opensearch.indices.monitoring');

        $body = [
            'size' => 0,
            'query' => [
                'bool' => [
                    'must' => [
                        ['term' => ['id' => $agentId]],
                        ['range' => ['timestamp' => ['gte' => $from]]],
                    ],
                ],
            ],
            'aggs' => [
                'timeline' => [
                    'date_histogram' => ['field' => 'timestamp', 'fixed_interval' => '1h', 'min_doc_count' => 0],
                    'aggs' => ['statuses' => ['terms' => ['field' => 'status', 'size' => 10]]],
                ],
            ],
        ];

        try {
            $raw = $this->client->search($index, $body, 'agents:history:' . $agentId . ':' . md5($from), 60);
        } catch (\RuntimeException) {
            return [];
        }

        return $raw['aggregations']['timeline']['buckets'] ?? [];
    }

    public function vulnerabilities(string $from = 'now-30d'): array
    {
        $index = config('opensearch.indices.vulnerabilities');

        $body = [
            'size' => 0,
            'track_total_hits' => true,
            'aggs' => [
                'severity' => ['terms' => ['field' => 'vulnerability.severity', 'size' => 10]],
                'agents' => ['terms' => ['field' => 'agent.name', 'size' => 30]],
                'cves' => [
                    'terms' => ['field' => 'vulnerability.id', 'size' => 15],
                    'aggs' => [
                        'severity' => ['terms' => ['field' => 'vulnerability.severity', 'size' => 1]],
                        'score' => ['max' => ['field' => 'vulnerability.score.base']],
                        'desc' => ['terms' => ['field' => 'vulnerability.description', 'size' => 1]],
                    ],
                ],
                'by_agent_severity' => [
                    'terms' => ['field' => 'agent.name', 'size' => 30],
                    'aggs' => ['severity' => ['terms' => ['field' => 'vulnerability.severity', 'size' => 10]]],
                ],
            ],
        ];

        try {
            $raw = $this->client->search($index, $body, 'vulns:summary', 120);
        } catch (\RuntimeException) {
            return ['available' => false, 'total' => 0, 'severity' => [], 'agents' => [], 'cves' => []];
        }

        $aggs = $raw['aggregations'] ?? [];

        return [
            'available' => true,
            'total' => $raw['hits']['total']['value'] ?? 0,
            'severity' => collect($aggs['severity']['buckets'] ?? [])->map(fn ($b) => ['key' => $b['key'], 'count' => $b['doc_count']])->all(),
            'agents' => collect($aggs['agents']['buckets'] ?? [])->map(fn ($b) => ['key' => $b['key'], 'count' => $b['doc_count']])->all(),
            'cves' => collect($aggs['cves']['buckets'] ?? [])->map(fn ($b) => [
                'id' => $b['key'],
                'count' => $b['doc_count'],
                'severity' => $b['severity']['buckets'][0]['key'] ?? 'Unknown',
                'score' => $b['score']['value'] ?? null,
                'description' => $b['desc']['buckets'][0]['key'] ?? null,
            ])->all(),
            'by_agent_severity' => collect($aggs['by_agent_severity']['buckets'] ?? [])->map(fn ($b) => [
                'agent' => $b['key'],
                'total' => $b['doc_count'],
                'severity' => collect($b['severity']['buckets'] ?? [])->mapWithKeys(fn ($s) => [$s['key'] => $s['doc_count']])->all(),
            ])->all(),
        ];
    }

    /**
     * Paginated vulnerability findings, optionally scoped to one agent and
     * one severity. Returns the hit envelope so callers can link to detail.
     */
    public function vulnerabilityList(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $index = config('opensearch.indices.vulnerabilities');
        $must = [];

        if (! empty($filters['agent'])) {
            $must[] = ['term' => ['agent.name' => $filters['agent']]];
        }

        if (! empty($filters['severity'])) {
            $must[] = ['term' => ['vulnerability.severity' => $filters['severity']]];
        }

        if (! empty($filters['category'])) {
            $must[] = ['term' => ['vulnerability.category' => $filters['category']]];
        }

        if (! empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $must[] = ['bool' => [
                'should' => [
                    ['match_phrase' => ['vulnerability.id' => $q]],
                    ['match_phrase' => ['vulnerability.description' => $q]],
                    ['match_phrase' => ['package.name' => $q]],
                ],
                'minimum_should_match' => 1,
            ]];
        }

        $detected = $this->detectedRange($filters);
        if ($detected !== null) {
            $must[] = $detected;
        }

        $body = [
            'from' => max(0, ($page - 1) * $perPage),
            'size' => $perPage,
            'track_total_hits' => true,
            'sort' => [
                ['vulnerability.score.base' => ['order' => 'desc', 'missing' => '_last']],
                ['vulnerability.id' => ['order' => 'asc']],
            ],
            'query' => $must === [] ? ['match_all' => new \stdClass()] : ['bool' => ['must' => $must]],
        ];

        try {
            $raw = $this->client->searchUncached($index, $body);
        } catch (\RuntimeException) {
            return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'pages' => 0, 'available' => false];
        }

        $total = $raw['hits']['total']['value'] ?? 0;

        return [
            'available' => true,
            'items' => collect($raw['hits']['hits'] ?? [])->map(fn ($hit) => [
                'id' => $hit['_id'],
                'index' => $hit['_index'],
                'source' => $hit['_source'] ?? [],
            ])->all(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * `vulnerability.detected_at` window shared by the list, the facets and
     * the export so all three describe the same findings.
     */
    private function detectedRange(array $filters): ?array
    {
        $range = [];

        if (! empty($filters['from'])) {
            $range['gte'] = $filters['from'];
        }
        if (! empty($filters['to'])) {
            $range['lte'] = $filters['to'];
        }

        return $range === [] ? null : ['range' => ['vulnerability.detected_at' => $range]];
    }

    /**
     * Every finding matching the filters, for PDF export. The caller supplies
     * the cap because a report table is only readable up to a few hundred rows.
     */
    public function vulnerabilityFindings(array $filters, int $limit = 500): array
    {
        $result = $this->vulnerabilityList($filters, 1, $limit);

        return $result['items'];
    }

    /**
     * Severity/category counts for the vulnerability list header.
     */
    public function vulnerabilityFacets(string $agentName, array $filters = []): array
    {
        $index = config('opensearch.indices.vulnerabilities');

        $must = [['term' => ['agent.name' => $agentName]]];
        $detected = $this->detectedRange($filters);
        if ($detected !== null) {
            $must[] = $detected;
        }

        $body = [
            'size' => 0,
            'track_total_hits' => true,
            'query' => ['bool' => ['must' => $must]],
            'aggs' => [
                'severity' => ['terms' => ['field' => 'vulnerability.severity', 'size' => 10]],
                'category' => ['terms' => ['field' => 'vulnerability.category', 'size' => 10]],
                'packages' => ['terms' => ['field' => 'package.name', 'size' => 10]],
            ],
        ];

        try {
            $raw = $this->client->searchUncached($index, $body);
        } catch (\RuntimeException) {
            return ['total' => 0, 'severity' => [], 'category' => [], 'packages' => []];
        }

        $aggs = $raw['aggregations'] ?? [];

        return [
            'total' => $raw['hits']['total']['value'] ?? 0,
            'severity' => collect($aggs['severity']['buckets'] ?? [])
                ->map(fn ($b) => ['key' => $b['key'], 'count' => $b['doc_count']])->all(),
            'category' => collect($aggs['category']['buckets'] ?? [])
                ->map(fn ($b) => ['key' => $b['key'], 'count' => $b['doc_count']])->all(),
            'packages' => collect($aggs['packages']['buckets'] ?? [])
                ->map(fn ($b) => ['key' => $b['key'], 'count' => $b['doc_count']])->all(),
        ];
    }

    /**
     * Individual SCA check results for one agent, with the latest result per
     * check id so a rescan does not duplicate rows.
     */
    public function scaChecks(string $agentName, array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $index = config('opensearch.indices.alerts');
        $must = [
            ['term' => ['decoder.name' => 'sca']],
            ['term' => ['agent.name' => $agentName]],
            ['bool' => ['must_not' => [['term' => ['data.sca.type' => 'summary']]]]],
        ];

        $window = $this->scaRange($filters);
        if ($window !== null) {
            $must[] = $window;
        }

        if (! empty($filters['result'])) {
            $must[] = ['term' => ['data.sca.check.result' => $filters['result']]];
        }

        if (! empty($filters['policy'])) {
            $must[] = ['term' => ['data.sca.policy' => $filters['policy']]];
        }

        if (! empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $must[] = ['bool' => [
                'should' => [
                    ['match_phrase' => ['data.sca.check.title' => $q]],
                    ['match_phrase' => ['data.sca.check.id' => $q]],
                    ['match_phrase' => ['data.sca.check.description' => $q]],
                ],
                'minimum_should_match' => 1,
            ]];
        }

        $body = [
            'from' => max(0, ($page - 1) * $perPage),
            'size' => $perPage,
            'track_total_hits' => true,
            'sort' => [
                ['data.sca.check.result' => ['order' => 'asc']],
                ['timestamp' => ['order' => 'desc']],
            ],
            'query' => ['bool' => ['must' => $must]],
        ];

        try {
            $raw = $this->client->searchUncached($index, $body);
        } catch (\RuntimeException) {
            return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'pages' => 0, 'available' => false];
        }

        $total = $raw['hits']['total']['value'] ?? 0;

        $items = collect($raw['hits']['hits'] ?? [])->map(function ($hit) {
            $sca = $hit['_source']['data']['sca'] ?? [];
            $check = $sca['check'] ?? [];

            return [
                'id' => $hit['_id'],
                'index' => $hit['_index'],
                'check_id' => (string) ($check['id'] ?? ''),
                'title' => $check['title'] ?? '—',
                'result' => $check['result'] ?? 'unknown',
                'description' => $check['description'] ?? null,
                'rationale' => $check['rationale'] ?? null,
                'remediation' => $check['remediation'] ?? null,
                'command' => $check['command'] ?? [],
                'directory' => $check['directory'] ?? [],
                'policy' => $sca['policy'] ?? null,
                'scan_id' => $sca['scan_id'] ?? null,
                'timestamp' => $hit['_source']['timestamp'] ?? null,
                'rule_id' => $hit['_source']['rule']['id'] ?? null,
            ];
        })->all();

        return [
            'available' => true,
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Timestamp window for SCA check events, shared by list, facets and export.
     */
    private function scaRange(array $filters): ?array
    {
        $range = [];

        if (! empty($filters['from'])) {
            $range['gte'] = $filters['from'];
        }
        if (! empty($filters['to'])) {
            $range['lte'] = $filters['to'];
        }

        return $range === [] ? null : ['range' => ['timestamp' => $range]];
    }

    /**
     * Every SCA check matching the filters, for PDF export.
     */
    public function scaCheckResults(string $agentName, array $filters = [], int $limit = 500): array
    {
        $result = $this->scaChecks($agentName, $filters, 1, $limit);

        return $result['items'];
    }

    public function scaFacets(string $agentName, array $filters = []): array
    {        $index = config('opensearch.indices.alerts');

        $must = [
            ['term' => ['decoder.name' => 'sca']],
            ['term' => ['agent.name' => $agentName]],
            ['bool' => ['must_not' => [['term' => ['data.sca.type' => 'summary']]]]],
        ];

        $window = $this->scaRange($filters);
        if ($window !== null) {
            $must[] = $window;
        }

        $body = [
            'size' => 0,
            'track_total_hits' => true,
            'query' => ['bool' => ['must' => $must]],
            'aggs' => [
                'result' => ['terms' => ['field' => 'data.sca.check.result', 'size' => 10]],
                'policies' => ['terms' => ['field' => 'data.sca.policy', 'size' => 20]],
            ],
        ];

        try {
            $raw = $this->client->searchUncached($index, $body);
        } catch (\RuntimeException) {
            return ['total' => 0, 'result' => [], 'policies' => []];
        }

        $aggs = $raw['aggregations'] ?? [];

        return [
            'total' => $raw['hits']['total']['value'] ?? 0,
            'result' => collect($aggs['result']['buckets'] ?? [])
                ->map(fn ($b) => ['key' => $b['key'], 'count' => $b['doc_count']])->all(),
            'policies' => collect($aggs['policies']['buckets'] ?? [])
                ->map(fn ($b) => ['key' => $b['key'], 'count' => $b['doc_count']])->all(),
        ];
    }

    /**
     * Latest SCA summary for one agent inside an optional window, so a report
     * can quote the score that belongs to the period it covers.
     */
    public function scaScore(string $agentName, ?string $from = null, ?string $to = null): ?array
    {
        $index = config('opensearch.indices.alerts');
        $range = [];

        if ($from !== null) {
            $range['gte'] = $from;
        }
        if ($to !== null) {
            $range['lte'] = $to;
        }

        $must = [
            ['term' => ['decoder.name' => 'sca']],
            ['term' => ['data.sca.type' => 'summary']],
            ['term' => ['agent.name' => $agentName]],
        ];

        if ($range !== []) {
            $must[] = ['range' => ['timestamp' => $range]];
        }

        $body = [
            'size' => 0,
            'query' => ['bool' => ['must' => $must]],
            'aggs' => [
                'policies' => [
                    'terms' => ['field' => 'data.sca.policy', 'size' => 20],
                    'aggs' => [
                        'latest' => [
                            'top_hits' => [
                                'size' => 1,
                                'sort' => [['timestamp' => ['order' => 'desc']]],
                                '_source' => ['includes' => ['data.sca', 'timestamp']],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        try {
            $raw = $this->client->searchUncached($index, $body);
        } catch (\RuntimeException) {
            return null;
        }

        $best = null;

        foreach ($raw['aggregations']['policies']['buckets'] ?? [] as $bucket) {
            $hit = $bucket['latest']['hits']['hits'][0] ?? null;
            $sca = $hit['_source']['data']['sca'] ?? null;

            if ($sca === null) {
                continue;
            }

            $candidate = [
                'policy' => $sca['policy'] ?? null,
                'score' => (int) ($sca['score'] ?? 0),
                'passed' => (int) ($sca['passed'] ?? 0),
                'failed' => (int) ($sca['failed'] ?? 0),
                'total_checks' => (int) ($sca['total_checks'] ?? 0),
                'timestamp' => $hit['_source']['timestamp'] ?? null,
            ];

            // Same rule as the dashboard: the policy that assessed the most
            // checks is the one that represents the agent.
            if ($best === null || $candidate['total_checks'] > $best['total_checks']) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * Latest SCA summary per agent.
     *
     * An agent can run several policies (for example a CIS benchmark plus a
     * small custom policy). Taking only the newest summary would let a tiny
     * custom policy with a 100% score hide a CIS policy with hundreds of
     * failures, so the summary with the most checks wins.
     */
    /**
     * Is this exact CVE still reported for the agent/package?
     * Used to verify that a remediation actually removed the finding.
     */
    public function findingExists(string $agentName, string $cveId, ?string $packageName = null): bool
    {
        $index = config('opensearch.indices.vulnerabilities');

        $must = [
            ['term' => ['agent.name' => $agentName]],
            ['term' => ['vulnerability.id' => $cveId]],
        ];

        if ($packageName !== null && $packageName !== '') {
            $must[] = ['term' => ['package.name' => $packageName]];
        }

        $body = [
            'size' => 0,
            'track_total_hits' => true,
            'query' => ['bool' => ['must' => $must]],
        ];

        $raw = $this->client->searchUncached($index, $body);

        return ($raw['hits']['total']['value'] ?? 0) > 0;
    }

    /**
     * Installed software for one agent, grouped by package type.
     * Windows KBs live in a separate inventory index and are returned as well.
     */
    public function inventory(string $agentName, int $limit = 300): array
    {
        $packagesIndex = 'wazuh-states-inventory-packages-*';
        $hotfixIndex = 'wazuh-states-inventory-hotfixes-*';

        $packages = ['items' => [], 'total' => 0, 'types' => []];

        try {
            $body = [
                'size' => $limit,
                'track_total_hits' => true,
                'sort' => [
                    ['package.type' => ['order' => 'asc']],
                    ['package.name' => ['order' => 'asc']],
                ],
                'query' => ['bool' => ['must' => [['term' => ['agent.name' => $agentName]]]]],
                '_source' => ['includes' => ['package.name', 'package.version', 'package.type', 'package.architecture', 'package.description', 'package.vendor', 'package.installed']],
            ];

            $raw = $this->client->searchUncached($packagesIndex, $body);

            $packages['total'] = $raw['hits']['total']['value'] ?? 0;
            $packages['items'] = collect($raw['hits']['hits'] ?? [])->map(function ($hit) {
                $p = $hit['_source']['package'] ?? [];

                return [
                    'name' => $p['name'] ?? '—',
                    'version' => $p['version'] ?? null,
                    'type' => $p['type'] ?? 'unknown',
                    'architecture' => $p['architecture'] ?? null,
                    'vendor' => $p['vendor'] ?? null,
                    'description' => $p['description'] ?? null,
                    'installed' => $p['installed'] ?? null,
                ];
            })->all();

            // Type breakdown for the filter chips.
            $facetBody = [
                'size' => 0,
                'query' => ['bool' => ['must' => [['term' => ['agent.name' => $agentName]]]]],
                'aggs' => ['types' => ['terms' => ['field' => 'package.type', 'size' => 20]]],
            ];
            $facetRaw = $this->client->searchUncached($packagesIndex, $facetBody);
            $packages['types'] = collect($facetRaw['aggregations']['types']['buckets'] ?? [])
                ->map(fn ($b) => ['key' => $b['key'], 'count' => $b['doc_count']])->all();
        } catch (\RuntimeException) {
            // Inventory is supplementary; the page still renders without it.
        }

        $hotfixes = ['items' => [], 'total' => 0];

        try {
            $body = [
                'size' => $limit,
                'track_total_hits' => true,
                'sort' => [['package.hotfix.name' => ['order' => 'asc']]],
                'query' => ['bool' => ['must' => [['term' => ['agent.name' => $agentName]]]]],
                '_source' => ['includes' => ['package.hotfix.name']],
            ];

            $raw = $this->client->searchUncached($hotfixIndex, $body);

            $hotfixes['total'] = $raw['hits']['total']['value'] ?? 0;
            $hotfixes['items'] = collect($raw['hits']['hits'] ?? [])
                ->map(fn ($hit) => $hit['_source']['package']['hotfix']['name'] ?? null)
                ->filter()
                ->values()
                ->all();
        } catch (\RuntimeException) {
            // Windows-only inventory; absent on Linux agents.
        }

        return ['packages' => $packages, 'hotfixes' => $hotfixes];
    }

    /**
     * OS name and architecture reported by syscollector, used to pick the
     * catalog entry that matches the machine (x64 vs arm64 vs Server).
     */
    public function systemProfile(string $agentName): array
    {
        $empty = [
            'os_name' => null,
            'os_version' => null,
            'os_platform' => null,
            'hostname' => null,
            'arch' => null,
        ];

        $body = [
            'size' => 1,
            'query' => ['bool' => ['must' => [['term' => ['agent.name' => $agentName]]]]],
            '_source' => ['includes' => [
                'host.os.name',
                'host.os.version',
                'host.os.platform',
                'host.hostname',
                'host.architecture',
            ]],
        ];

        try {
            $raw = $this->client->searchUncached('wazuh-states-inventory-system-*', $body);
        } catch (\RuntimeException) {
            return $empty;
        }

        $host = $raw['hits']['hits'][0]['_source']['host'] ?? [];

        return [
            'os_name' => $host['os']['name'] ?? null,
            'os_version' => $host['os']['version'] ?? null,
            'os_platform' => $host['os']['platform'] ?? null,
            'hostname' => $host['hostname'] ?? null,
            'arch' => $host['architecture'] ?? null,
        ];
    }

    /**
     * CPU, memory and serial number reported by syscollector. Nulls when the
     * agent has not run the hardware inventory module.
     */
    public function hardware(string $agentName): array
    {
        $empty = [
            'available' => false,
            'cpu_name' => null,
            'cpu_cores' => null,
            'cpu_speed' => null,
            'mem_total' => null,
            'mem_used' => null,
            'mem_free' => null,
            'mem_usage' => null,
            'serial' => null,
        ];

        $body = [
            'size' => 1,
            'query' => ['bool' => ['must' => [['term' => ['agent.name' => $agentName]]]]],
            '_source' => ['includes' => [
                'host.cpu.name',
                'host.cpu.cores',
                'host.cpu.speed',
                'host.memory.total',
                'host.memory.used',
                'host.memory.free',
                'host.memory.usage',
                'host.serial_number',
            ]],
        ];

        try {
            $raw = $this->client->searchUncached('wazuh-states-inventory-hardware-*', $body);
        } catch (\RuntimeException) {
            return $empty;
        }

        $host = $raw['hits']['hits'][0]['_source']['host'] ?? [];

        if ($host === []) {
            return $empty;
        }

        return [
            'available' => true,
            'cpu_name' => $host['cpu']['name'] ?? null,
            'cpu_cores' => isset($host['cpu']['cores']) ? (int) $host['cpu']['cores'] : null,
            'cpu_speed' => isset($host['cpu']['speed']) ? (int) $host['cpu']['speed'] : null,
            'mem_total' => isset($host['memory']['total']) ? (int) $host['memory']['total'] : null,
            'mem_used' => isset($host['memory']['used']) ? (int) $host['memory']['used'] : null,
            'mem_free' => isset($host['memory']['free']) ? (int) $host['memory']['free'] : null,
            'mem_usage' => isset($host['memory']['usage']) ? (float) $host['memory']['usage'] : null,
            'serial' => $host['serial_number'] ?? null,
        ];
    }

    /**
     * Version of a named package on one agent, or null when it is not installed.
     *
     * Looked up directly instead of filtering inventory() output: on a machine
     * with hundreds of npm packages the Windows entries fall outside the page
     * limit, which would silently drop the version correlation.
     */
    public function packageVersion(string $agentName, string $nameFragment): ?string
    {
        $body = [
            'size' => 1,
            'query' => ['bool' => [
                'must' => [
                    ['term' => ['agent.name' => $agentName]],
                    ['match_phrase' => ['package.name' => $nameFragment]],
                ],
            ]],
            '_source' => ['includes' => ['package.version']],
        ];

        try {
            $raw = $this->client->searchUncached('wazuh-states-inventory-packages-*', $body);
        } catch (\RuntimeException) {
            return null;
        }

        return $raw['hits']['hits'][0]['_source']['package']['version'] ?? null;
    }

    /**
     * Every KB article reported by any agent. Used by the one-off backfill.
     */
    public function allHotfixNames(int $limit = 500): array
    {
        $body = [
            'size' => 0,
            'aggs' => [
                'kbs' => ['terms' => ['field' => 'package.hotfix.name', 'size' => $limit]],
            ],
        ];

        try {
            $raw = $this->client->searchUncached('wazuh-states-inventory-hotfixes-*', $body);
        } catch (\RuntimeException) {
            return [];
        }

        return collect($raw['aggregations']['kbs']['buckets'] ?? [])
            ->pluck('key')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Vulnerabilities for a single CVE, used by the Top CVEs drill-down.
     */
    public function vulnerabilityByCve(string $cveId, int $limit = 200): array
    {
        $index = config('opensearch.indices.vulnerabilities');

        $body = [
            'size' => $limit,
            'track_total_hits' => true,
            'sort' => [
                ['vulnerability.score.base' => ['order' => 'desc', 'missing' => '_last']],
                ['agent.name' => ['order' => 'asc']],
            ],
            'query' => ['bool' => ['must' => [['term' => ['vulnerability.id' => $cveId]]]]],
        ];

        try {
            $raw = $this->client->searchUncached($index, $body);
        } catch (\RuntimeException) {
            return ['available' => false, 'total' => 0, 'items' => []];
        }

        return [
            'available' => true,
            'total' => $raw['hits']['total']['value'] ?? 0,
            'items' => collect($raw['hits']['hits'] ?? [])->map(fn ($hit) => [
                'source' => $hit['_source'] ?? [],
            ])->all(),
        ];
    }

    public function scaSummary(): array
    {
        $index = config('opensearch.indices.alerts');

        $body = [
            'size' => 0,
            'query' => [
                'bool' => [
                    'must' => [
                        ['range' => ['timestamp' => ['gte' => 'now-30d']]],
                        ['term' => ['decoder.name' => 'sca']],
                        ['term' => ['data.sca.type' => 'summary']],
                    ],
                ],
            ],
            'aggs' => [
                'agents' => [
                    'terms' => ['field' => 'agent.name', 'size' => 50],
                    'aggs' => [
                        'policies' => [
                            'terms' => ['field' => 'data.sca.policy', 'size' => 20],
                            'aggs' => [
                                'latest' => [
                                    'top_hits' => [
                                        'size' => 1,
                                        'sort' => [['timestamp' => ['order' => 'desc']]],
                                        '_source' => ['includes' => ['data.sca']],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        try {
            $raw = $this->client->search($index, $body, 'sca:summary:v2', 120);
        } catch (\RuntimeException) {
            return [];
        }

        $rows = [];
        foreach ($raw['aggregations']['agents']['buckets'] ?? [] as $bucket) {
            $best = null;

            foreach ($bucket['policies']['buckets'] ?? [] as $policyBucket) {
                $sca = $policyBucket['latest']['hits']['hits'][0]['_source']['data']['sca'] ?? null;
                if ($sca === null) {
                    continue;
                }

                $candidate = [
                    'agent' => $bucket['key'],
                    'score' => (int) ($sca['score'] ?? 0),
                    'passed' => (int) ($sca['passed'] ?? 0),
                    'failed' => (int) ($sca['failed'] ?? 0),
                    'total_checks' => (int) ($sca['total_checks'] ?? 0),
                    'policy' => $sca['policy'] ?? null,
                    'policy_id' => $sca['policy_id'] ?? null,
                    'policies' => array_column($bucket['policies']['buckets'], 'key'),
                ];

                // Prefer the policy that actually assessed the most checks.
                if ($best === null || $candidate['total_checks'] > $best['total_checks']) {
                    $best = $candidate;
                }
            }

            if ($best !== null) {
                $rows[] = $best;
            }
        }

        usort($rows, fn ($a, $b) => $a['score'] <=> $b['score']);

        return $rows;
    }
}
