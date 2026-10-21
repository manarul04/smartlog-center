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
                           placeholder="rule text, command line, agent, IP, rule idâ€¦" autocomplete="off"
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
                        <span class="field-label-spacer" aria-hidden="true">&nbsp;</span>
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
                    <span class="field-label-spacer" aria-hidden="true">&nbsp;</span>
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
                            <span class="field-label-spacer" aria-hidden="true">&nbsp;</span>
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
                                ? ($dir === 'asc' ? 'â†‘' : 'â†“')
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
                            <th style="width:130px">Last action</th>
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
                                    {{ isset($src['timestamp']) ? \Illuminate\Support\Carbon::parse($src['timestamp'])->format('d M H:i:s') : 'â€”' }}
                                </td>
                                <td>
                                    <span class="badge {{ $token }}">{{ $level }}</span>
                                </td>
                                <td class="wrap">
                                    @if (!empty($item['highlight']['rule.description'][0]))
                                        {!! $item['highlight']['rule.description'][0] !!}
                                    @else
                                        {{ \Illuminate\Support\Str::limit($src['rule']['description'] ?? 'â€”', 110) }}
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
                                       class="link-strong">{{ $src['agent']['name'] ?? 'â€”' }}</a>
                                    <div class="fs-11 text-muted mono">{{ $src['agent']['ip'] ?? '' }}</div>
                                </td>
                                <td>
                                    <span class="status-chip {{ \App\Support\AlertStatus::isUnreviewed($statusKey) ? 'is-muted' : '' }}"
                                          style="--chip:{{ \App\Support\AlertStatus::color($statusKey) }}">
                                        {{ \App\Support\AlertStatus::label($statusKey) }}
                                    </span>
                                </td>
                                <td class="fs-11 text-secondary">
                                    @if ($ann && $ann->last_action_at)
                                        <div>{{ $ann->last_action_at->diffForHumans() }}</div>
                                        <div class="text-muted truncate">{{ $ann->lastActor->name ?? 'system' }}</div>
                                    @else
                                        <span class="text-muted">&mdash;</span>
                                    @endif
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
                                <td colspan="9" class="empty">
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
                       href="{{ route('explorer.index', array_merge($query, ['page' => max(1, $current - 1)])) }}">â€¹</a>

                    @if ($start > 1)
                        <a class="page-btn" href="{{ route('explorer.index', array_merge($query, ['page' => 1])) }}">1</a>
                        @if ($start > 2)<span class="text-muted">â€¦</span>@endif
                    @endif

                    @for ($p = $start; $p <= $end; $p++)
                        <a class="page-btn {{ $p === $current ? 'active' : '' }}"
                           href="{{ route('explorer.index', array_merge($query, ['page' => $p])) }}">{{ $p }}</a>
                    @endfor

                    @if ($end < $last)
                        @if ($end < $last - 1)<span class="text-muted">â€¦</span>@endif
                        <a class="page-btn" href="{{ route('explorer.index', array_merge($query, ['page' => $last])) }}">{{ $last }}</a>
                    @endif

                    <a class="page-btn {{ $current >= $last ? 'disabled' : '' }}"
                       href="{{ route('explorer.index', array_merge($query, ['page' => min($last, $current + 1)])) }}">â€º</a>
                </div>
            @endif
        @endif
    </div>

    @include('partials.alert-drawer', ['users' => $users, 'options' => $options])
@endsection


@push('scripts')
@include('partials.alert-drawer-script')
@endpush
