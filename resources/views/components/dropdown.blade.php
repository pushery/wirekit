{{-- optimistic-ui: n/a — client-only
     Its state is open state. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'placement' => config('wirekit.components.dropdown.placement', 'bottom-start'),
    'offset' => config('wirekit.components.dropdown.offset', 8),
    // Pin the panel id across renders. `alert-dialog`, `drawer` and `lightbox` all
    // carry this escape hatch; the dropdown was the one overlay without it, which is
    // why a Livewire round trip could not be made survivable from the call site.
    'name' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('dropdown', $attributes->getAttributes());

    // STABLE across re-renders, which the old `Str::random(12)` was not — and the
    // panel is the one place where that costs everything.
    //
    // The panel teleports out of the document flow, so it lives OUTSIDE the subtree Livewire
    // morphs. A fresh id per render therefore updates the trigger's aria-controls
    // and the x-data scope while the panel still in the document carries the id
    // from the render before it. The two halves stop pointing at each other, and
    // nothing in the markup looks wrong: both sides are well-formed, they simply
    // name different things. `progress` had the identical defect for the identical
    // reason and its comment says so — inside a wire:poll region every poll minted
    // a new id.
    //
    // NOT uniqid(): microsecond resolution, so two dropdowns rendered in the same
    // microsecond shared one id. Measured on the split-button preview — two roots,
    // one DOM id, and a single click left BOTH panels open. `DomId::unique` keeps
    // that property while deriving from the caller's `name` when there is one.
    $panelId = \Pushery\WireKit\Support\DomId::unique(
        $name ? $name.'-panel' : null,
        'wk-dropdown-panel-'
    );

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
    {{-- panelId travels through the Alpine SCOPE, not the DOM. The panel is
         teleported out of the document flow to escape a host stacking context, and Alpine keeps
         the scope across that move while `closest()` does not — the panel used to
         read this id off `data-wk-panel-id` with an ancestor walk, which returns
         null the moment the element leaves the component. --}}
    x-data="wirekitDropdown({ placement: {{ \Pushery\WireKit\Support\AlpinePayload::string($placement) }}, offset: {{ (int) $offset }}, panelId: {{ \Pushery\WireKit\Support\AlpinePayload::string($panelId) }} })"
    x-on:keydown="handleKeydown"
    x-on:keydown.escape.window="open && close()"
    x-on:click.outside="close()"
    {{-- The same exact-match trap as `_getItems()`: `[role=menuitem]` does not match a
         `menuitemradio` or `menuitemcheckbox` row, so a radio menu did not match here
         either. It still closed — but by ACCIDENT, because the panel is teleported outside
         this wrapper and a click inside it therefore counts as `click.outside`. Resting a
         documented behavior on a side effect of the teleport is one refactor away from a
         silent regression, so all three roles are named. --}}
    x-on:click="$event.target.closest('[role=menuitem]:not([aria-disabled=true]), [role=menuitemradio]:not([aria-disabled=true]), [role=menuitemcheckbox]:not([aria-disabled=true])') && close()"
    data-wk-panel-id="{{ $panelId }}"
    {{ $attributes->class([$classes]) }}
>
    @isset($trigger)
        {{-- Quick form: <x-slot:trigger> provided. Auto-wrap trigger +
             default slot in the canonical sub-component shells so the
             developer doesn't repeat the trigger/panel composition.

             MIXING THE TWO FORMS used to wrap a panel around a panel, silently.
             A call site naming BOTH `<x-slot:trigger>` and an explicit
             `<x-wirekit::dropdown.panel>` got two nested shells, two teleports
             and — because the id travels through the Alpine scope — the SAME id
             on both. The only signal was a duplicate-id accessibility violation
             two layers from the cause, which is what made it expensive: three
             hypotheses were tested and killed here before the documentation site
             read the served HTML and found two templates already in it.

             So the wrap is now conditional, and the mistake says so out loud in
             development. Silence was the defect; the second shell was only how
             it showed. --}}
        @php
            // The rendered slot, once, because touching a ComponentSlot twice
            // re-renders it. `data-wk-dropdown-panel` is the panel's own marker.
            $slotHtml = (string) $slot;
            $slotCarriesPanel = str_contains($slotHtml, 'data-wk-dropdown-panel');
        @endphp
        @if($slotCarriesPanel && config('app.debug'))
            @php
                // Gated on debug per the house rule: a developer warning never
                // reaches a production page.
                logger()->warning('[wirekit] dropdown: this call site uses <x-slot:trigger> AND an explicit <x-wirekit::dropdown.panel>. Pick one — the quick form wraps the default slot in a panel for you, so naming both nests a panel inside a panel and gives the two the same id.');
            @endphp
        @endif
        <x-wirekit::dropdown.trigger>{{ $trigger }}</x-wirekit::dropdown.trigger>
        @if($slotCarriesPanel)
            {{-- Already a panel. Wrapping it again is the defect. --}}
            {!! $slotHtml !!}
        @else
            <x-wirekit::dropdown.panel>{!! $slotHtml !!}</x-wirekit::dropdown.panel>
        @endif
    @else
        {{-- Explicit form: developer nests <x-wirekit::dropdown.trigger>
             and <x-wirekit::dropdown.panel> children directly. The default
             slot passes through unchanged — the explicit sub-components
             carry their own ARIA wiring. --}}
        {{ $slot }}
    @endisset
</div>
