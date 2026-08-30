{{-- optimistic-ui: n/a — client-only
     Its state is which panels are open. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'mode' => config('wirekit.components.accordion.mode', 'single'),
    // Visual treatment of the container + items:
    //   - 'bordered'  → outer border + rounded card + row dividers + elevated bg
    //                   (default; byte-identical to the pre-variant look).
    //   - 'flush'     → no outer chrome, just hair-line row dividers — for an
    //                   FAQ that sits inline in page content.
    //   - 'separated' → each item is its own standalone card with a gap between
    //                   them (the container draws nothing; items carry the chrome).
    'variant' => config('wirekit.components.accordion.variant', 'bordered'),
    // Row density. 'md' is the default trigger/panel padding; 'lg' is roomier
    // (larger padding + trigger text) for marketing / spacious layouts.
    'size' => config('wirekit.components.accordion.size', 'md'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('accordion', $attributes->getAttributes());

    // Accordion container — visually a vertically stacked list with dividers.
    // `mode` controls whether multiple panels can be open at once:
    //   - 'single'   → opening one closes the others (like radio buttons)
    //   - 'multiple' → any combination of panels can be open (like checkboxes)
    // The mode reaches the behavior through the `x-data` payload below, and ONLY through it.
    //
    // ⚠️ THIS COMMENT USED TO SAY the mode is exposed via `data-wk-accordion-mode` "so that the
    // accordion.item sub-component can read it at click-time". It does not and never did:
    // nothing in `resources/js` reads that attribute, in either the literal or the
    // `dataset.wkAccordionMode` spelling. Measured against the real factory — given the
    // attribute and no config the component behaved as `single`; given the config and no
    // attribute it behaved as `multiple`.
    //
    // The attribute itself is harmless and stays: the suite pins it and it is a usable hook for
    // a developer's own CSS. The sentence was the damage. A maintainer trimming the `x-data`
    // payload because "the attribute already carries the mode" would have broken the component
    // while reading a comment that told them it was safe.
    //
    // Container classes are variant-driven. `bordered` keeps the original card
    // look; `flush` strips the chrome to just row dividers; `separated` turns
    // the container into a gapped stack and lets each item own its card chrome.
    $variant = in_array($variant, ['bordered', 'flush', 'separated'], true) ? $variant : 'bordered';
    $containerClasses = match ($variant) {
        'flush' => implode(' ', [
            'divide-y-[length:var(--border-wk-width)]',
            'divide-[var(--color-wk-border)]',
        ]),
        'separated' => implode(' ', [
            'flex flex-col',
            'gap-[var(--padding-wk-y-sm)]',
        ]),
        default => implode(' ', [
            'border-[length:var(--border-wk-width)]',
            'border-[var(--color-wk-border)]',
            'rounded-[var(--radius-wk-lg)]',
            'divide-y-[length:var(--border-wk-width)]',
            'divide-[var(--color-wk-border)]',
            'overflow-hidden',
            'bg-[var(--color-wk-bg-elevated)]',
        ]),
    };
    $classes = WireKit::resolveClasses('accordion', 'base', $containerClasses, $scope);
@endphp

{{-- Accordion root — holds the mode flag and exposes a tiny Alpine API:
     `opened` is an array of currently open item ids. Child items access
     toggle()/isOpen() directly via Alpine's scope chain inheritance. --}}
<div
    {{-- Which panels are open lives in resources/js/components/accordion.js.
         It cannot live here: an inline literal cannot declare methods under
         Alpine's CSP build, and the spread and arrow function inside them are
         out of its grammar too — no panel opened under a strict policy. The
         mode is a validated enum, so it goes in as a plain quoted literal
         rather than through {{ \Pushery\WireKit\Support\AlpinePayload::from() }}. --}}
    x-data="wirekitAccordion({ mode: {{ \Pushery\WireKit\Support\AlpinePayload::string($mode) }} })"
    data-wk-accordion-mode="{{ $mode }}"
    {{-- WAI-ARIA 1.2 forbids author naming on an element with an implicit
         role="generic": a bare <div> carrying aria-label is not reliably exposed
         by assistive technology, and axe reports aria-prohibited-attr. So the
         name a caller asks for was silently not arriving.

         `role="group"` is added ONLY when a name is actually present. Naming it
         unconditionally would push an empty group into the accessibility tree of
         every plain accordion, which is noise rather than structure. --}}
    @if(($attributes->get('aria-label') ?? $attributes->get('aria-labelledby')) !== null)
        role="group"
    @endif
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</div>
