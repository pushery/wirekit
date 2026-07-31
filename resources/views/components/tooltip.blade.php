{{-- optimistic-ui: n/a — client-only
     Its state is visibility and placement. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'text' => null,
    'placement' => config('wirekit.components.tooltip.placement', 'top'),
    'offset' => config('wirekit.components.tooltip.offset', 6),
    'delayShow' => config('wirekit.components.tooltip.delay-show', 300),
    'delayHide' => config('wirekit.components.tooltip.delay-hide', 100),
    // Make the trigger keyboard-focusable so the tooltip is reachable by keyboard,
    // not just hover (WCAG 2.1.1). Default true covers the common case of a tooltip
    // on a NON-interactive slot (an icon, a text span). Set false when the slot is
    // already interactive (a button/link) to avoid a double tab-stop — the slot's own
    // focus then bubbles to the trigger and still shows the tooltip.
    'focusableTrigger' => true,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $focusableTrigger = BooleanProp::from($focusableTrigger, true);

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('tooltip', $attributes->getAttributes());

    // Generate unique ID for ARIA association between trigger and tooltip
    $tooltipId = 'wk-tooltip-' . uniqid();

    // Tooltip panel classes — inverted colors, small rounded box
    // w-max ensures the tooltip sizes to its content (not the trigger width)
    // Uses `fixed` positioning so the tooltip escapes ancestor `overflow: hidden` containers.
    // Floating UI uses strategy: 'fixed' to position relative to the viewport.
    $tooltipClasses = WireKit::resolveClasses('tooltip', 'panel', implode(' ', [
        'fixed',
        'w-max',
        'z-[var(--z-wk-tooltip)]',
        'max-w-[var(--size-wk-tooltip-max)]',
        'px-[var(--padding-wk-x-sm)]',
        'py-[var(--padding-wk-y-xs)]',
        'bg-[var(--color-wk-tooltip-bg)]',
        'text-[color:var(--color-wk-tooltip-text)]',
        'text-[length:var(--text-wk-sm)]',
        'font-[family-name:var(--font-wk-sans)]',
        'rounded-[var(--radius-wk-sm)]',
        'shadow-[var(--shadow-wk-md)]',
        'pointer-events-none',
    ]), $scope);
@endphp

{{-- Tooltip wrapper — handles hover, focus, touch, and keyboard events --}}
<div
    x-data="wirekitTooltip({
        placement: '{{ $placement }}',
        offset: {{ (int) $offset }},
        delayShow: {{ (int) $delayShow }},
        delayHide: {{ (int) $delayHide }}
    })"
    x-on:mouseenter="mouseenter()"
    x-on:mouseleave="mouseleave()"
    x-on:focusin="focusin()"
    x-on:focusout="focusout()"
    x-on:pointerdown="pointerdown($event)"
    x-on:pointerup="pointerup($event)"
    x-on:pointerleave="pointerleave($event)"
    x-on:keydown.escape="keydownEscape()"
    {{ $attributes->class(['relative inline-block']) }}
>
    {{-- Trigger element — linked to tooltip via aria-describedby --}}
    <div x-ref="trigger" aria-describedby="{{ $tooltipId }}" @if($focusableTrigger) tabindex="0" @endif>
        {{ $slot }}
    </div>

    {{-- Tooltip panel, teleported to <body>.

         The comment above this block claimed the teleport for a long time while
         the markup had none, and the gap was expensive: a tooltip inside a
         `<x-wirekit::scroll-area fade="…">` was CUT OFF at the scroll area's
         edge. The panel is `position: fixed` and so escapes overflow clipping —
         but `fade` works by `mask-image`, and a mask applies to the whole
         rendered subtree, fixed descendants included. Measured 18.5 px of the
         panel missing above the bar.

         The stylesheet already carried an escape hatch for this
         (`.wk-scroll-fade…:focus-within { mask-image: none }`) and it could
         never fire here: a tooltip opens on HOVER, and focus-within does not
         see a hover.

         Teleporting fixes it at the root rather than widening that hatch — the
         panel leaves the masked subtree entirely, which also settles every
         other stacking-context case (a clipping card, a transformed ancestor,
         an `isolation: isolate` wrapper) in one move.

         `$refs` survive the teleport: Alpine registers the ref in the ORIGINAL
         scope, so the positioning code that reads `this.$refs.tooltip` is
         unaffected. --}}
    <template x-teleport="body">
    <div
        x-ref="tooltip"
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-cloak
        role="tooltip"
        id="{{ $tooltipId }}"
        class="{{ $tooltipClasses }}"
    >
        {{-- Rich content slot or plain text --}}
        @if(isset($content))
            {{ $content }}
        @else
            {{ $text }}
        @endif
    </div>
    </template>
</div>
