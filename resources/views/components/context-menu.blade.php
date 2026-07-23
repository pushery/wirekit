@props([
    // When true (default) the panel teleports to <body> so it escapes every
    // ancestor stacking context, overflow:hidden, and `contain: layout`
    // container. This is the right default for 99% of production use cases
    // and it mirrors what Modal, Drawer, Alert-Dialog, Tooltip, and
    // Command-Palette already do. Set to false if the panel must stay inside
    // the component root — e.g. when you're embedding the context menu inside
    // a scoped stacking container that should contain the overlay itself
    // (rare, and usually an anti-pattern).
    'teleport' => true,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $teleport = BooleanProp::from($teleport, true);

    // Context Menu — right-click triggered dropdown menu.
    // Positions at cursor coordinates using a virtual Floating UI reference.
    $wrapperClasses = WireKit::resolveClasses('context-menu', 'wrapper', implode(' ', [
        'relative',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // min-w-48: 12rem floor so single-icon items still feel like a menu, not a tooltip.
    // max-w-[calc(100vw-1rem)] + w-max: panel grows to fit longest item (combined with
    // whitespace-nowrap on items) but never overflows the viewport. Without w-max the
    // shrink-to-fit behavior of fixed elements would cap at the longest unbreakable
    // run rather than the longest line.
    $panelClasses = WireKit::resolveClasses('context-menu', 'panel', implode(' ', [
        'fixed z-[var(--z-wk-dropdown)]',
        'min-w-48 max-w-[calc(100vw-1rem)] w-max',
        'py-[var(--padding-wk-y-xs)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-md)]',
        'shadow-[var(--shadow-wk-md)]',
        'overflow-hidden',
    ]), $scope);
@endphp

<div
    x-data="wirekitContextMenu()"
    x-on:keydown="handleKeydown"
    {{ $attributes->class([$wrapperClasses]) }}
>
    {{-- Trigger — the area that responds to right-click (desktop) and to a
         touch long-press / hold (touch devices, which have no right-click).
         The touch listeners are passive: they never block scrolling — a
         scroll/drag cancels the pending long-press instead. --}}
    <div
        x-on:contextmenu="openAt($event)"
        x-on:touchstart.passive="onTouchStart($event)"
        x-on:touchmove.passive="onTouchMove($event)"
        x-on:touchend.passive="onTouchEnd()"
        x-on:touchcancel.passive="onTouchEnd()"
        x-ref="trigger"
    >
        {{ $trigger }}
    </div>

    {{-- Context menu panel. Wrapped in `<template x-teleport="body">` by
         default so the fixed-positioned panel escapes every ancestor
         `overflow: hidden` / `contain: layout` / `transform` container and
         opens at true viewport coordinates — same pattern as Modal, Drawer,
         Alert-Dialog, Tooltip, and Command-Palette. Alpine's x-teleport
         preserves `x-ref` and `x-data` scope across the move, so
         `this.$refs.panel` and the items' `x-on:click="close()"` keep
         working transparently. `click.outside` lives on the panel (not the
         wrapper) so it fires correctly in both teleport modes — clicking
         anywhere that isn't the panel (including the trigger) closes the
         menu, which is the standard menu behavior. --}}
    @if($teleport)
    <template x-teleport="body">
    @endif
        <div
            x-ref="panel"
            x-show="open"
            x-on:click.outside="close()"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            role="menu"
            class="{{ $panelClasses }}"
            x-cloak
        >
            {{ $slot }}
        </div>
    @if($teleport)
    </template>
    @endif
</div>
