@extends('layouts.app')

@section('title', $report->reference . ' · ' . $report->title)
@section('subtitle', \App\Models\Ticket::typeLabel($report->type) . ' report')

@section('actions')
    <a href="{{ route('reports.index') }}" class="btn sm">&larr; Reports</a>
@endsection

@section('content')
    <div class="grid grid-3-2">
        <div class="col">
            @if ($group && !isset($group['error']))
                <div class="card">
                    <div class="card-head">
                        <h3>Tracked Alert Group</h3>
                        <div class="spacer"></div>
                        <span class="sub">
                            rule {{ $report->group_rule_id }} &middot;
                            @php $trackedAgents = $report->groupedAgents(); @endphp
                            @if (count($trackedAgents) > 1)
                                {{ count($trackedAgents) }} agents
                            @else
                                {{ $trackedAgents[0] ?? '—' }}
                            @endif
                        </span>
                        @can('triage')
                            <form method="POST" action="{{ route('reports.refresh', $report) }}" style="display:inline">
                                @csrf
                                <button class="btn ghost sm" type="submit">Refresh</button>
                            </form>
                        @endcan
                    </div>
                    <div class="card-body">
                        <div class="row wrap" style="gap:28px;align-items:flex-start">
                            <div class="col" style="gap:2px">
                                <span class="fs-11 text-muted" style="text-transform:uppercase;letter-spacing:.05em">Total alerts</span>
                                <span style="font-size:32px;font-weight:680;letter-spacing:-.03em;color:var(--critical-text)">
                                    {{ number_format($group['total']) }}
                                </span>
                                <span class="fs-11 text-muted">live count from OpenSearch</span>
                            </div>
                            <div class="col" style="gap:2px">
                                <span class="fs-11 text-muted" style="text-transform:uppercase;letter-spacing:.05em">First seen</span>
                                <span class="fs-12">{{ $group['first_seen'] ? \Illuminate\Support\Carbon::parse($group['first_seen'])->format('d M Y H:i') : '—' }}</span>
                                <span class="fs-11 text-muted" style="text-transform:uppercase;letter-spacing:.05em;margin-top:8px">Last seen</span>
                                <span class="fs-12">{{ $group['last_seen'] ? \Illuminate\Support\Carbon::parse($group['last_seen'])->diffForHumans() : '—' }}</span>
                            </div>
                            <div class="col" style="gap:2px">
                                <span class="fs-11 text-muted" style="text-transform:uppercase;letter-spacing:.05em">Window</span>
                                <span class="fs-12">{{ $report->group_window_from?->format('d M Y H:i') ?? '—' }}</span>
                                <span class="fs-11 text-muted" style="text-transform:uppercase;letter-spacing:.05em;margin-top:8px">Until</span>
                                <span class="fs-12">{{ $report->group_window_to?->format('d M Y H:i') ?? 'live' }}</span>
                                <span class="fs-11 text-muted" style="text-transform:uppercase;letter-spacing:.05em;margin-top:8px">Counted</span>
                                <span class="fs-12">{{ $report->group_counted_at?->diffForHumans() ?? '—' }}</span>
                            </div>
                        </div>

                        @if (count($trackedAgents) > 1)
                            <div class="mt-14">
                                <div class="fs-11 text-muted" style="text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px">Tracked agents</div>
                                <div class="row wrap" style="gap:4px">
                                    @foreach ($trackedAgents as $trackedAgent)
                                        <span class="badge neutral mono">{{ $trackedAgent }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if (!empty($group['levels']))
                            <div class="mt-14">
                                @php $maxLvl = collect($group['levels'])->max('count') ?: 1; @endphp
                                @foreach ($group['levels'] as $lvl)
                                    <div class="row" style="margin-bottom:7px">
                                        <span class="badge {{ \App\Support\Severity::token($lvl['key']) }}" style="width:28px;justify-content:center">{{ $lvl['key'] }}</span>
                                        <div class="bar-track" style="flex:1">
                                            <div class="bar-fill" style="width:{{ ($lvl['count'] / $maxLvl) * 100 }}%;background:{{ \App\Support\Severity::color($lvl['key']) }}"></div>
                                        </div>
                                        <span class="mono fs-11 text-secondary" style="width:70px;text-align:right">{{ number_format($lvl['count']) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @elseif ($group && isset($group['error']))
                <div class="banner warn">
                    <div><strong>Live alert count unavailable.</strong> {{ $group['error'] }}</div>
                </div>
            @endif

            <div class="card">
                <div class="card-head">
                    <h3>Details</h3>
                    <div class="spacer"></div>
                    <span class="status-chip" style="--chip:{{ \App\Models\Ticket::typeColor($report->type) }}">
                        {{ \App\Models\Ticket::typeLabel($report->type) }}
                    </span>
                    <span class="status-chip {{ \App\Models\Ticket::isUnreviewed($report->status) ? 'is-muted' : '' }}"
                          style="--chip:{{ \App\Models\Ticket::statusColor($report->status) }}">
                        {{ \App\Models\Ticket::statusLabel($report->status) }}
                    </span>
                </div>
                <div class="card-body">
                    <div style="font-size:16px;font-weight:650;margin-bottom:10px">{{ $report->title }}</div>
                    @if ($report->description)
                        <p class="fs-12 text-secondary" style="white-space:pre-wrap;margin-top:0">{{ $report->description }}</p>
                    @endif

                    <div class="detail-grid">
                        <div class="k">Reference</div><div class="v">{{ $report->reference }}</div>
                        <div class="k">Priority</div><div class="v">{{ $priorities[$report->priority]['label'] ?? $report->priority }}</div>
                        @if ($report->severity)
                            <div class="k">Severity</div><div class="v">{{ ucfirst($report->severity) }}</div>
                        @endif
                        <div class="k">Assignee</div><div class="v">{{ $report->assignee->name ?? 'Unassigned' }}</div>
                        <div class="k">Created</div><div class="v">{{ $report->created_at->format('d M Y H:i') }} by {{ $report->creator->name ?? 'system' }}</div>
                        <div class="k">Due</div><div class="v">{{ $report->due_at?->format('d M Y') ?? '—' }}</div>
                        <div class="k">Resolved</div><div class="v">{{ $report->resolved_at?->format('d M Y H:i') ?? '—' }}</div>
                        <div class="k">Linked items</div><div class="v">{{ number_format($items->count()) }}</div>
                    </div>

                    @if ($report->meta && ($report->meta['cve_id'] ?? null))
                        <div class="mt-14">
                            <div class="fs-11 text-muted" style="text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px">Vulnerability</div>
                            <div class="row wrap" style="gap:6px">
                                <span class="badge neutral mono">{{ $report->meta['cve_id'] }}</span>
                                @if (!empty($report->meta['cvss']))
                                    <span class="badge neutral">CVSS {{ number_format((float) $report->meta['cvss'], 1) }}</span>
                                @endif
                                @if (!empty($report->meta['package_name']))
                                    <span class="badge neutral">{{ \Illuminate\Support\Str::limit($report->meta['package_name'], 40) }}</span>
                                @endif
                            </div>

                            <div class="detail-grid mt-14">
                                <div class="k">Scanner reports it</div>
                                <div class="v" style="color:{{ $report->still_present ? 'var(--critical-text)' : 'var(--success-text)' }}">
                                    {{ $report->still_present ? 'still present' : 'no longer present' }}
                                </div>
                                <div class="k">Last verified</div>
                                <div class="v">{{ $report->last_verified_at?->format('d M Y H:i') ?? 'never' }}</div>
                            </div>

                            @can('triage')
                                <form method="POST" action="{{ route('reports.verify', $report) }}" class="mt-14">
                                    @csrf
                                    <button type="submit" class="btn sm">
                                        Verify against scanner
                                    </button>
                                    <div class="fs-11 text-muted" style="margin-top:5px">
                                        Re-checks OpenSearch. A remediating finding that disappeared
                                        becomes Verified Fixed automatically.
                                    </div>
                                </form>
                            @endcan
                        </div>
                    @endif

                    @if ($report->meta && ($report->meta['check_id'] ?? null))
                        <div class="mt-14">
                            <div class="fs-11 text-muted" style="text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px">SCA Check</div>
                            <div class="row wrap" style="gap:6px">
                                <span class="badge neutral mono">{{ $report->meta['check_id'] }}</span>
                                <span class="badge neutral">{{ \Illuminate\Support\Str::limit($report->meta['policy'] ?? '', 40) }}</span>
                            </div>
                            @if (!empty($report->meta['remediation']))
                                <pre class="json mt-14" style="max-height:120px">{{ $report->meta['remediation'] }}</pre>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            @if ($items->isNotEmpty())
                <div class="card">
                    <div class="card-head">
                        <h3>Linked Items</h3>
                        <div class="spacer"></div>
                        <span class="sub">{{ number_format($items->count()) }}</span>
                    </div>
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                                <tr>
                                    <th style="width:120px">Kind</th>
                                    <th>Item</th>
                                    <th style="width:130px">Agent</th>
                                    <th style="width:120px">Severity</th>
                                    <th style="width:130px">Occurred</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $item)
                                    <tr>
                                        <td class="fs-11 text-secondary">{{ str_replace('_', ' ', ucfirst($item->kind)) }}</td>
                                        <td class="wrap mono fs-11">{{ $item->title ?? $item->ref_key }}</td>
                                        <td class="fs-12">{{ $item->agent_name ?? '—' }}</td>
                                        <td class="fs-11">{{ $item->severity ?? '—' }}</td>
                                        <td class="fs-11 text-secondary nowrap">{{ $item->occurred_at?->format('d M H:i') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if (!empty($groupAlerts))
                <div class="card">
                    <div class="card-head">
                        <h3>Alerts in This Group</h3>
                        <div class="spacer"></div>
                        <span class="sub">newest 50 of {{ number_format($group['total'] ?? 0) }}</span>
                    </div>
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                                <tr>
                                    <th style="width:130px">Time</th>
                                    <th style="width:52px" class="num">Lvl</th>
                                    <th>Rule</th>
                                    <th style="width:110px">Agent</th>
                                    <th style="width:130px">Decoder</th>
                                    <th style="width:50px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($groupAlerts as $ga)
                                    @php
                                        $gs = $ga['source'];
                                        $glvl = (int) ($gs['rule']['level'] ?? 0);
                                    @endphp
                                    <tr>
                                        <td class="mono fs-11 text-secondary nowrap">
                                            {{ isset($gs['timestamp']) ? \Illuminate\Support\Carbon::parse($gs['timestamp'])->format('d M H:i:s') : '—' }}
                                        </td>
                                        <td class="num"><span class="badge {{ \App\Support\Severity::token($glvl) }}">{{ $glvl }}</span></td>
                                        <td class="wrap">{{ \Illuminate\Support\Str::limit($gs['rule']['description'] ?? '—', 85) }}</td>
                                        <td class="fs-12">{{ $gs['agent']['name'] ?? '—' }}</td>
                                        <td class="mono fs-11 text-secondary">{{ $gs['decoder']['name'] ?? '—' }}</td>
                                        <td>
                                            <a class="btn ghost sm"
                                               href="{{ route('explorer.index', ['q' => $gs['rule']['id'] ?? '', 'agent' => $gs['agent']['name'] ?? '', 'range' => '30d', 'alert' => $gs['id'] ?? '']) }}">
                                                Open
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-head"><h3>Timeline</h3></div>
                <div class="card-body">
                    @can('triage')
                        <form method="POST" action="{{ route('reports.note', $report) }}" style="margin-bottom:16px">
                            @csrf
                            <div class="form-row">
                                <textarea name="note" class="input" rows="2" required
                                          placeholder="Add an investigation note…"></textarea>
                            </div>
                            <button type="submit" class="btn sm">Post note</button>
                        </form>
                    @endcan

                    @forelse ($report->events as $event)
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-meta">
                                    {{ $event->user->name ?? 'system' }} · {{ $event->created_at->format('d M Y H:i') }}
                                    @if ($event->to_status)
                                        <span class="badge neutral" style="margin-left:6px">
                                            {{ \App\Models\Ticket::statusLabel($event->from_status) }}
                                            &rarr; {{ \App\Models\Ticket::statusLabel($event->to_status) }}
                                        </span>
                                    @endif
                                </div>
                                <div class="timeline-body">
                                    {{ $event->note ?: ucfirst(str_replace('_', ' ', $event->action)) }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted fs-12">No activity yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col side-panel">
            @can('triage')
                <div class="card">
                    <div class="card-head"><h3>Update Report</h3></div>
                    <form method="POST" action="{{ route('reports.update', $report) }}">
                        @csrf
                        @method('PUT')
                        <div class="card-body col">
                            <div class="form-row">
                                <label for="title">Title</label>
                                <input id="title" name="title" class="input" required value="{{ $report->title }}">
                            </div>
                            <div class="form-row">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" class="input" rows="3">{{ $report->description }}</textarea>
                            </div>
                            <div class="form-row">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="select" required>
                                    @foreach ($statuses as $key => $meta)
                                        <option value="{{ $key }}" @selected($report->status === $key)>{{ $meta['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-row">
                                <label for="priority">Priority</label>
                                <select id="priority" name="priority" class="select" required>
                                    @foreach ($priorities as $key => $meta)
                                        <option value="{{ $key }}" @selected($report->priority === $key)>{{ $meta['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-row">
                                <label for="assignee_id">Assignee</label>
                                <select id="assignee_id" name="assignee_id" class="select">
                                    <option value="">Unassigned</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" @selected($report->assignee_id === $user->id)>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-row">
                                <label for="resolution">Resolution</label>
                                <textarea id="resolution" name="resolution" class="input" rows="2"
                                          placeholder="Patch version, mitigation, exception reason...">{{ $report->resolution }}</textarea>
                            </div>
                            <div class="form-row">
                                <label for="note">Note</label>
                                <textarea id="note" name="note" class="input" rows="2"
                                          placeholder="Optional note for the timeline"></textarea>
                            </div>
                            <button type="submit" class="btn primary" style="justify-content:center">Save changes</button>
                        </div>
                    </form>
                </div>
            @endcan

            <div class="card">
                <div class="card-head"><h3>Quick Actions</h3></div>
                <div class="card-body col">
                    @if ($report->group_agent)
                        <a class="btn sm" href="{{ route('explorer.index', ['q' => $report->group_rule_id, 'agent' => $report->group_agent, 'range' => '30d']) }}">
                            All alerts for this rule
                        </a>
                        <a class="btn sm" href="{{ route('alert-groups.index', ['agent' => $report->group_agent, 'range' => '30d']) }}">
                            Open in Alert Groups
                        </a>
                    @endif
                    @if ($report->meta && ($report->meta['cve_id'] ?? null))
                        <a class="btn sm" target="_blank" rel="noopener noreferrer"
                           href="https://nvd.nist.gov/vuln/detail/{{ urlencode($report->meta['cve_id']) }}">
                            Open in NVD
                        </a>
                    @endif
                    <a class="btn sm" href="{{ route('reports.index') }}">Back to reports</a>
                </div>
            </div>
        </div>
    </div>
@endsection
