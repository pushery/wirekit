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

    // z-index: tooltip-level (60) so the panel stays above other dropdown/sticky
    // chrome on the page when the user interacts with anything else while the
    // popover is open (matches hover-card and tooltip stacking).
    // Width: min-w-72 instead of fixed w-72 so the panel grows to fit content
    // wider than 18 rem (e.g. long share URLs in input fields) instead of clipping.
    $panelClasses = WireKit::resolveClasses('popover', 'panel', implode(' ', [
        'fixed z-[var(--z-wk-tooltip)]',
        'min-w-72 max-w-[calc(100vw-1rem)] w-max',
        'rounded-[var(--radius-wk-lg)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'shadow-[var(--shadow-wk-lg)]',
        'p-[var(--padding-wk-x-md)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);

    // Applied AFTER the resolved classes rather than by removing the padding utility from
    // the list above: a personalization may have replaced that list wholesale, and a
    // caller asking for an unpadded panel means it whatever the theme did.
    $padded = BooleanProp::from($padded, true);
    $paddingClasses = $padded ? '' : 'p-0';
@endphp

<div
    x-data="wirekitPopover({ placement: {{ \Pushery\WireKit\Support\AlpinePayload::string($placement) }}, offset: {{ (int) $offset }} })"
    x-on:click.outside="close()"
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
{{-- Teleported to <body>. `position: fixed` escapes a clipping ancestor but NOT a
     stacking context: a host with `contain: layout`, a transform or a filter scopes
     this panel's z-index inside itself, and anything painted after that ancestor
     covers the panel however high the z-index goes. Reported from the documentation
     site for the sibling components; fixed here at the same time rather than waiting
     for the same screenshot a third time. --}}
<template x-teleport="#wk-overlay-root">
    <div
        {{-- Theme marker — see docs/theming.md "Theme markers". --}}
        data-wk-popover
        x-ref="panel"
        x-show="open"
        x-transition:enter="transition ease-out duration-[var(--transition-wk-duration)]"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-[var(--transition-wk-duration)]"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        role="dialog"
        aria-label="{{ $label ?? __('Popover') }}"
        class="{{ $panelClasses }} {{ $paddingClasses }}"
        x-cloak
    >
        {{ $slot }}
    </div>
</template>
</div>
