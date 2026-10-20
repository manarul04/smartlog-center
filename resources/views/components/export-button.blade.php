@props(['href' => null, 'modal' => false])

{{-- One export control for every page: page-header position, same icon,
     same label, same weight, so the action reads the same everywhere.
     Pass `modal` on pages that open a period picker instead of downloading
     straight away. --}}
@php
    $tag = $href ? 'a' : 'button';
    $attrs = $attributes->merge(['class' => 'btn sm']);
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @else type="button" @endif
    @if ($modal) data-report-open @endif
    {{ $attrs }}>
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
    </svg>
    {{ $slot->isEmpty() ? 'Export PDF' : $slot }}
</{{ $tag }}>
