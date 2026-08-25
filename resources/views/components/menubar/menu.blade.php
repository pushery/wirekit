{{-- optimistic-ui: n/a — sub-component
     Open state lives on the menubar. --}}
@props([
    'label' => '',
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('menubar.menu', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // Menu trigger button + dropdown panel container.
    $name = \Illuminate\Support\Str::slug($label);

    $triggerClasses = WireKit::resolveClasses('menubar.menu', 'trigger', implode(' ', [
        'px-[var(--padding-wk-x-sm)]',
        'py-[var(--padding-wk-y-xs)]',
        'cursor-pointer',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text)]',
        'rounded-[var(--radius-wk-sm)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'hover:bg-[var(--color-wk-bg-subtle)]',
        'focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
    ]), $scope);

    $panelClasses = WireKit::resolveClasses('menubar.menu', 'panel', implode(' ', [
        'fixed z-[var(--z-wk-dropdown)]',
        'min-w-[12rem]',
        'py-[var(--padding-wk-y-xs)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-md)]',
        'shadow-[var(--shadow-wk-md)]',
        'overflow-hidden',
    ]), $scope);
@endphp

<div {{ $attributes->class('relative') }}>
    {{-- Menu trigger --}}
    <button
        type="button"
        x-on:click="toggleMenu({{ \Pushery\WireKit\Support\AlpinePayload::string($name) }})"
        x-on:mouseenter="openMenu({{ \Pushery\WireKit\Support\AlpinePayload::string($name) }})"
        :aria-expanded="activeMenu === {{ \Pushery\WireKit\Support\AlpinePayload::string($name) }} ? 'true' : 'false'"
        aria-haspopup="menu"
        role="menuitem"
        data-wk-menubar-trigger="{{ $name }}"
        class="{{ $triggerClasses }}"
    >
        {{ $label }}
    </button>

    {{-- Dropdown panel. Wrapped in `<template x-teleport="#wk-overlay-root">` so the
         `position: fixed` panel escapes every ancestor `transform` /
         `contain` / `overflow: hidden` container and anchors against the
         true viewport — same pattern as Context-Menu, Modal, Drawer, and
         Tooltip. Without the teleport, a menubar rendered inside a
         transformed ancestor (a CSS `transform` establishes a containing
         block for fixed descendants) positioned its panel relative to that
         ancestor instead of the viewport, so the dropdown opened far from
         its trigger. Alpine's x-teleport preserves `x-ref` + `x-data` scope
         across the move, so `this.$refs['panel-<name>']` (used by the JS to
         position the panel and collect its items) keeps resolving and the
         per-instance ref namespace avoids the cross-menubar collision a
         global `data-wk-menubar-panel` selector would have. --}}
    <template x-teleport="#wk-overlay-root">
        <div
            x-ref="panel-{{ $name }}"
            x-show="activeMenu === {{ \Pushery\WireKit\Support\AlpinePayload::string($name) }}"
            {{-- Keydown is ALSO wired here, not only on the menubar root: once
                 the panel teleports out of the document flow it is no longer a DOM descendant
                 of the root, so arrow-key / Escape keydowns fired while a panel
                 item has focus would never bubble to the root's
                 x-on:keydown="handleKeydown". With the handler on the panel too,
                 in-menu keyboard navigation (WAI-ARIA menubar pattern) keeps
                 working after the teleport. Alpine's x-teleport preserves the
                 x-data scope, so handleKeydown resolves to the same component. --}}
            x-on:keydown="handleKeydown"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            data-wk-menubar-panel="{{ $name }}"
            role="menu"
            class="{{ $panelClasses }}"
            x-cloak
        >
            {{ $slot }}
        </div>
    </template>
</div>
