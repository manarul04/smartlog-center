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
    $sevCounts = collect($facets['severity'])->pluck('count', 'key');
    $critical = $sevCounts['Critical'] ?? 0;
    $high = $sevCounts['High'] ?? 0;
    $medium = $sevCounts['Medium'] ?? 0;
    $low = $sevCounts['Low'] ?? 0;
    $shown = count($items);
@endphp

<table class="kpi-table">
    <tr>
        <td style="width:25%">
            <div class="kpi-k">Findings</div>
            <div class="kpi-v">{{ number_format($facets['total']) }}</div>
            <div class="kpi-s">{{ number_format($shown) }} listed below</div>
        </td>
        <td style="width:25%">
            <div class="kpi-k">Critical</div>
            <div class="kpi-v">{{ number_format($critical) }}</div>
            <div class="kpi-s">CVSS 9.0 and above</div>
        </td>
        <td style="width:25%">
            <div class="kpi-k">High</div>
            <div class="kpi-v">{{ number_format($high) }}</div>
            <div class="kpi-s">CVSS 7.0 &ndash; 8.9</div>
        </td>
        <td style="width:25%">
            <div class="kpi-k">Medium &amp; low</div>
            <div class="kpi-v">{{ number_format($medium + $low) }}</div>
            <div class="kpi-s">CVSS below 7.0</div>
        </td>
    </tr>
</table>

<div class="section">
    <h2>Severity Distribution</h2>
    @php $maxSev = max(1, $sevCounts->max() ?: 1); @endphp
    <table class="barlist">
        @foreach (['Critical' => '#e11d48', 'High' => '#ea580c', 'Medium' => '#ca8a04', 'Low' => '#0284c7'] as $label => $color)
            @php $count = $sevCounts[$label] ?? 0; @endphp
            <tr>
                <td class="lbl">{{ $label }}</td>
                <td class="bar">
                    <div class="track"><div class="fill" style="width:{{ ($count / $maxSev) * 100 }}%;background:{{ $color }}"></div></div>
                </td>
                <td class="val">{{ number_format($count) }}</td>
            </tr>
        @endforeach
    </table>
</div>

@if (!empty($facets['packages']))
    <div class="section">
        <h2>Most Affected Packages</h2>
        @php $maxPkg = max(1, collect($facets['packages'])->max('count') ?: 1); @endphp
        <table class="barlist">
            @foreach ($facets['packages'] as $package)
                <tr>
                    <td class="lbl">{{ \Illuminate\Support\Str::limit($package['key'], 48) }}</td>
                    <td class="bar">
                        <div class="track"><div class="fill" style="width:{{ ($package['count'] / $maxPkg) * 100 }}%;background:#ea580c"></div></div>
                    </td>
                    <td class="val">{{ number_format($package['count']) }}</td>
                </tr>
            @endforeach
        </table>
    </div>
@endif

<div class="section">
    <h2>Findings ({{ number_format($shown) }} of {{ number_format($facets['total']) }})</h2>

    @if ($facets['total'] > $shown)
        <div class="note">
            Showing {{ number_format($shown) }} findings. The counts above cover the full result set.
        </div>
    @endif

    <table class="data">
        <thead>
            <tr>
                <th style="width:14%">CVE</th>
                <th style="width:7%" class="num">CVSS</th>
                <th style="width:9%">Severity</th>
                <th style="width:42%">Description</th>
                <th style="width:16%">Package</th>
                <th style="width:12%">Detected</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
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
                        {{ \Illuminate\Support\Str::limit($pkg['name'] ?? '—', 34) }}
                        <span class="muted mono">{{ $pkg['version'] ?? '' }}</span>
                    </td>
                    <td class="mono">
                        {{ !empty($v['detected_at']) ? \Illuminate\Support\Carbon::parse($v['detected_at'])->format('d M Y') : '—' }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No findings match the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('reports.partials.pdf-foot')
</body>
</html>
