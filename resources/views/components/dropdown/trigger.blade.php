{{-- optimistic-ui: n/a — client-only
     Its state is the parent's open state. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'scope' => null,
    // Optional fallback aria-label used when the trigger has no accessible
    // name (icon-only triggers, responsive layouts that hide the visible
    // label below sm). Defaults to "Open menu". Explicit aria-label /
    // aria-labelledby on the inner button or an sr-only span both win
    // over this fallback — see x-init below.
    'ariaLabelFallback' => 'Open menu',
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('dropdown.trigger', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('dropdown.trigger', 'base', '', $scope);
@endphp

{{-- Dropdown trigger wrapper — positioning reference for Floating UI.

     ARIA attributes (aria-haspopup, aria-expanded, aria-controls) are applied
     to the INNER interactive element (button/link) via x-init, NOT to this
     wrapper div. The ARIA spec requires these attributes to live on elements
     with interactive roles; placing them on a generic <div> fails axe-core's
     aria-allowed-attr rule.

     Auto-aria-label: when the inner button has no accessible name (no
     aria-label, no aria-labelledby, no visible OR sr-only text content),
     we inject `ariaLabelFallback` ("Open menu" by default). This catches
     icon-only triggers and responsive layouts where the visible label is
     hidden below the `sm` breakpoint. Explicit developer-side labels on
     the inner button always win — the auto-inject only fires on the
     empty-name path. --}}
<div
    x-ref="trigger"
    x-on:click="toggle()"
    {{-- The ARIA wiring lives in resources/js/components/dropdown-trigger.js,
         over the same util popover and hover-card use. It was a third copy of
         that logic and had already drifted; it was also an IIFE with two
         declarations and an early return, which Alpine's CSP build parses none
         of — so under a strict policy the trigger got no ARIA at all. It still
         opened, and announced itself as a button that leads nowhere. --}}
    x-data="wirekitDropdownTrigger({ labelFallback: {{ \Pushery\WireKit\Support\AlpinePayload::from($ariaLabelFallback) }} })"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</div>
