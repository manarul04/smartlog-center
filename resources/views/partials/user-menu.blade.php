@auth
    <div class="row user-menu" style="gap:8px; position:relative">
        <div class="col user-menu-name" style="gap:0; align-items:flex-end; line-height:1.25">
            <span style="font-size:12px;font-weight:600">{{ auth()->user()->name }}</span>
            <span class="fs-11 text-muted">{{ auth()->user()->roleLabel() }}</span>
        </div>
        <div class="avatar" title="{{ auth()->user()->name }}">
            {{ auth()->user()->initials() }}
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn ghost sm" title="Sign out">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
                    <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>
                </svg>
            </button>
        </form>
    </div>
@endauth
