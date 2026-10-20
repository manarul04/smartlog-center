@extends('layouts.app')

@section('title', 'Audit Log')
@section('subtitle', 'Administrative and access activity')

@section('content')
    <div class="card">
        <form method="GET" action="{{ route('audit.index') }}">
            <div class="toolbar">
                <div class="field">
                    <label for="action">Action</label>
                    <select id="action" name="action" class="select" data-autosubmit>
                        <option value="">All actions</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>&nbsp;</label>
                    <a href="{{ route('audit.index') }}" class="btn sm">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th style="width:170px">Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>IP</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="mono fs-11 text-secondary nowrap">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                            <td style="font-size:12px">{{ $log->user->name ?? 'system' }}</td>
                            <td><span class="badge neutral mono">{{ $log->action }}</span></td>
                            <td class="fs-12 text-secondary">{{ $log->target ?? '—' }}</td>
                            <td class="mono fs-11 text-muted">{{ $log->ip ?? '—' }}</td>
                            <td class="fs-11 text-muted truncate" style="max-width:280px">
                                {{ $log->meta ? json_encode($log->meta) : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">No audit entries yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="pager">
                <span>{{ $logs->total() }} entries</span>
                <div class="spacer"></div>
                {{ $logs->links('pagination.minimal') }}
            </div>
        @endif
    </div>
@endsection
