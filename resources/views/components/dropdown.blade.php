@props([
    'placement' => config('wirekit.components.dropdown.placement', 'bottom-start'),
    'offset' => config('wirekit.components.dropdown.offset', 8),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Generate unique ID for aria-controls link between trigger and panel
    $panelId = 'wk-dropdown-panel-' . uniqid();

    // Base wrapper classes — relative positioning context for floating panel
    $classes = WireKit::resolveClasses('dropdown', 'base', 'relative inline-block', $scope);
@endphp

{{-- Alpine dropdown component with Floating UI positioning.
     Auto-close on item click: event delegation catches bubbled clicks on any
     `[role="menuitem"]` descendant and closes the dropdown. This matches the
     standard WAI-ARIA menu pattern (GitHub, Linear, every OS menu) — activating
     a menu item dismisses the menu. User @click handlers on items run first
     (event target phase), then this wrapper handler runs (bubble phase), so the
     user's action is already applied when close() fires. Disabled items are
     filtered out via :not([aria-disabled="true"]).

     ESC is handled at WINDOW level (not on the wrapper) so it works regardless
     of where focus currently sits. Background: Playwright's `locator.press()`
     on the non-focusable panel `<div>` moves focus to `document.body` in some
     headless environments, so the keydown never bubbles up to a wrapper-level
     listener. Attaching to `window` avoids the race entirely and also gives
     users the conventional "ESC closes menu from anywhere" UX.

     Two composition forms supported (use ONE, not both):
       1. Named-slot quick form — provide <x-slot:trigger>...</x-slot:trigger>
          and the default slot becomes the panel content. The parent
          auto-wraps trigger + panel sub-components with their ARIA wiring.
       2. Explicit form — nest <x-wirekit::dropdown.trigger> +
          <x-wirekit::dropdown.panel> children directly. Full control over
          sub-component props (width, scope, etc.). --}}
<div
    x-data="wirekitDropdown({ placement: '{{ $placement }}', offset: {{ (int) $offset }} })"
    x-on:keydown="handleKeydown"
    x-on:keydown.escape.window="open && close()"
    x-on:click.outside="close()"
    x-on:click="$event.target.closest('[role=menuitem]:not([aria-disabled=true])') && close()"
    data-wk-panel-id="{{ $panelId }}"
    {{ $attributes->class([$classes]) }}
>
    @isset($trigger)
        {{-- Quick form: <x-slot:trigger> provided. Auto-wrap trigger +
             default slot in the canonical sub-component shells so the
             developer doesn't repeat the trigger/panel composition. --}}
        <x-wirekit::dropdown.trigger>{{ $trigger }}</x-wirekit::dropdown.trigger>
        <x-wirekit::dropdown.panel>{{ $slot }}</x-wirekit::dropdown.panel>
    @else
        {{-- Explicit form: developer nests <x-wirekit::dropdown.trigger>
             and <x-wirekit::dropdown.panel> children directly. The default
             slot passes through unchanged — the explicit sub-components
             carry their own ARIA wiring. --}}
        {{ $slot }}
    @endisset
</div>
