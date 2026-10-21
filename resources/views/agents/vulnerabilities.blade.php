@extends('layouts.app')

@section('title', 'Vulnerabilities · ' . $agent['name'])
@section('subtitle', 'Findings detected on this endpoint')

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Agents & Health', 'url' => route('agents.index')],
        ['label' => $agent['name'], 'url' => route('agents.show', $agent['id'])],
        ['label' => 'Vulnerabilities'],
    ]" />
@endsection

@section('actions')
    <a href="{{ route('reports.index', ['type' => 'vulnerability']) }}" class="btn sm">All reports</a>
    <x-export-button :href="route('agents.vulnerabilities.report', array_filter([
        'id' => $agent['id'],
        'range' => $range['key'],
        'from' => $range['key'] === 'custom' ? $range['from']?->format('Y-m-d') : null,
        'to' => $range['key'] === 'custom' ? $range['to']->format('Y-m-d') : null,
        'severity' => $filters['severity'],
        'category' => $filters['category'],
        'q' => $filters['q'] ?: null,
    ]))" title="Export these findings as a PDF report" />
@endsection

@section('content')
    @if ($error)
        <div class="banner error"><div><strong>Query failed.</strong> {{ $error }}</div></div>
    @endif

    @php
        $sevCounts = collect($facets['severity'])->pluck('count', 'key');
        $critical = $sevCounts['Critical'] ?? 0;
        $high = $sevCounts['High'] ?? 0;
        $medium = $sevCounts['Medium'] ?? 0;
        $low = $sevCounts['Low'] ?? 0;
        $activeSev = $filters['severity'];
    @endphp

    {{-- KPI cards double as severity filters --}}
    <div class="grid grid-4" style="margin-bottom:14px">
        @foreach ([
            ['key' => 'Critical', 'count' => $critical, 'tone' => 'critical', 'hint' => 'CVSS 9.0 and above'],
            ['key' => 'High', 'count' => $high, 'tone' => 'high', 'hint' => 'CVSS 7.0 – 8.9'],
            ['key' => 'Medium', 'count' => $medium, 'tone' => 'medium', 'hint' => 'CVSS 4.0 – 6.9'],
            ['key' => 'Low', 'count' => $low, 'tone' => 'low', 'hint' => $low > 0 ? 'CVSS below 4.0' : 'none detected'],
        ] as $card)
            @php $isActive = $activeSev === $card['key']; @endphp
            <a class="kpi kpi-filter {{ $isActive ? 'active' : '' }}"
               style="--kpi-accent:var(--{{ $card['tone'] }})"
               href="{{ route('agents.vulnerabilities', array_filter([
                    'id' => $agent['id'],
                    'range' => $range['key'],
                    'from' => $range['key'] === 'custom' ? $range['from']?->format('Y-m-d') : null,
                    'to' => $range['key'] === 'custom' ? $range['to']->format('Y-m-d') : null,
                    'severity' => $isActive ? null : $card['key'],
                    'category' => $filters['category'],
                    'q' => $filters['q'] ?: null,
               ])) }}"
               title="{{ $isActive ? 'Remove severity filter' : 'Filter by ' . $card['key'] }}">
                <span class="kpi-label">{{ $card['key'] }}</span>
                <span class="kpi-value" style="color:var(--{{ $card['tone'] }}-text)">{{ number_format($card['count']) }}</span>
                <span class="kpi-meta">{{ $isActive ? 'filter active — click to clear' : $card['hint'] }}</span>
            </a>
        @endforeach
    </div>

    <div class="card">
        <form method="GET" action="{{ route('agents.vulnerabilities', $agent['id']) }}">
            <div class="toolbar">
                <div class="field" style="flex:1;min-width:200px">
                    <label for="q">Search</label>
                    <input id="q" name="q" class="input" value="{{ $filters['q'] }}"
                           placeholder="CVE id, package, description…" data-debounce-submit>
                </div>

                <div class="field">
                    <label for="severity">Severity</label>
                    <select id="severity" name="severity" class="select" data-autosubmit>
                        <option value="">All severities</option>
                        @foreach ($facets['severity'] as $sev)
                            <option value="{{ $sev['key'] }}" @selected($filters['severity'] === $sev['key'])>
                                {{ $sev['key'] }} ({{ number_format($sev['count']) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="category">Category</label>
                    <select id="category" name="category" class="select" data-autosubmit>
                        <option value="">All categories</option>
                        @foreach ($facets['category'] as $cat)
                            <option value="{{ $cat['key'] }}" @selected($filters['category'] === $cat['key'])>
                                {{ $cat['key'] }} ({{ number_format($cat['count']) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="range">Detected</label>
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
                    <label>&nbsp;</label>
                    <a href="{{ route('agents.vulnerabilities', $agent['id']) }}" class="btn sm">Reset</a>
                </div>
            </div>
        </form>

        @if ($result['total'] > 0)
            <div class="row between" style="padding:10px 16px;border-bottom:1px solid var(--border);background:var(--bg-surface-2)">
                <span class="fs-12 text-secondary">
                    <strong style="color:var(--text-primary)">{{ number_format($result['total']) }}</strong> findings
                    @if ($filters['severity'])
                        <span class="badge {{ strtolower($filters['severity']) }}">{{ $filters['severity'] }}</span>
                    @endif
                </span>
                <span class="fs-11 text-muted">Page {{ $result['page'] }} of {{ max(1, $result['pages']) }}</span>
            </div>
        @endif

        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th style="width:150px">CVE</th>
                        <th style="width:64px" class="num">CVSS</th>
                        <th style="width:84px">Severity</th>
                        <th>Description</th>
                        <th style="width:160px">Package</th>
                        <th style="width:130px">Review</th>
                        <th style="width:44px"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($result['items'] as $item)
                        @php
                            $v = $item['source']['vulnerability'] ?? [];
                            $pkg = $item['source']['package'] ?? [];
                            $sev = $v['severity'] ?? 'Unknown';
                            $sevClass = match (strtolower((string) $sev)) {
                                'critical' => 'critical',
                                'high' => 'high',
                                'medium' => 'medium',
                                'low' => 'low',
                                default => 'info',
                            };
                            $score = $v['score']['base'] ?? null;
                            $review = $item['review'];
                            $reviewKey = $review?->status ?? 'not_reviewed';
                        @endphp
                        @php
                            $vulnPayload = [
                            "cve" => $v["id"] ?? null,
                            "severity" => $sev,
                            "cvss" => $score,
                            "description" => $v["description"] ?? null,
                            "reference" => $v["reference"] ?? null,
                            "published_at" => $v["published_at"] ?? null,
                            "detected_at" => $v["detected_at"] ?? null,
                            "enumeration" => $v["enumeration"] ?? null,
                            "classification" => $v["classification"] ?? null,
                            "category" => $v["category"] ?? null,
                            "under_evaluation" => $v["under_evaluation"] ?? null,
                            "condition" => $v["scanner"]["condition"] ?? null,
                            "scanner_ref" => $v["scanner"]["reference"] ?? null,
                            "scanner_source" => $v["scanner"]["source"] ?? null,
                            "scanner_vendor" => $v["scanner"]["vendor"] ?? null,
                            "package_name" => $pkg["name"] ?? null,
                            "package_version" => $pkg["version"] ?? null,
                            "package_type" => $pkg["type"] ?? null,
                            "package_arch" => $pkg["architecture"] ?? null,
                            "package_installed" => $pkg["installed"] ?? null,
                            "agent_name" => $item["source"]["agent"]["name"] ?? null,
                            "host_os" => $item["source"]["host"]["os"]["full"] ?? null,
                            "review" => $review ? [
                                "status" => $review->status,
                                "priority" => $review->priority,
                                "assignee_id" => $review->assignee_id,
                                "assignee" => $review->assignee->name ?? null,
                                "note" => $review->note,
                                "resolution" => $review->resolution,
                                "due_at" => $review->due_at?->format("Y-m-d"),
                                "still_present" => $review->still_present,
                                "last_verified_at" => $review->last_verified_at?->format("d M Y H:i"),
                            ] : null,
];
                        @endphp
                        <tr class="row-clickable" data-vuln="{{ json_encode($vulnPayload, JSON_HEX_APOS | JSON_HEX_QUOT) }}">
                            <td class="mono" style="color:var(--accent)">{{ $v['id'] ?? '—' }}</td>
                            <td class="num mono">{{ $score !== null ? number_format((float) $score, 1) : '—' }}</td>
                            <td><span class="badge {{ $sevClass }}">{{ $sev }}</span></td>
                            <td class="wrap">
                                {{ \Illuminate\Support\Str::limit($v['description'] ?? '—', 120) }}
                                @if (!empty($v['scanner']['condition']))
                                    <div class="fs-11 text-muted">Condition: {{ $v['scanner']['condition'] }}</div>
                                @endif
                            </td>
                            <td class="wrap">
                                <div style="font-size:12px">{{ \Illuminate\Support\Str::limit($pkg['name'] ?? '—', 32) }}</div>
                                <div class="fs-11 text-muted mono">{{ $pkg['version'] ?? '' }}</div>
                            </td>
                            <td>
                                <span class="status-chip {{ \App\Models\Ticket::isUnreviewed($reviewKey) ? 'is-muted' : '' }}"
                                      style="--chip:{{ \App\Models\Ticket::statusColor($reviewKey) }}">
                                    {{ \App\Models\Ticket::statusLabel($reviewKey) }}
                                </span>
                                @if ($review && $review->assignee)
                                    <div class="fs-11 text-muted truncate" style="max-width:120px">{{ $review->assignee->name }}</div>
                                @endif
                            </td>
                            <td class="center">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="opacity:.5">
                                    <path d="M9 18l6-6-6-6"/>
                                </svg>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty">
                                <x-empty-icon name="shield" />
                                No vulnerabilities match the current filters.
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
                <span>{{ number_format($result['total']) }} findings</span>
                <div class="spacer"></div>

                <a class="page-btn {{ $current <= 1 ? 'disabled' : '' }}"
                   href="{{ route('agents.vulnerabilities', array_merge($query, ['id' => $agent['id'], 'page' => max(1, $current - 1)])) }}">&lsaquo;</a>

                @for ($p = $start; $p <= $end; $p++)
                    <a class="page-btn {{ $p === $current ? 'active' : '' }}"
                       href="{{ route('agents.vulnerabilities', array_merge($query, ['id' => $agent['id'], 'page' => $p])) }}">{{ $p }}</a>
                @endfor

                <a class="page-btn {{ $current >= $last ? 'disabled' : '' }}"
                   href="{{ route('agents.vulnerabilities', array_merge($query, ['id' => $agent['id'], 'page' => min($last, $current + 1)])) }}">&rsaquo;</a>
            </div>
        @endif
    </div>

    @if (!empty($facets['packages']))
        <div class="card mt-14">
            <div class="card-head">
                <h3>Most Affected Packages</h3>
            </div>
            <div class="card-body">
                @foreach ($facets['packages'] as $package)
                    <div class="row" style="margin-bottom:9px">
                        <span class="truncate" style="width:240px;font-size:12px">{{ \Illuminate\Support\Str::limit($package['key'], 40) }}</span>
                        <div class="bar-track" style="flex:1">
                            <div class="bar-fill" style="width:{{ ($package['count'] / ($facets['packages'][0]['count'] ?: 1)) * 100 }}%;background:var(--high)"></div>
                        </div>
                        <span class="mono fs-11 text-secondary" style="width:60px;text-align:right">{{ number_format($package['count']) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Right offcanvas drawer --}}
    <div class="drawer-backdrop" id="vulnDrawer" style="display:none">
        <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="vulnDrawerTitle">
            <header class="drawer-head">
                <div style="min-width:0">
                    <div class="row" style="gap:8px">
                        <span class="mono" id="vulnDrawerCve" style="color:var(--accent);font-size:13px">—</span>
                        <span id="vulnDrawerSeverity"></span>
                    </div>
                    <h3 id="vulnDrawerTitle" style="margin:4px 0 0;font-size:13px;font-weight:600">—</h3>
                </div>
                <button type="button" class="btn ghost sm" id="vulnDrawerClose" aria-label="Close">&times;</button>
            </header>

            <div class="drawer-body">
                <div id="vulnDrawerFacts"></div>

                <div class="mt-14" id="vulnDrawerLinks"></div>

                <div class="mt-14" id="vulnDrawerCondition"></div>

                @can('triage')
                    <div class="drawer-section">
                        <h4>Actions</h4>
                        <div class="row wrap" style="gap:6px">
                            <button type="button" class="btn sm" id="vulnToggleReport">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                    <path d="M12 5v14M5 12h14"/>
                                </svg>
                                Create report
                            </button>
                            <a class="btn sm" id="vulnReportLink" style="display:none" href="#">View report</a>
                        </div>
                        <div class="fs-12 text-secondary mt-14" id="vulnCurrentState"></div>
                    </div>

                    <div class="drawer-section" id="vulnReportSection" style="display:none">
                        <h4>New Report</h4>
                        <form method="POST" action="{{ route('reports.from-vulnerability') }}" id="vulnReviewForm">
                            @csrf
                            <input type="hidden" name="agent_name" id="vrAgent">
                            <input type="hidden" name="cve_id" id="vrCve">
                            <input type="hidden" name="package_name" id="vrPackage">
                            <input type="hidden" name="package_version" id="vrPackageVersion">
                            <input type="hidden" name="severity" id="vrSeverity">
                            <input type="hidden" name="cvss" id="vrCvss">

                            <div class="form-row">
                                <label for="vrTitle">Title</label>
                                <input id="vrTitle" name="title" class="input" maxlength="200">
                            </div>

                            <div class="form-row">
                                <label for="vrPriority">Priority</label>
                                <select id="vrPriority" name="priority" class="select" required>
                                    @foreach ($priorities as $key => $meta)
                                        <option value="{{ $key }}">{{ $meta['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-row">
                                <label for="vrAssignee">Assign to</label>
                                <select id="vrAssignee" name="assignee_id" class="select">
                                    <option value="">Unassigned</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-row">
                                <label for="vrDue">Due date</label>
                                <input id="vrDue" name="due_at" type="date" class="input">
                            </div>

                            <div class="row" style="gap:6px">
                                <button type="submit" class="btn primary sm" style="flex:1;justify-content:center">
                                    Create report
                                </button>
                                <button type="button" class="btn ghost sm" id="vulnReportCancel">Cancel</button>
                            </div>
                        </form>
                    </div>
                @endcan
            </div>
        </aside>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var drawer = document.getElementById('vulnDrawer');
    if (!drawer) return;

    var elCve = document.getElementById('vulnDrawerCve');
    var elSev = document.getElementById('vulnDrawerSeverity');
    var elTitle = document.getElementById('vulnDrawerTitle');
    var elFacts = document.getElementById('vulnDrawerFacts');
    var elLinks = document.getElementById('vulnDrawerLinks');
    var elCond = document.getElementById('vulnDrawerCondition');
    var elReviewMeta = document.getElementById('vulnDrawerReviewMeta');

    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fact(k, v) {
        if (v === null || v === undefined || v === '') return '';
        return '<div class="k">' + esc(k) + '</div><div class="v">' + esc(v) + '</div>';
    }

    function setVal(id, v) {
        var el = document.getElementById(id);
        if (el) el.value = v === null || v === undefined ? '' : v;
    }

    function openDrawer(d) {
        elCve.textContent = d.cve || '—';
        elSev.innerHTML = '<span class="badge ' + esc((d.severity || '').toLowerCase()) + '">' + esc(d.severity || '') + '</span>';
        elTitle.textContent = d.description || 'Vulnerability detail';

        elFacts.innerHTML = '<div class="detail-grid">' +
            fact('CVE', d.cve) +
            fact('CVSS', d.cvss !== null && d.cvss !== undefined ? Number(d.cvss).toFixed(1) : null) +
            fact('Severity', d.severity) +
            fact('Category', d.category) +
            fact('Published', d.published_at) +
            fact('Detected', d.detected_at) +
            fact('Agent', d.agent_name) +
            fact('Host OS', d.host_os) +
            fact('Package', d.package_name) +
            fact('Version', d.package_version) +
            fact('Architecture', d.package_arch) +
            fact('Installed', d.package_installed) +
            '</div>';

        // External references, one link per comma-separated URL.
        var refs = (d.reference || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
        var scanner = d.scanner_ref ? [d.scanner_ref] : [];
        var all = refs.concat(scanner);
        var html = '';
        if (all.length) {
            html += '<div class="fs-11 text-muted" style="text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px">References</div>';
            html += '<div class="col" style="gap:5px">';
            all.forEach(function (u) {
                var safe = /^https?:\/\//i.test(u) ? u : null;
                if (!safe) return;
                html += '<a class="ref-link" href="' + esc(safe) + '" target="_blank" rel="noopener noreferrer">' +
                    esc(safe) + '</a>';
            });
            html += '</div>';
        }
        if (d.cve) {
            html += '<div class="row wrap" style="gap:6px;margin-top:9px">' +
                '<a class="btn sm" target="_blank" rel="noopener noreferrer" href="https://nvd.nist.gov/vuln/detail/' + encodeURIComponent(d.cve) + '">NVD</a>' +
                '<a class="btn sm" target="_blank" rel="noopener noreferrer" href="https://cti.wazuh.com/vulnerabilities/cves/' + encodeURIComponent(d.cve) + '">Wazuh CTI</a>' +
                '</div>';
        }
        elLinks.innerHTML = html;

        elCond.innerHTML = d.condition
            ? '<div class="banner" style="margin:0"><div><strong>Fix condition:</strong> ' + esc(d.condition) +
              (d.scanner_source ? '<div class="fs-11 text-muted" style="margin-top:3px">Source: ' + esc(d.scanner_source) + '</div>' : '') +
              '</div></div>'
            : '';

        // Populate the report form with the finding identity.
        setVal('vrAgent', d.agent_name); setVal('vrCve', d.cve); setVal('vrPackage', d.package_name);
        setVal('vrPackageVersion', d.package_version); setVal('vrSeverity', d.severity); setVal('vrCvss', d.cvss);

        var r = d.review;
        setVal('vrTitle', r ? r.title : (d.cve + ' on ' + (d.agent_name || '')));
        setVal('vrPriority', r ? r.priority : ((d.severity || '').toLowerCase() || 'medium'));
        setVal('vrAssignee', r ? r.assignee_id : '');
        setVal('vrDue', r ? r.due_at : '');

        // Compact state, so the drawer stays clean until a form is requested.
        if (elReviewMeta) {
            elReviewMeta.textContent = r
                ? 'Report: ' + (r.reference || 'existing') + '  ·  Status: ' + (r.status || 'not_reviewed') +
                  '  ·  Assignee: ' + (r.assignee || 'Unassigned')
                : 'No report yet for this finding.';
        }

        var reportLink = document.getElementById('vulnReportLink');
        if (r && r.id) {
            reportLink.style.display = '';
            reportLink.href = '/reports/' + r.id;
            reportLink.textContent = 'View ' + (r.reference || 'report');
        } else {
            reportLink.style.display = 'none';
        }

        // The form starts collapsed.
        var reportSection = document.getElementById('vulnReportSection');
        if (reportSection) reportSection.style.display = 'none';

        drawer.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    var vulnToggle = document.getElementById('vulnToggleReport');
    var vulnSection = document.getElementById('vulnReportSection');
    var vulnCancel = document.getElementById('vulnReportCancel');
    if (vulnToggle && vulnSection) {
        vulnToggle.addEventListener('click', function () {
            vulnSection.style.display = vulnSection.style.display === 'none' ? '' : 'none';
        });
    }
    if (vulnCancel && vulnSection) {
        vulnCancel.addEventListener('click', function () { vulnSection.style.display = 'none'; });
    }

    function close() {
        drawer.style.display = 'none';
        document.body.style.overflow = '';
    }

    document.querySelectorAll('[data-vuln]').forEach(function (row) {
        row.addEventListener('click', function (e) {
            // Let real links inside the row behave normally.
            if (e.target.closest('a')) return;
            openDrawer(JSON.parse(row.getAttribute('data-vuln')));
        });
    });

    document.getElementById('vulnDrawerClose').addEventListener('click', close);
    drawer.addEventListener('click', function (e) { if (e.target === drawer) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
</script>
@endpush
