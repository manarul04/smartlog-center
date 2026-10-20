<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ config('app.name') }} &middot; {{ $meta['title'] }} &middot; {{ $meta['scope'] }}</title>
    <style>
        @page { margin: 24px 22px 40px 22px; }

        * { box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8.5px;
            color: #1e293b;
            line-height: 1.45;
            margin: 0;
        }

        table { border-collapse: collapse; }
        td, th { vertical-align: top; }

        /* ---------- Header ---------- */
        .head-table { width: 100%; border-bottom: 2.5px solid #1d4ed8; margin-bottom: 12px; }
        .head-table td { padding: 0 0 9px 0; }

        .brand {
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 1.2px;
            color: #1d4ed8;
            text-transform: uppercase;
        }

        h1 {
            font-size: 17px;
            margin: 2px 0 2px;
            color: #0f172a;
        }

        .scope { font-size: 9px; color: #475569; margin: 0; }

        .meta { font-size: 7.5px; color: #64748b; line-height: 1.65; text-align: right; }
        .meta b { color: #334155; }

        /* ---------- Filter chips ---------- */
        .filters {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            padding: 6px 9px;
            margin-bottom: 11px;
        }

        .ftitle {
            font-size: 6.8px;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: #64748b;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .fchip {
            display: inline-block;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 1.5px 5px;
            margin: 0 4px 2px 0;
            font-size: 7.3px;
        }

        .fchip b { color: #1d4ed8; }

        /* ---------- KPI ---------- */
        .kpi-table { width: 100%; margin-bottom: 12px; }
        .kpi-table td {
            width: 20%;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-top: 2px solid #1d4ed8;
            padding: 6px 7px;
        }
        .kpi-table td + td { border-left: none; }

        .kpi-k {
            font-size: 6.4px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #64748b;
            font-weight: bold;
        }
        .kpi-v { font-size: 14.5px; font-weight: bold; color: #0f172a; line-height: 1.15; }
        .kpi-s { font-size: 6.4px; color: #94a3b8; }

        /* ---------- Sections ---------- */
        h2 {
            font-size: 9px;
            margin: 0 0 5px;
            padding-bottom: 2.5px;
            border-bottom: 1px solid #cbd5e1;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .section { margin-bottom: 12px; }

        /* ---------- Data tables ---------- */
        table.data { width: 100%; }

        table.data th {
            background: #1e293b;
            color: #ffffff;
            font-size: 6.7px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            text-align: left;
            padding: 3.5px 4px;
            font-weight: bold;
        }

        table.data td {
            padding: 3px 4px;
            border-bottom: 1px solid #e8edf3;
            font-size: 7.3px;
        }

        table.data tr:nth-child(even) td { background: #fafbfc; }

        .num { text-align: right; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 6.8px; }

        /* ---------- Pills ---------- */
        .pill {
            display: inline-block;
            padding: 0.5px 4px;
            border-radius: 7px;
            font-size: 6.3px;
            font-weight: bold;
            color: #ffffff;
        }
        .pill.critical { background: #e11d48; }
        .pill.high { background: #ea580c; }
        .pill.medium { background: #ca8a04; }
        .pill.low { background: #0284c7; }
        .pill.info { background: #64748b; }

        .status {
            display: inline-block;
            padding: 0.5px 4px;
            border-radius: 2px;
            font-size: 6.3px;
            font-weight: bold;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #334155;
        }

        /* ---------- Bar lists (inside real tables) ---------- */
        .barlist { width: 100%; }
        .barlist td { padding: 1.8px 0; font-size: 7.3px; border: none; background: none !important; }
        .barlist .lbl { width: 40%; color: #334155; }
        .barlist .bar { width: 46%; }
        .barlist .val { width: 14%; text-align: right; color: #0f172a; font-weight: bold; }

        .track { background: #e8edf3; height: 5px; width: 100%; }
        .fill { height: 5px; background: #1d4ed8; }

        /* ---------- Notes / footer ---------- */
        .note {
            background: #fffbeb;
            border: 1px solid #fde68a;
            padding: 5px 7px;
            font-size: 7.3px;
            color: #92400e;
            margin-bottom: 8px;
        }

        .footer {
            margin-top: 10px;
            border-top: 1px solid #cbd5e1;
            padding-top: 4px;
            font-size: 6.3px;
            color: #94a3b8;
        }
        .footer .left { float: left; width: 72%; }
        .footer .right { float: right; width: 26%; text-align: right; }

        .muted { color: #94a3b8; }
    </style>
</head>
<body>


<table class="head-table">
    <tr>
        <td style="width:64%">
            <table>
                <tr>
                    <td style="padding:0 7px 0 0;width:30px">
                        <img src="{{ public_path('assets/img/logo.png') }}" width="26" height="26" alt="">
                    </td>
                    <td style="padding:0">
                        <div class="brand">{{ config('app.name') }} &middot; Threat Management</div>
                        <h1>{{ $meta['title'] }}</h1>
                        <p class="scope">{{ $meta['scope'] }}</p>
                    </td>
                </tr>
            </table>
        </td>
        <td style="width:36%" class="meta">
            <b>Period</b><br>
            {{ $meta['period']['from']->format('d M Y H:i') }} &mdash; {{ $meta['period']['to']->format('d M Y H:i') }}<br>
            <b>Range</b> {{ $meta['period']['label'] }}<br>
            <b>Generated</b> {{ $meta['generated_at']->format('d M Y H:i') }}<br>
            <b>By</b> {{ $generatedBy->name ?? 'system' }}
        </td>
    </tr>
</table>

@if (!empty($meta['descriptors']))
    <div class="filters">
        <div class="ftitle">Applied filters</div>
        @foreach ($meta['descriptors'] as $descriptor)
            <span class="fchip"><b>{{ $descriptor['label'] }}</b>: {{ $descriptor['value'] }}</span>
        @endforeach
    </div>
@endif

@php
    $total = $data['total'];
    $criticalish = ($data['severity']['critical'] ?? 0) + ($data['severity']['high'] ?? 0);
    $openStatuses = collect($statuses)->filter(fn ($s) => $s['open'])->keys()->all();
    $openCount = 0;
    foreach ($data['rows'] as $row) {
        $st = $row['annotation']->status ?? \App\Support\AlertStatus::NOT_REVIEWED;
        if (in_array($st, $openStatuses, true)) {
            $openCount++;
        }
    }
@endphp

<table class="kpi-table">
    <tr>
        <td>
            <div class="kpi-k">Total alerts</div>
            <div class="kpi-v">{{ number_format($total) }}</div>
            <div class="kpi-s">matching all filters</div>
        </td>
        <td>
            <div class="kpi-k">Critical &amp; high</div>
            <div class="kpi-v">{{ number_format($criticalish) }}</div>
            <div class="kpi-s">{{ $total > 0 ? number_format(($criticalish / $total) * 100, 1) : 0 }}% of total</div>
        </td>
        <td>
            <div class="kpi-k">Medium</div>
            <div class="kpi-v">{{ number_format($data['severity']['medium'] ?? 0) }}</div>
            <div class="kpi-s">level 7 &ndash; 9</div>
        </td>
        <td>
            <div class="kpi-k">Agents affected</div>
            <div class="kpi-v">{{ count($data['agents']) }}</div>
            <div class="kpi-s">distinct endpoints</div>
        </td>
        <td>
            <div class="kpi-k">Open handling</div>
            <div class="kpi-v">{{ number_format($openCount) }}</div>
            <div class="kpi-s">of {{ number_format($data['shown']) }} shown</div>
        </td>
    </tr>
</table>

<div class="section">
    <h2>Severity Distribution</h2>
    @php $maxSev = max(1, max($data['severity'] ?: [0])); @endphp
    <table class="barlist">
        @foreach ($bands as $key => $band)
            @php $count = $data['severity'][$key] ?? 0; @endphp
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
    <h2>Top Agents &amp; Rule Groups</h2>
    @php
        $maxAgent = max(1, collect($data['agents'])->max('count') ?: 1);
        $maxGroup = max(1, collect($data['groups'])->max('count') ?: 1);
        $agents = array_slice($data['agents'], 0, 8);
        $groups = array_slice($data['groups'], 0, 8);
        $pairCount = max(count($agents), count($groups));
    @endphp
    <table style="width:100%">
        <tr>
            <td style="width:49%;padding:0 8px 0 0">
                <table class="barlist">
                    @for ($i = 0; $i < $pairCount; $i++)
                        @php $agent = $agents[$i] ?? null; @endphp
                        <tr>
                            <td class="lbl">{{ $agent ? \Illuminate\Support\Str::limit($agent['key'], 24) : '' }}</td>
                            <td class="bar">
                                @if ($agent)
                                    <div class="track"><div class="fill" style="width:{{ ($agent['count'] / $maxAgent) * 100 }}%"></div></div>
                                @endif
                            </td>
                            <td class="val">{{ $agent ? number_format($agent['count']) : '' }}</td>
                        </tr>
                    @endfor
                </table>
            </td>
            <td style="width:49%;padding:0 0 0 8px">
                <table class="barlist">
                    @for ($i = 0; $i < $pairCount; $i++)
                        @php $group = $groups[$i] ?? null; @endphp
                        <tr>
                            <td class="lbl">{{ $group ? \Illuminate\Support\Str::limit($group['key'], 24) : '' }}</td>
                            <td class="bar">
                                @if ($group)
                                    <div class="track"><div class="fill" style="width:{{ ($group['count'] / $maxGroup) * 100 }}%;background:#7c3aed"></div></div>
                                @endif
                            </td>
                            <td class="val">{{ $group ? number_format($group['count']) : '' }}</td>
                        </tr>
                    @endfor
                </table>
            </td>
        </tr>
        <tr>
            <td class="muted" style="font-size:6.5px;padding-top:2px">Agents</td>
            <td class="muted" style="font-size:6.5px;padding-top:2px;padding-left:8px">Rule groups</td>
        </tr>
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
            @forelse ($data['rules'] as $i => $rule)
                <tr>
                    <td class="num">{{ $i + 1 }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($rule['key'], 105) }}</td>
                    <td class="num">{{ number_format($rule['count']) }}</td>
                    <td class="num">{{ $total > 0 ? number_format(($rule['count'] / $total) * 100, 1) : 0 }}%</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No rules matched.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="section">
    <h2>Alert Detail ({{ number_format($data['shown']) }} of {{ number_format($total) }})</h2>

    @if ($data['truncated'])
        <div class="note">
            Showing the {{ number_format($data['limit']) }} most recent alerts of
            {{ number_format($total) }} total. Aggregate figures above cover the full result set.
        </div>
    @endif

    <table class="data">
        <thead>
            <tr>
                <th style="width:13%">Timestamp</th>
                <th style="width:5%" class="num">Lvl</th>
                <th style="width:37%">Rule</th>
                <th style="width:12%">Agent</th>
                <th style="width:13%">Decoder</th>
                <th style="width:10%">Status</th>
                <th style="width:10%">Labels</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data['rows'] as $row)
                @php
                    $src = $row['source'];
                    $level = (int) ($src['rule']['level'] ?? 0);
                    $token = \App\Support\Severity::token($level);
                    $ann = $row['annotation'];
                    $statusKey = $ann->status ?? \App\Support\AlertStatus::NOT_REVIEWED;
                @endphp
                <tr>
                    <td class="mono">
                        {{ isset($src['timestamp']) ? \Illuminate\Support\Carbon::parse($src['timestamp'])->format('d M H:i:s') : '&mdash;' }}
                    </td>
                    <td class="num"><span class="pill {{ $token }}">{{ $level }}</span></td>
                    <td>
                        {{ \Illuminate\Support\Str::limit($src['rule']['description'] ?? '—', 92) }}
                        <span class="muted"> &middot; rule {{ $src['rule']['id'] ?? '?' }}</span>
                    </td>
                    <td>{{ $src['agent']['name'] ?? '—' }}</td>
                    <td class="mono">{{ $src['decoder']['name'] ?? '—' }}</td>
                    <td><span class="status">{{ \App\Support\AlertStatus::label($statusKey) }}</span></td>
                    <td>
                        @if ($ann && $ann->labels->isNotEmpty())
                            {{ \Illuminate\Support\Str::limit($ann->labels->pluck('name')->implode(', '), 28) }}
                        @else
                            <span class="muted">&mdash;</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No alerts matched the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="footer">
    <div class="left">
        {{ config('app.name') }} &middot; {{ $meta['title'] }} &middot; {{ $meta['scope'] }} &middot; {{ $meta['period_label'] }}
    </div>
    <div class="right">Generated {{ $meta['generated_at']->format('d M Y H:i') }}</div>
</div>
</body>
</html>
