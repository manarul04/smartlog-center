@extends('layouts.app')

@section('title', 'Alert Groups')
@section('subtitle', 'Grouped by agent, then by rule')

@section('breadcrumb')
    <x-breadcrumb :items="[['label' => 'Alert Groups']]" />
@endsection

@section('content')
    @if ($error)
        <div class="banner error"><div><strong>Query failed.</strong> {{ $error }}</div></div>
    @endif

    <div class="card">
        <form method="GET" action="{{ route('alert-groups.index') }}">
            <div class="toolbar">
                <div class="field" style="flex:1;min-width:200px">
                    <label for="q">Search rule text</label>
                    <input id="q" name="q" class="input" value="{{ $filters['q'] }}"
                           placeholder="rule text, command line…" autocomplete="off" data-debounce-submit>
                </div>

                <div class="field">
                    <label for="range">Time range</label>
                    <select id="range" name="range" class="select" data-autosubmit>
                        @foreach ($ranges as $key => $meta)
                            <option value="{{ $key }}" @selected($range['key'] === $key)>{{ $meta['label'] }}</option>
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
                    <span class="field-label-spacer" aria-hidden="true">&nbsp;</span>
                    <a href="{{ route('alert-groups.index') }}" class="btn sm">Reset</a>
                </div>
            </div>
        </form>

        @if ($tree === null)
            <div class="empty" style="padding:30px">The tree could not be built.</div>
        @elseif ($tree['groups'] === [])
            <div class="empty" style="padding:30px">
                No alerts in {{ strtolower($range['label']) }} for these filters.
            </div>
        @else
            <div class="group-summary">
                <span>{{ count($tree['groups']) }} agents</span>
                <span>{{ number_format($tree['total_pairs']) }} agent/rule groups</span>
                <span>{{ number_format(array_sum(array_column($tree['groups'], 'count'))) }} alerts</span>
                <span class="text-muted">{{ $range['label'] }}</span>
                @if ($tree['truncated'])
                    <span class="text-muted">Showing the busiest 2.000 groups; narrow the range to see the rest.</span>
                @endif
            </div>

            <div class="table-wrap">
                <table class="data tree-table">
                    <thead>
                        <tr>
                            <th>Agent / Rule</th>
                            <th class="num" style="width:110px">Alerts</th>
                            <th style="width:130px">Max level</th>
                            <th style="width:150px">Level mix</th>
                            <th style="width:150px">Last seen</th>
                            <th style="width:110px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tree['groups'] as $agent)
                            <tr class="tree-agent" data-toggle="agent-{{ $loop->index }}">
                                <td>
                                    <span class="tree-caret">▸</span>
                                    <strong>{{ $agent['agent'] }}</strong>
                                    <span class="fs-11 text-muted">· {{ $agent['rules'] }} rules</span>
                                </td>
                                <td class="num mono">{{ number_format($agent['count']) }}</td>
                                <td>
                                    <span class="badge {{ \App\Support\Severity::token($agent['max_level']) }}">{{ $agent['max_level'] }}</span>
                                </td>
                                <td class="level-mix">
                                    @foreach (array_slice($agent['levels'], 0, 3) as $level)
                                        <span class="level-pill">{{ $level['key'] }}:{{ number_format($level['count']) }}</span>
                                    @endforeach
                                </td>
                                <td class="fs-11 text-secondary">
                                    {{ $agent['last_seen'] ? \Illuminate\Support\Carbon::parse($agent['last_seen'])->diffForHumans() : '—' }}
                                </td>
                                <td></td>
                            </tr>

                            @foreach ($agent['rule_rows'] as $rule)
                                <tr class="tree-rule" data-parent="agent-{{ $loop->parent->index }}"
                                    data-agent="{{ $agent['agent'] }}"
                                    data-rule="{{ $rule['rule_id'] }}"
                                    data-description="{{ $rule['rule_description'] }}"
                                    data-level="{{ $rule['max_level'] }}"
                                    data-count="{{ $rule['count'] }}"
                                    style="display:none">
                                    <td>
                                        <span class="tree-indent"></span>
                                        <span class="tree-caret">▸</span>
                                        <span class="mono fs-11 text-secondary">rule {{ $rule['rule_id'] }}</span>
                                        <span class="tree-rule-title">{{ $rule['rule_description'] ?: 'Unknown rule' }}</span>
                                        @if (!empty($rule['rule_groups']))
                                            <span class="fs-11 tree-rule-meta">· {{ implode(', ', array_slice($rule['rule_groups'], 0, 3)) }}</span>
                                        @endif
                                    </td>
                                    <td class="num mono">{{ number_format($rule['count']) }}</td>
                                    <td>
                                        <span class="badge {{ \App\Support\Severity::token($rule['max_level']) }}">{{ $rule['max_level'] }}</span>
                                    </td>
                                    <td class="level-mix">
                                        @foreach (array_slice($rule['levels'], 0, 3) as $level)
                                            <span class="level-pill">{{ $level['key'] }}:{{ number_format($level['count']) }}</span>
                                        @endforeach
                                    </td>
                                    <td class="fs-11 text-secondary">
                                        {{ $rule['last_seen'] ? \Illuminate\Support\Carbon::parse($rule['last_seen'])->diffForHumans() : '—' }}
                                    </td>
                                    <td class="nowrap">
                                        @can('triage')
                                            <button type="button" class="btn sm"
                                                    data-report
                                                    data-agent="{{ $agent['agent'] }}"
                                                    data-rule="{{ $rule['rule_id'] }}"
                                                    data-description="{{ $rule['rule_description'] }}"
                                                    data-level="{{ $rule['max_level'] }}"
                                                    data-count="{{ $rule['count'] }}">Report</button>
                                        @endcan
                                    </td>
                                </tr>

                                <tr class="tree-alerts" data-parent-rule="{{ $agent['agent'] }}|{{ $rule['rule_id'] }}" style="display:none">
                                    <td colspan="6">
                                        <div class="tree-alert-body" data-alerts-for="{{ $agent['agent'] }}|{{ $rule['rule_id'] }}">
                                            <div class="fs-11 text-secondary">Loading…</div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @include('partials.alert-drawer', ['users' => $users, 'options' => ['agents' => $options['agents'] ?? []]])

    @can('triage')
        <div class="modal-backdrop" id="reportModal" style="display:none">
            <div class="modal">
                <form method="POST" action="{{ route('reports.from-group') }}" id="reportForm">
                    @csrf
                    <input type="hidden" name="agent" id="rpAgent">
                    <input type="hidden" name="rule_id" id="rpRule">
                    <input type="hidden" name="rule_description" id="rpRuleDescription">
                    <input type="hidden" name="level" id="rpLevel">
                    <input type="hidden" name="range" value="{{ $range['key'] }}">

                    <div class="modal-head">
                        <div>
                            <div class="fs-11 text-muted mono">Report rule group</div>
                            <h3 id="rpHeading" style="margin:2px 0 0">—</h3>
                        </div>
                        <div class="spacer"></div>
                        <button type="button" class="btn ghost sm" data-close-dialog aria-label="Close">&times;</button>
                    </div>

                    <div class="modal-body">
                        <div class="review-meta" style="margin-bottom:14px">
                            <div class="row between">
                                <span>Agent</span>
                                <span class="mono" id="rpAgentLabel" style="color:var(--text-primary)"></span>
                            </div>
                            <div class="row between">
                                <span>Rule</span>
                                <span class="mono" id="rpRuleLabel" style="color:var(--text-primary)"></span>
                            </div>
                            <div class="row between">
                                <span>Alerts in window</span>
                                <span class="mono" id="rpCountLabel" style="color:var(--text-primary)"></span>
                            </div>
                            <div class="row between">
                                <span>Window</span>
                                <span class="mono" style="color:var(--text-primary)">{{ $range['label'] }}</span>
                            </div>
                        </div>

                        <div class="form-row">
                            <label for="rpTitle">Title</label>
                            <input id="rpTitle" name="title" class="input" maxlength="200">
                        </div>

                        <div class="form-row">
                            <label for="rpDescription">Context</label>
                            <textarea id="rpDescription" name="description" class="input" rows="3"
                                      placeholder="What was observed, what to check first…"></textarea>
                        </div>

                        <div class="form-row">
                            <label for="rpPriority">Priority</label>
                            <select id="rpPriority" name="priority" class="select" required>
                                @foreach ($priorities as $key => $meta)
                                    <option value="{{ $key }}" @selected($key === 'high')>{{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-row" style="margin-bottom:0">
                            <label for="rpAssignee">Assignee</label>
                            <select id="rpAssignee" name="assignee_id" class="select">
                                <option value="">Unassigned</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <p class="fs-11 text-secondary" style="margin:14px 0 0">
                            The report stores the grouping key, not the alerts: the total is counted
                            live from OpenSearch for this agent, rule and window.
                        </p>
                    </div>

                    <div class="modal-foot">
                        <button type="submit" class="btn primary">Create report</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    var rangeKey = @json($range['key']);

    // ---- Agent rows expand their rules ------------------------------------
    document.querySelectorAll('.tree-agent').forEach(function (row) {
        row.addEventListener('click', function () {
            var key = row.getAttribute('data-toggle');
            var open = row.classList.toggle('is-open');
            row.querySelector('.tree-caret').textContent = open ? '▾' : '▸';

            document.querySelectorAll('[data-parent="' + key + '"]').forEach(function (rule) {
                rule.style.display = open ? '' : 'none';
                if (!open) {
                    collapseRule(rule);
                }
            });
        });
    });

    // ---- Rule rows expand their alerts ------------------------------------
    document.querySelectorAll('.tree-rule').forEach(function (row) {
        row.addEventListener('click', function (event) {
            if (event.target.closest('[data-report]')) {
                return;
            }

            var key = row.getAttribute('data-agent') + '|' + row.getAttribute('data-rule');
            var open = row.classList.toggle('is-open');
            row.querySelector('.tree-caret').textContent = open ? '▾' : '▸';

            var panel = document.querySelector('[data-parent-rule="' + key + '"]');
            if (!panel) return;

            panel.style.display = open ? '' : 'none';

            if (open) {
                loadAlerts(row, panel);
            }
        });
    });

    function collapseRule(rule) {
        rule.classList.remove('is-open');
        rule.querySelector('.tree-caret').textContent = '▸';

        var key = rule.getAttribute('data-agent') + '|' + rule.getAttribute('data-rule');
        var panel = document.querySelector('[data-parent-rule="' + key + '"]');
        if (panel) {
            panel.style.display = 'none';
        }
    }

    function loadAlerts(row, panel) {
        var body = panel.querySelector('[data-alerts-for]');

        if (body.getAttribute('data-loaded') === '1') {
            return;
        }

        var url = new URL(@json(route('alert-groups.alerts')), window.location.origin);
        url.searchParams.set('agent', row.getAttribute('data-agent'));
        url.searchParams.set('rule_id', row.getAttribute('data-rule'));
        url.searchParams.set('range', rangeKey);
        url.searchParams.set('limit', '25');

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    body.innerHTML = '<div class="fs-11 t-critical">' + escapeHtml(data.error || 'Could not load alerts.') + '</div>';
                    return;
                }

                if (data.items.length === 0) {
                    body.innerHTML = '<div class="fs-11 text-secondary">No alerts left in this window.</div>';
                    body.setAttribute('data-loaded', '1');
                    return;
                }

                var html = '<table class="data tree-alert-list"><tbody>';

                data.items.forEach(function (item) {
                    var ts = item.timestamp ? new Date(item.timestamp) : null;
                    var when = ts ? ts.toLocaleString([], { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '—';

                    html += '<tr class="alert-row" data-index="' + escapeHtml(item.index || '') + '"'
                        + ' data-id="' + escapeHtml(item.id || '') + '" tabindex="0" role="button"'
                        + ' aria-label="Open alert detail">'
                        + '<td class="mono fs-11 text-secondary nowrap" style="width:150px">' + escapeHtml(when) + '</td>'
                        + '<td style="width:60px"><span class="badge ' + levelToken(item.level) + '">' + item.level + '</span></td>'
                        + '<td class="wrap">' + escapeHtml(item.description || '—')
                        + '<div class="fs-11 text-muted">' + escapeHtml(item.decoder || '') + (item.location ? ' · ' + escapeHtml(item.location) : '') + '</div>'
                        + '</td>'
                        + '</tr>';
                });

                html += '</tbody></table>';
                body.innerHTML = html;
                body.setAttribute('data-loaded', '1');
            })
            .catch(function () {
                body.innerHTML = '<div class="fs-11 t-critical">Could not load alerts. Check the connection.</div>';
            });
    }

    function levelToken(level) {
        if (level >= 12) return 'critical';
        if (level >= 10) return 'high';
        if (level >= 7) return 'medium';
        if (level >= 4) return 'low';
        return 'info';
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ---- Report dialog ----------------------------------------------------
    var modal = document.getElementById('reportModal');
    var form = document.getElementById('reportForm');

    function openModal() {
        if (modal) modal.style.display = 'flex';
    }

    function closeModal() {
        if (modal) modal.style.display = 'none';
    }

    document.querySelectorAll('[data-report]').forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            event.stopPropagation();
            if (!modal || !form) return;

            var agent = btn.getAttribute('data-agent');
            var rule = btn.getAttribute('data-rule');
            var description = btn.getAttribute('data-description') || '';
            var count = btn.getAttribute('data-count') || '0';

            form.querySelector('#rpAgent').value = agent;
            form.querySelector('#rpRule').value = rule;
            form.querySelector('#rpRuleDescription').value = description;
            form.querySelector('#rpLevel').value = btn.getAttribute('data-level') || '';

            form.querySelector('#rpHeading').textContent = description || ('Rule ' + rule);
            form.querySelector('#rpAgentLabel').textContent = agent;
            form.querySelector('#rpRuleLabel').textContent = 'rule ' + rule;
            form.querySelector('#rpCountLabel').textContent = Number(count).toLocaleString();
            form.querySelector('#rpTitle').value = description || ('Rule ' + rule + ' on ' + agent);

            openModal();
        });
    });

    document.querySelectorAll('[data-close-dialog]').forEach(function (btn) {
        btn.addEventListener('click', closeModal);
    });

    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal && modal.style.display !== 'none') {
            closeModal();
        }
    });

    // ---- Alert rows open the shared detail drawer -------------------------
    document.addEventListener('click', function (event) {
        var row = event.target.closest('.alert-row');
        if (!row || !window.AlertDrawer) return;

        window.AlertDrawer.open(row.getAttribute('data-index'), row.getAttribute('data-id'));
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' && event.key !== ' ') return;

        var row = event.target.closest && event.target.closest('.alert-row');
        if (!row || !window.AlertDrawer) return;

        event.preventDefault();
        window.AlertDrawer.open(row.getAttribute('data-index'), row.getAttribute('data-id'));
    });
})();
</script>
@include('partials.alert-drawer-script')
@endpush
