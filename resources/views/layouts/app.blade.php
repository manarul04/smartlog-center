<!DOCTYPE html>
@php($theme = auth()->user()?->theme === 'light' ? 'light' : 'dark')
<html lang="en" data-theme="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        // Apply the stored theme before first paint to avoid a flash, and fall
        // back to the OS preference when the account has not chosen one.
        (function () {
            try {
                var stored = localStorage.getItem('wlc-theme');
                var account = '{{ $theme }}';
                var theme = account || stored ||
                    (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) { /* ignore */ }
            document.documentElement.classList.add('js');
        })();
    </script>
    <title>@yield('title', 'Overview') · {{ config('app.name') }}</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}">
    @stack('head')
</head>
<body>
<div class="shell">
    @include('partials.sidebar')

    <div class="main">
        <header class="topbar">
            <button class="btn ghost sm" id="sidebarToggle" aria-label="Toggle navigation" style="display:none">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
            </button>

            <div class="page-heading">
                <div class="page-title">@yield('title', 'Overview')</div>
                <div class="page-subtitle">@yield('subtitle', 'Threat management console')</div>
            </div>

            <div class="topbar-spacer"></div>

            @hasSection('actions')
                <div class="row">@yield('actions')</div>
            @endif

            <div class="row" style="gap:10px">
                <span class="fs-11 text-muted nowrap" id="clock"></span>
                @include('partials.theme-toggle')
                @include('partials.user-menu')
            </div>
        </header>

        <main class="content">
            @if (session('status'))
                <div class="banner" style="border-color:rgba(16,185,129,.35);background:var(--success-soft);color:var(--success-text)">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="banner error">{{ session('error') }}</div>
            @endif

            @if ($errors->any())
                <div class="banner error">
                    <div>
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="{{ asset('assets/js/app.js') }}?v={{ filemtime(public_path('assets/js/app.js')) }}"></script>
@stack('scripts')
</body>
</html>

