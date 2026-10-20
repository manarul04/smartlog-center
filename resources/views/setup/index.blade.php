@php($theme = request()->cookie('wlc-theme') === 'light' ? 'light' : 'dark')
<!DOCTYPE html>
<html lang="en" data-theme="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Setup · {{ config('app.name') }}</title>
    @include('partials.favicon')
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('wlc-theme');
                var theme = stored || (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) { /* ignore */ }
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card setup-card">
        <div class="row between" style="margin-bottom:22px">
            <div class="row" style="gap:11px">
                <img src="{{ asset('assets/img/logo.png') }}" alt="" class="brand-logo" width="36" height="36">
                <div class="brand-text">
                    <span class="brand-title">{{ config('app.name') }}</span>
                    <span class="brand-sub">Threat Management</span>
                </div>
            </div>

            <button type="button" class="theme-toggle" id="setupThemeToggle"
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

        <ol class="wizard-steps" aria-label="Setup progress">
            @foreach (['Database', 'Wazuh indexer', 'Administrator'] as $index => $label)
                @php($number = $index + 1)
                <li class="{{ $step === $number ? 'is-current' : ($step > $number ? 'is-done' : '') }}">
                    <span class="wizard-step-num">{{ $step > $number ? '✓' : $number }}</span>
                    {{ $label }}
                </li>
            @endforeach
        </ol>

        @if (session('status'))
            <div class="banner" style="border-color:rgba(16,185,129,.35);background:var(--success-soft);color:var(--success-text);margin-bottom:16px">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert-error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @if ($step === 1)
            <h1>Database</h1>
            <p class="sub">
                The console keeps accounts, reports and triage state here. The schema is
                created as soon as this step connects.
            </p>

            <form method="POST" action="{{ route('setup.database.store') }}" id="setupForm">
                @csrf

                <div class="setup-grid">
                    <div class="form-row">
                        <label for="db_connection">Engine</label>
                        <select id="db_connection" name="db_connection" class="select" required>
                            <option value="pgsql" @selected($values['db_connection'] === 'pgsql')>PostgreSQL</option>
                            <option value="mysql" @selected($values['db_connection'] === 'mysql')>MySQL / MariaDB</option>
                        </select>
                    </div>

                    <div class="form-row">
                        <label for="db_port">Port</label>
                        <input id="db_port" name="db_port" class="input" type="number"
                               min="1" max="65535" required value="{{ $values['db_port'] }}">
                    </div>
                </div>

                <div class="form-row">
                    <label for="db_host">Host</label>
                    <input id="db_host" name="db_host" class="input" required value="{{ $values['db_host'] }}">
                </div>

                <div class="form-row">
                    <label for="db_database">Database name</label>
                    <input id="db_database" name="db_database" class="input" required value="{{ $values['db_database'] }}">
                    <span class="fs-11 text-secondary">Created automatically when the user is allowed to create databases.</span>
                </div>

                <div class="setup-grid">
                    <div class="form-row">
                        <label for="db_username">Username</label>
                        <input id="db_username" name="db_username" class="input" required
                               autocomplete="off" value="{{ $values['db_username'] }}">
                    </div>

                    <div class="form-row">
                        <label for="db_password">Password</label>
                        <input id="db_password" name="db_password" class="input" type="password"
                               autocomplete="new-password">
                    </div>
                </div>

                <div class="row" style="gap:10px;flex-wrap:wrap;margin-bottom:16px">
                    <button type="button" class="btn sm" data-test="database">Test connection</button>
                    <span class="fs-11 text-secondary" data-result="database" role="status" aria-live="polite">
                        Nothing tested yet.
                    </span>
                </div>

                <button type="submit" class="btn primary" style="width:100%;justify-content:center;min-height:38px">
                    Connect database
                </button>
            </form>
        @elseif ($step === 2)
            <h1>Wazuh indexer</h1>
            <p class="sub">
                The indexer holds alerts, agents, vulnerabilities and SCA results. The
                console reads them with the credentials below.
            </p>

            <form method="POST" action="{{ route('setup.connection.store') }}" id="setupForm">
                @csrf

                <div class="form-row">
                    <label for="opensearch_host">Host</label>
                    <input id="opensearch_host" name="opensearch_host" class="input" type="text" inputmode="url" required
                           value="{{ $values['opensearch_host'] }}">
                    <span class="fs-11 text-secondary">Scheme is optional; <span class="mono">host:port</span> is split automatically.</span>
                </div>

                <div class="setup-grid">
                    <div class="form-row">
                        <label for="opensearch_port">Port</label>
                        <input id="opensearch_port" name="opensearch_port" class="input" type="number"
                               min="1" max="65535" required value="{{ $values['opensearch_port'] }}">
                    </div>

                    <div class="form-row">
                        <label for="opensearch_username">Username</label>
                        <input id="opensearch_username" name="opensearch_username" class="input"
                               autocomplete="off" value="{{ $values['opensearch_username'] }}">
                    </div>
                </div>

                <div class="form-row">
                    <label for="opensearch_password">Password</label>
                    <input id="opensearch_password" name="opensearch_password" class="input" type="password"
                           autocomplete="new-password">
                </div>

                <div class="form-row" style="margin-bottom:12px">
                    <label class="check-row">
                        <input type="checkbox" name="opensearch_tls_verify" value="1"
                               @checked(old('opensearch_tls_verify', $values['opensearch_tls_verify']))>
                        Verify TLS certificate
                    </label>
                    <span class="fs-11 text-secondary">Leave off for the self-signed certificate Wazuh installs by default.</span>
                </div>

                <div class="row" style="gap:10px;flex-wrap:wrap;margin-bottom:16px">
                    <button type="button" class="btn sm" data-test="indexer">Test connection</button>
                    <span class="fs-11 text-secondary" data-result="indexer" role="status" aria-live="polite">
                        Nothing tested yet.
                    </span>
                </div>

                <button type="submit" class="btn primary" style="width:100%;justify-content:center;min-height:38px">
                    Connect indexer
                </button>

                <p class="fs-11 text-secondary center" style="margin:14px 0 0">
                    The password is stored encrypted with the application key.
                </p>
            </form>
        @else
            <h1>Administrator</h1>
            <p class="sub">
                This account owns the console. Usernames are what you sign in with;
                email is optional and only used as contact information.
            </p>

            <form method="POST" action="{{ route('setup.account.store') }}" id="setupForm">
                @csrf

                <div class="form-row">
                    <label for="name">Full name</label>
                    <input id="name" name="name" class="input" required value="{{ old('name') }}">
                </div>

                <div class="form-row">
                    <label for="username">Username</label>
                    <input id="username" name="username" class="input" required
                           autocomplete="username" value="{{ old('username') }}">
                    <span class="fs-11 text-secondary">Letters, numbers, dashes and underscores.</span>
                </div>

                <div class="form-row">
                    <label for="email">Email (optional)</label>
                    <input id="email" name="email" type="email" class="input" value="{{ old('email') }}">
                </div>

                <div class="setup-grid">
                    <div class="form-row">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" class="input" required
                               autocomplete="new-password">
                    </div>

                    <div class="form-row">
                        <label for="password_confirmation">Confirm password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password"
                               class="input" required autocomplete="new-password">
                    </div>
                </div>

                <span class="fs-11 text-secondary">At least 10 characters with letters, numbers and symbols.</span>

                <button type="submit" class="btn primary" style="width:100%;justify-content:center;min-height:38px;margin-top:16px">
                    Create administrator and finish
                </button>
            </form>
        @endif
    </div>
