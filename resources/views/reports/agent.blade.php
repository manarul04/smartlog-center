<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ config('app.name') }} &middot; {{ $meta['title'] }} &middot; {{ $agent['name'] }}</title>
    @include('reports.partials.pdf-style')
</head>
<body>

@include('reports.partials.pdf-head')

@php
    $alerts = $data['alerts'];
    $vulns = $data['vulnerabilities'];
    $sca = $data['sca'];

    $vulnCounts = $vulns ? collect($vulns['facets']['severity'])->pluck('count', 'key') : collect();
    $scaCounts = $sca ? collect($sca['facets']['result'])->pluck('count', 'key') : collect();
@endphp

{{-- ---------- Endpoint identity ---------- --}}
<table class="identity">
    <tr>
        <td class="k">Agent</td><td>{{ $agent['name'] }}</td>
        <td class="k">ID</td><td class="mono">{{ $agent['id'] }}</td>
        <td class="k">Status</td><td>{{ $agent['status'] }}</td>
    </tr>
    <tr>
        <td class="k">IP</td><td class="mono">{{ $agent['ip'] ?? '—' }}</td>
        <td class="k">OS</td><td>{{ $agent['os'] ?? '—' }}</td>
        <td class="k">Version</td><td>{{ $agent['version'] ?? '—' }}</td>
    </tr>
    <tr>
        <td class="k">Group</td><td>{{ $agent['group'] ? implode(', ', $agent['group']) : 'default' }}</td>
        <td class="k">Node</td><td>{{ $agent['node'] ?? '—' }}</td>
        <td class="k">Registered</td>
        <td>{{ $agent['added'] ? \Illuminate\Support\Carbon::parse($agent['added'])->format('d M Y') : '—' }}</td>
    </tr>
</table>

{{-- ---------- KPI strip ---------- --}}
<table class="kpi-table">
    <tr>
        @if ($alerts)
            <td style="width:20%">
                <div class="kpi-k">Alerts</div>
                <div class="kpi-v">{{ number_format($alerts['total']) }}</div>
                <div class="kpi-s">in selected period</div>
            </td>
            <td style="width:20%">
                <div class="kpi-k">Critical &amp; high</div>
                <div class="kpi-v">{{ number_format(($alerts['severity']['critical'] ?? 0) + ($alerts['severity']['high'] ?? 0)) }}</div>
                <div class="kpi-s">level 10 and above</div>
            </td>
        @endif
        @if ($vulns)
            <td style="width:20%">
                <div class="kpi-k">Vulnerabilities</div>
                <div class="kpi-v">{{ number_format($vulns['facets']['total']) }}</div>
                <div class="kpi-s">detected in period</div>
            </td>
            <td style="width:20%">
                <div class="kpi-k">Vuln critical &amp; high</div>
                <div class="kpi-v">{{ number_format(($vulnCounts['Critical'] ?? 0) + ($vulnCounts['High'] ?? 0)) }}</div>
                <div class="kpi-s">CVSS 7.0 and above</div>
            </td>
        @endif
        @if ($sca)
            <td style="width:20%">
                <div class="kpi-k">SCA failed</div>
                <div class="kpi-v">{{ number_format($scaCounts['failed'] ?? 0) }}</div>
                <div class="kpi-s">
                    {{ $sca['score'] ? 'score ' . $sca['score']['score'] . '%' : 'no summary in period' }}
                </div>
            </td>
        @endif
    </tr>
</table>

