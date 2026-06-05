@props([
    'scope' => null,
    // Optional fallback aria-label used when the trigger has no accessible
    // name (icon-only triggers, responsive layouts that hide the visible
    // label below sm). Defaults to "Open menu". Explicit aria-label /
    // aria-labelledby on the inner button or an sr-only span both win
    // over this fallback — see x-init below.
    'ariaLabelFallback' => 'Open menu',
])

@php
    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('dropdown.trigger', 'base', '', $scope);
@endphp

{{-- Dropdown trigger wrapper — positioning reference for Floating UI.

     ARIA attributes (aria-haspopup, aria-expanded, aria-controls) are applied
     to the INNER interactive element (button/link) via x-init, NOT to this
     wrapper div. The ARIA spec requires these attributes to live on elements
     with interactive roles; placing them on a generic <div> fails axe-core's
     aria-allowed-attr rule.

     Auto-aria-label: when the inner button has no accessible name (no
     aria-label, no aria-labelledby, no visible OR sr-only text content),
     we inject `ariaLabelFallback` ("Open menu" by default). This catches
     icon-only triggers and responsive layouts where the visible label is
     hidden below the `sm` breakpoint. Explicit developer-side labels on
     the inner button always win — the auto-inject only fires on the
     empty-name path. --}}
<div
    x-ref="trigger"
    x-on:click="toggle()"
    x-init="(() => {
        const interactive = $el.querySelector('button, [role=button], a');
        if (!interactive) {
            // eslint-disable-next-line no-console
            console.warn('[wirekit] dropdown.trigger: slot has no focusable element (button/link). Keyboard users cannot open the dropdown. Wrap the trigger content in a <button>.');
            return;
        }
        interactive.setAttribute('aria-haspopup', 'menu');
        const panelId = $el.closest('[data-wk-panel-id]')?.dataset.wkPanelId;
        if (panelId) interactive.setAttribute('aria-controls', panelId);
        interactive.setAttribute('aria-expanded', 'false');
        $watch('open', value => interactive.setAttribute('aria-expanded', value ? 'true' : 'false'));

        // Auto-derive aria-label when the interactive child has no
        // accessible name. Heuristic: no aria-label, no aria-labelledby,
        // AND empty trimmed textContent (covers both visible-text and
        // sr-only spans — textContent includes screen-reader-only nodes).
        const hasLabel = interactive.hasAttribute('aria-label');
        const hasLabelledBy = interactive.hasAttribute('aria-labelledby');
        const hasText = (interactive.textContent || '').trim().length > 0;
        if (!hasLabel && !hasLabelledBy && !hasText) {
            interactive.setAttribute('aria-label', @js($ariaLabelFallback));
        }
    })()"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</div>
