<?php

namespace App\Services\OpenSearch;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AlertRepository
{
    /**
     * Sortable columns. Only fields that exist as keyword/date/numeric in the
     * index are listed: sorting on an analyzed text field would require
     * fielddata and blow up the heap.
     */
    public const SORTS = [
        'time' => ['field' => 'timestamp', 'label' => 'Time'],
        'level' => ['field' => 'rule.level', 'label' => 'Level'],
        'rule' => ['field' => 'rule.description', 'label' => 'Rule'],
        'rule_id' => ['field' => 'rule.id', 'label' => 'Rule ID'],
        'agent' => ['field' => 'agent.name', 'label' => 'Agent'],
        'decoder' => ['field' => 'decoder.name', 'label' => 'Decoder'],
        'location' => ['field' => 'location', 'label' => 'Location'],
    ];

    public function __construct(private OpenSearchClient $client) {}

    private function alertsIndex(): string
    {
        return config('opensearch.indices.alerts');
    }

    private function timeRange(string $from): array
    {
        return ['range' => ['timestamp' => ['gte' => $from, 'format' => 'epoch_millis||strict_date_optional_time']]];
    }

    /**
     * Resolve the requested sort into an OpenSearch sort clause.
     *
     * Time is always the tie-breaker so paging is stable: without a unique
     * secondary key, rows with the same level/agent could repeat across pages.
     */
    private function buildSort(array $filters): array
    {
        $key = (string) ($filters['sort'] ?? 'time');
        $column = self::SORTS[$key] ?? self::SORTS['time'];
        $direction = strtolower((string) ($filters['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $sort = [[$column['field'] => ['order' => $direction, 'missing' => '_last']]];

        if ($column['field'] !== 'timestamp') {
            $sort[] = ['timestamp' => ['order' => 'desc']];
        }

        return $sort;
    }

    public function overview(string $from = 'now-24h', string $interval = '1h', array $filters = []): array
    {
        $must = [$this->timeRange($from)];
        $must = array_merge($must, $this->buildFilters($filters));

        $body = [
            'size' => 0,
            'track_total_hits' => true,
            'query' => ['bool' => ['must' => $must]],
            'aggs' => [
                'timeline' => [
                    'date_histogram' => [
                        'field' => 'timestamp',
                        'fixed_interval' => $interval,
                        'min_doc_count' => 0,
                    ],
                    'aggs' => [
                        'by_severity' => [
                            'terms' => ['field' => 'rule.level', 'size' => 20],
                        ],
                    ],
                ],
                'levels' => ['terms' => ['field' => 'rule.level', 'size' => 20]],
                'agents' => ['terms' => ['field' => 'agent.name', 'size' => 15]],
                'groups' => ['terms' => ['field' => 'rule.groups', 'size' => 15]],
                'top_rules' => [
                    'terms' => ['field' => 'rule.description', 'size' => 10],
                ],
                'decoders' => ['terms' => ['field' => 'decoder.name', 'size' => 10]],
                'locations' => ['terms' => ['field' => 'location', 'size' => 10]],
                'critical' => ['filter' => ['range' => ['rule.level' => ['gte' => 12]]]],
                'high' => ['filter' => ['range' => ['rule.level' => ['gte' => 10, 'lt' => 12]]]],
                'auth_failed' => ['filter' => ['term' => ['rule.groups' => 'authentication_failed']]],
                'auth_success' => ['filter' => ['term' => ['rule.groups' => 'authentication_success']]],
                'unique_agents' => ['cardinality' => ['field' => 'agent.id']],
                'integrity' => ['filter' => ['prefix' => ['rule.groups' => 'syscheck']]],
            ],
        ];

        $raw = $this->client->search($this->alertsIndex(), $body, 'alerts:overview:' . md5(json_encode([$from, $interval, $filters])));

        return $this->formatOverview($raw, $interval);
    }

    private function formatOverview(array $raw, string $interval): array
    {
        $aggs = $raw['aggregations'] ?? [];

        $timeline = [];
        foreach ($aggs['timeline']['buckets'] ?? [] as $bucket) {
            $entry = [
                'time' => $bucket['key_as_string'] ?? Carbon::createFromTimestampMs($bucket['key'])->toIso8601String(),
                'ts' => $bucket['key'],
                'total' => $bucket['doc_count'],
                'levels' => [],
            ];
            foreach ($bucket['by_severity']['buckets'] ?? [] as $sev) {
                $entry['levels'][(string) $sev['key']] = $sev['doc_count'];
            }
            $timeline[] = $entry;
        }

        return [
            'total' => $raw['hits']['total']['value'] ?? 0,
            'timeline' => $timeline,
            'levels' => $this->buckets($aggs['levels'] ?? []),
            'agents' => $this->buckets($aggs['agents'] ?? []),
            'groups' => $this->buckets($aggs['groups'] ?? []),
            'top_rules' => $this->buckets($aggs['top_rules'] ?? []),
            'decoders' => $this->buckets($aggs['decoders'] ?? []),
            'locations' => $this->buckets($aggs['locations'] ?? []),
            'counters' => [
                'critical' => $aggs['critical']['doc_count'] ?? 0,
                'high' => $aggs['high']['doc_count'] ?? 0,
                'auth_failed' => $aggs['auth_failed']['doc_count'] ?? 0,
                'auth_success' => $aggs['auth_success']['doc_count'] ?? 0,
                'unique_agents' => $aggs['unique_agents']['value'] ?? 0,
                'integrity' => $aggs['integrity']['doc_count'] ?? 0,
                'total' => $raw['hits']['total']['value'] ?? 0,
            ],
            'severity_bands' => $this->bandTotals($aggs['levels']['buckets'] ?? []),
            'took' => $raw['took'] ?? null,
        ];
    }

    private function bandTotals(array $levelBuckets): array
    {
        $bands = config('opensearch.severity_bands');
        $totals = array_fill_keys(array_keys($bands), 0);

        foreach ($levelBuckets as $bucket) {
            $level = (int) $bucket['key'];
            foreach ($bands as $key => $band) {
                if ($level >= $band['min'] && $level <= $band['max']) {
                    $totals[$key] += $bucket['doc_count'];
                    break;
                }
            }
        }

        return $totals;
    }

    private function buckets(array $agg): array
    {
        return collect($agg['buckets'] ?? [])
            ->map(fn ($b) => ['key' => $b['key'], 'count' => $b['doc_count']])
            ->all();
    }

    public function search(array $filters, int $page = 1, int $perPage = 25): array
    {
        $from = $filters['from'] ?? 'now-24h';
        $must = [$this->timeRange($from)];
        $must = array_merge($must, $this->buildFilters($filters));

        $body = [
            'from' => max(0, ($page - 1) * $perPage),
            'size' => $perPage,
            'track_total_hits' => true,
            'sort' => $this->buildSort($filters),
            'query' => ['bool' => array_filter([
                'must' => $must,
                'must_not' => $this->buildMustNot($filters),
            ])],
            'highlight' => [
                'fields' => ['rule.description' => new \stdClass(), 'data.win.system.message' => new \stdClass()],
            ],
        ];

        $raw = $this->client->searchUncached($this->alertsIndex(), $body);
        $total = $raw['hits']['total']['value'] ?? 0;

        return [
            'items' => collect($raw['hits']['hits'] ?? [])->map(fn ($hit) => [
                'id' => $hit['_id'],
                'index' => $hit['_index'],
                'source' => $hit['_source'] ?? [],
                'highlight' => $hit['highlight'] ?? [],
                'sort' => $hit['sort'] ?? null,
            ])->all(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => (int) ceil($total / $perPage),
            'took' => $raw['took'] ?? null,
        ];
    }

    public function find(string $index, string $id): ?array
    {
        try {
            $raw = $this->client->get(rawurlencode($index) . '/_doc/' . rawurlencode($id));

            return $raw['_source'] ?? null;
        } catch (\RuntimeException) {
            return null;
        }
    }

    /**
     * Raw search against the alerts index, for callers that need the hit
     * envelope (_index/_id) rather than a formatted projection.
     */
    public function searchRaw(array $body): array
    {
        return $this->client->searchUncached($this->alertsIndex(), $body);
    }

    public function histogram(string $from, string $interval, ?string $groupField = null): array
    {
        $aggs = [
            'timeline' => [
                'date_histogram' => [
                    'field' => 'timestamp',
                    'fixed_interval' => $interval,
                    'min_doc_count' => 0,
                ],
            ],
        ];

        if ($groupField !== null) {
            $aggs['timeline']['aggs'] = ['group' => ['terms' => ['field' => $groupField, 'size' => 12]]];
        }

        $body = [
            'size' => 0,
            'query' => ['bool' => ['must' => [$this->timeRange($from)]]],
            'aggs' => $aggs,
        ];

        $raw = $this->client->search($this->alertsIndex(), $body, 'alerts:histogram:' . md5(json_encode([$from, $interval, $groupField])));

        return $raw['aggregations']['timeline']['buckets'] ?? [];
    }

    public function mitre(string $from = 'now-30d'): array
    {
        $body = [
            'size' => 0,
            'track_total_hits' => true,
            'query' => [
                'bool' => [
                    'must' => [
                        $this->timeRange($from),
                        ['exists' => ['field' => 'rule.mitre.id']],
                    ],
                ],
            ],
            'aggs' => [
                'tactics' => [
                    'terms' => ['field' => 'rule.mitre.tactic', 'size' => 30],
                    'aggs' => [
                        'techniques' => [
                            'terms' => ['field' => 'rule.mitre.id', 'size' => 200],
                            'aggs' => [
                                'names' => ['terms' => ['field' => 'rule.mitre.technique', 'size' => 1]],
                            ],
                        ],
                    ],
                ],
                'techniques' => [
                    'terms' => ['field' => 'rule.mitre.id', 'size' => 200],
                    'aggs' => [
                        'names' => ['terms' => ['field' => 'rule.mitre.technique', 'size' => 1]],
                        'tactics' => ['terms' => ['field' => 'rule.mitre.tactic', 'size' => 5]],
                        'agents' => ['terms' => ['field' => 'agent.name', 'size' => 10]],
                    ],
                ],
            ],
        ];

        $raw = $this->client->search($this->alertsIndex(), $body, 'alerts:mitre:' . md5($from));
        $aggs = $raw['aggregations'] ?? [];

        $tactics = collect($aggs['tactics']['buckets'] ?? [])->map(fn ($t) => [
            'tactic' => $t['key'],
            'count' => $t['doc_count'],
            'techniques' => collect($t['techniques']['buckets'] ?? [])->map(fn ($tech) => [
                'id' => $tech['key'],
                'count' => $tech['doc_count'],
                'name' => $tech['names']['buckets'][0]['key'] ?? $tech['key'],
            ])->all(),
        ])->all();

        $techniques = collect($aggs['techniques']['buckets'] ?? [])->map(fn ($t) => [
            'id' => $t['key'],
            'count' => $t['doc_count'],
            'name' => $t['names']['buckets'][0]['key'] ?? $t['key'],
            'tactics' => collect($t['tactics']['buckets'] ?? [])->pluck('key')->all(),
            'agents' => collect($t['agents']['buckets'] ?? [])->map(fn ($a) => ['name' => $a['key'], 'count' => $a['doc_count']])->all(),
        ])->all();

        return [
            'tactics' => $tactics,
            'techniques' => $techniques,
            'total' => $raw['hits']['total']['value'] ?? 0,
        ];
    }

    public function compliance(string $from = 'now-30d'): array
    {
        $frameworks = config('opensearch.compliance_frameworks');
        $aggs = [];

        foreach ($frameworks as $key => $framework) {
            $aggs[$key] = [
                'terms' => ['field' => $framework['field'], 'size' => 100],
                'aggs' => [
                    'level' => ['max' => ['field' => 'rule.level']],
                    'last' => ['max' => ['field' => 'timestamp']],
                ],
            ];
        }

        $body = [
            'size' => 0,
            'track_total_hits' => true,
            'query' => ['bool' => ['must' => [$this->timeRange($from)]]],
            'aggs' => $aggs,
        ];

        $raw = $this->client->search($this->alertsIndex(), $body, 'alerts:compliance:' . md5($from));

        $result = [];
        foreach ($frameworks as $key => $framework) {
            $buckets = $raw['aggregations'][$key]['buckets'] ?? [];
            $controls = collect($buckets)->map(fn ($b) => [
                'control' => $b['key'],
                'count' => $b['doc_count'],
                'max_level' => $b['level']['value'] ?? 0,
                'last_seen' => $b['last']['value_as_string'] ?? null,
            ])->sortByDesc('count')->values()->all();

            $result[$key] = [
                'key' => $key,
                'label' => $framework['label'],
                // Each alert can reference several controls, so bucket sums over-count
                // the underlying alerts. Report the alert total and the control coverage.
                'total' => $raw['hits']['total']['value'] ?? 0,
                'controls' => $controls,
                'controls_hit' => count($controls),
            ];
        }

        return $result;
    }

    public function agentActivity(string $from = 'now-24h'): array
    {
        $body = [
            'size' => 0,
            'query' => ['bool' => ['must' => [$this->timeRange($from)]]],
            'aggs' => [
                'agents' => [
                    'terms' => ['field' => 'agent.name', 'size' => 100],
                    'aggs' => [
                        'max_level' => ['max' => ['field' => 'rule.level']],
                        'last_seen' => ['max' => ['field' => 'timestamp']],
                        'severity' => ['terms' => ['field' => 'rule.level', 'size' => 20]],
                        'groups' => ['terms' => ['field' => 'rule.groups', 'size' => 8]],
                    ],
                ],
            ],
        ];

        $raw = $this->client->search($this->alertsIndex(), $body, 'alerts:agent-activity:' . md5($from));

        return collect($raw['aggregations']['agents']['buckets'] ?? [])->map(fn ($b) => [
            'name' => $b['key'],
            'count' => $b['doc_count'],
            'max_level' => $b['max_level']['value'] ?? 0,
            'last_seen' => $b['last_seen']['value_as_string'] ?? null,
            'severity' => collect($b['severity']['buckets'] ?? [])->mapWithKeys(fn ($s) => [(string) $s['key'] => $s['doc_count']])->all(),
            'top_groups' => collect($b['groups']['buckets'] ?? [])->map(fn ($g) => ['key' => $g['key'], 'count' => $g['doc_count']])->all(),
        ])->sortByDesc('count')->values()->all();
    }

    private function buildFilters(array $filters): array
    {
        $clauses = [];

        if (! empty($filters['agent'])) {
            $clauses[] = ['term' => ['agent.name' => $filters['agent']]];
        }
        if (! empty($filters['group'])) {
            $clauses[] = ['term' => ['rule.groups' => $filters['group']]];
        }
        if (! empty($filters['level_min']) || ! empty($filters['level_max'])) {
            $range = [];
            if (isset($filters['level_min']) && $filters['level_min'] !== '') {
                $range['gte'] = (int) $filters['level_min'];
            }
            if (isset($filters['level_max']) && $filters['level_max'] !== '') {
                $range['lte'] = (int) $filters['level_max'];
            }
            $clauses[] = ['range' => ['rule.level' => $range]];
        }
        if (! empty($filters['mitre'])) {
            $clauses[] = ['term' => ['rule.mitre.id' => $filters['mitre']]];
        }
        if (! empty($filters['compliance_field']) && ! empty($filters['compliance_value'])) {
            $clauses[] = ['term' => [$filters['compliance_field'] => $filters['compliance_value']]];
        }
        if (! empty($filters['to'])) {
            $clauses[] = ['range' => ['timestamp' => ['lte' => $filters['to']]]];
        }

        // Handling-state constraints resolved from Postgres. An empty include
        // list must match nothing rather than falling through to "all alerts".
        if (array_key_exists('uid_include', $filters)) {
            $uids = $filters['uid_include'] ?? [];
            $clauses[] = $uids === []
                ? ['match_none' => new \stdClass()]
                : ['terms' => ['id' => array_values($uids)]];
        }

        return $clauses;
    }

    private function buildMustNot(array $filters): array
    {
        $clauses = [];

        if (! empty($filters['uid_exclude'])) {
            $clauses[] = ['terms' => ['id' => array_values($filters['uid_exclude'])]];
        }

        return $clauses;
    }

    /**
     * Aggregate block used by the PDF report: totals plus the breakdowns a
     * reader expects in a written summary.
     */
    public function reportAggregations(array $filters): array
    {
        $from = $filters['from'] ?? 'now-24h';
        $must = [$this->timeRange($from)];
        $must = array_merge($must, $this->buildFilters($filters));

        if (! empty($filters['to'])) {
            $must[] = ['range' => ['timestamp' => ['lte' => $filters['to']]]];
        }

        $body = [
            'size' => 0,
            'track_total_hits' => true,
            'query' => ['bool' => array_filter([
                'must' => $must,
                'must_not' => $this->buildMustNot($filters),
            ])],
            'aggs' => [
                'levels' => ['terms' => ['field' => 'rule.level', 'size' => 20]],
                'agents' => ['terms' => ['field' => 'agent.name', 'size' => 15]],
                'groups' => ['terms' => ['field' => 'rule.groups', 'size' => 15]],
                'rules' => ['terms' => ['field' => 'rule.description', 'size' => 10]],
                'decoders' => ['terms' => ['field' => 'decoder.name', 'size' => 10]],
                'timeline' => [
                    'date_histogram' => ['field' => 'timestamp', 'fixed_interval' => '1d', 'min_doc_count' => 0],
                ],
            ],
        ];

        $raw = $this->client->searchUncached($this->alertsIndex(), $body);
        $aggs = $raw['aggregations'] ?? [];

        $levels = collect($aggs['levels']['buckets'] ?? [])
            ->map(fn ($b) => ['key' => (int) $b['key'], 'count' => $b['doc_count']])
            ->sortKeys()
            ->all();

        $bands = array_fill_keys(array_keys(config('opensearch.severity_bands')), 0);
        foreach ($levels as $level) {
            foreach (config('opensearch.severity_bands') as $key => $band) {
                if ($level['key'] >= $band['min'] && $level['key'] <= $band['max']) {
                    $bands[$key] += $level['count'];
                    break;
                }
            }
        }

        return [
            'total' => $raw['hits']['total']['value'] ?? 0,
            'levels' => $levels,
            'severity' => $bands,
            'agents' => $this->buckets($aggs['agents'] ?? []),
            'groups' => $this->buckets($aggs['groups'] ?? []),
            'rules' => $this->buckets($aggs['rules'] ?? []),
            'decoders' => $this->buckets($aggs['decoders'] ?? []),
            'timeline' => collect($aggs['timeline']['buckets'] ?? [])
                ->map(fn ($b) => ['date' => $b['key_as_string'], 'count' => $b['doc_count']])
                ->all(),
        ];
    }

    /**
     * Most recent alerts for a given level range and/or rule group, used by the
     * overview "latest activity" cards.
     */
    public function latestEvents(array $options = [], int $limit = 5): array
    {
        $must = [];

        if (isset($options['level_min'])) {
            $range = ['gte' => (int) $options['level_min']];
            if (isset($options['level_max'])) {
                $range['lte'] = (int) $options['level_max'];
            }
            $must[] = ['range' => ['rule.level' => $range]];
        }

        if (! empty($options['group'])) {
            $must[] = ['term' => ['rule.groups' => $options['group']]];
        }

        if (! empty($options['from'])) {
            $must[] = $this->timeRange($options['from']);
        }

        $body = [
            'size' => $limit,
            'sort' => [['timestamp' => ['order' => 'desc']]],
            'query' => $must === [] ? ['match_all' => new \stdClass()] : ['bool' => ['must' => $must]],
            '_source' => [
                'includes' => [
                    'timestamp', 'id', 'agent.name', 'agent.ip', 'rule.id',
                    'rule.level', 'rule.description', 'rule.groups', 'decoder.name',
                    'location',
                ],
            ],
        ];

        $raw = $this->client->searchUncached($this->alertsIndex(), $body);

        return collect($raw['hits']['hits'] ?? [])->map(fn ($hit) => [
            'index' => $hit['_index'],
            'id' => $hit['_id'],
            'source' => $hit['_source'] ?? [],
        ])->all();
    }

    /**
     * Total alerts in a window, used as the denominator for triage coverage.
     */
    public function countInRange(string $from, ?string $to = null): int
    {
        $must = [$this->timeRange($from)];

        if ($to !== null) {
            $must[] = ['range' => ['timestamp' => ['lte' => $to]]];
        }

        $body = [
            'size' => 0,
            'track_total_hits' => true,
            'query' => ['bool' => ['must' => $must]],
        ];

        $raw = $this->client->searchUncached($this->alertsIndex(), $body);

        return (int) ($raw['hits']['total']['value'] ?? 0);
    }

    /**
     * Count alerts that belong to an incident group: the same rule firing on
     * the tracked agents inside the incident's window.
     *
     * Returns total plus a per-hour timeline and per-agent breakdown so the
     * incident page can show how the activity is distributed.
     */
    public function countGroupedAlerts(array $agents, string $ruleId, string $from, ?string $to = null): array
    {
        $must = [
            ['term' => ['rule.id' => $ruleId]],
            $this->agentClause($agents),
            ['range' => ['timestamp' => ['gte' => $from]]],
        ];

        if ($to !== null) {
            $must[2]['range']['timestamp']['lte'] = $to;
        }

        $body = [
            'size' => 0,
            'track_total_hits' => true,
            'query' => ['bool' => ['must' => $must]],
            'aggs' => [
                'timeline' => [
                    'date_histogram' => [
                        'field' => 'timestamp',
                        'fixed_interval' => '1h',
                        'min_doc_count' => 0,
                    ],
                ],
                'agents' => ['terms' => ['field' => 'agent.name', 'size' => 20]],
                'levels' => ['terms' => ['field' => 'rule.level', 'size' => 20]],
                'first' => ['min' => ['field' => 'timestamp']],
                'last' => ['max' => ['field' => 'timestamp']],
            ],
        ];

        $raw = $this->client->searchUncached($this->alertsIndex(), $body);
        $aggs = $raw['aggregations'] ?? [];

        return [
            'total' => $raw['hits']['total']['value'] ?? 0,
            'timeline' => collect($aggs['timeline']['buckets'] ?? [])
                ->map(fn ($b) => ['time' => $b['key_as_string'], 'count' => $b['doc_count']])
                ->all(),
            'agents' => collect($aggs['agents']['buckets'] ?? [])
                ->map(fn ($b) => ['key' => $b['key'], 'count' => $b['doc_count']])->all(),
            'levels' => collect($aggs['levels']['buckets'] ?? [])
                ->map(fn ($b) => ['key' => (int) $b['key'], 'count' => $b['doc_count']])->all(),
            'first_seen' => $aggs['first']['value_as_string'] ?? null,
            'last_seen' => $aggs['last']['value_as_string'] ?? null,
        ];
    }

    /**
     * Agent constraint for grouped queries: a single term for one agent, a
     * terms query when the report spans several.
     */
    private function agentClause(array $agents): array
    {
        $agents = array_values(array_filter($agents));

        if (count($agents) === 1) {
            return ['term' => ['agent.name' => $agents[0]]];
        }

        return ['terms' => ['agent.name' => $agents]];
    }

    /**
     * Alert documents for an incident group, newest first, for the detail table.
     */
    public function groupedAlerts(array $agents, string $ruleId, string $from, ?string $to = null, int $limit = 50): array
    {
        $must = [
            ['term' => ['rule.id' => $ruleId]],
            $this->agentClause($agents),
            ['range' => ['timestamp' => ['gte' => $from]]],
        ];

        if ($to !== null) {
            $must[2]['range']['timestamp']['lte'] = $to;
        }

        $body = [
            'size' => $limit,
            'sort' => [['timestamp' => ['order' => 'desc']]],
            'query' => ['bool' => ['must' => $must]],
            '_source' => [
                'includes' => [
                    'timestamp', 'id', 'agent.name', 'agent.ip', 'rule.id',
                    'rule.level', 'rule.description', 'rule.groups', 'decoder.name', 'location',
                ],
            ],
        ];

        $raw = $this->client->searchUncached($this->alertsIndex(), $body);

        return collect($raw['hits']['hits'] ?? [])->map(fn ($hit) => [
            'index' => $hit['_index'],
            'id' => $hit['_id'],
            'source' => $hit['_source'] ?? [],
        ])->all();
    }

    /**
     * Agent → rule tree for the Alert Groups page.
     *
     * A composite aggregation is used instead of nested terms aggregations so
     * the bucket count is not capped at the default 10k: each page returns a
     * cursor (`after_key`) and the caller keeps fetching until it is empty.
     *
     * @return array{groups: array<int, array>, truncated: bool, took: int|null}
     */
    public function agentRuleGroups(array $filters, int $maxBuckets = 2000): array
    {
        $must = [$this->timeRange($filters['from'] ?? 'now-24h')];
        $must = array_merge($must, $this->buildFilters($filters));

        // Free-text search narrows the tree instead of filtering rendered rows,
        // so counts and levels stay accurate for the matched subset.
        $term = trim((string) ($filters['q'] ?? ''));

        if ($term !== '') {
            $must[] = ['bool' => [
                'should' => [
                    ['match_phrase' => ['rule.description' => $term]],
                    ['term' => ['rule.id' => $term]],
                    ['wildcard' => ['rule.description' => ['value' => '*' . $term . '*', 'case_insensitive' => true]]],
                ],
                'minimum_should_match' => 1,
            ]];
        }

        $groups = [];
        $after = null;
        $truncated = false;
        $took = null;
        $fetched = 0;

        do {
            $composite = [
                'size' => 500,
                'sources' => [
                    ['agent' => ['terms' => ['field' => 'agent.name', 'missing_bucket' => false]]],
                    ['rule' => ['terms' => ['field' => 'rule.id', 'missing_bucket' => false]]],
                ],
            ];

            if ($after !== null) {
                $composite['after'] = $after;
            }

            $body = [
                'size' => 0,
                'track_total_hits' => false,
                'query' => ['bool' => array_filter([
                    'must' => $must,
                    'must_not' => $this->buildMustNot($filters),
                ])],
                'aggs' => [
                    'pairs' => [
                        'composite' => $composite,
                        'aggs' => [
                            'max_level' => ['max' => ['field' => 'rule.level']],
                            'levels' => ['terms' => ['field' => 'rule.level', 'size' => 20]],
                            'first_seen' => ['min' => ['field' => 'timestamp']],
                            'last_seen' => ['max' => ['field' => 'timestamp']],
                            'rule' => [
                                'top_hits' => [
                                    'size' => 1,
                                    'sort' => [['timestamp' => ['order' => 'desc']]],
                                    '_source' => ['includes' => ['rule.description', 'rule.groups']],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $raw = $this->client->searchUncached($this->alertsIndex(), $body);
            $took = $raw['took'] ?? $took;

            $bucketData = $raw['aggregations']['pairs'] ?? [];

            foreach ($bucketData['buckets'] ?? [] as $bucket) {
                $hit = $bucket['rule']['hits']['hits'][0]['_source']['rule'] ?? [];

                $groups[] = [
                    'agent' => (string) $bucket['key']['agent'],
                    'rule_id' => (string) $bucket['key']['rule'],
                    'rule_description' => $hit['description'] ?? null,
                    'rule_groups' => $hit['groups'] ?? [],
                    'count' => (int) $bucket['doc_count'],
                    'max_level' => (int) round($bucket['max_level']['value'] ?? 0),
                    'levels' => collect($bucket['levels']['buckets'] ?? [])
                        ->map(fn ($l) => ['key' => (int) $l['key'], 'count' => (int) $l['doc_count']])
                        ->sortKeysDesc()
                        ->values()
                        ->all(),
                    'first_seen' => $bucket['first_seen']['value_as_string'] ?? null,
                    'last_seen' => $bucket['last_seen']['value_as_string'] ?? null,
                ];

                $fetched++;
            }

            $after = $bucketData['after_key'] ?? null;

            if ($fetched >= $maxBuckets) {
                $truncated = $after !== null;
                break;
            }
        } while ($after !== null);

        return [
            'groups' => $this->nestByAgent($groups),
            'total_pairs' => count($groups),
            'truncated' => $truncated,
            'took' => $took,
        ];
    }

    /**
     * Fold flat agent+rule rows into one node per agent, rules sorted by volume
     * so the busiest rule of each endpoint is the first thing an analyst sees.
     */
    private function nestByAgent(array $rows): array
    {
        $agents = [];

        foreach ($rows as $row) {
            $name = $row['agent'];

            if (! isset($agents[$name])) {
                $agents[$name] = [
                    'agent' => $name,
                    'count' => 0,
                    'max_level' => 0,
                    'rules' => 0,
                    'last_seen' => null,
                    'levels' => [],
                    'rule_rows' => [],
                ];
            }

            $agents[$name]['count'] += $row['count'];
            $agents[$name]['rules']++;
            $agents[$name]['max_level'] = max($agents[$name]['max_level'], $row['max_level']);

            if ($row['last_seen'] !== null
                && ($agents[$name]['last_seen'] === null || $row['last_seen'] > $agents[$name]['last_seen'])) {
                $agents[$name]['last_seen'] = $row['last_seen'];
            }

            foreach ($row['levels'] as $level) {
                $agents[$name]['levels'][$level['key']] =
                    ($agents[$name]['levels'][$level['key']] ?? 0) + $level['count'];
            }

            $agents[$name]['rule_rows'][] = $row;
        }

        $result = [];

        foreach ($agents as $agent) {
            $rules = $agent['rule_rows'];
            unset($agent['rule_rows']);

            usort($rules, fn ($a, $b) => $b['count'] <=> $a['count']);

            krsort($agent['levels']);

            $agent['levels'] = collect($agent['levels'])
                ->map(fn ($count, $level) => ['key' => (int) $level, 'count' => $count])
                ->values()
                ->all();

            $agent['rule_rows'] = $rules;
            $result[] = $agent;
        }

        usort($result, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $result;
    }

    /**
     * Alerts for one agent + rule inside the window, newest first. Used when a
     * rule row is expanded.
     */
    public function ruleAlerts(string $agent, string $ruleId, string $from, ?string $to = null, int $limit = 25): array
    {
        return $this->groupedAlerts([$agent], $ruleId, $from, $to, $limit);
    }

    public function searchText(array $filters, int $page = 1, int $perPage = 25): array
    {
        if (empty(trim($filters['q'] ?? ''))) {
            return $this->search($filters, $page, $perPage);
        }

        $query = trim($filters['q']);
        $from = $filters['from'] ?? 'now-24h';
        $must = [$this->timeRange($from)];
        $must = array_merge($must, $this->buildFilters($filters));

        $body = [
            'from' => max(0, ($page - 1) * $perPage),
            'size' => $perPage,
            'track_total_hits' => true,
            'sort' => $this->buildSort($filters),
            'query' => [
                'bool' => [
                    'must' => $must,
                    'should' => [
                        ['match' => ['rule.description' => ['query' => $query, 'operator' => 'and']]],
                        ['match_phrase' => ['data.win.system.message' => $query]],
                        ['match_phrase' => ['data.win.eventdata.commandLine' => $query]],
                        ['term' => ['agent.name' => $query]],
                        ['term' => ['agent.ip' => $query]],
                        ['term' => ['rule.id' => $query]],
                        ['wildcard' => ['rule.description' => ['value' => '*' . $query . '*', 'case_insensitive' => true]]],
                    ],
                    'minimum_should_match' => 1,
                    'must_not' => $this->buildMustNot($filters),
                ],
            ],
            'highlight' => [
                'fields' => [
                    'rule.description' => new \stdClass(),
                    'data.win.system.message' => new \stdClass(),
                ],
            ],
        ];

        $raw = $this->client->searchUncached($this->alertsIndex(), $body);
        $total = $raw['hits']['total']['value'] ?? 0;

        return [
            'items' => collect($raw['hits']['hits'] ?? [])->map(fn ($hit) => [
                'id' => $hit['_id'],
                'index' => $hit['_index'],
                'source' => $hit['_source'] ?? [],
                'highlight' => $hit['highlight'] ?? [],
            ])->all(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => (int) ceil($total / $perPage),
            'took' => $raw['took'] ?? null,
        ];
    }

    public function filterOptions(string $from = 'now-7d'): array
    {
        $body = [
            'size' => 0,
            'query' => ['bool' => ['must' => [$this->timeRange($from)]]],
            'aggs' => [
                'agents' => ['terms' => ['field' => 'agent.name', 'size' => 100]],
                'groups' => ['terms' => ['field' => 'rule.groups', 'size' => 100]],
                'decoders' => ['terms' => ['field' => 'decoder.name', 'size' => 100]],
                'mitre' => ['terms' => ['field' => 'rule.mitre.id', 'size' => 100]],
            ],
        ];

        $raw = $this->client->search($this->alertsIndex(), $body, 'alerts:filter-options:' . md5($from));
        $aggs = $raw['aggregations'] ?? [];

        return [
            'agents' => array_column($aggs['agents']['buckets'] ?? [], 'key'),
            'groups' => array_column($aggs['groups']['buckets'] ?? [], 'key'),
            'decoders' => array_column($aggs['decoders']['buckets'] ?? [], 'key'),
            'mitre' => array_column($aggs['mitre']['buckets'] ?? [], 'key'),
        ];
    }
}