@if ($alerts)
    <div class="section">
        <h2>Alert Severity Distribution</h2>
        @php $maxSev = max(1, max($alerts['severity'] ?: [0])); @endphp
        <table class="barlist">
            @foreach ($bands as $key => $band)
                @php $count = $alerts['severity'][$key] ?? 0; @endphp
                <tr>
                    <td class="lbl">
                        <span class="pill {{ $key }}">{{ $band['label'] }}</span>
                        <span class="muted"> (level {{ $band['min'] }}&ndash;{{ $band['max'] }})</span>
                    </td>
                    <td class="bar">
                        <div class="track"><div class="fill" style="width:{{ ($count / $maxSev) * 100 }}%;background:{{ $band['color'] }}"></div></div>
                    </td>
                    <td class="val">{{ number_format($count) }}</td>
                </tr>
            @endforeach
        </table>
    </div>

    <div class="section">
        <h2>Top Triggered Rules</h2>
        <table class="data">
            <thead>
                <tr>
                    <th style="width:5%" class="num">#</th>
                    <th>Rule description</th>
                    <th class="num" style="width:13%">Alerts</th>
                    <th class="num" style="width:11%">Share</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($alerts['rules'] as $i => $rule)
                    <tr>
                        <td class="num">{{ $i + 1 }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($rule['key'], 105) }}</td>
                        <td class="num">{{ number_format($rule['count']) }}</td>
                        <td class="num">{{ $alerts['total'] > 0 ? number_format(($rule['count'] / $alerts['total']) * 100, 1) : 0 }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">No alerts in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Recent Alerts ({{ number_format($alerts['shown']) }} of {{ number_format($alerts['total']) }})</h2>

        @if ($alerts['truncated'])
            <div class="note">
                Showing the {{ number_format($alerts['limit']) }} most recent alerts of
                {{ number_format($alerts['total']) }} total. Aggregate figures above cover the full result set.
            </div>
        @endif

        <table class="data">
            <thead>
                <tr>
                    <th style="width:13%">Timestamp</th>
                    <th style="width:5%" class="num">Lvl</th>
                    <th style="width:45%">Rule</th>
                    <th style="width:14%">Decoder</th>
                    <th style="width:13%">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($alerts['rows'] as $row)
                    @php
                        $src = $row['source'];
                        $level = (int) ($src['rule']['level'] ?? 0);
                        $ann = $row['annotation'];
                        $statusKey = $ann->status ?? \App\Support\AlertStatus::NOT_REVIEWED;
                    @endphp
                    <tr>
                        <td class="mono">
                            {{ isset($src['timestamp']) ? \Illuminate\Support\Carbon::parse($src['timestamp'])->format('d M H:i:s') : '—' }}
                        </td>
                        <td class="num"><span class="pill {{ \App\Support\Severity::token($level) }}">{{ $level }}</span></td>
                        <td>
                            {{ \Illuminate\Support\Str::limit($src['rule']['description'] ?? '—', 100) }}
                            <span class="muted"> &middot; rule {{ $src['rule']['id'] ?? '?' }}</span>
                        </td>
                        <td class="mono">{{ $src['decoder']['name'] ?? '—' }}</td>
                        <td><span class="status">{{ \App\Support\AlertStatus::label($statusKey) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No alerts matched the selected period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif

@if ($vulns)
    <div class="section">
        <h2>Vulnerability Summary</h2>
        @php $maxVuln = max(1, $vulnCounts->max() ?: 1); @endphp
        <table class="barlist">
            @foreach (['Critical' => '#e11d48', 'High' => '#ea580c', 'Medium' => '#ca8a04', 'Low' => '#0284c7'] as $label => $color)
                @php $count = $vulnCounts[$label] ?? 0; @endphp
                <tr>
                    <td class="lbl">{{ $label }}</td>
                    <td class="bar">
                        <div class="track"><div class="fill" style="width:{{ ($count / $maxVuln) * 100 }}%;background:{{ $color }}"></div></div>
                    </td>
                    <td class="val">{{ number_format($count) }}</td>
                </tr>
            @endforeach
        </table>
    </div>

    <div class="section">
        <h2>Vulnerability Findings ({{ number_format(count($vulns['items'])) }} shown)</h2>
        <table class="data">
            <thead>
                <tr>
                    <th style="width:14%">CVE</th>
                    <th style="width:7%" class="num">CVSS</th>
                    <th style="width:9%">Severity</th>
                    <th style="width:45%">Description</th>
                    <th style="width:25%">Package</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($vulns['items'] as $item)
                    @php
                        $v = $item['source']['vulnerability'] ?? [];
                        $pkg = $item['source']['package'] ?? [];
                        $sev = strtolower((string) ($v['severity'] ?? 'unknown'));
                        $sevToken = in_array($sev, ['critical', 'high', 'medium', 'low'], true) ? $sev : 'info';
                    @endphp
                    <tr>
                        <td class="mono">{{ $v['id'] ?? '—' }}</td>
                        <td class="num mono">{{ isset($v['score']['base']) ? number_format((float) $v['score']['base'], 1) : '—' }}</td>
                        <td><span class="pill {{ $sevToken }}">{{ $v['severity'] ?? 'Unknown' }}</span></td>
                        <td>{{ \Illuminate\Support\Str::limit($v['description'] ?? '—', 110) }}</td>
                        <td>
                            {{ \Illuminate\Support\Str::limit($pkg['name'] ?? '—', 42) }}
                            <span class="muted mono">{{ $pkg['version'] ?? '' }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No findings detected in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif

@if ($sca)
    <div class="section">
        <h2>Configuration Assessment</h2>
        <table class="kpi-table">
            <tr>
                <td style="width:25%">
                    <div class="kpi-k">Compliance score</div>
                    <div class="kpi-v">{{ $sca['score'] ? $sca['score']['score'] . '%' : '—' }}</div>
                    <div class="kpi-s">{{ $sca['score']['policy'] ?? 'no summary in period' }}</div>
                </td>
                <td style="width:25%">
                    <div class="kpi-k">Failed</div>
                    <div class="kpi-v">{{ number_format($scaCounts['failed'] ?? 0) }}</div>
                    <div class="kpi-s">need remediation</div>
                </td>
                <td style="width:25%">
                    <div class="kpi-k">Passed</div>
                    <div class="kpi-v">{{ number_format($scaCounts['passed'] ?? 0) }}</div>
                    <div class="kpi-s">compliant</div>
                </td>
                <td style="width:25%">
                    <div class="kpi-k">Not applicable</div>
                    <div class="kpi-v">{{ number_format($scaCounts['not applicable'] ?? 0) }}</div>
                    <div class="kpi-s">{{ number_format($sca['facets']['total']) }} check events</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>SCA Checks ({{ number_format(count($sca['items'])) }} shown)</h2>
        <table class="data">
            <thead>
                <tr>
                    <th style="width:9%">Check</th>
                    <th style="width:53%">Title</th>
                    <th style="width:11%">Result</th>
                    <th style="width:27%">Policy</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sca['items'] as $item)
                    @php
                        $res = strtolower((string) $item['result']);
                        $resToken = match ($res) {
                            'passed' => 'info',
                            'failed' => 'critical',
                            default => 'info',
                        };
                    @endphp
                    <tr>
                        <td class="mono">{{ $item['check_id'] ?: '—' }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($item['title'] ?? '—', 110) }}</td>
                        <td><span class="pill {{ $resToken }}">{{ ucfirst($item['result']) }}</span></td>
                        <td>{{ \Illuminate\Support\Str::limit($item['policy'] ?? '—', 42) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">No checks recorded in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif

@include('reports.partials.pdf-foot')
</body>
</html>
