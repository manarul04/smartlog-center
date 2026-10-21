@extends('layouts.app')

@section('title', 'SCA Checks · ' . $agent['name'])
@section('subtitle', 'Configuration assessment results and remediation')

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Agents & Health', 'url' => route('agents.index')],
        ['label' => $agent['name'], 'url' => route('agents.show', $agent['id'])],
        ['label' => 'SCA Checks'],
    ]" />
@endsection

@section('actions')
    <x-export-button :href="route('agents.sca.report', array_filter([
        'id' => $agent['id'],
        'range' => $range['key'],
        'from' => $range['key'] === 'custom' ? $range['from']?->format('Y-m-d') : null,
        'to' => $range['key'] === 'custom' ? $range['to']->format('Y-m-d') : null,
        'result' => $filters['result'],
        'policy' => $filters['policy'],
        'q' => $filters['q'] ?: null,
    ]))" title="Export these check results as a PDF report" />
@endsection

@section('content')
    @if ($error)
        <div class="banner error"><div><strong>Query failed.</strong> {{ $error }}</div></div>
    @endif

    @php
        $resultCounts = collect($facets['result'])->pluck('count', 'key');
        $failed = $resultCounts['failed'] ?? 0;
        $passed = $resultCounts['passed'] ?? 0;
        $na = $resultCounts['not applicable'] ?? 0;
        $score = $summary['score'] ?? null;
    @endphp

    <div class="grid grid-4" style="margin-bottom:14px">
        <div class="kpi" style="--kpi-accent:{{ ($score ?? 0) >= 70 ? 'var(--success)' : (($score ?? 0) >= 40 ? 'var(--warning)' : 'var(--critical)') }}">
            <span class="kpi-label">Compliance Score</span>
            <span class="kpi-value">{{ $score !== null ? $score . '%' : '—' }}</span>
            <span class="kpi-meta">{{ $summary['policy'] ?? 'latest policy result' }}</span>
        </div>
        <div class="kpi" style="--kpi-accent:var(--critical)">
            <span class="kpi-label">Failed Checks</span>
            <span class="kpi-value" style="color:var(--critical-text)">{{ number_format($failed) }}</span>
            <span class="kpi-meta">need remediation</span>
        </div>
        <div class="kpi" style="--kpi-accent:var(--success)">
            <span class="kpi-label">Passed Checks</span>
            <span class="kpi-value" style="color:var(--success-text)">{{ number_format($passed) }}</span>
            <span class="kpi-meta">compliant</span>
        </div>
        <div class="kpi" style="--kpi-accent:var(--info)">
            <span class="kpi-label">Not Applicable</span>
            <span class="kpi-value">{{ number_format($na) }}</span>
            <span class="kpi-meta">{{ number_format($facets['total']) }} total check events</span>
        </div>
    </div>

    <div class="card">
        <form method="GET" action="{{ route('agents.sca', $agent['id']) }}">
            <div class="toolbar">
                <div class="field" style="flex:1;min-width:200px">
                    <label for="q">Search</label>
                    <input id="q" name="q" class="input" value="{{ $filters['q'] }}"
                           placeholder="check title, id, description…" data-debounce-submit>
                </div>

                <div class="field">
                    <label for="range">Period</label>
                    <select id="range" name="range" class="select" data-range-select>
                        @foreach (\App\Support\ReportRange::PRESETS as $key => $preset)
                            <option value="{{ $key }}" @selected($range['key'] === $key)>{{ $preset['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field" data-range-custom style="{{ $range['key'] === 'custom' ? '' : 'display:none' }}">
                    <label for="from">From</label>
                    <input id="from" name="from" type="date" class="input"
                           value="{{ $range['key'] === 'custom' ? $range['from']?->format('Y-m-d') : '' }}">
                </div>

                <div class="field" data-range-custom style="{{ $range['key'] === 'custom' ? '' : 'display:none' }}">
                    <label for="to">To</label>
                    <input id="to" name="to" type="date" class="input"
                           value="{{ $range['key'] === 'custom' ? $range['to']->format('Y-m-d') : '' }}">
                </div>

                <div class="field">
                    <label for="result">Result</label>
                    <select id="result" name="result" class="select" data-autosubmit>
                        <option value="">All results</option>
                        @foreach ($facets['result'] as $res)
                            <option value="{{ $res['key'] }}" @selected($filters['result'] === $res['key'])>
                                {{ ucfirst($res['key']) }} ({{ number_format($res['count']) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="policy">Policy</label>
                    <select id="policy" name="policy" class="select" data-autosubmit>
                        <option value="">All policies</option>
                        @foreach ($facets['policies'] as $pol)
                            <option value="{{ $pol['key'] }}" @selected($filters['policy'] === $pol['key'])>
                                {{ \Illuminate\Support\Str::limit($pol['key'], 38) }} ({{ number_format($pol['count']) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label>&nbsp;</label>
                    <a href="{{ route('agents.sca', $agent['id']) }}" class="btn sm">Reset</a>
                </div>
            </div>
        </form>

        @if ($result['total'] > 0)
            <div class="row between" style="padding:10px 16px;border-bottom:1px solid var(--border);background:var(--bg-surface-2)">
                <span class="fs-12 text-secondary">
                    <strong style="color:var(--text-primary)">{{ number_format($result['total']) }}</strong> check results
                </span>
                <span class="fs-11 text-muted">Page {{ $result['page'] }} of {{ max(1, $result['pages']) }}</span>
            </div>
        @endif

        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th style="width:62px">Check</th>
                        <th>Title</th>
                        <th style="width:96px">Result</th>
                        <th style="width:150px">Policy</th>
                        <th style="width:130px">Handling</th>
                        <th style="width:70px"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($result['items'] as $item)
                        @php
                            $res = strtolower((string) $item['result']);
                            $resClass = match ($res) {
                                'passed' => 'success',
                                'failed' => 'critical',
                                'not applicable' => 'info',
                                default => 'neutral',
                            };
                            $state = $item['state'];
                            $stateKey = $state->status ?? 'open';
                        @endphp
                        <tr>
                            <td class="mono fs-11">{{ $item['check_id'] ?: '—' }}</td>
                            <td class="wrap">
                                <div style="font-size:12.5px;font-weight:550">{{ $item['title'] }}</div>
                                @if ($item['rationale'])
                                    <div class="fs-11 text-muted">{{ \Illuminate\Support\Str::limit($item['rationale'], 110) }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $resClass }}">{{ ucfirst($item['result']) }}</span>
                            </td>
                            <td class="fs-11 text-secondary wrap">{{ \Illuminate\Support\Str::limit($item['policy'] ?? '—', 30) }}</td>
                            <td>
                                <span class="status-chip" style="--chip:{{ \App\Models\Ticket::statusColor($stateKey) }}">
                                    {{ \App\Models\Ticket::statusLabel($stateKey) }}
                                </span>
                                @if ($state && $state->assignee)
                                    <div class="fs-11 text-muted">{{ $state->assignee->name }}</div>
                                @endif
                            </td>
                            <td>
                                @php
                                    $payload = [
                                        'check_id' => $item['check_id'],
                                        'policy' => $item['policy'],
                                        'title' => $item['title'],
                                        'result' => $item['result'],
                                        'status' => $stateKey,
                                        'note' => $state->note ?? '',
                                        'assignee_id' => $state->assignee_id ?? null,
                                        'remediation' => $item['remediation'],
                                        'description' => $item['description'],
                                        'command' => $item['command'],
                                        'directory' => $item['directory'],
                                    ];
                                @endphp
                                <button type="button" class="btn ghost sm"
                                        data-sca="{{ json_encode($payload, JSON_HEX_APOS | JSON_HEX_QUOT) }}">Handle</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty">
                                <x-empty-icon name="clipboard" />
                                No SCA checks match the current filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($result['pages'] > 1)
            @php
                $query = request()->query();
                $current = $result['page'];
                $last = $result['pages'];
                $start = max(1, $current - 2);
                $end = min($last, $current + 2);
            @endphp
            <div class="pager">
                <span>{{ number_format($result['total']) }} results</span>
                <div class="spacer"></div>

                <a class="page-btn {{ $current <= 1 ? 'disabled' : '' }}"
                   href="{{ route('agents.sca', array_merge($query, ['id' => $agent['id'], 'page' => max(1, $current - 1)])) }}">&lsaquo;</a>

                @for ($p = $start; $p <= $end; $p++)
                    <a class="page-btn {{ $p === $current ? 'active' : '' }}"
                       href="{{ route('agents.sca', array_merge($query, ['id' => $agent['id'], 'page' => $p])) }}">{{ $p }}</a>
                @endfor

                <a class="page-btn {{ $current >= $last ? 'disabled' : '' }}"
                   href="{{ route('agents.sca', array_merge($query, ['id' => $agent['id'], 'page' => min($last, $current + 1)])) }}">&rsaquo;</a>
            </div>
        @endif
    </div>

    @can('triage')
        {{-- Handling drawer --}}
        <div class="modal-backdrop" id="scaModal" style="display:none">
            <div class="modal">
                <div class="modal-head">
                    <div>
                        <div class="fs-11 text-muted mono" id="scaModalCheck">—</div>
                        <h3 id="scaModalTitle" style="margin:2px 0 0">—</h3>
                    </div>
                    <button type="button" class="btn ghost sm" id="scaModalClose" aria-label="Close">&times;</button>
                </div>

                <div class="modal-body">
                    <div class="detail-grid" id="scaModalFacts"></div>

                    <div id="scaModalRemediation" class="mt-14"></div>

                    <div class="row wrap" style="gap:6px;margin-top:14px">
                        <button type="button" class="btn sm" id="scaToggleReport">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <path d="M12 5v14M5 12h14"/>
                            </svg>
                            Create report
                        </button>
                        <a class="btn sm" id="scaReportLink" style="display:none" href="#">View report</a>
                    </div>

                    <div class="fs-12 text-secondary mt-14" id="scaCurrentState"></div>

                    <div id="scaReportSection" style="display:none;margin-top:14px">
                        <form method="POST" action="{{ route('reports.from-sca') }}" id="scaReportForm">
                            @csrf
                            <input type="hidden" name="agent_name" value="{{ $agent['name'] }}">
                            <input type="hidden" name="check_id" id="scaFieldCheckId">
                            <input type="hidden" name="policy" id="scaFieldPolicy">

                            <div class="form-row">
                                <label for="scaTitle">Title</label>
                                <input id="scaTitle" name="title" class="input" maxlength="200">
                            </div>

                            <div class="form-row">
                                <label for="scaDescription">Description</label>
                                <textarea id="scaDescription" name="description" class="input" rows="2"></textarea>
                            </div>

                            <div class="form-row">
                                <label for="scaPriority">Priority</label>
                                <select id="scaPriority" name="priority" class="select" required>
                                    @foreach (\App\Models\Ticket::PRIORITIES as $key => $meta)
                                        <option value="{{ $key }}" @selected($key === 'medium')>{{ $meta['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-row">
                                <label for="scaAssignee">Assign to</label>
                                <select id="scaAssignee" name="assignee_id" class="select">
                                    <option value="">Unassigned</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-row">
                                <label for="scaDue">Due date</label>
                                <input id="scaDue" name="due_at" type="date" class="input">
                            </div>

                            <div class="row" style="gap:6px">
                                <button type="submit" class="btn primary sm" style="flex:1;justify-content:center">Create report</button>
                                <button type="button" class="btn ghost sm" id="scaReportCancel">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endcan
@endsection

@push('scripts')
@can('triage')
<script>
(function () {
    var modal = document.getElementById('scaModal');
    if (!modal) return;

    var elCheck = document.getElementById('scaModalCheck');
    var elTitle = document.getElementById('scaModalTitle');
    var elFacts = document.getElementById('scaModalFacts');
    var elRemediation = document.getElementById('scaModalRemediation');
    var fCheck = document.getElementById('scaFieldCheckId');
    var fPolicy = document.getElementById('scaFieldPolicy');
    var fAssignee = document.getElementById('scaAssignee');
    var fTitle = document.getElementById('scaTitle');
    var fDesc = document.getElementById('scaDescription');
    var fDue = document.getElementById('scaDue');
    var reportSection = document.getElementById('scaReportSection');
    var reportLink = document.getElementById('scaReportLink');

    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function fact(k, v) {
        return '<div class="k">' + esc(k) + '</div><div class="v">' + esc(v) + '</div>';
    }

    document.querySelectorAll('[data-sca]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var d = JSON.parse(btn.getAttribute('data-sca'));

            elCheck.textContent = 'Check ' + (d.check_id || '—');
            elTitle.textContent = d.title || '—';

            elFacts.innerHTML =
                fact('Result', d.result) +
                fact('Policy', d.policy || '—') +
                '<div class="k">Description</div>' +
                '<div class="v sca-desc">' + esc(d.description || '—') + '</div>';

            var rem = '';
            if (d.remediation) rem += '<div class="fs-11 text-muted" style="text-transform:uppercase;letter-spacing:.05em">Remediation</div>' +
                '<div class="fs-12" style="margin:3px 0 8px">' + esc(d.remediation) + '</div>';
            if (d.command && d.command.length) rem += '<pre class="json" style="max-height:90px">' + esc(d.command.join('\n')) + '</pre>';
            if (d.directory && d.directory.length) rem += '<pre class="json" style="max-height:90px">' + esc(d.directory.join('\n')) + '</pre>';
            elRemediation.innerHTML = rem;

            fCheck.value = d.check_id || '';
            fPolicy.value = d.policy || '';
            fTitle.value = d.title || '';
            fDesc.value = d.description || '';
            fAssignee.value = d.assignee_id || '';
            fDue.value = '';

            // Keep the modal short: the report form is opt-in.
            if (reportSection) reportSection.style.display = 'none';

            var stateEl = document.getElementById('scaCurrentState');
            if (stateEl) {
                stateEl.textContent = 'Report: ' + (d.report_reference || 'none yet') +
                    '  ·  Result: ' + (d.result || 'unknown');
            }
            if (reportLink) {
                if (d.report_id) {
                    reportLink.style.display = '';
                    reportLink.href = '/reports/' + d.report_id;
                    reportLink.textContent = 'View ' + (d.report_reference || 'report');
                } else {
                    reportLink.style.display = 'none';
                }
            }

            modal.style.display = 'flex';
        });
    });

    function close() { modal.style.display = 'none'; }

    var toggleReport = document.getElementById('scaToggleReport');
    if (toggleReport && reportSection) {
        toggleReport.addEventListener('click', function () {
            reportSection.style.display = reportSection.style.display === 'none' ? '' : 'none';
        });
    }
    var cancelReport = document.getElementById('scaReportCancel');
    if (cancelReport && reportSection) {
        cancelReport.addEventListener('click', function () { reportSection.style.display = 'none'; });
    }

    document.getElementById('scaModalClose').addEventListener('click', close);
    modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
</script>
@endcan
@endpush
