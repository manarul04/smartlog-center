@if ($paginator->hasPages())
    @if ($paginator->onFirstPage())
        <span class="page-btn disabled">‹</span>
    @else
        <a class="page-btn" href="{{ $paginator->previousPageUrl() }}">‹</a>
    @endif

    <span class="fs-11 text-muted" style="margin:0 8px">
        Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}
    </span>

    @if ($paginator->hasMorePages())
        <a class="page-btn" href="{{ $paginator->nextPageUrl() }}">›</a>
    @else
        <span class="page-btn disabled">›</span>
    @endif
@endif
