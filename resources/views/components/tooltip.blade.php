@props([
    'text' => null,
    'placement' => config('wirekit.components.tooltip.placement', 'top'),
    'offset' => config('wirekit.components.tooltip.offset', 6),
    'delayShow' => config('wirekit.components.tooltip.delay-show', 300),
    'delayHide' => config('wirekit.components.tooltip.delay-hide', 100),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

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
        'text-[var(--color-wk-tooltip-text)]',
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
    <div x-ref="trigger" aria-describedby="{{ $tooltipId }}">
        {{ $slot }}
    </div>

    {{-- Tooltip panel — rendered via x-teleport to body for proper stacking --}}
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
</div>
