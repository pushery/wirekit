@props([
    'scope' => null,
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
     aria-allowed-attr rule. --}}
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
    })()"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</div>
