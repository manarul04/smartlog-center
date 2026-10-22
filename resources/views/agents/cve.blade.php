@extends('layouts.app')

@section('title', $cve)
@section('subtitle', 'Agents affected by this vulnerability')

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Agents & Health', 'url' => route('agents.index')],
        ['label' => $cve],
    ]" />
@endsection

@section('content')
    @php
        $items = $result['items'] ?? [];
        $first = $items[0]['source'] ?? [];
        $vuln = $first['vulnerability'] ?? [];
        $severity = $vuln['severity'] ?? 'Unknown';
        $sevClass = match (strtolower((string) $severity)) {
            'critical' => 'critical',
            'high' => 'high',
            'medium' => 'medium',
            'low' => 'low',
            default => 'info',
        };

        // Group affected agents so one row per endpoint, with its package.
        $byAgent = collect($items)->groupBy(fn ($i) => $i['source']['agent']['name'] ?? '—');
    @endphp

    @if (!$result['available'])
        <div class="banner error">
            <div><strong>Vulnerability index unavailable.</strong> Could not query the scanner data.</div>
        </div>
    @else
        <div class="grid grid-3" style="margin-bottom:14px">
            <div class="kpi" style="--kpi-accent:var(--{{ $sevClass === 'info' ? 'info' : $sevClass }})">
                <span class="kpi-label">CVE</span>
                <span class="kpi-value" style="font-size:18px;font-family:var(--mono)">{{ $cve }}</span>
                <span class="kpi-meta">
                    <span class="badge {{ $sevClass }}">{{ $severity }}</span>
                    @if (!empty($vuln['score']['base']))
                        <span class="badge neutral">CVSS {{ number_format((float) $vuln['score']['base'], 1) }}</span>
                    @endif
                </span>
            </div>
            <div class="kpi" style="--kpi-accent:var(--accent)">
                <span class="kpi-label">Affected Agents</span>
                <span class="kpi-value">{{ number_format($byAgent->count()) }}</span>
                <span class="kpi-meta">{{ number_format($result['total']) }} findings</span>
            </div>
            <div class="kpi" style="--kpi-accent:var(--high)">
                <span class="kpi-label">Published</span>
                <span class="kpi-value" style="font-size:16px">
                    {{ !empty($vuln['published_at']) ? \Illuminate\Support\Carbon::parse($vuln['published_at'])->format('d M Y') : '—' }}
                </span>
                <span class="kpi-meta">{{ $vuln['category'] ?? 'Uncategorised' }}</span>
            </div>
        </div>

        @if (!empty($vuln['description']))
            <div class="card mb-14">
                <div class="card-head">
                    <h3>Description</h3>
                    <div class="spacer"></div>
                    <a class="btn sm" target="_blank" rel="noopener noreferrer"
                       href="https://nvd.nist.gov/vuln/detail/{{ urlencode($cve) }}">Open in NVD</a>
                </div>
                <div class="card-body">
                    <p class="fs-12 text-secondary" style="margin:0 0 10px">{{ $vuln['description'] }}</p>
                    @if (!empty($vuln['scanner']['condition']))
                        <div class="fs-11 text-muted">
                            <strong>Fix condition:</strong> {{ $vuln['scanner']['condition'] }}
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-head">
                <h3>Affected Agents</h3>
                <div class="spacer"></div>
                <span class="sub">{{ $byAgent->count() }} endpoints</span>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th style="width:180px">Agent</th>
                            <th>Affected Package(s)</th>
                            <th style="width:140px">Version</th>
                            <th style="width:110px">Category</th>
                            <th style="width:120px">Detected</th>
                            <th style="width:80px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($byAgent as $agentName => $findings)
                            @php
                                $first = $findings->first();
                                $src = $first['source'];
                                $v = $src['vulnerability'] ?? [];
                                $agentId = $src['agent']['id'] ?? null;
                                $firstPkg = $src['package'] ?? [];
                            @endphp
                            <tr>
                                <td>
                                    @if ($agentId)
                                        <a href="{{ route('agents.show', $agentId) }}" class="link-strong">{{ $agentName }}</a>
                                    @else
                                        {{ $agentName }}
                                    @endif
                                    <div class="fs-11 text-muted mono">{{ $src['agent']['version'] ?? '' }}</div>
                                </td>
                                <td class="wrap">
                                    {{-- One row per agent: list every affected package so the
                                         finding count stays readable. --}}
                                    @foreach ($findings as $f)
                                        @php $fp = $f['source']['package'] ?? []; @endphp
                                        <div class="row" style="gap:6px;margin-bottom:3px">
                                            <span class="mono fs-11">{{ $fp['version'] ?? '—' }}</span>
                                            <span>{{ \Illuminate\Support\Str::limit($fp['name'] ?? '—', 52) }}</span>
                                            <span class="fs-11 text-muted">{{ $fp['type'] ?? '' }}</span>
                                        </div>
                                    @endforeach
                                </td>
                                <td class="mono fs-11">
                                    {{ $findings->count() > 1 ? $findings->count() . ' versions' : ($firstPkg['version'] ?? '—') }}
                                </td>
                                <td class="fs-11 text-secondary">{{ $v['category'] ?? '—' }}</td>
                                <td class="fs-11 text-secondary nowrap">
                                    {{ !empty($v['detected_at']) ? \Illuminate\Support\Carbon::parse($v['detected_at'])->format('d M H:i') : '—' }}
                                </td>
                                <td>
                                    @if ($agentId)
                                        <a class="btn ghost sm"
                                           href="{{ route('agents.vulnerabilities', ['id' => $agentId, 'q' => $cve]) }}">Open</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="empty">
                                    <x-empty-icon name="shield" />
                                    No agent currently reports this CVE.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
