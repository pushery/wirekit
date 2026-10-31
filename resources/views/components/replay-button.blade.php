{{-- optimistic-ui: n/a — client-only
     Its state is whether the animation is replaying. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    // Accessible label for the button. Default is the well-known
    // "Replay" verb so screen readers announce a clear affordance.
    'label' => __('wirekit::Replay'),

    // Optional explicit data-replay-target selector when the closest
    // ancestor isn't the right re-mount root. Pass a CSS selector and
    // the click handler walks up via $el.closest(selector) instead.
    'target' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('replay-button', $attributes->getAttributes());

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables and
    // `autofocus="false"` focuses — the opposite of what the call site says, with no error
    // either way. Strip such flags when their value reads as false, before the bag is printed.
    $attributes = \Pushery\WireKit\Support\BooleanProp::stripFalseHtmlFlags($attributes);

    // A caller's listener for an event this view listens to on the element the bag lands on
    // goes in the other spelling, so both run (Support\CallerListeners).
    $attributes = \Pushery\WireKit\Support\CallerListeners::beside($attributes, ['x-on:click']);

    // A caller's `x-ref` belongs to the caller's component, and this bag lands on our root,
    // which would keep it: CallerRef::onRoot() hands it to the root above.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);

    // The accessible name. Words in the slot are the name a reader sees, and voice control
    // reaches the button only by them (WCAG 2.5.3, Label in Name): the label names the button
    // where the slot shows no words, an icon alone, and where it holds the words it shows. Where
    // it holds other words, the visible text names the button. A caller's `aria-label` wins.
    $slotText = \Pushery\WireKit\Support\SlotContent::plain($slot);
    $ariaLabel = match (true) {
        $slotText === '' => $label,
        $label !== null && str_contains((string) $label, $slotText) => $label,
        default => null,
    };
@endphp

{{--
    Replay button — re-mounts the closest [data-replay-target] ancestor
    by replacing its innerHTML with the snapshot stored in
    data-replay-source (set on initial mount), then calling
    Alpine.initTree() to re-bind the Alpine x-data scopes.

    Pairs with the `data-replayable="true"` contract emitted by every
    WireKit component whose preview can be re-run or reset: animation-
    capable components (reveal, stat[animate], card/hero/cta/feature/
    footer[animateIn], chart) AND state-mutating components whose demo is
    "used up" by interaction (badge[dismissible], alert[dismissible]).
    The docs site's preview wrapper sets data-replay-target on the iframe
    / preview root and uses this button to give developers a "↻" affordance
    to re-run or reset the demo.

    Style hooks via `wk-replay-button` BEM root — matches the existing
    `wk-*` namespace convention used by reading-progress, scrollbar etc.

    Default icon: a circular-arrow glyph (16×16). Developers can override
    by passing slot content.
--}}
{{-- The kit's focus ring, which follows the ring tokens; the browser's follows none. --}}
<button
    {{ $attributes->merge([
        'type' => 'button',
        'class' => 'wk-replay-button focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] '.\Pushery\WireKit\WireKit::resolveClasses('replay-button', 'base', ''),
        'aria-label' => $ariaLabel,
    ]) }}
    @if($target)
        data-replay-target-selector="{{ $target }}"
    @endif
    {{-- The replay lives in resources/js/components/replay-button.js: two
         declarations, an early return and a constructed event, none of which
         Alpine's CSP build parses. The button had no scope of its own before —
         it gains one because a directive that cannot hold statements has to
         call something. --}}
    x-data="wirekitReplayButton"
    x-on:click="replay()"
>
    {{-- Default circular-arrow icon when no slot content is given. `@isset($slot)`
         is always true (an empty slot is still a ComponentSlot), so the default
         must be gated on the slot being EMPTY, not unset — otherwise the icon
         never renders for `<x-wirekit::replay-button />`. --}}
    @if(! $slot->hasActualContent())
        <svg
            aria-hidden="true"
            viewBox="0 0 24 24"
            width="16"
            height="16"
            fill="currentColor"
        >
            <path d="M12 5V2L8 6l4 4V7a5 5 0 1 1-5 5H5a7 7 0 1 0 7-7Z"/>
        </svg>
    @else
        {{ $slot }}
    @endif
</button>