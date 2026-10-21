@extends('layouts.app')

@section('title', 'Threat Hunting')
@section('subtitle', 'MITRE ATT&CK coverage and technique hits')

@section('breadcrumb')
    <x-breadcrumb :items="[['label' => 'Threat Hunting']]" />
@endsection

@section('actions')
    <div class="seg">
        @foreach (['7d', '30d', '90d'] as $key)
            <a href="{{ route('threats.index', ['range' => $key]) }}" class="{{ $range === $key ? 'active' : '' }}">{{ $key }}</a>
        @endforeach
    </div>
@endsection

@section('content')
    @if ($error)
        <div class="banner error"><div><strong>Query failed.</strong> {{ $error }}</div></div>
    @endif

    @if ($data)
        @if (empty($data['techniques']))
            <div class="banner warn">
                <div>
                    <strong>No MITRE-tagged alerts in this window.</strong>
                    Rules in your current ruleset may not carry ATT&amp;CK metadata for these events. Try a wider range.
                </div>
            </div>
        @else
            @php
                $maxTactic = collect($data['tactics'])->max('count') ?: 1;
                $maxTech = collect($data['techniques'])->max('count') ?: 1;
            @endphp

            <div class="grid grid-3" style="margin-bottom:14px">
                <div class="kpi" style="--kpi-accent:var(--critical)">
                    <span class="kpi-label">Tagged Alerts</span>
                    <span class="kpi-value">{{ number_format($data['total']) }}</span>
                    <span class="kpi-meta">{{ $ranges[$range]['label'] }}</span>
                </div>
                <div class="kpi" style="--kpi-accent:var(--high)">
                    <span class="kpi-label">Tactics Observed</span>
                    <span class="kpi-value">{{ count($data['tactics']) }}</span>
                    <span class="kpi-meta">distinct ATT&amp;CK tactics</span>
                </div>
                <div class="kpi" style="--kpi-accent:var(--accent)">
                    <span class="kpi-label">Techniques Observed</span>
                    <span class="kpi-value">{{ count($data['techniques']) }}</span>
                    <span class="kpi-meta">distinct technique IDs</span>
                </div>
            </div>

            <div class="card" style="margin-bottom:14px">
                <div class="card-head">
                    <h3>ATT&amp;CK Matrix</h3>
                    <span class="sub">tactic → technique, sized by hit count</span>
                </div>
                <div class="card-body">
                    <div class="matrix">
                        @foreach ($data['tactics'] as $tactic)
                            <div class="matrix-col">
                                <div class="matrix-col-head">
                                    <span class="truncate" style="text-transform:none">{{ $tactic['tactic'] }}</span>
                                    <span class="mono">{{ number_format($tactic['count']) }}</span>
                                </div>
                                @foreach (collect($tactic['techniques'])->sortByDesc('count') as $tech)
                                    <a class="tech-chip"
                                       href="{{ route('explorer.index', ['mitre' => $tech['id'], 'range' => $range]) }}"
                                       style="border-left:3px solid {{ $maxTech > 0 ? 'rgba(59,130,246,' . max(0.15, min(1, $tech['count'] / $maxTech)) . ')' : '#3b82f6' }}">
                                        <span class="tcount">{{ number_format($tech['count']) }}</span>
                                        <span class="tid">{{ $tech['id'] }}</span>
                                        <span class="tname">{{ \Illuminate\Support\Str::limit($tech['name'], 42) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="grid grid-2">
                <div class="card">
                    <div class="card-head">
                        <h3>Tactic Coverage</h3>
                    </div>
                    <div class="card-body">
                        @foreach (collect($data['tactics'])->sortByDesc('count') as $tactic)
                            <div class="row" style="margin-bottom:10px">
                                <span class="truncate" style="width:170px;font-size:12px;color:var(--text-primary)">{{ $tactic['tactic'] }}</span>
                                <div class="bar-track" style="flex:1">
                                    <div class="bar-fill" style="width:{{ ($tactic['count'] / $maxTactic) * 100 }}%;background:#f43f5e"></div>
                                </div>
                                <span class="mono fs-11 text-secondary" style="width:70px;text-align:right">{{ number_format($tactic['count']) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="card">
                    <div class="card-head">
                        <h3>Top Techniques</h3>
                        <div class="spacer"></div>
                        <span class="sub">most frequent</span>
                    </div>
                    <div class="card-body flush">
                        <div class="table-wrap">
                            <table class="data">
                                <thead>
                                    <tr>
                                        <th>Technique</th>
                                        <th>Tactic</th>
                                        <th class="num">Hits</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (collect($data['techniques'])->sortByDesc('count')->take(12) as $tech)
                                        <tr>
                                            <td class="wrap">
                                                <a href="{{ route('explorer.index', ['mitre' => $tech['id'], 'range' => $range]) }}" class="link-strong">
                                                    <span class="mono" style="color:var(--low-text)">{{ $tech['id'] }}</span>
                                                    · {{ \Illuminate\Support\Str::limit($tech['name'], 46) }}
                                                </a>
                                            </td>
                                            <td class="fs-11 text-muted">{{ implode(', ', $tech['tactics']) ?: '—' }}</td>
                                            <td class="num mono">{{ number_format($tech['count']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif
@endsection


