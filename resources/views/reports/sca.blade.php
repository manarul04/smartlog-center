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
    $resultCounts = collect($facets['result'])->pluck('count', 'key');
    $failed = $resultCounts['failed'] ?? 0;
    $passed = $resultCounts['passed'] ?? 0;
    $na = $resultCounts['not applicable'] ?? 0;
    $shown = count($items);
    $score = $score['score'] ?? null;
@endphp

<table class="kpi-table">
    <tr>
        <td style="width:25%">
            <div class="kpi-k">Compliance score</div>
            <div class="kpi-v">{{ $score !== null ? $score . '%' : '—' }}</div>
            <div class="kpi-s">{{ $score !== null ? 'latest policy in period' : 'no summary in period' }}</div>
        </td>
        <td style="width:25%">
            <div class="kpi-k">Failed checks</div>
            <div class="kpi-v">{{ number_format($failed) }}</div>
            <div class="kpi-s">need remediation</div>
        </td>
        <td style="width:25%">
            <div class="kpi-k">Passed checks</div>
            <div class="kpi-v">{{ number_format($passed) }}</div>
            <div class="kpi-s">compliant</div>
        </td>
        <td style="width:25%">
            <div class="kpi-k">Not applicable</div>
            <div class="kpi-v">{{ number_format($na) }}</div>
            <div class="kpi-s">{{ number_format($facets['total']) }} check events</div>
        </td>
    </tr>
</table>

<div class="section">
    <h2>Result Distribution</h2>
    @php $maxRes = max(1, $resultCounts->max() ?: 1); @endphp
    <table class="barlist">
        @foreach (['failed' => '#e11d48', 'passed' => '#10b981', 'not applicable' => '#64748b'] as $key => $color)
            @php $count = $resultCounts[$key] ?? 0; @endphp
            <tr>
                <td class="lbl">{{ ucfirst($key) }}</td>
                <td class="bar">
                    <div class="track"><div class="fill" style="width:{{ ($count / $maxRes) * 100 }}%;background:{{ $color }}"></div></div>
                </td>
                <td class="val">{{ number_format($count) }}</td>
            </tr>
        @endforeach
    </table>
</div>

@if (!empty($facets['policies']))
    <div class="section">
        <h2>Policies</h2>
        @php $maxPol = max(1, collect($facets['policies'])->max('count') ?: 1); @endphp
        <table class="barlist">
            @foreach ($facets['policies'] as $policy)
                <tr>
                    <td class="lbl">{{ \Illuminate\Support\Str::limit($policy['key'], 52) }}</td>
                    <td class="bar">
                        <div class="track"><div class="fill" style="width:{{ ($policy['count'] / $maxPol) * 100 }}%;background:#38bdf8"></div></div>
                    </td>
                    <td class="val">{{ number_format($policy['count']) }}</td>
                </tr>
            @endforeach
        </table>
    </div>
@endif

<div class="section">
    <h2>Check Results ({{ number_format($shown) }} of {{ number_format($facets['total']) }})</h2>

    @if ($facets['total'] > $shown)
        <div class="note">
            Showing {{ number_format($shown) }} check results. The counts above cover the full result set.
        </div>
    @endif

    <table class="data">
        <thead>
            <tr>
                <th style="width:9%">Check</th>
                <th style="width:46%">Title</th>
                <th style="width:10%">Result</th>
                <th style="width:23%">Policy</th>
                <th style="width:12%">Last seen</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                @php
                    $res = strtolower((string) $item['result']);
                    $resToken = match ($res) {
                        'passed' => 'success',
                        'failed' => 'critical',
                        default => 'info',
                    };
                @endphp
                <tr>
                    <td class="mono">{{ $item['check_id'] ?: '—' }}</td>
                    <td>
                        {{ \Illuminate\Support\Str::limit($item['title'] ?? '—', 105) }}
                        @if (!empty($item['rationale']))
                            <div class="muted">{{ \Illuminate\Support\Str::limit($item['rationale'], 90) }}</div>
                        @endif
                    </td>
                    <td><span class="pill {{ $resToken }}">{{ ucfirst($item['result']) }}</span></td>
                    <td>{{ \Illuminate\Support\Str::limit($item['policy'] ?? '—', 40) }}</td>
                    <td class="mono">
                        {{ !empty($item['timestamp']) ? \Illuminate\Support\Carbon::parse($item['timestamp'])->format('d M Y H:i') : '—' }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No checks match the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('reports.partials.pdf-foot')
</body>
</html>
