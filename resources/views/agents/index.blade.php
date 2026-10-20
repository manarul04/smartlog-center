@extends('layouts.app')

@section('title', 'Agents & Health')
@section('subtitle', 'Endpoint inventory, availability and exposure')

@section('content')
    @foreach ($warnings as $warning)
        <div class="banner warn"><div>{{ $warning }}</div></div>
    @endforeach

    @php
        $active = collect($rows)->where('status', 'active')->count();
        $disconnected = collect($rows)->whereIn('status', ['disconnected', 'never_connected', 'pending'])->count();
        $totalEvents = collect($rows)->sum('events_24h');
        $vulnTotal = $vulns['total'] ?? 0;
        $criticalVulns = collect($vulns['severity'] ?? [])->firstWhere('key', 'Critical')['count'] ?? 0;
        $highVulns = collect($vulns['severity'] ?? [])->firstWhere('key', 'High')['count'] ?? 0;
    @endphp

    <div class="grid grid-4" style="margin-bottom:14px">
        <div class="kpi" style="--kpi-accent:var(--success)">
            <span class="kpi-label">Active Agents</span>
            <span class="kpi-value">{{ $active }} <span class="text-muted" style="font-size:15px;font-weight:500">/ {{ count($rows) }}</span></span>
            <span class="kpi-meta">{{ $disconnected }} disconnected or pending</span>
        </div>
        <div class="kpi" style="--kpi-accent:var(--accent)">
            <span class="kpi-label">Events (24h)</span>
            <span class="kpi-value">{{ number_format($totalEvents) }}</span>
            <span class="kpi-meta">across all endpoints</span>
        </div>
        <div class="kpi" style="--kpi-accent:var(--high)">
            <span class="kpi-label">Vulnerabilities</span>
            <span class="kpi-value">{{ number_format($vulnTotal) }}</span>
            <span class="kpi-meta">
                <span class="badge critical">{{ number_format($criticalVulns) }} crit</span>
                <span class="badge high">{{ number_format($highVulns) }} high</span>
            </span>
        </div>
        <div class="kpi" style="--kpi-accent:var(--warning)">
            <span class="kpi-label">SCA Policies</span>
            <span class="kpi-value">{{ count($sca) }}</span>
            <span class="kpi-meta">
                avg score {{ count($sca) ? number_format(collect($sca)->avg('score'), 0) : 0 }}%
            </span>
        </div>
    </div>

    <div class="card mb-14">
        <div class="card-head">
            <h3>Endpoint Inventory</h3>
            <div class="spacer"></div>
            <span class="sub">{{ count($rows) }} managed agents</span>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Agent</th>
                        <th>Status</th>
                        <th>Operating System</th>
                        <th>Group</th>
                        <th class="num">Events 24h</th>
                        <th class="num">Max Level</th>
                        <th>Last Event</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                <div style="font-weight:600">{{ $row['name'] }}</div>
                                <div class="fs-11 text-muted mono">{{ $row['id'] }} · {{ $row['ip'] ?? '—' }}</div>
                            </td>
                            <td>
                                @php $ok = in_array($row['status'], ['active', 'connected']); @endphp
                                <span class="badge {{ $ok ? 'success' : 'critical' }}">{{ $row['status'] }}</span>
                            </td>
                            <td class="wrap">
                                <div style="font-size:12px">{{ $row['os'] ?? '—' }}</div>
                                <div class="fs-11 text-muted mono">{{ $row['version'] ?? '' }}</div>
                            </td>
                            <td class="fs-11 text-secondary">{{ implode(', ', $row['group'] ?? []) ?: '—' }}</td>
                            <td class="num mono">{{ number_format($row['events_24h']) }}</td>
                            <td class="num">
                                <span class="badge {{ \App\Support\Severity::token((int) $row['max_level_24h']) }}">
                                    {{ (int) $row['max_level_24h'] }}
                                </span>
                            </td>
                            <td class="fs-11 text-secondary nowrap">
                                {{ $row['last_event'] ? \Illuminate\Support\Carbon::parse($row['last_event'])->diffForHumans() : '—' }}
                            </td>
                            <td>
                                <a class="btn ghost sm" href="{{ route('agents.show', $row['id']) }}">Inspect</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty">No agent telemetry available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <div class="card-head">
                <h3>Vulnerability Exposure</h3>
                <div class="spacer"></div>
                <span class="sub">{{ number_format($vulnTotal) }} findings</span>
            </div>
            <div class="card-body flush">
                @if (!empty($vulns['by_agent_severity']))
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                                <tr>
                                    <th>Agent</th>
                                    <th class="num">Critical</th>
                                    <th class="num">High</th>
                                    <th class="num">Medium</th>
                                    <th class="num">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (collect($vulns['by_agent_severity'])->sortByDesc('total') as $row)
                                    @php
                                        $agentId = collect($rows)->firstWhere('name', $row['agent'])['id'] ?? null;
                                    @endphp
                                    <tr>
                                        <td style="font-weight:550">
                                            @if ($agentId)
                                                <a href="{{ route('agents.show', $agentId) }}" class="link-strong">{{ $row['agent'] }}</a>
                                            @else
                                                {{ $row['agent'] }}
                                            @endif
                                        </td>
                                        @foreach (['Critical' => 'critical-text', 'High' => 'high-text', 'Medium' => 'medium-text'] as $sev => $tone)
                                            <td class="num mono" style="color:var(--{{ $tone }})">
                                                @if ($agentId && ($row['severity'][$sev] ?? 0) > 0)
                                                    <a href="{{ route('agents.vulnerabilities', ['id' => $agentId, 'severity' => $sev]) }}"
                                                       class="count-link" title="Show {{ $sev }} findings for {{ $row['agent'] }}">
                                                        {{ number_format($row['severity'][$sev]) }}
                                                    </a>
                                                @else
                                                    {{ number_format($row['severity'][$sev] ?? 0) }}
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="num mono">
                                            @if ($agentId && $row['total'] > 0)
                                                <a href="{{ route('agents.vulnerabilities', $agentId) }}"
                                                   class="count-link">{{ number_format($row['total']) }}</a>
                                            @else
                                                {{ number_format($row['total']) }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty">No vulnerability data indexed.</div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h3>SCA Compliance Scores</h3>
                <div class="spacer"></div>
                <span class="sub">latest policy result</span>
            </div>
            <div class="card-body flush">
                @if (!empty($sca))
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                                <tr>
                                    <th>Agent</th>
                                    <th>Score</th>
                                    <th class="num">Passed</th>
                                    <th class="num">Failed</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sca as $row)
                                    @php $scaAgentId = collect($rows)->firstWhere('name', $row['agent'])['id'] ?? null; @endphp
                                    <tr>
                                        <td>
                                            @if ($scaAgentId)
                                                <a href="{{ route('agents.sca', $scaAgentId) }}" class="link-strong" style="font-weight:550">{{ $row['agent'] }}</a>
                                            @else
                                                <div style="font-weight:550">{{ $row['agent'] }}</div>
                                            @endif
                                            <div class="fs-11 text-muted truncate" style="max-width:220px">{{ $row['policy'] }}</div>
                                        </td>
                                        <td style="min-width:130px">
                                            <div class="row" style="gap:8px">
                                                <div class="progress" style="flex:1">
                                                    <i style="width:{{ $row['score'] }}%;background:{{ $row['score'] >= 70 ? 'var(--success)' : ($row['score'] >= 40 ? 'var(--warning)' : 'var(--critical)') }}"></i>
                                                </div>
                                                <span class="mono fs-11" style="width:32px;text-align:right">{{ $row['score'] }}%</span>
                                            </div>
                                        </td>
                                        <td class="num mono" style="color:var(--success-text)">{{ number_format($row['passed']) }}</td>
                                        <td class="num mono" style="color:var(--critical-text)">
                                            @if ($scaAgentId && $row['failed'] > 0)
                                                <a href="{{ route('agents.sca', ['id' => $scaAgentId, 'result' => 'failed']) }}"
                                                   class="count-link" title="Show failed checks for {{ $row['agent'] }}">
                                                    {{ number_format($row['failed']) }}
                                                </a>
                                            @else
                                                {{ number_format($row['failed']) }}
                                            @endif
                                        </td>
                                        <td>
                                            @if ($scaAgentId)
                                                <a class="btn ghost sm" href="{{ route('agents.sca', $scaAgentId) }}">Handle</a>
                                            @else
                                                <a class="btn ghost sm" href="{{ route('explorer.index', ['agent' => $row['agent'], 'group' => 'sca', 'range' => '30d']) }}">View</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty">No SCA results in the last 30 days.</div>
                @endif
            </div>
        </div>
    </div>

    @if (!empty($vulns['cves']))
        <div class="card mt-14">
            <div class="card-head">
                <h3>Top CVEs</h3>
                <div class="spacer"></div>
                <span class="sub">highest prevalence</span>
            </div>
            <div class="card-body flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>CVE</th>
                                <th class="num">CVSS</th>
                                <th>Severity</th>
                                <th>Description</th>
                                <th class="num">Affected</th>
                                <th style="width:32px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($vulns['cves'] as $cve)
                                <tr class="row-clickable"
                                    data-href="{{ route('vulnerabilities.cve', $cve['id']) }}">
                                    <td class="mono" style="color:var(--low-text)">
                                        <a href="{{ route('vulnerabilities.cve', $cve['id']) }}" class="link-strong mono">
                                            {{ $cve['id'] }}
                                        </a>
                                    </td>
                                    <td class="num mono">{{ $cve['score'] ? number_format($cve['score'], 1) : '—' }}</td>
                                    <td>
                                        @php
                                            $sevClass = match (strtolower((string) $cve['severity'])) {
                                                'critical' => 'critical',
                                                'high' => 'high',
                                                'medium' => 'medium',
                                                'low' => 'low',
                                                default => 'info',
                                            };
                                        @endphp
                                        <span class="badge {{ $sevClass }}">{{ $cve['severity'] }}</span>
                                    </td>
                                    <td class="wrap fs-11 text-secondary">{{ \Illuminate\Support\Str::limit($cve['description'], 120) }}</td>
                                    <td class="num mono">{{ number_format($cve['count']) }}</td>
                                    <td class="center">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="2" stroke-linecap="round" style="opacity:.45">
                                            <path d="M9 18l6-6-6-6"/>
                                        </svg>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection

