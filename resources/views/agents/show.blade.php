@extends('layouts.app')

@section('title', $agent['name'] ?? 'Agent')
@section('subtitle', 'Endpoint detail')

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Agents & Health', 'url' => route('agents.index')],
        ['label' => $agent['name'] ?? 'Agent'],
    ]" />
@endsection

@section('actions')
    @if ($agent)
        <x-export-button modal>Export PDF</x-export-button>
    @endif
@endsection

@section('content')
    @if (!$agent)
        <div class="banner error"><div><strong>Agent not found.</strong> It may not have reported within the last 30 minutes.</div></div>
    @else
        @php
            $ok = in_array($agent['status'], ['active', 'connected']);
            $historyLabels = collect($history)->map(fn ($h) => \Illuminate\Support\Carbon::parse($h['key_as_string'])->format('d M H:i'));
            $historyValues = collect($history)->map(fn ($h) => $h['doc_count']);
        @endphp

        <div class="grid grid-4" style="margin-bottom:14px">
            <div class="kpi" style="--kpi-accent:{{ $ok ? 'var(--success)' : 'var(--critical)' }}">
                <span class="kpi-label">Status</span>
                <span class="kpi-value" style="font-size:20px">{{ $agent['status'] }}</span>
                <span class="kpi-meta">last keep-alive {{ $agent['last_keepalive'] ? \Illuminate\Support\Carbon::parse($agent['last_keepalive'])->diffForHumans() : '—' }}</span>
            </div>
            <div class="kpi" style="--kpi-accent:var(--accent)">
                <span class="kpi-label">Events (24h)</span>
                <span class="kpi-value">{{ number_format($activity['count'] ?? 0) }}</span>
                <span class="kpi-meta">{{ $activity ? 'peak level ' . ($activity['max_level'] ?? 0) : 'no recent activity' }}</span>
            </div>
            <div class="kpi" style="--kpi-accent:var(--high)">
                <span class="kpi-label">Agent Version</span>
                <span class="kpi-value" style="font-size:19px">{{ str_replace('Wazuh ', '', $agent['version'] ?? '—') }}</span>
                <span class="kpi-meta">{{ $agent['os_platform'] ?? '' }}</span>
            </div>
            <div class="kpi" style="--kpi-accent:var(--medium)">
                <span class="kpi-label">Registered</span>
                <span class="kpi-value" style="font-size:19px">{{ $agent['added'] ? \Illuminate\Support\Carbon::parse($agent['added'])->format('d M Y') : '—' }}</span>
                <span class="kpi-meta">{{ $agent['group'] ? implode(', ', $agent['group']) : 'default group' }}</span>
            </div>
        </div>

        <div class="grid grid-2-1">
            <div class="col">
                <div class="card">
                    <div class="card-head">
                        <h3>Availability (7 days)</h3>
                        <div class="spacer"></div>
                        <span class="sub">heartbeat volume per hour</span>
                    </div>
                    <div class="card-body">
                        <div class="chart-wrap" style="height:220px">
                            <canvas id="availabilityChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-head"><h3>Recent Activity Breakdown</h3></div>
                    <div class="card-body">
                        @if ($activity && !empty($activity['top_groups']))
                            @php $maxG = collect($activity['top_groups'])->max('count') ?: 1; @endphp
                            @foreach ($activity['top_groups'] as $group)
                                <div class="row" style="margin-bottom:9px">
                                    <span class="truncate" style="width:170px;font-size:12px;color:var(--text-primary)">{{ $group['key'] }}</span>
                                    <div class="bar-track" style="flex:1">
                                        <div class="bar-fill" style="width:{{ ($group['count'] / $maxG) * 100 }}%"></div>
                                    </div>
                                    <span class="mono fs-11 text-secondary" style="width:70px;text-align:right">{{ number_format($group['count']) }}</span>
                                </div>
                            @endforeach
                        @else
                            <div class="empty">No events in the last 24 hours.</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col">
                <div class="card">
                    <div class="card-head"><h3>Identity</h3></div>
                    <div class="card-body">
                        <div class="detail-grid">
                            <div class="k">Agent ID</div><div class="v">{{ $agent['id'] }}</div>
                            <div class="k">Name</div><div class="v">{{ $agent['name'] }}</div>
                            <div class="k">IP</div><div class="v">{{ $agent['ip'] ?? '—' }}</div>
                            <div class="k">Node</div><div class="v">{{ $agent['node'] ?? '—' }}</div>
                            <div class="k">Host</div><div class="v">{{ $agent['host'] ?? '—' }}</div>
                            <div class="k">OS</div><div class="v">{{ $agent['os'] ?? '—' }}</div>
                            <div class="k">Version</div><div class="v">{{ $agent['version'] ?? '—' }}</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-head"><h3>Actions</h3></div>
                    <div class="card-body col">
                        <a class="btn sm" href="{{ route('explorer.index', ['agent' => $agent['name'], 'range' => '24h']) }}">
                            Investigate alerts (24h)
                        </a>
                        <a class="btn sm" href="{{ route('explorer.index', ['agent' => $agent['name'], 'level_min' => 10, 'range' => '7d']) }}">
                            High severity (7d)
                        </a>
                        <a class="btn sm" href="{{ route('explorer.index', ['agent' => $agent['name'], 'group' => 'syscheck', 'range' => '7d']) }}">
                            File integrity changes
                        </a>
                        <a class="btn sm" href="{{ route('agents.vulnerabilities', $agent['id']) }}">
                            Vulnerabilities ({{ number_format($vulnFacets['total'] ?? 0) }})
                        </a>
                        <a class="btn sm" href="{{ route('agents.sca', $agent['id']) }}">
                            SCA checks ({{ number_format($scaFacets['total'] ?? 0) }})
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Installed software: packages for every OS, KBs on Windows --}}
        <div class="card mt-14">
            <div class="card-head">
                <h3>Software</h3>
                <div class="spacer"></div>
                <span class="sub">
                    {{ number_format($inventory['packages']['total'] ?? 0) }} packages
                    @if (($inventory['hotfixes']['total'] ?? 0) > 0)
                        &middot; {{ number_format($inventory['hotfixes']['total']) }} KBs
                    @endif
                </span>
            </div>

            <div class="tabs" style="margin:0;padding:0 16px">
                <div class="tab active" data-sw-tab="packages">Packages</div>
                @if (($inventory['hotfixes']['total'] ?? 0) > 0)
                    <div class="tab" data-sw-tab="hotfixes">Windows KBs</div>
                @endif
            </div>

            <div class="tab-panel" data-sw-panel="packages">
                @if (!empty($inventory['packages']['types']))
                    <div class="toolbar" style="border-top:none">
                        <div class="field" style="flex:1;min-width:180px">
                            <label for="pkgSearch">Filter</label>
                            <input id="pkgSearch" class="input" placeholder="Package name or version…" autocomplete="off">
                        </div>
                        <div class="field">
                            <label for="pkgType">Type</label>
                            <select id="pkgType" class="select">
                                <option value="">All types</option>
                                @foreach ($inventory['packages']['types'] as $type)
                                    <option value="{{ $type['key'] }}">{{ $type['key'] }} ({{ number_format($type['count']) }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif

                <div class="table-wrap">
                    <table class="data" id="pkgTable">
                        <thead>
                            <tr>
                                <th>Package</th>
                                <th style="width:150px">Version</th>
                                <th style="width:90px">Type</th>
                                <th style="width:90px">Arch</th>
                                <th style="width:110px">Installed</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($inventory['packages']['items'] as $pkg)
                                <tr data-pkg-row data-type="{{ $pkg['type'] }}"
                                    data-search="{{ strtolower($pkg['name'] . ' ' . ($pkg['version'] ?? '')) }}">
                                    <td class="wrap">
                                        {{ \Illuminate\Support\Str::limit($pkg['name'], 56) }}
                                        @if ($pkg['vendor'])
                                            <div class="fs-11 text-muted">{{ \Illuminate\Support\Str::limit($pkg['vendor'], 46) }}</div>
                                        @endif
                                    </td>
                                    <td class="mono fs-11">{{ $pkg['version'] ?? '—' }}</td>
                                    <td><span class="badge neutral">{{ $pkg['type'] }}</span></td>
                                    <td class="fs-11 text-secondary">{{ $pkg['architecture'] ?? '—' }}</td>
                                    <td class="fs-11 text-secondary nowrap">
                                        {{ $pkg['installed'] ? \Illuminate\Support\Carbon::parse($pkg['installed'])->format('d M Y') : '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="empty">
                                        <x-empty-icon name="inbox" />
                                        No package inventory for this agent. Wazuh collects it with the
                                        <span class="mono">syscollector</span> module.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if (($inventory['packages']['total'] ?? 0) > count($inventory['packages']['items'] ?? []))
                    <div class="pager" style="border-top:1px solid var(--border)">
                        <span>
                            Showing {{ number_format(count($inventory['packages']['items'])) }} of
                            {{ number_format($inventory['packages']['total']) }} packages.
                        </span>
                    </div>
                @endif
            </div>

            @if (($inventory['hotfixes']['total'] ?? 0) > 0)
                @php
                    // "Resolved" covers both a real title and an article the
                    // catalog does not list; only never-looked-up rows remain.
                    $kbResolved = collect($kbTitles)->filter(fn ($t) => $t['status'] !== 'pending')->count();
                @endphp
                <div class="tab-panel" data-sw-panel="hotfixes" style="display:none">
                    <div class="toolbar" style="border-top:none">
                        <div class="field" style="flex:1;min-width:180px">
                            <label for="kbSearch">Filter</label>
                            <input id="kbSearch" class="input" placeholder="KB number or title…" autocomplete="off">
                        </div>
                        <div class="field" style="flex:0 0 auto">
                            <label>&nbsp;</label>
                            <button type="button" class="btn sm" id="kbResolveAll"
                                    data-endpoint="{{ route('agents.kb-titles', $agent['id']) }}"
                                    @if (count($kbPending) === 0) disabled @endif>
                                Load all titles
                            </button>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table class="data" id="kbTable">
                            <thead>
                                <tr>
                                    <th style="width:120px">KB</th>
                                    <th>Title</th>
                                    <th style="width:180px">Product</th>
                                    <th style="width:110px">Released</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($inventory['hotfixes']['items'] as $kb)
                                    @php
                                        $entry = $kbTitles[$kb] ?? null;
                                        $title = $entry['title'] ?? null;
                                        $url = $entry['url'] ?? 'https://support.microsoft.com/help/' . preg_replace('/\D+/', '', $kb);
                                    @endphp
                                    <tr data-kb-row data-kb="{{ $kb }}"
                                        data-search="{{ strtolower($kb . ' ' . ($title ?? '') . ' ' . ($entry['product'] ?? '')) }}">
                                        <td class="mono fs-11">
                                            <a href="{{ $url }}" target="_blank" rel="noopener">{{ $kb }}</a>
                                        </td>
                                        <td class="wrap" data-kb-title>
                                            @if ($title)
                                                {{ $title }}
                                            @elseif (($entry['status'] ?? null) === 'not_found')
                                                <span class="text-muted">No catalog title for this article</span>
                                            @else
                                                <span class="text-muted" data-kb-pending>Loading title…</span>
                                            @endif
                                        </td>
                                        <td class="fs-11 text-secondary" data-kb-product title="{{ $entry['product'] ?? '' }}">
                                            {{ !empty($entry['product']) ? \Illuminate\Support\Str::limit($entry['product'], 42) : '—' }}
                                        </td>
                                        <td class="fs-11 text-secondary nowrap" data-kb-date>
                                            {{ !empty($entry['date']) ? \Illuminate\Support\Carbon::createFromTimestamp($entry['date'])->format('d M Y') : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="pager" style="border-top:1px solid var(--border)">
                        <span>
                            <span id="kbCachedCount">{{ number_format($kbResolved) }}</span> of
                            {{ number_format($inventory['hotfixes']['total']) }} resolved.
                            @if ($inventory['hotfixes']['total'] > count($inventory['hotfixes']['items']))
                                Showing {{ number_format(count($inventory['hotfixes']['items'])) }} installed updates.
                            @endif
                        </span>
                    </div>
                </div>
            @endif
        </div>

        {{-- Report builder: pick a period and the sections to include. --}}
        <div class="modal-backdrop" id="agentReportModal" style="display:none">
            <div class="modal">
                <div class="modal-head">
                    <div style="min-width:0">
                        <div class="fs-11 text-muted">PDF report</div>
                        <h3 style="margin:2px 0 0">{{ $agent['name'] }}</h3>
                    </div>
                    <div class="spacer"></div>
                    <button type="button" class="btn ghost sm" data-report-close aria-label="Close">&times;</button>
                </div>

                <div class="modal-body">
                    <form method="GET" action="{{ route('agents.report', $agent['id']) }}" id="agentReportForm">
                        <div class="form-row">
                            <label for="reportRange">Period</label>
                            <select id="reportRange" name="range" class="select">
                                @foreach (\App\Support\ReportRange::PRESETS as $key => $preset)
                                    <option value="{{ $key }}" @selected($key === '30d')>{{ $preset['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row" id="reportCustomDates" style="gap:10px;display:none;margin-bottom:14px">
                            <div class="form-row" style="flex:1;margin-bottom:0">
                                <label for="reportFrom">From</label>
                                <input id="reportFrom" name="from" type="date" class="input">
                            </div>
                            <div class="form-row" style="flex:1;margin-bottom:0">
                                <label for="reportTo">To</label>
                                <input id="reportTo" name="to" type="date" class="input">
                            </div>
                        </div>

                        <div class="form-row">
                            <label>Sections</label>
                            <label class="check-row">
                                <input type="checkbox" name="sections[]" value="alerts" checked>
                                Security alerts
                            </label>
                            <label class="check-row">
                                <input type="checkbox" name="sections[]" value="vulnerabilities" checked>
                                Vulnerabilities
                            </label>
                            <label class="check-row">
                                <input type="checkbox" name="sections[]" value="sca" checked>
                                SCA checks
                            </label>
                        </div>

                        <div class="fs-11 text-muted" style="margin-bottom:14px">
                            The report covers only this endpoint and states the exact period on its cover page.
                        </div>

                        <div class="row" style="gap:6px">
                            <button type="submit" class="btn primary sm" style="flex:1;justify-content:center">
                                Download PDF
                            </button>
                            <button type="button" class="btn ghost sm" data-report-close>Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
@if ($agent)
<script>
(function () {
    new Chart(document.getElementById('availabilityChart'), {
        type: 'bar',
        data: {
            labels: @json($historyLabels),
            datasets: [{
                label: 'Heartbeats',
                data: @json($historyValues),
                backgroundColor: 'rgba(59,130,246,.55)',
                borderColor: '#3b82f6',
                borderWidth: 1,
                borderRadius: 2
            }]
        },
        options: {
            scales: {
                x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 20 } },
                y: { beginAtZero: true, grid: { color: WLC.theme().grid } }
            }
        }
    });
})();

(function () {
    var modal = document.getElementById('agentReportModal');
    if (!modal) return;

    var rangeSelect = document.getElementById('reportRange');
    var customDates = document.getElementById('reportCustomDates');

    function syncDates() {
        var custom = rangeSelect.value === 'custom';
        customDates.style.display = custom ? 'flex' : 'none';
        customDates.querySelectorAll('input').forEach(function (input) {
            input.required = custom;
        });
    }

    function open() {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        syncDates();
    }

    function close() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    document.querySelectorAll('[data-report-open]').forEach(function (btn) {
        btn.addEventListener('click', open);
    });
    document.querySelectorAll('[data-report-close]').forEach(function (btn) {
        btn.addEventListener('click', close);
    });

    rangeSelect.addEventListener('change', syncDates);
    modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

    syncDates();
})();

// ---- Software inventory: tabs + client-side filtering ---------------------
(function () {
    var tabs = document.querySelectorAll('[data-sw-tab]');
    var panels = document.querySelectorAll('[data-sw-panel]');

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('active'); });
            tab.classList.add('active');
            var name = tab.getAttribute('data-sw-tab');
            panels.forEach(function (p) {
                p.style.display = p.getAttribute('data-sw-panel') === name ? '' : 'none';
            });
        });
    });

    var search = document.getElementById('pkgSearch');
    var typeFilter = document.getElementById('pkgType');
    var rows = document.querySelectorAll('[data-pkg-row]');

    function apply() {
        var q = (search && search.value ? search.value : '').toLowerCase().trim();
        var t = typeFilter && typeFilter.value ? typeFilter.value : '';

        rows.forEach(function (row) {
            var matchesText = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
            var matchesType = !t || row.getAttribute('data-type') === t;
            row.style.display = matchesText && matchesType ? '' : 'none';
        });
    }

    if (search) search.addEventListener('input', apply);
    if (typeFilter) typeFilter.addEventListener('change', apply);
})();

// ---- Windows KB titles: fill in missing ones after the page is on screen ---
(function () {
    var button = document.getElementById('kbResolveAll');
    var table = document.getElementById('kbTable');

    if (!button || !table) {
        return;
    }

    var endpoint = button.getAttribute('data-endpoint');
    var token = document.querySelector('meta[name="csrf-token"]');
    var cachedCounter = document.getElementById('kbCachedCount');
    var search = document.getElementById('kbSearch');
    var running = false;

    function pendingRows() {
        return Array.prototype.slice.call(table.querySelectorAll('[data-kb-pending]'))
            .map(function (node) { return node.closest('[data-kb-row]'); })
            .filter(Boolean);
    }

    function applyFilter() {
        var q = (search && search.value ? search.value : '').toLowerCase().trim();

        table.querySelectorAll('[data-kb-row]').forEach(function (row) {
            var matches = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
            row.style.display = matches ? '' : 'none';
        });
    }

    function fill(row, entry) {
        if (entry.title) {
            row.setAttribute('data-search', (row.getAttribute('data-search') || '') + ' ' + entry.title + ' ' + (entry.product || ''));
        }

        var titleCell = row.querySelector('[data-kb-title]');
        if (titleCell) {
            if (entry.title) {
                titleCell.textContent = entry.title;
            } else if (entry.status === 'pending') {
                // Not looked up yet (the batch limit stopped before this row).
                titleCell.innerHTML = '<span class="text-muted" data-kb-pending>Loading title…</span>';
            } else {
                titleCell.innerHTML = '<span class="text-muted">No catalog title for this article</span>';
            }
        }

        var productCell = row.querySelector('[data-kb-product]');
        if (productCell && entry.product) {
            productCell.textContent = entry.product.length > 42
                ? entry.product.slice(0, 42) + '…'
                : entry.product;
            productCell.title = entry.product;
        }

        var dateCell = row.querySelector('[data-kb-date]');
        if (dateCell && entry.date) {
            dateCell.textContent = new Date(entry.date * 1000)
                .toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        }
    }

    function bumpCached() {
        if (!cachedCounter) return;
        var current = parseInt(cachedCounter.textContent.replace(/\D/g, ''), 10) || 0;
        cachedCounter.textContent = String(current + 1);
    }

    function batch(loop) {
        if (running) return;
        running = true;

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (payload) {
                if (!payload || !payload.titles) return;

                Object.keys(payload.titles).forEach(function (kb) {
                    var row = table.querySelector('[data-kb-row][data-kb="' + kb + '"]');
                    if (!row) return;

                    var wasPending = !!row.querySelector('[data-kb-pending]');
                    fill(row, payload.titles[kb]);
                    if (wasPending && payload.titles[kb].status !== 'pending') bumpCached();
                });

                applyFilter();

                if (loop && payload.remaining > 0 && pendingRows().length > 0) {
                    running = false;
                    batch(true);
                } else {
                    running = false;
                    if (pendingRows().length === 0) {
                        button.disabled = true;
                    }
                }
            })
            .catch(function () {
                running = false;
            });
    }

    if (search) search.addEventListener('input', applyFilter);

    button.addEventListener('click', function () {
        button.disabled = true;
        button.textContent = 'Loading…';
        batch(true);
    });

    // Nothing is fetched during render. One small batch on open covers the KBs
    // this visitor is looking at; the button drains the rest on demand.
    if (pendingRows().length > 0) {
        batch(false);
    }
})();
</script>
@endif
@endpush



