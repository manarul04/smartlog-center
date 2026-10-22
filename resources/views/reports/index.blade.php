@extends('layouts.app')

@section('title', 'Reports')
@section('subtitle', 'Central queue for incidents, vulnerabilities and SCA findings')

@section('breadcrumb')
    <x-breadcrumb :items="[['label' => 'Reports']]" />
@endsection

@section('content')
    <div class="grid grid-4" style="margin-bottom:14px">
        <a class="kpi" style="--kpi-accent:var(--accent)"
           href="{{ route('reports.index') }}">
            <span class="kpi-label">All Reports</span>
            <span class="kpi-value">{{ number_format($stats['total']) }}</span>
            <span class="kpi-meta">{{ number_format($stats['incidents']) }} incidents</span>
        </a>
        <a class="kpi" style="--kpi-accent:var(--critical)"
           href="{{ route('reports.index', ['status' => 'open_all']) }}">
            <span class="kpi-label">Open Work</span>
            <span class="kpi-value" style="color:var(--critical-text)">{{ number_format($stats['open']) }}</span>
            <span class="kpi-meta">not yet closed</span>
        </a>
        <a class="kpi" style="--kpi-accent:var(--high)"
           href="{{ route('reports.index', ['status' => 'open_all', 'assignee_id' => 'unassigned']) }}">
            <span class="kpi-label">Unassigned</span>
            <span class="kpi-value" style="color:var(--high-text)">{{ number_format($stats['unassigned']) }}</span>
            <span class="kpi-meta">needs an owner</span>
        </a>
        <a class="kpi" style="--kpi-accent:var(--success)"
           href="{{ route('reports.index', ['assignee_id' => 'me']) }}">
            <span class="kpi-label">Assigned to Me</span>
            <span class="kpi-value">{{ number_format($stats['mine']) }}</span>
            <span class="kpi-meta">your queue</span>
        </a>
    </div>

    <div class="card">
        <form method="GET" action="{{ route('reports.index') }}">
            <div class="toolbar">
                <div class="field" style="flex:1;min-width:190px">
                    <label for="q">Search</label>
                    <input id="q" name="q" class="input" value="{{ request('q') }}"
                           placeholder="reference, title, agent, rule…" data-debounce-submit>
                </div>

                <div class="field">
                    <label for="type">Type</label>
                    <select id="type" name="type" class="select" data-autosubmit>
                        <option value="">All types</option>
                        @foreach ($types as $key => $meta)
                            <option value="{{ $key }}" @selected(request('type') === $key)>{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="select" data-autosubmit>
                        <option value="">All statuses</option>
                        <option value="open_all" @selected(request('status') === 'open_all')>Open (any stage)</option>
                        @foreach ($statuses as $key => $meta)
                            <option value="{{ $key }}" @selected(request('status') === $key)>{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="assignee_id">Assignee</label>
                    <select id="assignee_id" name="assignee_id" class="select" data-autosubmit>
                        <option value="">Anyone</option>
                        <option value="me" @selected(request('assignee_id') === 'me')>Assigned to me</option>
                        <option value="unassigned" @selected(request('assignee_id') === 'unassigned')>Unassigned</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((string) request('assignee_id') === (string) $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label>&nbsp;</label>
                    <a href="{{ route('reports.index') }}" class="btn sm">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th style="width:110px">Reference</th>
                        <th style="width:110px">Type</th>
                        <th>Title</th>
                        <th style="width:120px">Status</th>
                        <th style="width:90px">Priority</th>
                        <th style="width:130px">Assignee</th>
                        <th class="num" style="width:70px">Items</th>
                        <th style="width:110px">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $report)
                        <tr class="row-clickable" data-href="{{ route('reports.show', $report) }}">
                            <td class="mono" style="color:var(--accent)">
                                <a href="{{ route('reports.show', $report) }}" class="link-strong">{{ $report->reference }}</a>
                            </td>
                            <td>
                                <span class="status-chip" style="--chip:{{ \App\Models\Ticket::typeColor($report->type) }}">
                                    {{ \App\Models\Ticket::typeLabel($report->type) }}
                                </span>
                            </td>
                            <td class="wrap">
                                <a href="{{ route('reports.show', $report) }}" class="link-strong" style="font-weight:600">
                                    {{ \Illuminate\Support\Str::limit($report->title, 90) }}
                                </a>
                                @if ($report->group_agent)
                                    @php $listAgents = $report->groupedAgents(); @endphp
                                    <div class="fs-11 text-muted">
                                        rule {{ $report->group_rule_id }} on
                                        {{ count($listAgents) > 1 ? count($listAgents) . ' agents' : ($listAgents[0] ?? '—') }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <span class="status-chip {{ \App\Models\Ticket::isUnreviewed($report->status) ? 'is-muted' : '' }}"
                                      style="--chip:{{ \App\Models\Ticket::statusColor($report->status) }}">
                                    {{ \App\Models\Ticket::statusLabel($report->status) }}
                                </span>
                            </td>
                            <td>
                                <span class="status-chip" style="--chip:{{ \App\Models\Ticket::priorityColor($report->priority) }}">
                                    {{ $priorities[$report->priority]['label'] ?? $report->priority }}
                                </span>
                            </td>
                            <td class="fs-12">{{ $report->assignee->name ?? '—' }}</td>
                            <td class="num mono">{{ number_format($report->items_count) }}</td>
                            <td class="fs-11 text-secondary nowrap">{{ $report->created_at->format('d M Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty">
                                <x-empty-icon name="clipboard" />
                                No reports yet. Report an incident from the Alert Explorer drawer,
                                or open a vulnerability finding to create one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($reports->hasPages())
            <div class="pager">
                <span>{{ number_format($reports->total()) }} reports</span>
                <div class="spacer"></div>
                {{ $reports->links('pagination.minimal') }}
            </div>
        @endif
    </div>
@endsection
