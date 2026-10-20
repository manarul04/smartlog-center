@extends('layouts.app')

@section('title', 'Alert Explorer')
@section('subtitle', 'Search and triage security events')

@section('actions')
    <x-export-button :href="route('explorer.report', collect(request()->query())->except(['sort', 'dir', 'page'])->all())"
                     title="Export these filters as a PDF report" />
@endsection

@section('content')
    @if ($error)
        <div class="banner error"><div><strong>Query failed.</strong> {{ $error }}</div></div>
    @endif

    <div class="card">
        <form method="GET" action="{{ route('explorer.index') }}">
            <div class="toolbar">
                <div class="field" style="flex:1;min-width:220px">
                    <label for="q">Search</label>
                    <input id="q" name="q" class="input" value="{{ $filters['q'] }}"
                           placeholder="rule text, command line, agent, IP, rule id…" autocomplete="off"
                           data-debounce-submit>
                </div>

                <div class="field">
                    <label for="range">Time range</label>
                    <select id="range" name="range" class="select" data-autosubmit>
                        @foreach ($ranges as $key => $meta)
                            <option value="{{ $key }}" @selected($range === $key)>{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="agent">Agent</label>
                    <select id="agent" name="agent" class="select" data-autosubmit>
                        <option value="">All agents</option>
                        @foreach ($options['agents'] as $agent)
                            <option value="{{ $agent }}" @selected($filters['agent'] === $agent)>{{ $agent }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="group">Rule group</label>
                    <select id="group" name="group" class="select" data-autosubmit>
                        <option value="">All groups</option>
                        @foreach ($options['groups'] as $group)
                            <option value="{{ $group }}" @selected($filters['group'] === $group)>{{ $group }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="level_min">Min level</label>
                    <select id="level_min" name="level_min" class="select" data-autosubmit>
                        <option value="">Any</option>
                        @for ($i = 1; $i <= 15; $i++)
                            <option value="{{ $i }}" @selected((string) $filters['level_min'] === (string) $i)>{{ $i }}+</option>
                        @endfor
                    </select>
                </div>

                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="select" data-autosubmit>
                        <option value="">Any status</option>
                        @foreach ($statuses as $key => $meta)
                            <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="label">Label</label>
                    <select id="label" name="label" class="select" data-autosubmit>
                        <option value="">Any label</option>
                        @foreach ($labels as $label)
                            <option value="{{ $label->id }}" @selected((string) $filters['label'] === (string) $label->id)>{{ $label->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Optional sorting: pick a column and a direction, or clear it
                     to fall back to newest first. --}}
                <div class="field">
                    <label for="sort">Sort by</label>
                    <select id="sort" name="sort" class="select" data-autosubmit>
                        <option value="">Newest first</option>
                        @foreach ($sortColumns as $key => $column)
                            <option value="{{ $key }}" @selected($sort === $key)>{{ $column['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="dir">Order</label>
                    <select id="dir" name="dir" class="select" data-autosubmit @disabled($sort === '')>
                        <option value="desc" @selected($dir === 'desc')>Descending</option>
                        <option value="asc" @selected($dir === 'asc')>Ascending</option>
                    </select>
                </div>

                @if ($sort !== '')
                    <div class="field">
                        <label>&nbsp;</label>
                        <a class="btn sm"
                           href="{{ route('explorer.index', array_filter(array_merge(request()->query(), ['sort' => null, 'dir' => null, 'page' => null]))) }}"
                           title="Remove sorting">Clear sort</a>
                    </div>
                @endif

                @if ($filters['mitre'])
                    <input type="hidden" name="mitre" value="{{ $filters['mitre'] }}">
                @endif
                @if ($filters['compliance_field'])
                    <input type="hidden" name="compliance_field" value="{{ $filters['compliance_field'] }}">
                    <input type="hidden" name="compliance_value" value="{{ $filters['compliance_value'] }}">
                @endif

                <div class="field">
                    <label>&nbsp;</label>
                    <a href="{{ route('explorer.index', ['range' => $range]) }}" class="btn sm">Reset</a>
                </div>
            </div>

            @if ($filters['mitre'] || $filters['compliance_field'])
                <div class="toolbar" style="border-top:1px solid var(--border);background:var(--bg-root)">
                    <span class="fs-11 text-muted">Active filters:</span>
                    @if ($filters['mitre'])
                        <span class="badge neutral">MITRE {{ $filters['mitre'] }}</span>
                    @endif
                    @if ($filters['compliance_field'])
                        <span class="badge neutral">{{ $filters['compliance_field'] }} = {{ $filters['compliance_value'] }}</span>
                    @endif
                </div>
            @endif
        </form>

        @if ($result)
            <div class="row between" style="padding:10px 16px;border-bottom:1px solid var(--border);background:var(--bg-surface-2)">
                <span class="fs-12 text-secondary">
                    <strong style="color:var(--text-primary)">{{ number_format($result['total']) }}</strong> matching events
                    @if ($result['took'])
                        <span class="text-muted fs-11">· {{ $result['took'] }} ms</span>
                    @endif
                    @if ($sort !== '')
                        <span class="badge neutral" style="margin-left:6px">
                            {{ $sortColumns[$sort]['label'] }} {{ $dir === 'asc' ? 'ascending' : 'descending' }}
                        </span>
                    @endif
                </span>
                <span class="fs-11 text-muted">Page {{ $result['page'] }} of {{ max(1, $result['pages']) }}</span>
            </div>

            @can('triage')
                <form method="POST" action="{{ route('alerts.bulk') }}" id="bulkForm">
                    @csrf
                    <input type="hidden" name="back" value="{{ request()->fullUrl() }}">

                    <div class="toolbar" id="bulkBar" style="display:none">
                        <span class="fs-12 text-secondary"><strong id="bulkCount" style="color:var(--text-primary)">0</strong> selected</span>
                        <div class="field">
                            <label for="bulkStatus">Set status</label>
                            <select name="status" id="bulkStatus" class="select" required>
                                @foreach ($statuses as $key => $meta)
                                    <option value="{{ $key }}">{{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="bulkAssignee">Assign to</label>
                            <select name="assignee_id" id="bulkAssignee" class="select">
                                <option value="">Unchanged</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn primary sm">Apply to selected</button>
                        </div>
                    </div>
            @endcan

            <div class="table-wrap">
                <table class="data">
                    <thead>
                        @php
                            // Header links toggle direction on the active column and
                            // keep every other query parameter intact.
                            $sortLink = function (string $column) use ($sort, $dir) {
                                $next = ($sort === $column && $dir === 'asc') ? 'desc' : 'asc';

                                return route('explorer.index', array_merge(request()->query(), [
                                    'sort' => $column,
                                    'dir' => $next,
                                    'page' => null,
                                ]));
                            };
                            $sortMark = fn (string $column) => $sort === $column
                                ? ($dir === 'asc' ? '↑' : '↓')
                                : '';
                        @endphp
                        <tr>
                            @can('triage')
                                <th style="width:34px">
                                    <input type="checkbox" id="selectAll" style="accent-color:var(--accent)">
                                </th>
                            @endcan
                            <th style="width:140px">
                                <a class="th-sort {{ $sort === 'time' ? 'is-active' : '' }}" href="{{ $sortLink('time') }}"
                                   title="Sort by time">Time <span class="th-sort-mark">{{ $sortMark('time') }}</span></a>
                            </th>
                            <th style="width:70px">
                                <a class="th-sort {{ $sort === 'level' ? 'is-active' : '' }}" href="{{ $sortLink('level') }}"
                                   title="Sort by level">Level <span class="th-sort-mark">{{ $sortMark('level') }}</span></a>
                            </th>
                            <th>
                                <a class="th-sort {{ $sort === 'rule' ? 'is-active' : '' }}" href="{{ $sortLink('rule') }}"
                                   title="Sort by rule">Rule <span class="th-sort-mark">{{ $sortMark('rule') }}</span></a>
                            </th>
                            <th style="width:130px">
                                <a class="th-sort {{ $sort === 'agent' ? 'is-active' : '' }}" href="{{ $sortLink('agent') }}"
                                   title="Sort by agent">Agent <span class="th-sort-mark">{{ $sortMark('agent') }}</span></a>
                            </th>
                            <th style="width:110px">Status</th>
                            <th style="width:150px">Labels</th>
                            <th style="width:32px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($result['items'] as $item)
                            @php
                                $src = $item['source'];
                                $level = (int) ($src['rule']['level'] ?? 0);
                                $token = \App\Support\Severity::token($level);
                                $ann = $item['annotation'] ?? null;
                                $statusKey = $ann->status ?? \App\Support\AlertStatus::NOT_REVIEWED;
                            @endphp
                            <tr class="row-clickable" data-alert="{{ json_encode([
                                'index' => $item['index'],
                                'id' => $item['id'],
                                'uid' => $item['uid'] ?? ($src['id'] ?? null),
                            ], JSON_HEX_APOS | JSON_HEX_QUOT) }}">
                                @can('triage')
                                    <td>
                                        <input type="checkbox" class="rowCheck" style="accent-color:var(--accent)"
                                               name="sel" data-index="{{ $item['index'] }}" data-id="{{ $item['id'] }}">
                                    </td>
                                @endcan
                                <td class="mono text-secondary nowrap" data-ts="{{ $src['timestamp'] ?? '' }}">
                                    {{ isset($src['timestamp']) ? \Illuminate\Support\Carbon::parse($src['timestamp'])->format('d M H:i:s') : '—' }}
                                </td>
                                <td>
                                    <span class="badge {{ $token }}">{{ $level }}</span>
                                </td>
                                <td class="wrap">
                                    @if (!empty($item['highlight']['rule.description'][0]))
                                        {!! $item['highlight']['rule.description'][0] !!}
                                    @else
                                        {{ \Illuminate\Support\Str::limit($src['rule']['description'] ?? '—', 110) }}
                                    @endif
                                    <div class="fs-11 text-muted" style="margin-top:2px">
                                        rule {{ $src['rule']['id'] ?? '?' }}
                                        @if (!empty($src['rule']['groups']))
                                            · {{ implode(', ', array_slice($src['rule']['groups'], 0, 3)) }}
                                        @endif
                                        @if ($ann && $ann->ticket)
                                            · <a href="{{ route('reports.show', $ann->ticket) }}" style="color:var(--accent)">{{ $ann->ticket->reference }}</a>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <a href="{{ route('explorer.index', array_merge(request()->query(), ['agent' => $src['agent']['name'] ?? '', 'page' => 1])) }}"
                                       class="link-strong">{{ $src['agent']['name'] ?? '—' }}</a>
                                    <div class="fs-11 text-muted mono">{{ $src['agent']['ip'] ?? '' }}</div>
                                </td>
                                <td>
                                    <span class="status-chip {{ \App\Support\AlertStatus::isUnreviewed($statusKey) ? 'is-muted' : '' }}"
                                          style="--chip:{{ \App\Support\AlertStatus::color($statusKey) }}">
                                        {{ \App\Support\AlertStatus::label($statusKey) }}
                                    </span>
                                </td>
                                <td>
                                    @if ($ann && $ann->labels->isNotEmpty())
                                        <div class="row wrap" style="gap:3px">
                                            @foreach ($ann->labels->take(2) as $label)
                                                <span class="label-chip" style="--chip:{{ $label->color }}">{{ $label->name }}</span>
                                            @endforeach
                                            @if ($ann->labels->count() > 2)
                                                <span class="fs-11 text-muted">+{{ $ann->labels->count() - 2 }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-muted fs-11">&mdash;</span>
                                    @endif
                                </td>
                                <td class="center">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" style="opacity:.45">
                                        <path d="M9 18l6-6-6-6"/>
                                    </svg>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="empty">
                                    <x-empty-icon name="search" />
                                    No events match the current filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="bulkItems"></div>

            @can('triage')
                </form>
            @endcan

            @if ($result['pages'] > 1)
                @php
                    $current = $result['page'];
                    $last = $result['pages'];
                    $start = max(1, $current - 2);
                    $end = min($last, $current + 2);
                    $query = request()->query();
                @endphp
                <div class="pager">
                    <span>{{ number_format($result['total']) }} results</span>
                    <div class="spacer"></div>

                    <a class="page-btn {{ $current <= 1 ? 'disabled' : '' }}"
                       href="{{ route('explorer.index', array_merge($query, ['page' => max(1, $current - 1)])) }}">‹</a>

                    @if ($start > 1)
                        <a class="page-btn" href="{{ route('explorer.index', array_merge($query, ['page' => 1])) }}">1</a>
                        @if ($start > 2)<span class="text-muted">…</span>@endif
                    @endif

                    @for ($p = $start; $p <= $end; $p++)
                        <a class="page-btn {{ $p === $current ? 'active' : '' }}"
                           href="{{ route('explorer.index', array_merge($query, ['page' => $p])) }}">{{ $p }}</a>
                    @endfor

                    @if ($end < $last)
                        @if ($end < $last - 1)<span class="text-muted">…</span>@endif
                        <a class="page-btn" href="{{ route('explorer.index', array_merge($query, ['page' => $last])) }}">{{ $last }}</a>
                    @endif

                    <a class="page-btn {{ $current >= $last ? 'disabled' : '' }}"
                       href="{{ route('explorer.index', array_merge($query, ['page' => min($last, $current + 1)])) }}">›</a>
                </div>
            @endif
        @endif
    </div>

    {{-- Alert detail off-canvas drawer --}}
    <div class="drawer-backdrop" id="alertDrawer" style="display:none">
        <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="adTitle">
            <header class="drawer-head">
                <div style="min-width:0;flex:1">
                    <div class="row" style="gap:8px">
                        <span id="adLevel" class="badge info">0</span>
                        <span class="mono fs-11 text-muted" id="adMeta">&mdash;</span>
                    </div>
                    <h3 id="adTitle" style="margin:5px 0 0;font-size:13px;font-weight:600;line-height:1.4">&mdash;</h3>
                </div>
                <button type="button" class="btn ghost sm" id="adClose" aria-label="Close">&times;</button>
            </header>

            <div class="drawer-body">
                <div id="adLoading" class="empty" style="padding:30px 10px">Loading alert&hellip;</div>
                <div id="adError" class="banner error" style="display:none"></div>

                <div id="adContent" style="display:none">
                    <div id="adFacts"></div>

                    <div class="drawer-section" id="adMessageSection" style="display:none">
                        <h4>Windows Event Message</h4>
                        <pre class="json" id="adMessage" style="max-height:220px"></pre>
                    </div>

                    <div class="drawer-section" id="adEventDataSection" style="display:none">
                        <h4>Event Data</h4>
                        <div class="detail-grid" id="adEventData"></div>
                    </div>

                    <div class="drawer-section" id="adComplianceSection" style="display:none">
                        <h4>Compliance Mapping</h4>
                        <div id="adCompliance"></div>
                    </div>

                    <div class="drawer-section">
                        <h4>Raw Event</h4>
                        <pre class="json" id="adRaw" data-json style="max-height:260px"></pre>
                    </div>

                    <div class="drawer-section" id="adActionsSection">
                        <h4>Actions</h4>

                        <div class="row wrap" style="gap:6px">
                            @can('triage')
                            <button type="button" class="btn sm" id="adToggleHandling">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                    <path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"/>
                                </svg>
                                Update handling
                            </button>
                            @endcan
                            @can('triage')
                                <button type="button" class="btn sm" id="adToggleReport">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                        <path d="M12 5v14M5 12h14"/>
                                    </svg>
                                    New report
                                </button>
                            @endcan
                            <a class="btn sm" id="adTicketLink" style="display:none" href="#">View ticket</a>
                        </div>

                        <div class="fs-12 text-secondary mt-14" id="adCurrentState"></div>
                    </div>

                    @can('triage')
                        <div class="drawer-section" id="adHandlingSection" style="display:none">
                            <h4>Handling</h4>
                            <form method="POST" action="{{ route('alerts.update') }}" id="adHandleForm">
                                @csrf
                                <input type="hidden" name="index" id="adIndex">
                                <input type="hidden" name="id" id="adId">

                                <div class="form-row">
                                    <label for="adStatus">Status</label>
                                    <select id="adStatus" name="status" class="select" required></select>
                                </div>

                                <div class="form-row">
                                    <label for="adAssignee">Assignee</label>
                                    <select id="adAssignee" name="assignee_id" class="select"></select>
                                </div>

                                <div class="form-row">
                                    <label>Labels</label>
                                    <div class="label-picker" id="adLabels"></div>
                                </div>

                                <div class="form-row">
                                    <label for="adComment">Add note</label>
                                    <textarea id="adComment" name="comment" class="input" rows="2"
                                              placeholder="What did you find? (optional)"></textarea>
                                </div>

                                <div class="row" style="gap:6px">
                                    <button type="submit" class="btn primary sm" style="flex:1;justify-content:center">
                                        Save handling
                                    </button>
                                    <button type="button" class="btn ghost sm" data-close-section="adHandlingSection">Cancel</button>
                                </div>
                            </form>
                        </div>

                        <div class="drawer-section" id="adReportSection" style="display:none">
                            <h4>New Report</h4>
                            <form method="POST" action="{{ route('reports.from-alert') }}" id="adIncidentForm">
                                @csrf
                                <input type="hidden" name="index" id="adRIndex">
                                <input type="hidden" name="id" id="adRId">

                                <div class="form-row">
                                    <label for="adRTitle">Title</label>
                                    <input id="adRTitle" name="title" class="input" maxlength="200">
                                </div>

                                <div class="form-row">
                                    <label for="adRDesc">Description</label>
                                    <textarea id="adRDesc" name="description" class="input" rows="2"
                                              placeholder="Initial findings, scope..."></textarea>
                                </div>

                                <div class="form-row">
                                    <label for="adRPriority">Priority</label>
                                    <select id="adRPriority" name="priority" class="select" required>
                                        @foreach (\App\Models\Ticket::PRIORITIES as $key => $meta)
                                            <option value="{{ $key }}">{{ $meta['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-row">
                                    <label for="adRAssignee">Assign to</label>
                                    <select id="adRAssignee" name="assignee_id" class="select">
                                        <option value="">Unassigned</option>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-row">
                                    <label for="adRFrom">Count alerts from <span class="text-muted" style="font-weight:400;text-transform:none">(optional)</span></label>
                                    <input id="adRFrom" name="window_from" type="date" class="input">
                                    <input id="adRTo" name="window_to" type="date" class="input" style="margin-top:6px"
                                           aria-label="Count alerts until (optional)">
                                    <span class="fs-11 text-muted">
                                        Leave both empty to count from this alert's timestamp with no end.
                                        Fill "to" to freeze the window at a fixed period.
                                    </span>
                                </div>

                                <div class="form-row">
                                    <label>Agents <span class="text-muted" style="font-weight:400;text-transform:none">(optional)</span></label>
                                    <div class="label-picker" id="adRAgents">
                                        @foreach ($options['agents'] as $agentName)
                                            <label class="label-option" style="--chip:var(--accent)">
                                                <input type="checkbox" name="agents[]" value="{{ $agentName }}">
                                                <span>{{ $agentName }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <span class="fs-11 text-muted">
                                        Leave empty to count only this alert's agent. Select more to track
                                        the same rule across several endpoints.
                                    </span>
                                </div>

                                <div class="row" style="gap:6px">
                                    <button type="submit" class="btn primary sm" style="flex:1;justify-content:center">
                                        Create ticket
                                    </button>
                                    <button type="button" class="btn ghost sm" data-close-section="adReportSection">Cancel</button>
                                </div>
                            </form>
                        </div>
                    @endcan


                    <div class="drawer-section" id="adNotesSection" style="display:none">
                        <h4>Notes</h4>
                        <div class="timeline" id="adNotes"></div>
                    </div>
                </div>
            </div>
        </aside>
    </div>
@endsection


@push('scripts')
<script>
(function () {
    var drawer = document.getElementById('alertDrawer');
    if (!drawer) return;

    var elLoading = document.getElementById('adLoading');
    var elError = document.getElementById('adError');
    var elContent = document.getElementById('adContent');
    var elLevel = document.getElementById('adLevel');
    var elMeta = document.getElementById('adMeta');
    var elTitle = document.getElementById('adTitle');
    var elFacts = document.getElementById('adFacts');
    var elRaw = document.getElementById('adRaw');
    var current = null;

    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fact(k, v) {
        if (v === null || v === undefined || v === '') return '';
        return '<div class="k">' + esc(k) + '</div><div class="v">' + esc(v) + '</div>';
    }

    function levelClass(level) {
        level = Number(level) || 0;
        if (level >= 12) return 'critical';
        if (level >= 10) return 'high';
        if (level >= 7) return 'medium';
        if (level >= 4) return 'low';
        return 'info';
    }

    function fillSelect(el, options, selected, placeholder) {
        el.innerHTML = '';
        if (placeholder !== null) {
            var opt = document.createElement('option');
            opt.value = '';
            opt.textContent = placeholder;
            el.appendChild(opt);
        }
        options.forEach(function (o) {
            var opt = document.createElement('option');
            opt.value = o.value;
            opt.textContent = o.label;
            if (String(o.value) === String(selected)) opt.selected = true;
            el.appendChild(opt);
        });
    }

    function render(data) {
        var src = data.source || {};
        var rule = src.rule || {};
        var agent = src.agent || {};
        var level = Number(rule.level || 0);

        elLevel.className = 'badge ' + levelClass(level);
        elLevel.textContent = level;
        elMeta.textContent = (src.timestamp || '') +
            (agent.name ? ' · ' + agent.name : '');
        elTitle.textContent = rule.description || 'Alert';

        elFacts.innerHTML = '<div class="detail-grid">' +
            fact('Time', src.timestamp) +
            fact('Rule', (rule.id || '?') + (rule.firedtimes ? ' · fired ' + rule.firedtimes + 'x' : '')) +
            fact('Groups', (rule.groups || []).join(', ')) +
            fact('Agent', (agent.name || '—') + (agent.ip ? ' (' + agent.ip + ')' : '')) +
            fact('Decoder', (src.decoder || {}).name) +
            fact('Location', src.location) +
            fact('Manager', (src.manager || {}).name) +
            '</div>';

        // Windows event message
        var msg = ((src.data || {}).win || {}).system || {};
        var msgText = msg.message;
        var msgSection = document.getElementById('adMessageSection');
        if (msgText) {
            document.getElementById('adMessage').textContent = msgText;
            msgSection.style.display = '';
        } else {
            msgSection.style.display = 'none';
        }

        // Windows event data
        var eventData = ((src.data || {}).win || {}).eventdata;
        var edSection = document.getElementById('adEventDataSection');
        var edHost = document.getElementById('adEventData');
        if (eventData && Object.keys(eventData).length) {
            var html = '';
            Object.keys(eventData).forEach(function (k) {
                var v = eventData[k];
                html += fact(k, typeof v === 'object' ? JSON.stringify(v) : v);
            });
            edHost.innerHTML = html;
            edSection.style.display = '';
        } else {
            edSection.style.display = 'none';
        }

        // Compliance mapping
        var comp = data.compliance || {};
        var compSection = document.getElementById('adComplianceSection');
        var compHost = document.getElementById('adCompliance');
        var compHtml = '';
        Object.keys(comp).forEach(function (fw) {
            var controls = comp[fw] || [];
            if (!controls.length) return;
            compHtml += '<div style="margin-bottom:10px"><div class="fs-11 text-muted" style="margin-bottom:4px">' +
                esc(fw) + '</div><div class="row wrap" style="gap:4px">';
            controls.forEach(function (c) {
                compHtml += '<span class="badge neutral mono">' + esc(c) + '</span>';
            });
            compHtml += '</div></div>';
        });
        if (compHtml) {
            compHost.innerHTML = compHtml;
            compSection.style.display = '';
        } else {
            compSection.style.display = 'none';
        }

        // Raw JSON with syntax highlighting
        var pretty = JSON.stringify(src, null, 2);
        elRaw.textContent = pretty;
        elRaw.innerHTML = pretty.replace(
            /("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g,
            function (match) {
                var cls = 'n';
                if (/^"/.test(match)) cls = /:$/.test(match) ? 'k' : 's';
                else if (/true|false|null/.test(match)) cls = 'b';
                return '<span class="' + cls + '">' + match + '</span>';
            }
        );

        var opts = data.options || {};
        window.__adStatuses = opts.statuses || [];
        var ann = data.annotation;

        if (opts.can_triage) {
            document.getElementById('adIndex').value = data.index;
            document.getElementById('adId').value = data.id;
            document.getElementById('adRIndex').value = data.index;
            document.getElementById('adRId').value = data.id;

            fillSelect(document.getElementById('adStatus'),
                (opts.statuses || []).map(function (s) { return { value: s.value, label: s.label }; }),
                ann ? ann.status : 'not_reviewed', null);

            fillSelect(document.getElementById('adAssignee'),
                (opts.users || []).map(function (u) { return { value: u.id, label: u.name }; }),
                ann ? ann.assignee_id : '', 'Unassigned');


            var labelHost = document.getElementById('adLabels');
            labelHost.innerHTML = '';
            var selected = ann ? ann.labels.map(function (l) { return String(l.id); }) : [];
            (opts.labels || []).forEach(function (l) {
                var wrap = document.createElement('label');
                wrap.className = 'label-option';
                wrap.style.setProperty('--chip', l.color);
                var input = document.createElement('input');
                input.type = 'checkbox';
                input.name = 'label_ids[]';
                input.value = l.id;
                if (selected.indexOf(String(l.id)) !== -1) input.checked = true;
                var span = document.createElement('span');
                span.textContent = l.name;
                wrap.appendChild(input);
                wrap.appendChild(span);
                labelHost.appendChild(wrap);
            });

            document.getElementById('adRTitle').value = (rule.description || '').slice(0, 200);
            document.getElementById('adRFrom').value = '';
            document.getElementById('adRTo').value = '';

            renderAgentPicker(opts.agents || [], agent.name);
        }

        // Notes timeline
        var notesSection = document.getElementById('adNotesSection');
        var notesHost = document.getElementById('adNotes');
        if (ann && ann.comments && ann.comments.length) {
            notesHost.innerHTML = ann.comments.map(function (c) {
                return '<div class="timeline-item"><div class="timeline-meta">' +
                    esc(c.user) + ' · ' + esc(c.at) + '</div><div class="timeline-body">' +
                    esc(c.body) + '</div></div>';
            }).join('');
            notesSection.style.display = '';
        } else {
            notesSection.style.display = 'none';
        }

        // Compact current state, so the drawer stays clean until the user
        // explicitly opens a form.
        var stateHost = document.getElementById('adCurrentState');
        var parts = [];
        parts.push('Status: ' + (ann ? statusLabel(ann.status) : 'Not Reviewed'));
        parts.push('Assignee: ' + (ann && ann.assignee ? ann.assignee : 'Unassigned'));
        if (ann && ann.labels && ann.labels.length) {
            parts.push('Labels: ' + ann.labels.map(function (l) { return l.name; }).join(', '));
        }
        stateHost.textContent = parts.join('  ·  ');

        var ticketLink = document.getElementById('adTicketLink');
        if (ann && ann.ticket) {
            ticketLink.style.display = '';
            ticketLink.href = '/reports/' + ann.ticket.id;
            ticketLink.textContent = 'View ' + ann.ticket.reference;
        } else {
            ticketLink.style.display = 'none';
        }

        // Forms start collapsed.
        document.getElementById('adHandlingSection').style.display = 'none';
        document.getElementById('adReportSection').style.display = 'none';

        elLoading.style.display = 'none';
        elError.style.display = 'none';
        elContent.style.display = '';
    }

    function statusLabel(value) {
        var found = (window.__adStatuses || []).filter(function (s) { return s.value === value; })[0];
        return found ? found.label : 'Not Reviewed';
    }

    /**
     * Optional multi-agent picker for the report form. The alert's own agent is
     * checked by default and is always present, even when it had no other
     * activity in the cached option list.
     */
    function renderAgentPicker(agents, currentAgent) {
        var host = document.getElementById('adRAgents');
        if (!host) return;

        host.innerHTML = '';

        var names = agents.slice();
        if (currentAgent && names.indexOf(currentAgent) === -1) names.unshift(currentAgent);

        names.forEach(function (name) {
            var wrap = document.createElement('label');
            wrap.className = 'label-option';
            wrap.style.setProperty('--chip', 'var(--accent)');

            var input = document.createElement('input');
            input.type = 'checkbox';
            input.name = 'agents[]';
            input.value = name;
            input.checked = name === currentAgent;

            var span = document.createElement('span');
            span.textContent = name;

            wrap.appendChild(input);
            wrap.appendChild(span);
            host.appendChild(wrap);
        });
    }

    function open(index, id) {
        drawer.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        elLoading.style.display = '';
        elError.style.display = 'none';
        elContent.style.display = 'none';
        current = { index: index, id: id };

        var url = new URL('{{ route('explorer.detail') }}', window.location.origin);
        url.searchParams.set('index', index);
        url.searchParams.set('id', id);

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
            .then(function (res) {
                if (!res.ok || !res.body.ok) {
                    elLoading.style.display = 'none';
                    elError.style.display = '';
                    elError.textContent = (res.body && res.body.error) || 'Unable to load alert.';
                    return;
                }
                render(res.body);
            })
            .catch(function () {
                elLoading.style.display = 'none';
                elError.style.display = '';
                elError.textContent = 'Network error while loading the alert.';
            });
    }

    function close() {
        drawer.style.display = 'none';
        document.body.style.overflow = '';
        if (current) {
            var params = new URLSearchParams(window.location.search);
            params.delete('alert');
            var qs = params.toString();
            history.replaceState({}, '', window.location.pathname + (qs ? '?' + qs : ''));
        }
        current = null;
    }

    document.querySelectorAll('[data-alert]').forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (e.target.closest('a, input, button, select')) return;
            var d = JSON.parse(row.getAttribute('data-alert'));
            open(d.index, d.id);
        });
    });

    // Toggle the optional forms; only one open at a time keeps the drawer short.
    function toggleSection(id) {
        var target = document.getElementById(id);
        var others = ['adHandlingSection', 'adReportSection'].filter(function (x) { return x !== id; });
        others.forEach(function (x) {
            var el = document.getElementById(x);
            if (el) el.style.display = 'none';
        });
        target.style.display = target.style.display === 'none' ? '' : 'none';
        if (target.style.display !== 'none') {
            target.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    var btnHandling = document.getElementById('adToggleHandling');
    var btnReport = document.getElementById('adToggleReport');
    if (btnHandling) btnHandling.addEventListener('click', function () { toggleSection('adHandlingSection'); });
    if (btnReport) btnReport.addEventListener('click', function () { toggleSection('adReportSection'); });

    document.querySelectorAll('[data-close-section]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById(btn.getAttribute('data-close-section')).style.display = 'none';
        });
    });

    document.getElementById('adClose').addEventListener('click', close);
    drawer.addEventListener('click', function (e) { if (e.target === drawer) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

    // Deep link: ?alert=<uid> opens the drawer for the matching row on this page.
    var wanted = new URLSearchParams(window.location.search).get('alert');
    if (wanted) {
        var rows = document.querySelectorAll('[data-alert]');
        for (var i = 0; i < rows.length; i++) {
            var d = JSON.parse(rows[i].getAttribute('data-alert'));
            if (d.uid === wanted) { open(d.index, d.id); break; }
        }
    }
})();
</script>
@endpush
