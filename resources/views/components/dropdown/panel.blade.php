{{-- optimistic-ui: n/a — client-only
     Its own state is which row currently holds focus, and that is not a value a server
     owns — there is nothing to anticipate and nothing to roll back. The rows themselves
     may each carry an optimistic action; that belongs to the row, not to the container.

     It said "presentational" until the panel took over the menu's keyboard model, and the
     guard refused it: an element carrying a keydown handler is interactive, so "renders no
     interactive element" stopped being true the moment the binding landed. The claim is
     measured against the file rather than trusted, which is the point of that arm. --}}
@props([
    'width' => config('wirekit.components.dropdown.panel.width', 'auto'),
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('dropdown.panel', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // Panel classes — elevated surface with shadow and border
    // Uses `fixed` positioning so the panel escapes ancestor `overflow: hidden` containers
    // (cards, scroll panels, docs preview boxes). Floating UI uses strategy: 'fixed' to match.
    $classes = WireKit::resolveClasses('dropdown.panel', 'base', implode(' ', [
        'fixed',
        'z-[var(--z-wk-dropdown)]',
        'min-w-[12rem]',
        'py-[var(--padding-wk-y-xs)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-md)]',
        'shadow-[var(--shadow-wk-md)]',
        'overflow-y-auto',  // fitViewport caps the height; the panel scrolls its excess instead of clipping
    ]), $scope);

    // Width handling — 'auto' uses min-width only, 'trigger' matches trigger width
    $widthStyle = match ($width) {
        'auto' => '',
        'trigger' => 'min-width: 100%;',
        default => "width: {$width};",
    };
@endphp

{{-- Dropdown panel — positioned by Floating UI, shown/hidden via Alpine.
     The id is bound dynamically from the parent's data-wk-panel-id for aria-controls.
     Only the ENTER transition is animated — the panel disappears instantly on close.
     That is the prevailing convention — a menu opens with a little motion and dismisses
     the instant it is asked to — and it avoids a race where Alpine's ~150ms leave
     transition leaves the panel visible long enough to break synchronous browser test
     assertions like `assertDontSee`. --}}
{{-- Teleported to <body>: `position: fixed` escapes a clipping ancestor but not a
     STACKING context. A host with `contain: layout`, a transform or a filter scopes
     this panel's z-index inside itself, and anything painted after it covers the
     menu however high the z-index goes — reported from the documentation site,
     where the open menu rendered under the code block below the preview. --}}
<template x-teleport="#wk-overlay-root">
<div
    {{-- Theme marker. A theme dresses surfaces by querying for these, and this
         panel had none — so a Cupertino-style glass map listing it reached
         nothing, silently, for as long as the map existed. A selector that
         matches zero elements throws nothing and looks identical to one that
         works. See docs/theming.md "Theme markers". --}}
    data-wk-dropdown-panel
    {{-- THE MORPH KEY, and it is what makes writing the id safe again.
         Livewire resolves a node's morph identity as `wire:id`, then `wire:key`,
         then `el.id`. With none of the first two present the id WAS the key — so
         the moment JavaScript wrote one, the live node's key stopped matching the
         incoming template's empty one, and a key mismatch does not patch, it
         SWAPS: `swapElements` inserts a native `cloneNode(true)`, which copies
         attributes and children and no Alpine expandos. The replacement arrived
         with no `_x_dataStack`, so `x-show="open"` resolved `open` on the global
         object — where it is `window.open`, a function, and therefore truthy.
         The panel showed itself, and applying a native function with the scope
         proxy as `this` raised `Illegal invocation`.
         A STATIC value on purpose. The key only has to be the same on both sides
         of one comparison — the morph patches a teleported node against its own
         counterpart, one to one — so it must NOT carry the per-render id, which
         is exactly the value that cannot agree across two renders. --}}
    wire:key="wk-dropdown-panel"
    x-ref="panel"
    {{-- The menu keyboard model is bound HERE, on the panel, and not only on the wrapper.

         `x-teleport` MOVES this element to `#wk-overlay-root`; it does not re-route the
         events it fires. So once focus is on a menu item, a keydown bubbles from here to
         the overlay root and on to the document, and never passes through the dropdown
         wrapper at all — where `x-on:keydown="handleKeydown"` was the only binding. Arrow
         keys, Home and End did nothing, in EVERY dropdown, for as long as the panel has
         teleported. The wrapper binding is kept because it is what serves a keypress made
         while focus is still on the trigger.

         The Escape handler two files over already documents this exact mechanism from a
         different angle — it is bound `.window` and works. This is the same fix applied to
         the keys that stayed broken.

         It cannot double-fire: a node outside the wrapper cannot bubble through it. --}}
    x-on:keydown="handleKeydown"
    x-show="open"
    {{-- The id is set from the factory (`_applyPanelId`), not bound here.
         `x-bind:id="panelId"` read the value out of the Alpine scope, which is right
         across the TELEPORT and wrong across a Livewire MORPH: on every morph the
         binding was re-evaluated in a scope that no longer had `panelId`, and threw.
         And a JavaScript error during a morph ends the pass — so whatever came after it
         silently did not run, with nothing turning red. --}}
    x-transition:enter="transition ease-out"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    x-cloak
    role="menu"
    {{ $attributes->merge($widthStyle ? ['style' => $widthStyle] : [])->class([$classes]) }}
>
    {{ $slot }}
</div>
</template>
