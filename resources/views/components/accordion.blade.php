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
    // Closed panels stay findable by the browser's find in page, which opens the one a match
    // lands in. Only where the engine supports it; elsewhere a closed panel is hidden as before.
    // Read by every item through @aware.
    'findable' => config('wirekit.components.accordion.findable', true),
    // Whether a panel animates its height open and shut. Read by every item through @aware.
    //
    // ⚠️ ON BY DEFAULT, AND THAT IS A CHANGE RATHER THAN A NEW SWITCH. The mechanism was always
    // there — `x-wk-findable` carries a `.collapse` modifier that animates the height, reads
    // `--transition-wk-duration` off the element, and skips itself under reduced motion — and
    // `collapsible`, the sibling disclosure in this same library, has been calling it all along.
    // The accordion simply did not, so a page showing both had one that eased and one that
    // jumped. Reported from a screen, because no suite can see it: nothing is broken.
    //
    // A switch defaulting to OFF would have closed the ticket without changing the screen it
    // came from. The escape hatch is here for a caller who wants the jump back, which is the
    // half that makes the default safe to move.
    'animate' => config('wirekit.components.accordion.animate', true),
    // Heading level each item's trigger is wrapped in (1–6). Defaults to 3, which
    // is what every accordion shipped before this prop existed. The ARIA authoring
    // practices require the level to be "appropriate for the information
    // architecture of the page", and that is a property of the PAGE, not of the
    // widget: an accordion sitting directly under the page <h1> wants level 2,
    // one inside an <h2> section wants 3, and a fixed <h3> produces a skipped or
    // flattened outline in every other position. Screen-reader heading navigation
    // is where that shows up, so nothing on screen reports it. Items read this
    // through @aware — the level belongs to the group, not to one row.
    'level' => 3,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // `findable="false"` on an unbound tag is the string "false", which is truthy.
    $findable = BooleanProp::from($findable, config('wirekit.components.accordion.findable', true));
    $animate = BooleanProp::from($animate, config('wirekit.components.accordion.animate', true));

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

    // `wk-accordion` is a marker, not a styled class, and it is load-bearing.
    //
    // The library's reduced-motion clamp matches a `wk-` CLASS TOKEN and its
    // descendants — deliberately a token and not a substring, because matching the
    // attribute as a substring once clamped a whole application's animations
    // through `bg-[var(--color-wk-bg)]` on its <body>. Every class this root emits
    // spells `wk-` inside an arbitrary value (`--border-wk-width`,
    // `--color-wk-border`), where the prefix is preceded by a dash and no branch of
    // the clamp can reach it. The chevron in accordion/item.blade.php makes a
    // half-turn on the themed transition duration, so for a reader who had asked
    // their operating system for no motion it kept turning — and
    // `data-wk-accordion-mode` is named in no motion rule, so nothing else reached
    // it either. The header buttons are all descendants of this element, which is
    // why one token here covers the whole subtree, and why it also brings the
    // component inside the `data-reduce-motion` escape hatch written against the
    // same selector. Same remedy, same reason as `wk-toggle`.
    //
    // Prepended OUTSIDE the match so it survives every variant: `flush` and
    // `separated` carry no chrome of their own, and a marker living in one arm of
    // three is a marker two variants do not have.
    $classes = WireKit::resolveClasses('accordion', 'base', 'wk-accordion '.$containerClasses, $scope);
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
    {{-- Arrow keys, Home and End move focus between the headers — the model the
         component's documented keyboard table promises. The handler sits on the
         root rather than on each button because the headers are rendered by the
         item sub-component, which cannot see its siblings; the keydown bubbles
         here, where the whole list is one query away. It reads the headers from
         the DOM on every press, so an item added or removed between two presses
         cannot leave focus pointing into a list that no longer exists. --}}
    x-on:keydown="handleKeydown($event)"
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
