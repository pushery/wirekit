{{-- Debug-mode placeholder rendered by <x-wirekit-chart> when no chart
     adapter is configured (`config('wirekit.charts.library') === null`).
     In production the Chart constructor throws hard; this view is only
     reached when APP_DEBUG=true so end users never see it. The wrapper keeps
     the developer-supplied `height` so layout stays stable once the config
     is fixed.

     The two classes below are this placeholder's OWN. `wk-chart` in
     particular marks nothing else: the chart component emits no `wk-*` class
     at all, `chart-mixed` emits `wk-chart-mixed`, and `dist/wirekit.css`
     styles neither. A selector built on `wk-chart` expecting to find a
     rendered chart will match this placeholder and nothing else.

     The signal a host should aim at is `data-wk-chart`, and this placeholder
     deliberately does NOT carry it. Every rendered chart emits it with the
     active library as its value; nothing renders here, so a lazy-loader firing
     on this element would fetch a chart library for a chart that was never
     configured — the exact cost the deferral exists to avoid. The absence is
     part of the contract rather than an omission. --}}
@php
    $tag = $inline ?? false ? 'span' : 'div';
@endphp

{{-- No `role="region"`, and no `aria-label`, each for its own reason.

     The role would make a debug placeholder a page landmark — something a reader navigates by,
     and something axe reports as `landmark-unique` the moment a page renders two charts. This
     package's rule for a generic region is that the role waits for a name the CALLER chose;
     there is no caller-supplied name reaching here at all: the element merges no attribute bag.

     The label would be worse than redundant: an `aria-label` on a container replaces its
     contents in the accessible-name computation, so the one thing a reader needs — the two lines
     below naming the config key to set — would be hidden behind an English string that no
     catalog translates.

     Nothing is owed in their place. The visible text is a complete sentence, it is read
     normally without a name, and the element is not scrollable, so WCAG 2.1.1 asks for no
     `tabindex` either. This view is reached only under `APP_DEBUG`, and what a developer needs
     from it is the instruction, not a landmark. --}}
<{{ $tag }}
    class="wk-chart wk-chart-disabled"
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
