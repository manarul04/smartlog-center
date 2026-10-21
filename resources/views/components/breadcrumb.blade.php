@props(['items' => []])

{{--
    Breadcrumb trail. Each item is ['label' => string, 'url' => ?string]; the
    last item is the current page and never renders as a link. It replaces the
    "back" buttons that used to crowd the top bar.
--}}
@php
    $items = array_values(array_filter($items, fn ($i) => ! empty($i['label'])));
    $last = count($items) - 1;
@endphp

@if ($items !== [])
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <ol>
            @foreach ($items as $index => $item)
                <li>
                    @if (! empty($item['url']) && $index !== $last)
                        <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                    @else
                        <span @if ($index === $last) aria-current="page" @endif>{{ $item['label'] }}</span>
                    @endif

                    @if ($index !== $last)
                        <svg class="crumb-sep" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 6l6 6-6 6"/>
                        </svg>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
