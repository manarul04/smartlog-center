{{-- Cover block shared by the agent-scoped PDF exports. --}}
<table class="head-table">
    <tr>
        <td style="width:64%">
            <table>
                <tr>
                    <td style="padding:0 7px 0 0;width:30px">
                        <img src="{{ public_path('assets/img/logo.png') }}" width="26" height="26" alt="">
                    </td>
                    <td style="padding:0">
                        <div class="brand">{{ config('app.name') }} &middot; Threat Management</div>
                        <h1>{{ $meta['title'] }}</h1>
                        <p class="scope">{{ $meta['scope'] }}</p>
                    </td>
                </tr>
            </table>
        </td>
        <td style="width:36%" class="meta">
            <b>Period</b><br>
            {{ $meta['period']['from']?->format('d M Y H:i') ?? 'start of retention' }} &mdash; {{ $meta['period']['to']->format('d M Y H:i') }}<br>
            <b>Range</b> {{ $meta['period']['label'] }}<br>
            <b>Generated</b> {{ $meta['generated_at']->format('d M Y H:i') }}<br>
            <b>By</b> {{ $generatedBy->name ?? 'system' }}
        </td>
    </tr>
</table>

@if (!empty($meta['descriptors']))
    <div class="filters">
        <div class="ftitle">Applied filters</div>
        @foreach ($meta['descriptors'] as $descriptor)
            <span class="fchip"><b>{{ $descriptor['label'] }}</b>: {{ $descriptor['value'] }}</span>
        @endforeach
    </div>
@endif
