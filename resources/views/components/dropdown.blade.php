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
     users the conventional "ESC closes menu from anywhere" UX. --}}
<div
    x-data="wirekitDropdown({ placement: '{{ $placement }}', offset: {{ (int) $offset }} })"
    x-on:keydown="handleKeydown"
    x-on:keydown.escape.window="open && close()"
    x-on:click.outside="close()"
    x-on:click="$event.target.closest('[role=menuitem]:not([aria-disabled=true])') && close()"
    data-wk-panel-id="{{ $panelId }}"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</div>
