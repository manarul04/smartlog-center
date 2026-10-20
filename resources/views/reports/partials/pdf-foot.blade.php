<div class="footer">
    <div class="left">
        {{ config('app.name') }} &middot; {{ $meta['title'] }} &middot; {{ $meta['scope'] }} &middot; {{ $meta['period_label'] }}
    </div>
    <div class="right">Generated {{ $meta['generated_at']->format('d M Y H:i') }}</div>
</div>
