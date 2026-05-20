@props([
    // Accessible label for the button. Default is the well-known
    // "Replay" verb so screen readers announce a clear affordance.
    'label' => 'Replay',

    // Optional explicit data-replay-target selector when the closest
    // ancestor isn't the right re-mount root. Pass a CSS selector and
    // the click handler walks up via $el.closest(selector) instead.
    'target' => null,
])

{{--
    Replay button — re-mounts the closest [data-replay-target] ancestor
    by replacing its innerHTML with the snapshot stored in
    data-replay-source (set on initial mount), then calling
    Alpine.initTree() to re-bind the Alpine x-data scopes.

    Pairs with the `data-replayable="true"` contract emitted by every
    animation-capable WireKit component (reveal, stat[animate],
    stat[animateIn]). The docs site's preview wrapper sets
    data-replay-target on the iframe / preview root and uses this
    button to give developers a "↻" affordance to re-run the demo.

    Style hooks via `wk-replay-button` BEM root — matches the existing
    `wk-*` namespace convention used by reading-progress, scrollbar etc.

    Default icon: a circular-arrow glyph (16×16). Developers can override
    by passing slot content.
--}}
<button
    {{ $attributes->merge([
        'type' => 'button',
        'class' => 'wk-replay-button',
        'aria-label' => $label,
    ]) }}
    @if($target)
        data-replay-target-selector="{{ $target }}"
    @endif
    x-on:click="
        const selector = $el.dataset.replayTargetSelector;
        const root = selector
            ? $el.closest(selector)
            : $el.closest('[data-replay-target]');
        if (! root) return;
        const source = root.dataset.replaySource;
        if (source !== undefined) {
            root.innerHTML = source;
            if (window.Alpine) window.Alpine.initTree(root);
            root.dispatchEvent(new CustomEvent('wirekit:replayed', { bubbles: true }));
        }
    "
>
    @isset($slot)
        {{ $slot }}
    @else
        <svg
            aria-hidden="true"
            viewBox="0 0 24 24"
            width="16"
            height="16"
            fill="currentColor"
        >
            <path d="M12 5V2L8 6l4 4V7a5 5 0 1 1-5 5H5a7 7 0 1 0 7-7Z"/>
        </svg>
    @endisset
</button>