</div>

<script>
    (function () {
        var themeBtn = document.getElementById('setupThemeToggle');
        if (themeBtn) {
            themeBtn.addEventListener('click', function () {
                var next = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', next);
                try {
                    localStorage.setItem('wlc-theme', next);
                    document.cookie = 'wlc-theme=' + next + ';path=/;max-age=31536000;samesite=lax';
                } catch (e) { /* ignore */ }
            });
        }

        var form = document.getElementById('setupForm');
        if (!form) return;

        var csrf = document.querySelector('meta[name=csrf-token]').content;
        var endpoints = {
            database: @json(route('setup.database.test')),
            indexer: @json(route('setup.connection.test'))
        };

        function payload(kind) {
            var get = function (name) {
                var el = form.querySelector('[name=' + name + ']');
                return el ? el.value : '';
            };

            if (kind === 'database') {
                return {
                    db_connection: get('db_connection'),
                    db_host: get('db_host'),
                    db_port: get('db_port'),
                    db_database: get('db_database'),
                    db_username: get('db_username'),
                    db_password: get('db_password')
                };
            }

            var tls = form.querySelector('[name=opensearch_tls_verify]');

            return {
                opensearch_host: get('opensearch_host'),
                opensearch_port: get('opensearch_port'),
                opensearch_username: get('opensearch_username'),
                opensearch_password: get('opensearch_password'),
                opensearch_tls_verify: tls && tls.checked ? 1 : 0
            };
        }

        form.querySelectorAll('[data-test]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var kind = btn.getAttribute('data-test');
                var out = form.querySelector('[data-result=' + kind + ']');

                btn.disabled = true;
                out.textContent = 'Testing…';
                out.className = 'fs-11 text-secondary';

                fetch(endpoints[kind], {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: JSON.stringify(payload(kind))
                })
                    .then(function (r) { return r.json().then(function (body) { return { ok: r.ok, body: body }; }); })
                    .then(function (res) {
                        if (res.ok && res.body.ok) {
                            out.textContent = res.body.message;
                            out.className = 'fs-11 t-success';
                            return;
                        }

                        var message = res.body && res.body.message;
                        if (!message && res.body && res.body.errors) {
                            message = Object.keys(res.body.errors).map(function (key) {
                                return res.body.errors[key][0];
                            }).join(' ');
                        }

                        out.textContent = message || 'Connection failed.';
                        out.className = 'fs-11 t-critical';
                    })
                    .catch(function () {
                        out.textContent = 'Could not run the test. Check the network and try again.';
                        out.className = 'fs-11 t-critical';
                    })
                    .then(function () { btn.disabled = false; });
            });
        });
    })();
</script>
</body>
</html>
