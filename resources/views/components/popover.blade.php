{{-- optimistic-ui: n/a — client-only
     Its state is open state and placement. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'placement' => config('wirekit.components.popover.placement', 'bottom'),
    'offset' => config('wirekit.components.popover.offset', 8),
    // The panel's accessible name. It is a `role="dialog"`, so a screen reader announces
    // this on entry — and the default said "Popover", which names the mechanism rather than
    // the content. A page with two of them announced the same word twice.
    'label' => null,
    // Whether the panel pads its own contents. `false` hands the whole surface to the
    // caller, which is what a panel with its own header, scroll region and footer needs:
    // those three have to reach the panel's edges, and padding on the outside puts a gutter
    // between the scrollbar and the border and stops a sticky header from sitting flush.
    'padded' => true,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\UtilityClasses;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('popover', $attributes->getAttributes());

    // Popover — click-triggered floating panel with focus trap.
    // Unlike Tooltip (hover) or HoverCard (hover + rich), Popover opens on click
    // and traps focus inside the panel. Uses role="dialog" for a11y.
    $wrapperClasses = WireKit::resolveClasses('popover', 'wrapper', implode(' ', [
        'relative inline-block',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // z-index: the dropdown layer (`--z-wk-dropdown`), the one hover-card uses too, so
    // the panel stays above sticky chrome on the page when the user interacts with
    // anything else while the popover is open; a tooltip sits one layer higher
    // (`--z-wk-tooltip`) and paints above it.
    // Width: min-w-72 instead of fixed w-72 so the panel grows to fit content
    // wider than 18 rem (e.g. long share URLs in input fields) instead of clipping.
    $panelClasses = WireKit::resolveClasses('popover', 'panel', implode(' ', [
        'fixed z-[var(--z-wk-dropdown)]',
        'min-w-72 max-w-[calc(100vw-1rem)] w-max',
        'rounded-[var(--radius-wk-lg)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'shadow-[var(--shadow-wk-lg)]',
        'p-[var(--padding-wk-x-md)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);

    // An unpadded panel takes every padding utility out of the RESOLVED list. Adding `p-0`
    // beside them did nothing: two padding utilities on one element are decided by the
    // stylesheet's order, and that put `p-[var(--padding-wk-x-md)]` over `p-0`. Filtering the
    // resolved list rather than the default one keeps the promise when a personalization
    // replaced the list wholesale, because a caller asking for an unpadded panel means it
    // whatever the theme did. A padding under a variant stays: it is the theme's.
    $padded = BooleanProp::from($padded, true);

    if (! $padded) {
        $panelClasses = UtilityClasses::without($panelClasses, UtilityClasses::PADDING);
    }
    // A caller's `x-ref` belongs to the caller's component, and this bag lands on our root,
    // which would keep it: CallerRef::onRoot() hands it to the root above.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);
@endphp

<div
    x-data="wirekitPopover({ placement: {{ \Pushery\WireKit\Support\AlpinePayload::string($placement) }}, offset: {{ (int) $offset }} })"
    {{ $attributes->class([$wrapperClasses]) }}
>
    {{-- Trigger — clicking toggles the popover.

         ARIA attributes (aria-haspopup, aria-expanded) are applied to the
         INNER interactive element (button/link) via x-init, NOT to this
         wrapper div. The ARIA spec requires these attributes to live on an
         element with an interactive role; placing them on a generic <div>
         fails axe-core's aria-allowed-attr rule. A popover is a click-to-open
         control, so a missing focusable descendant is a developer error
         (keyboard users can't open it) — surface it with a console.warn. --}}
    <div
        x-ref="trigger"
        x-on:click="toggle()"
        x-init="initTriggerAria()"
    >
        {{ $trigger }}
    </div>

    {{-- Popover panel — positioned via Floating UI, focus-trapped --}}
{{-- Teleported to the overlay root at the end of <body>. `position: fixed` escapes a clipping ancestor but NOT a
     stacking context: a host with `contain: layout`, a transform or a filter scopes
     this panel's z-index inside itself, and anything painted after that ancestor
     covers the panel however high the z-index goes. --}}
<template x-teleport="#wk-overlay-root">
    <div
        {{-- Theme marker — see docs/theming.md "Theme markers". --}}
        data-wk-popover
        x-ref="panel"
        x-show="isOpen"
        x-transition:enter="transition ease-out duration-[var(--transition-wk-duration)]"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-[var(--transition-wk-duration)]"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        {{-- No `x-on:click.outside` here. The factory closes on the release of a press that
             began and ended outside (utils/outside-release.js). A click is dispatched to the
             common ancestor of where the press began and ended, so a press beside the panel
             that a reader slides back into it would still read as a click outside. --}}
        role="dialog"
        {{-- Focusable, not tabbable. A panel of plain text has nothing else to hold the focus,
             so the trap focuses the panel itself, and it needs a negative tabindex for that.
             Written here rather than left to the trap, which would set it at runtime: a
             Livewire update patches the panel against this template and removes an attribute
             the template does not carry, and the focused panel would drop the focus to the
             page. --}}
        tabindex="-1"
        {{-- The panel traps focus and closes on Escape, which IS the modal contract — but
             without this attribute assistive technology is told the page behind stays
             reachable, so a screen reader keeps offering content its own virtual cursor can
             no longer get back out of. The two halves have to agree. --}}
        aria-modal="true"
        aria-label="{{ $label ?? __('wirekit::Popover') }}"
        class="{{ $panelClasses }}"
        x-cloak
    >
        {{ $slot }}
    </div>
</template>
</div>
