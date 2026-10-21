@extends('layouts.app')

@section('title', 'Compliance')
@section('subtitle', 'Regulatory control coverage')

@section('breadcrumb')
    <x-breadcrumb :items="[['label' => 'Compliance']]" />
@endsection

@section('actions')
    <div class="seg">
        @foreach (['7d', '30d', '90d'] as $key)
            <a href="{{ route('compliance.index', ['range' => $key]) }}" class="{{ $range === $key ? 'active' : '' }}">{{ $key }}</a>
        @endforeach
    </div>
@endsection

@section('content')
    @if ($error)
        <div class="banner error"><div><strong>Query failed.</strong> {{ $error }}</div></div>
    @endif

    @if (!empty($data))
        <div class="banner">
            <div>
                Compliance coverage is derived from the <span class="mono">rule.pci_dss</span>, <span class="mono">rule.hipaa</span>,
                <span class="mono">rule.gdpr</span>, <span class="mono">rule.nist_800_53</span> and <span class="mono">rule.tsc</span>
                mappings shipped with your Wazuh ruleset. Counts reflect alert volume touching each control, not an audit verdict.
            </div>
        </div>

        <div class="tabs">
            @foreach ($data as $key => $framework)
                <div class="tab {{ $loop->first ? 'active' : '' }}" data-tab="{{ $key }}">{{ $framework['label'] }}</div>
            @endforeach
        </div>

        @foreach ($data as $key => $framework)
            @php $maxCount = collect($framework['controls'])->max('count') ?: 1; @endphp
            <div class="tab-panel" data-panel="{{ $key }}" @if (!$loop->first) style="display:none" @endif>
                <div class="grid grid-3" style="margin-bottom:14px">
                    <div class="kpi" style="--kpi-accent:var(--accent)">
                        <span class="kpi-label">Alerts In Scope</span>
                        <span class="kpi-value">{{ number_format($framework['total']) }}</span>
                        <span class="kpi-meta">{{ $ranges[$range]['label'] }}</span>
                    </div>
                    <div class="kpi" style="--kpi-accent:var(--high)">
                        <span class="kpi-label">Controls Touched</span>
                        <span class="kpi-value">{{ $framework['controls_hit'] }}</span>
                        <span class="kpi-meta">distinct controls</span>
                    </div>
                    <div class="kpi" style="--kpi-accent:var(--medium)">
                        <span class="kpi-label">Highest Severity</span>
                        <span class="kpi-value">{{ collect($framework['controls'])->max('max_level') ?? 0 }}</span>
                        <span class="kpi-meta">rule level observed</span>
                    </div>
                </div>

                <div class="card">
                    <div class="card-head">
                        <h3>{{ $framework['label'] }} Controls</h3>
                        <div class="spacer"></div>
                        <span class="sub">{{ $framework['controls_hit'] }} controls</span>
                    </div>
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                                <tr>
                                    <th>Control</th>
                                    <th class="num">Alerts</th>
                                    <th style="width:34%">Share</th>
                                    <th class="num">Max Level</th>
                                    <th>Last Seen</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($framework['controls'] as $control)
                                    <tr>
                                        <td class="mono">{{ $control['control'] }}</td>
                                        <td class="num mono">{{ number_format($control['count']) }}</td>
                                        <td>
                                            <div class="bar-cell">
                                                <div class="bar-track">
                                                    <div class="bar-fill" style="width:{{ ($control['count'] / $maxCount) * 100 }}%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="num">
                                            <span class="badge {{ \App\Support\Severity::token((int) $control['max_level']) }}">
                                                {{ (int) $control['max_level'] }}
                                            </span>
                                        </td>
                                        <td class="fs-11 text-secondary nowrap">
                                            {{ $control['last_seen'] ? \Illuminate\Support\Carbon::parse($control['last_seen'])->diffForHumans() : '—' }}
                                        </td>
                                        <td>
                                            <a class="btn ghost sm"
                                               href="{{ route('explorer.index', ['compliance_field' => config("opensearch.compliance_frameworks.{$key}.field"), 'compliance_value' => $control['control'], 'range' => $range]) }}">
                                                Drill down
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="empty">No alerts mapped to this framework.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
@endsection

@push('scripts')
<script>
(function () {
    var tabs = document.querySelectorAll('[data-tab]');
    var panels = document.querySelectorAll('[data-panel]');

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('active'); });
            tab.classList.add('active');
            panels.forEach(function (p) {
                p.style.display = p.getAttribute('data-panel') === tab.getAttribute('data-tab') ? '' : 'none';
            });
        });
    });
})();
</script>
@endpush
