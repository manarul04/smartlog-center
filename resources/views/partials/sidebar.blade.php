@php
    $nav = [
        'Monitor' => [
            ['route' => 'overview', 'label' => 'Overview', 'icon' => 'M3 12l9-9 9 9M5 10v10h14V10'],
        ],
        'Threat Management' => [
            ['route' => 'explorer.index', 'label' => 'Alert Explorer', 'icon' => 'M11 4a7 7 0 105.2 11.9L21 21M11 4a7 7 0 010 14'],
            ['route' => 'threats.index', 'label' => 'Threat Hunting', 'icon' => 'M12 3l8 4v6c0 4.5-3.4 7.6-8 8-4.6-.4-8-3.5-8-8V7l8-4z'],
            ['route' => 'alert-groups.index', 'label' => 'Alert Groups', 'icon' => 'M4 6h16M4 12h10M4 18h7M17 15l3 3 3-3M20 18v4'],
        ],
        'Assets' => [
            ['route' => 'agents.index', 'label' => 'Agents & Health', 'icon' => 'M4 5h16v6H4zM4 13h16v6H4zM7 8h.01M7 16h.01'],
            ['route' => 'compliance.index', 'label' => 'Compliance', 'icon' => 'M9 12l2 2 4-4M12 3l8 4v6c0 4.5-3.4 7.6-8 8-4.6-.4-8-3.5-8-8V7l8-4z'],
        ],
        'Reports' => [
            ['route' => 'reports.index', 'label' => 'Reports', 'icon' => 'M4 5h16v6H4zM4 13h16v6H4zM9 8h.01M9 16h.01M15 8h2M15 16h2'],
        ],
    ];

    if (auth()->user()?->isAdmin()) {
        $nav['Administration'] = [
            ['route' => 'users.index', 'label' => 'Users', 'icon' => 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 7a4 4 0 100 8 4 4 0 000-8zM22 21v-2a4 4 0 00-3-3.9'],
            ['route' => 'audit.index', 'label' => 'Audit Log', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2M9 12h6M9 16h6'],
            ['route' => 'config.index', 'label' => 'Configuration', 'icon' => 'M12 15a3 3 0 100-6 3 3 0 000 6zM19.4 15a1.7 1.7 0 00.3 1.9l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.9-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.9.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.9 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.9l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.9.3H10a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.9-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.9V10a1.7 1.7 0 001.5 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z'],
        ];
    }

    // clusterHealth() is already cached per endpoint, so no extra wrapper here.
    $healthy = config('opensearch.host')
        ? app(\App\Services\OpenSearch\OpenSearchClient::class)->isHealthy()
        : false;
@endphp

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <img src="{{ asset('assets/img/logo.png') }}" alt="" class="brand-logo" width="30" height="30">
        <div class="brand-text">
            <span class="brand-title">{{ config('app.name') }}</span>
            <span class="brand-sub">Threat Management</span>
        </div>
    </div>

    <div class="sidebar-scroll">
        @foreach ($nav as $section => $items)
            <div class="nav-section">
                <div class="nav-label">{{ $section }}</div>
                @foreach ($items as $item)
                    <a href="{{ route($item['route']) }}"
                       class="nav-item {{ request()->routeIs($item['route']) || request()->routeIs(str_replace('.index', '', $item['route']) . '.*') ? 'active' : '' }}">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="{{ $item['icon'] }}"/>
                        </svg>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </div>

    <div class="sidebar-footer">
        <div class="row between">
            <span class="health-pill {{ $healthy ? '' : 'down' }}">
                <span class="dot"></span>
                {{ $healthy ? 'Connected' : 'Offline' }}
            </span>
            <span class="mono" style="font-size:10px">v1.0</span>
        </div>
        <div class="mt-14" style="font-size:10.5px;line-height:1.5">
            @if (config('opensearch.host'))
                {{ parse_url(config('opensearch.host'), PHP_URL_HOST) }}:{{ config('opensearch.port') }}
            @else
                not configured
            @endif
        </div>
    </div>
</aside>

