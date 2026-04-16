@props([
    'placement' => 'bottom',
    'offset' => 8,
    'delayShow' => 300,
    'delayHide' => 200,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Hover Card — rich tooltip-like overlay that shows on hover/focus.
    // Unlike tooltip, hover cards display structured content (avatar, bio, actions).
    // Uses Floating UI for positioning and role="dialog" for a11y.
    $wrapperClasses = WireKit::resolveClasses('hover-card', 'wrapper', implode(' ', [
        'relative inline-block',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    $panelClasses = WireKit::resolveClasses('hover-card', 'panel', implode(' ', [
        'fixed z-[var(--z-wk-tooltip)]',
        'w-72',
        'rounded-[var(--radius-wk-lg)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'shadow-[var(--shadow-wk-lg)]',
        'p-[var(--padding-wk-x-md)]',
        'text-[length:var(--text-wk-md)]',
        'text-[var(--color-wk-text)]',
    ]), $scope);
@endphp

<span
    x-data="wirekitHoverCard({ placement: '{{ $placement }}', offset: {{ $offset }}, delayShow: {{ $delayShow }}, delayHide: {{ $delayHide }} })"
    {{ $attributes->class([$wrapperClasses]) }}
>
    {{-- Trigger element --}}
    <span
        x-ref="trigger"
        @mouseenter="mouseenter()"
        @mouseleave="mouseleave()"
        @focusin="focusin()"
        @focusout="focusout()"
        aria-haspopup="dialog"
        :aria-expanded="open ? 'true' : 'false'"
    >
        {{ $trigger }}
    </span>

    {{-- Hover card panel — positioned via Floating UI --}}
    <div
        x-ref="panel"
        x-show="open"
        x-transition:enter="transition ease-out duration-[var(--transition-wk-duration)]"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-[var(--transition-wk-duration)]"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @mouseenter="mouseenter()"
        @mouseleave="mouseleave()"
        @keydown.escape.prevent="close()"
        role="dialog"
        aria-label="Hover card"
        class="{{ $panelClasses }}"
        x-cloak
    >
        {{ $slot }}
    </div>
</span>
