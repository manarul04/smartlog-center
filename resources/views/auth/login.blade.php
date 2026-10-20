@php($theme = request()->cookie('wlc-theme') === 'light' ? 'light' : 'dark')
<!DOCTYPE html>
<html lang="en" data-theme="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in Â· {{ config('app.name') }}</title>
    @include('partials.favicon')
    <script>
        // Resolve the theme before first paint so the sign-in screen does not
        // flash the wrong palette.
        (function () {
            try {
                var stored = localStorage.getItem('wlc-theme');
                var theme = stored || (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) { /* ignore */ }
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="row between" style="margin-bottom:22px">
            <div class="row" style="gap:11px">
                <img src="{{ asset('assets/img/logo.png') }}" alt="" class="brand-logo" width="36" height="36">
                <div class="brand-text">
                    <span class="brand-title">{{ config('app.name') }}</span>
                    <span class="brand-sub">Threat Management</span>
                </div>
            </div>

            <button type="button" class="theme-toggle" id="loginThemeToggle"
                    title="Toggle light / dark theme" aria-label="Toggle theme">
                <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/>
                </svg>
                <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
                    <circle cx="12" cy="12" r="4"/>
                    <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
                </svg>
            </button>
        </div>

        <h1>Sign in</h1>
        <p class="sub">Access the security operations console.</p>

        @if (session('status'))
            <div class="banner" style="border-color:rgba(16,185,129,.35);background:var(--success-soft);color:var(--success-text);margin-bottom:14px">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}">
            @csrf

            <div class="form-row">
                <label for="username">Username</label>
                <input id="username" type="text" name="username" class="input" value="{{ old('username') }}" required autofocus autocomplete="username">
            </div>

            <div class="form-row">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" class="input" required autocomplete="current-password">
            </div>

            <div class="row between" style="margin:16px 0 20px">
                <label class="check-row">
                    <input type="checkbox" name="remember" value="1">
                    Remember me
                </label>
            </div>

            <button type="submit" class="btn primary" style="width:100%;justify-content:center;min-height:38px">
                Sign in
            </button>
        </form>

        <div class="center text-muted" style="margin-top:20px;font-size:11px">
            @if (config('opensearch.host'))
                OpenSearch {{ parse_url(config('opensearch.host'), PHP_URL_HOST) }}:{{ config('opensearch.port') }}
            @endif
        </div>
    </div>
</div>

<script>
    (function () {
        var btn = document.getElementById('loginThemeToggle');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var next = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', next);
            try {
                localStorage.setItem('wlc-theme', next);
                document.cookie = 'wlc-theme=' + next + ';path=/;max-age=31536000;samesite=lax';
            } catch (e) { /* ignore */ }
        });
    })();
</script>
</body>
</html>

