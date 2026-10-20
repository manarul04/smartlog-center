@extends('layouts.app')

@section('title', 'Configuration')
@section('subtitle', 'Wazuh indexer connection')

@section('content')
    @php
        $isStored = fn (string $key) => in_array($key, $source, true);
        $online = $status !== '' && ! str_starts_with($status, 'Unreachable');
        // After a failed submit old() is the truth, including an unchecked box.
        $tlsChecked = $errors->any()
            ? (bool) old('opensearch_tls_verify')
            : $settings['opensearch_tls_verify'];
    @endphp

    <div class="grid grid-2-1">
        <div class="col">
            <div class="card">
                <div class="card-head">
                    <h3>Wazuh Indexer</h3>
                    <div class="spacer"></div>
                    <span class="badge {{ $online ? 'success' : 'critical' }}">
                        {{ $online ? 'Connected' : 'Offline' }}
                    </span>
                </div>

                <form method="POST" action="{{ route('config.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="card-body">
                        <div class="config-section">
                            <div class="config-section-head">
                                <h4>OpenSearch</h4>
                                <span class="fs-11 text-secondary">Source for alerts, agents, vulnerabilities and SCA</span>
                            </div>

                            <div class="config-grid">
                                <div class="form-row" style="grid-column: span 2">
                                    <label for="opensearch_host">
                                        Host URL
                                        @if ($isStored('opensearch.host'))
                                            <span class="badge neutral">stored</span>
                                        @endif
                                    </label>
                                    <input id="opensearch_host" name="opensearch_host" class="input" type="text" inputmode="url" required
                                           value="{{ old('opensearch_host', $settings['opensearch_host']) }}">
                                    <span class="fs-11 text-secondary">Scheme and host, optionally with the port. <span class="mono">https://</span> is assumed when omitted.</span>
                                </div>

                                <div class="form-row">
                                    <label for="opensearch_port">
                                        Port
                                        @if ($isStored('opensearch.port'))
                                            <span class="badge neutral">stored</span>
                                        @endif
                                    </label>
                                    <input id="opensearch_port" name="opensearch_port" class="input" type="number"
                                           min="1" max="65535" required
                                           value="{{ old('opensearch_port', $settings['opensearch_port']) }}">
                                </div>

                                <div class="form-row">
                                    <label for="opensearch_username">
                                        Username
                                        @if ($isStored('opensearch.username'))
                                            <span class="badge neutral">stored</span>
                                        @endif
                                    </label>
                                    <input id="opensearch_username" name="opensearch_username" class="input"
                                           autocomplete="off"
                                           value="{{ old('opensearch_username', $settings['opensearch_username']) }}">
                                </div>

                                <div class="form-row" style="grid-column: span 2">
                                    <label for="opensearch_password">
                                        Password
                                        @if ($settings['opensearch_password_set'])
                                            <span class="badge success">set</span>
                                        @endif
                                    </label>
                                    <input id="opensearch_password" name="opensearch_password" class="input" type="password"
                                           autocomplete="new-password" placeholder="Leave blank to keep">
                                    <span class="fs-11 text-secondary">Leave empty to keep the stored password.</span>
                                </div>
                            </div>

                            <div class="form-row" style="margin-bottom:0">
                                <label class="check-row">
                                    <input type="checkbox" name="opensearch_tls_verify" value="1"
                                           @checked($tlsChecked)>
                                    Verify TLS certificate
                                </label>
                                <span class="fs-11 text-secondary">Turn off for self-signed certificates, common on Wazuh installs.</span>
                            </div>
                        </div>

                        <div class="row" style="gap:8px">
                            <button type="submit" class="btn primary">Save configuration</button>
                            <span class="fs-11 text-secondary">Applies to the next request, no restart needed.</span>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-head">
                    <h3>Connection Test</h3>
                    <div class="spacer"></div>
                    <form method="POST" action="{{ route('config.test') }}">
                        @csrf
                        <button type="submit" class="btn sm">Test now</button>
                    </form>
                </div>
                <div class="card-body">
                    <div class="fs-12 text-secondary">
                        <span class="mono">{{ $status }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card">
                <div class="card-head"><h3>Effective Settings</h3></div>
                <div class="card-body">
                    <table class="data config-table">
                        <tbody>
                            <tr>
                                <td class="text-secondary">Source</td>
                                <td class="mono">{{ $source === [] ? '.env' : 'database' }}</td>
                            </tr>
                            <tr>
                                <td class="text-secondary">OpenSearch</td>
                                <td class="mono truncate">{{ $settings['opensearch_host'] }}:{{ $settings['opensearch_port'] }}</td>
                            </tr>
                            <tr>
                                <td class="text-secondary">Username</td>
                                <td class="mono truncate">{{ $settings['opensearch_username'] ?: 'not set' }}</td>
                            </tr>
                            <tr>
                                <td class="text-secondary">TLS verify</td>
                                <td class="mono">{{ $settings['opensearch_tls_verify'] ? 'on' : 'off' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3>Reset</h3></div>
                <div class="card-body">
                    <p class="fs-12 text-secondary" style="margin-top:0">
                        Deletes every stored override and falls back to
                        <span class="mono">OPENSEARCH_*</span> from <span class="mono">.env</span>.
                    </p>
                    <form method="POST" action="{{ route('config.reset') }}"
                          onsubmit="return confirm('Remove all stored overrides and fall back to .env?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn danger sm" {{ $source === [] ? 'disabled' : '' }}>
                            Reset to .env
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
