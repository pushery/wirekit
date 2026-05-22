{{-- Debug-mode placeholder rendered by <x-wirekit-chart> when no chart
     adapter is configured (`config('wirekit.charts.library') === null`).
     In production the Chart constructor throws hard; this view is only
     reached when APP_DEBUG=true so end users never see it. The wrapper
     mimics the real chart's `wk-chart` carve-out marker plus the
     developer-supplied `height` so layout stays stable when the
     developer fixes the config. --}}
@php
    $tag = $inline ?? false ? 'span' : 'div';
@endphp

<{{ $tag }}
    class="wk-chart wk-chart-disabled"
    role="region"
    aria-label="Chart placeholder — adapter not configured"
    style="
        height: {{ $height ?? '380px' }};
        display: {{ $inline ?? false ? 'inline-flex' : 'flex' }};
        align-items: center;
        justify-content: center;
        background: color-mix(in oklch, var(--color-wk-bg-muted) 60%, transparent);
        border: 1px dashed var(--color-wk-border-subtle);
        border-radius: var(--radius-wk-md);
        padding: var(--padding-wk-y-md) var(--padding-wk-x-md);
        color: var(--color-wk-text-muted);
        font-family: var(--font-wk-sans);
        font-size: 0.875rem;
        line-height: 1.5;
        text-align: center;
    "
>
    <span style="max-width: 28rem;">
        <strong style="color: var(--color-wk-text); display: block; margin-bottom: 0.25rem;">
            Chart adapter not configured
        </strong>
        Set <code style="font-family: var(--font-wk-mono); background: var(--color-wk-bg-subtle); padding: 0 0.25rem; border-radius: var(--radius-wk-sm);">'charts.library' =&gt; 'chartjs'</code> in <code style="font-family: var(--font-wk-mono); background: var(--color-wk-bg-subtle); padding: 0 0.25rem; border-radius: var(--radius-wk-sm);">config/wirekit.php</code>, then <code style="font-family: var(--font-wk-mono); background: var(--color-wk-bg-subtle); padding: 0 0.25rem; border-radius: var(--radius-wk-sm);">npm install chart.js</code>.
    </span>
</{{ $tag }}>
