@extends('layouts.app')

@section('title', 'Overview')
@section('subtitle', 'Security posture at a glance')

@section('actions')
    <div class="seg">
        @foreach ($ranges as $key => $meta)
            <a href="{{ route('overview', ['range' => $key]) }}" class="{{ $range === $key ? 'active' : '' }}">{{ $key }}</a>
        @endforeach
    </div>
    <span class="fs-11 text-muted nowrap" data-refresh-interval="60">Refresh <span id="refreshLabel">60s</span></span>
@endsection

@section('content')
    @if ($error)
        <div class="banner error">
            <div>
                <strong>Cannot reach OpenSearch.</strong> {{ $error }}
            </div>
        </div>
    @endif

    @if ($data)
        @php
            $c = $data['counters'];
            $activeAgents = collect($agentStatus)->where('status', 'active')->count();
            $totalAgents = count($agentStatus);
            $criticalish = $data['severity_bands']['critical'] + $data['severity_bands']['high'];
        @endphp

        {{-- KPI row: each card links into the explorer with the matching filter --}}
        <div class="grid grid-4" style="margin-bottom:14px">
            <a class="kpi" style="--kpi-accent:var(--accent)"
               href="{{ route('explorer.index', ['range' => $range]) }}"
               title="Open all alerts for this range">
                <span class="kpi-label">Total Alerts</span>
                <span class="kpi-value">{{ number_format($c['total']) }}</span>
                <span class="kpi-meta">{{ $ranges[$range]['label'] }}</span>
            </a>

            <a class="kpi" style="--kpi-accent:var(--critical)"
               href="{{ route('explorer.index', ['range' => $range, 'level_min' => 10]) }}"
               title="Open critical and high alerts">
                <span class="kpi-label">Critical &amp; High</span>
                <span class="kpi-value" style="color:var(--critical-text)">{{ number_format($criticalish) }}</span>
                <span class="kpi-meta">
                    <span class="badge critical">{{ number_format($data['severity_bands']['critical']) }} critical</span>
                    <span class="badge high">{{ number_format($data['severity_bands']['high']) }} high</span>
                </span>
            </a>

            <a class="kpi" style="--kpi-accent:var(--medium)"
               href="{{ route('explorer.index', ['range' => $range, 'group' => 'authentication_failed']) }}"
               title="Open authentication failures">
                <span class="kpi-label">Auth Failures</span>
                <span class="kpi-value">{{ number_format($c['auth_failed']) }}</span>
                <span class="kpi-meta">
                    {{ number_format($c['auth_success']) }} successful logons
                </span>
            </a>

            <a class="kpi" style="--kpi-accent:var(--success)"
               href="{{ route('agents.index') }}"
               title="Open agent health">
                <span class="kpi-label">Agents Online</span>
                <span class="kpi-value">{{ $activeAgents }}<span class="text-muted" style="font-size:15px;font-weight:500"> / {{ $totalAgents }}</span></span>
                <span class="kpi-meta">
                    {{ number_format($c['integrity']) }} integrity events
                </span>
            </a>
        </div>

        {{-- Trend + severity --}}
        <div class="grid grid-3-2" style="margin-bottom:14px">
            <div class="card">
                <div class="card-head">
                    <h3>Alert Volume Trend</h3>
                    <span class="sub">stacked by severity</span>
                    <div class="spacer"></div>
                    <div class="legend">
                        @foreach ($bands as $band)
                            <span class="legend-item">
                                <span class="legend-swatch" style="background:{{ $band['color'] }}"></span>{{ $band['label'] }}
                            </span>
                        @endforeach
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-wrap" style="height:262px">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <h3>Severity Distribution</h3>
                    <div class="spacer"></div>
                    <span class="sub">{{ number_format($c['total']) }} events</span>
                </div>
                <div class="card-body">
                    <div class="chart-wrap" style="height:150px">
                        <canvas id="severityChart"></canvas>
                    </div>
                    <div class="mt-14">
                        @foreach ($bands as $key => $band)
                            @php
                                $val = $data['severity_bands'][$key] ?? 0;
                                $pct = $c['total'] > 0 ? ($val / $c['total']) * 100 : 0;
                                $bandFilters = ['range' => $range];
                                if ($key === 'critical') {
                                    $bandFilters['level_min'] = 12;
                                } elseif ($key === 'high') {
                                    $bandFilters['level_min'] = 10;
                                    $bandFilters['level_max'] = 11;
                                } elseif ($key === 'medium') {
                                    $bandFilters['level_min'] = 7;
                                    $bandFilters['level_max'] = 9;
                                } elseif ($key === 'low') {
                                    $bandFilters['level_min'] = 4;
                                    $bandFilters['level_max'] = 6;
                                } else {
                                    $bandFilters['level_min'] = 0;
                                    $bandFilters['level_max'] = 3;
                                }
                            @endphp
                            <a class="stat-line stat-link"
                               href="{{ route('explorer.index', $bandFilters) }}"
                               title="Open {{ $band['label'] }} alerts">
                                <span class="k">
                                    <span class="legend-swatch" style="background:{{ $band['color'] }};display:inline-block;margin-right:6px"></span>{{ $band['label'] }}
                                </span>
                                <span class="v">{{ number_format($val) }} <span class="text-muted fs-11">({{ number_format($pct, 1) }}%)</span></span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Agents + rules --}}
        <div class="grid grid-2" style="margin-bottom:14px">
            <div class="card">
                <div class="card-head">
                    <h3>Agent Event Volume</h3>
                    <div class="spacer"></div>
                    <a href="{{ route('agents.index') }}" class="btn ghost sm">Details</a>
                </div>
                <div class="card-body flush">
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                                <tr>
                                    <th>Agent</th>
                                    <th class="num">Events</th>
                                    <th style="width:42%">Share</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $maxAgent = collect($data['agents'])->max('count') ?: 1; @endphp
                                @forelse ($data['agents'] as $agent)
                                    @php
                                        $st = collect($agentStatus)->firstWhere('name', $agent['key']);
                                    @endphp
                                    <tr>
                                        <td>
                                            <a href="{{ route('explorer.index', ['agent' => $agent['key'], 'range' => $range]) }}" class="link-strong">
                                                {{ $agent['key'] }}
                                            </a>
                                        </td>
                                        <td class="num mono">{{ number_format($agent['count']) }}</td>
                                        <td>
                                            <div class="bar-cell">
                                                <div class="bar-track">
                                                    <div class="bar-fill" style="width:{{ ($agent['count'] / $maxAgent) * 100 }}%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @if ($st)
                                                <span class="badge {{ in_array($st['status'], ['active', 'connected']) ? 'success' : 'neutral' }}">{{ $st['status'] }}</span>
                                            @else
                                                <span class="text-muted fs-11">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="empty">No agent activity in this window.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <h3>Top Triggered Rules</h3>
                    <div class="spacer"></div>
                    <a href="{{ route('explorer.index', ['range' => $range]) }}" class="btn ghost sm">Explore</a>
                </div>
                <div class="card-body flush">
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                                <tr>
                                    <th>Rule</th>
                                    <th class="num">Hits</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($data['top_rules'] as $rule)
                                    <tr>
                                        <td class="wrap">
                                            <a href="{{ route('explorer.index', ['q' => $rule['key'], 'range' => $range]) }}" class="link-strong">
                                                {{ \Illuminate\Support\Str::limit($rule['key'], 78) }}
                                            </a>
                                        </td>
                                        <td class="num mono">{{ number_format($rule['count']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="empty">No rules triggered.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Groups / decoders --}}
        <div class="grid grid-2">
            <div class="card">
                <div class="card-head">
                    <h3>Rule Groups</h3>
                </div>
                <div class="card-body">
                    @php $maxGroup = collect($data['groups'])->max('count') ?: 1; @endphp
                    @forelse ($data['groups'] as $group)
                        <div class="row" style="margin-bottom:9px">
                            <a href="{{ route('explorer.index', ['group' => $group['key'], 'range' => $range]) }}"
                               class="truncate" style="width:150px;color:var(--text-primary);font-size:12px">{{ $group['key'] }}</a>
                            <div class="bar-track" style="flex:1">
                                <div class="bar-fill" style="width:{{ ($group['count'] / $maxGroup) * 100 }}%"></div>
                            </div>
                            <span class="mono fs-11 text-secondary" style="width:64px;text-align:right">{{ number_format($group['count']) }}</span>
                        </div>
                    @empty
                        <div class="empty">No groups.</div>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <h3>Event Sources</h3>
                    <div class="spacer"></div>
                    <span class="sub">decoder · location</span>
                </div>
                <div class="card-body">
                    @php $maxDec = collect($data['decoders'])->max('count') ?: 1; @endphp
                    @forelse ($data['decoders'] as $dec)
                        <div class="row" style="margin-bottom:9px">
                            <span class="truncate mono" style="width:190px;font-size:11.5px;color:var(--text-primary)">{{ $dec['key'] }}</span>
                            <div class="bar-track" style="flex:1">
                                <div class="bar-fill" style="width:{{ ($dec['count'] / $maxDec) * 100 }}%;background:#8b5cf6"></div>
                            </div>
                            <span class="mono fs-11 text-secondary" style="width:64px;text-align:right">{{ number_format($dec['count']) }}</span>
                        </div>
                    @empty
                        <div class="empty">No decoders.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Latest activity --}}
        <div class="grid grid-2 mt-14">
            <div class="card">
                <div class="card-head">
                    <h3>Last Critical &amp; High</h3>
                    <div class="spacer"></div>
                    <a href="{{ route('explorer.index', ['range' => '7d', 'level_min' => 10]) }}" class="btn ghost sm">View all</a>
                </div>
                <div class="card-body flush">
                    @forelse ($latestCritical as $event)
                        @php
                            $src = $event['source'];
                            $lvl = (int) ($src['rule']['level'] ?? 0);
                        @endphp
                        <a class="latest-row"
                           href="{{ route('explorer.index', ['range' => '7d', 'alert' => $event['source']['id'] ?? '']) }}">
                            <span class="badge {{ \App\Support\Severity::token($lvl) }}" style="flex-shrink:0">{{ $lvl }}</span>
                            <span class="latest-main">
                                <span class="latest-title">{{ \Illuminate\Support\Str::limit($src['rule']['description'] ?? '—', 74) }}</span>
                                <span class="latest-meta">
                                    {{ $src['agent']['name'] ?? '—' }}
                                    &middot; rule {{ $src['rule']['id'] ?? '?' }}
                                    &middot; {{ isset($src['timestamp']) ? \Illuminate\Support\Carbon::parse($src['timestamp'])->diffForHumans() : '' }}
                                </span>
                            </span>
                        </a>
                    @empty
                        <div class="empty" style="padding:26px 12px">
                            <x-empty-icon name="shield" />
                            No critical or high alerts in this window.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <h3>Last Auth Failures</h3>
                    <div class="spacer"></div>
                    <a href="{{ route('explorer.index', ['range' => '7d', 'group' => 'authentication_failed']) }}" class="btn ghost sm">View all</a>
                </div>
                <div class="card-body flush">
                    @forelse ($latestAuthFailures as $event)
                        @php
                            $src = $event['source'];
                            $lvl = (int) ($src['rule']['level'] ?? 0);
                        @endphp
                        <a class="latest-row"
                           href="{{ route('explorer.index', ['range' => '7d', 'alert' => $event['source']['id'] ?? '']) }}">
                            <span class="badge {{ \App\Support\Severity::token($lvl) }}" style="flex-shrink:0">{{ $lvl }}</span>
                            <span class="latest-main">
                                <span class="latest-title">{{ \Illuminate\Support\Str::limit($src['rule']['description'] ?? '—', 74) }}</span>
                                <span class="latest-meta">
                                    {{ $src['agent']['name'] ?? '—' }}
                                    &middot; {{ $src['agent']['ip'] ?? 'no ip' }}
                                    &middot; {{ isset($src['timestamp']) ? \Illuminate\Support\Carbon::parse($src['timestamp'])->diffForHumans() : '' }}
                                </span>
                            </span>
                        </a>
                    @empty
                        <div class="empty" style="padding:26px 12px">
                            <x-empty-icon name="shield" />
                            No authentication failures in this window.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Handling state --}}
        <div class="grid grid-2 mt-14">
            <div class="card">
                <div class="card-head">
                    <h3>Triage Coverage</h3>
                    <div class="spacer"></div>
                    <span class="sub">{{ $ranges[$range]['label'] }}</span>
                </div>
                <div class="card-body">
                    @if ($coverage === null)
                        <div class="empty" style="padding:22px 10px">
                            Coverage cannot be computed while the alert query is failing.
                        </div>
                    @else
                        <div class="row" style="align-items:flex-end;gap:10px">
                            <span style="font-size:30px;font-weight:680;letter-spacing:-.03em">{{ number_format($coverage['coverage'], 1) }}%</span>
                            <span class="fs-11 text-secondary" style="padding-bottom:5px">
                                {{ number_format($coverage['handled']) }} of {{ number_format($coverage['total']) }} alerts handled
                            </span>
                        </div>
                        <div class="bar-track" style="margin:10px 0 14px">
                            <div class="bar-fill" style="width:{{ min(100, $coverage['coverage']) }}%;background:var(--success)"></div>
                        </div>
                        <div class="stat-line">
                            <span class="k">Untriaged (no handling record)</span>
                            <span class="v" style="color:var(--text-secondary)">{{ number_format($coverage['untriaged']) }}</span>
                        </div>
                        <div class="stat-line">
                            <span class="k">Open work older than 7 days</span>
                            <span class="v" style="color:var(--medium-text)">{{ number_format($coverage['open_7d']) }}</span>
                        </div>
                        <div class="stat-line">
                            <span class="k">Open work older than 30 days</span>
                            <span class="v" style="color:var(--high-text)">{{ number_format($coverage['open_30d']) }}</span>
                        </div>
                        <div class="fs-11 text-secondary" style="margin-top:10px;line-height:1.5">
                            Alerts without a handling record are untriaged, not ignored: they stay in the
                            explorer and remain searchable through the Untriaged filter.
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <h3>Alert Handling</h3>
                    <div class="spacer"></div>
                    <span class="sub">all time</span>
                    <a href="{{ route('explorer.index', ['range' => $range]) }}" class="btn ghost sm">Triage</a>
                </div>
                <div class="card-body">
                    @if ($triage['total'] === 0)
                        <div class="empty" style="padding:22px 10px">
                            <x-empty-icon name="tag" />
                            No alerts have been triaged yet. Open an alert to set a status and labels.
                        </div>
                    @else
                        @foreach ($statuses as $key => $meta)
                            @php $count = $triage['counts'][$key] ?? 0; @endphp
                            <div class="row" style="margin-bottom:9px">
                                <a href="{{ route('explorer.index', ['status' => $key, 'range' => $range]) }}"
                                   style="width:132px">
                                    <span class="status-chip" style="--chip:{{ $meta['color'] }}">{{ $meta['label'] }}</span>
                                </a>
                                <div class="bar-track" style="flex:1">
                                    <div class="bar-fill" style="width:{{ $triage['total'] > 0 ? ($count / $triage['total']) * 100 : 0 }}%;background:{{ $meta['color'] }}"></div>
                                </div>
                                <span class="mono fs-11 text-secondary" style="width:64px;text-align:right">{{ number_format($count) }}</span>
                            </div>
                        @endforeach
                        <div class="stat-line" style="border-top:1px solid var(--border);margin-top:10px;padding-top:9px">
                            <span class="k">Open (needs attention)</span>
                            <span class="v" style="color:var(--high-text)">{{ number_format($triage['open']) }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <h3>Reports</h3>
                    <div class="spacer"></div>
                    <a href="{{ route('reports.index') }}" class="btn ghost sm">All reports</a>
                </div>
                <div class="card-body">
                    <div class="row" style="gap:26px">
                        <div class="col" style="gap:2px">
                            <span class="fs-11 text-muted" style="text-transform:uppercase;letter-spacing:.05em">Open</span>
                            <span style="font-size:30px;font-weight:680;letter-spacing:-.03em;color:var(--critical-text)">{{ number_format($openReports) }}</span>
                        </div>
                        <div class="col" style="gap:2px">
                            <span class="fs-11 text-muted" style="text-transform:uppercase;letter-spacing:.05em">Triaged alerts</span>
                            <span style="font-size:30px;font-weight:680;letter-spacing:-.03em">{{ number_format($triage['total']) }}</span>
                        </div>
                    </div>
                    <p class="fs-12 text-secondary" style="margin:14px 0 0">
                        Reports group related alerts under one case with its own status,
                        assignee and timeline. Escalate an alert from its detail page.
                    </p>
                    <div class="row mt-14" style="gap:8px">
                        <a class="btn sm" href="{{ route('explorer.index', ['range' => '24h']) }}">Find alerts</a>
                        @can('triage')
                            <a class="btn sm" href="{{ route('reports.index') }}">New report</a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
@if ($data)
<script>
(function () {
    var timeline = @json($data['timeline']);
    var bands = @json($bands);

    var labels = timeline.map(function (b) {
        var d = new Date(b.time);
        return d.toLocaleString([], { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
    });

    // Map raw rule levels onto the configured severity bands so the chart
    // stays consistent with the legend instead of hard-coding level numbers.
    var bandOf = function (level) {
        level = Number(level);
        var order = ['critical', 'high', 'medium', 'low', 'info'];
        for (var i = 0; i < order.length; i++) {
            var b = bands[order[i]];
            if (level >= b.min && level <= b.max) return order[i];
        }
        return 'info';
    };

    var datasets = Object.keys(bands).map(function (key) {
        return {
            label: bands[key].label,
            data: timeline.map(function (b) {
                var sum = 0;
                Object.keys(b.levels).forEach(function (lvl) {
                    if (bandOf(lvl) === key) sum += b.levels[lvl];
                });
                return sum;
            }),
            backgroundColor: bands[key].color,
            borderColor: bands[key].color,
            borderWidth: 0,
            fill: true,
            tension: 0.32,
            pointRadius: 0,
            pointHoverRadius: 3,
            stack: 'volume'
        };
    });

    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: { labels: labels, datasets: datasets },
        options: {
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 24 } },
                y: { stacked: true, beginAtZero: true, grid: { color: WLC.theme().grid }, ticks: { callback: function (v) { return WLC.fmt(v); } } }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function (ctx) { return ctx.dataset.label + ': ' + ctx.parsed.y.toLocaleString(); }
                    }
                }
            }
        }
    });

    var sev = @json($data['severity_bands']);
    new Chart(document.getElementById('severityChart'), {
        type: 'doughnut',
        data: {
            labels: Object.keys(bands).map(function (k) { return bands[k].label; }),
            datasets: [{
                data: Object.keys(bands).map(function (k) { return sev[k] || 0; }),
                backgroundColor: Object.keys(bands).map(function (k) { return bands[k].color; }),
                borderColor: WLC.theme().surface,
                borderWidth: 2,
                hoverOffset: 4
            }]
        },
        options: {
            cutout: '68%',
            plugins: { tooltip: { callbacks: { label: function (ctx) { return ctx.label + ': ' + ctx.parsed.toLocaleString(); } } } }
        }
    });
})();
</script>
@endif
@endpush



