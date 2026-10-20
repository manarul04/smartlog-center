@auth
    <form method="POST" action="{{ route('theme.update') }}" id="themeForm" style="display:contents">
        @csrf
        <input type="hidden" name="theme" id="themeInput" value="{{ auth()->user()->theme ?? 'dark' }}">
        <input type="hidden" name="redirect" value="{{ request()->fullUrl() }}">
        <button type="submit" class="theme-toggle" title="Toggle light / dark theme" aria-label="Toggle theme">
            <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/>
            </svg>
            <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
                <circle cx="12" cy="12" r="4"/>
                <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
            </svg>
        </button>
    </form>
@endauth
