{{-- optimistic-ui: n/a — client-only
     Its only Alpine is a development-time warning about misuse. --}}
@props([
    'variant' => config('wirekit.components.card.variant', 'outlined'),
    'as' => 'div',
    'href' => null,
    // Optional reveal animation when card scrolls into view. One of 11 base presets
    // (or any -in / -out variant). Null = no animation (default, v1.5.0-identical).
    'animateIn' => null,
    // What the card does with a child that reaches past its box. The default clips,
    // which is what makes the rounded corners cut the content inside them — but it
    // also clips a badge pinned to a corner, an avatar that scales on hover, or a
    // focus ring that extends past the edge. `visible` releases all of it.
    //
    // A prop rather than an !important utility override on the base class, because that
    // override is not the same thing: it removes the corner clipping for EVERY
    // child, and nothing says so until someone drops a full-bleed image or a table
    // into that card. Reported from a consuming project that was carrying exactly
    // that override, on two elements.
    'overflow' => 'hidden',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('card', $attributes->getAttributes());

    // `$as` ends up interpolated into the opening tag below (through `$tag`), and Blade's
    // escaping does not make that safe: `e()` escapes neither a space nor an `=`, so
    // `as="div onmouseover=alert(1)"` would arrive as a working event handler. `tagName()`
    // checks the SHAPE rather than an allowlist — an enum has to guess which elements a
    // developer legitimately wants, and `article` is exactly the kind a guess omits.
    // Validated here rather than beside `$tag`, because the interactive-affordance check
    // further down reads `$as` too and both must see the same sanitized value.
    $as = WireKit::tagName('card', (string) $as);

    $animateAttr = WireKit::resolveAnimateIn($animateIn, 'card');

    // Written out in full rather than built as "overflow-{$overflow}". Tailwind
    // scans for COMPLETE literal class names and generates nothing for a name it
    // never sees spelled out — an interpolated one produces a class with no rule
    // behind it, and the failure is silent because the attribute is present and
    // simply does nothing.
    $overflowClass = match ($overflow) {
        'visible' => 'overflow-visible',
        'auto' => 'overflow-auto',
        'clip' => 'overflow-clip',
        default => 'overflow-hidden',
    };

    // Base classes: radius, overflow, transition for interactive cards
    $baseClasses = WireKit::resolveClasses('card', 'base', implode(' ', [
        'bg-[var(--color-wk-bg-elevated)]',
        'text-[color:var(--color-wk-text)]',
        'rounded-[var(--radius-wk-lg)]',
        $overflowClass,
        'transition-shadow',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
    ]), $scope);

    // accept `outline` AND `outlined` for the bordered-only treatment.
    // Button uses `surface="outline"` (no trailing -d); card
    // historically used `variant="outlined"` (with trailing -d). Same
    // visual concept, different vocabulary — the alias here exists for
    // muscle-memory parity across the two components. The canonical
    // spelling stays `outlined` for card so existing developer code
    // keeps working.
    $variantAliases = ['outline' => 'outlined'];
    $variant = $variantAliases[$variant] ?? $variant;

    // Variant classes: border/shadow combinations for visual weight
    $variantClasses = match ($variant) {
        'outlined' => implode(' ', [
            'border-[length:var(--border-wk-width)]',
            'border-[var(--color-wk-border)]',
        ]),
        'elevated' => implode(' ', [
            'shadow-[var(--shadow-wk-md)]',
            'border-[length:var(--border-wk-width)]',
            'border-transparent',
        ]),
        'flat' => implode(' ', [
            'bg-[var(--color-wk-bg-subtle)]',
            'border-[length:var(--border-wk-width)]',
            'border-transparent',
        ]),
        default => WireKit::validateProp('card', 'variant', $variant, ['outlined', 'elevated', 'flat']),
    };

    // The interactive treatment is keyed on whether the card DOES something when it is
    // clicked, not on whether it happens to render an `<a>`. The condition used to read
    // `$href ? …`, and its comment said "when rendered as a link (interactive)" — which
    // equated link with interactive. That stopped being true the moment `as` arrived:
    // `as="button"` got the card frame and no affordance at all, so a card that acts on
    // click looked exactly like a card that does nothing. Same class as the
    // `surface="soft"` hover state added in v2.43.0 — an element that does not answer
    // the pointer reads as not clickable. Reported from a consuming project that was
    // hand-rolling clickable cards out of a raw `<button>` plus tokens plus its own
    // `cursor-pointer`, because the component would not look clickable.
    $hasClickBinding = false;
    foreach ($attributes->getAttributes() as $attributeKey => $_) {
        if (! is_string($attributeKey)) {
            continue;
        }

        // Both Alpine spellings, because Blade keeps `@click` verbatim in the bag (its
        // component-tag attribute pattern admits `@`) and never rewrites it to `x-on:` —
        // checking one spelling would silently miss half the callers. `str_starts_with`
        // rather than equality so modifiers ride along: `wire:click.prevent`,
        // `@click.stop`, `x-on:click.away`.
        if (str_starts_with($attributeKey, 'wire:click')
            || str_starts_with($attributeKey, '@click')
            || str_starts_with($attributeKey, 'x-on:click')
            || $attributeKey === 'onclick') {
            $hasClickBinding = true;
            break;
        }
    }

    $isInteractive = (bool) $href
        || (string) $as === 'button'
        || $attributes->get('role') === 'button'
        || $hasClickBinding;

    // `block` stays with the anchor and is deliberately NOT part of the interactive set:
    // an `<a>` is inline and needs it to fill the card's box, which makes it a display
    // fix rather than an affordance. Putting it on the interactive branch would change
    // the width of every card that already renders as a `<button>` (inline-block by
    // default) — a layout change nobody asked for, in a release that may not break.
    $interactiveClasses = trim(
        ($isInteractive ? 'hover:shadow-[var(--shadow-wk-lg)] cursor-pointer ' : '')
        .($href ? 'block' : '')
    );

    // Render as <a> when href given, otherwise use $as tag (default: div)
    $tag = $href ? 'a' : $as;

    // Auto-inject rel="noopener noreferrer" when target="_blank"
    $targetAttr = $attributes->get('target', '');
    $opensNewTab = $href && str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr . ' noopener noreferrer')
        : $relAttr;

    // Dev-only composition warning. The card root is a FRAME with no
    // padding — real content belongs in card.body / card.header / card.footer
    // (each emits the shared `px-[var(--padding-wk-x-lg)]` padding). If the slot
    // carries visible text but none of those padded sub-components, the content
    // renders flush against the border — the #1 silent card bug the downstream
    // DX reports hit. Mirrors the table.blade.php raw-descendant warn pattern.
    //
    // False-positive guard: `strip_tags()` leaving non-empty text means there is
    // real copy in the slot, which EXCLUDES the legitimate edge-to-edge media
    // pattern (a bare <img>/<picture>/<video>/<svg> flush in the card has no
    // card.body by design — strip_tags() leaves nothing, so no warn). Errs toward
    // under-warning, never crying wolf.
    $warnNoBody = false;
    if (config('app.debug')) {
        $rawSlot = (string) $slot;
        if (trim(strip_tags($rawSlot)) !== '') {
            $composed = str_contains($rawSlot, 'wk-card-body')
                || str_contains($rawSlot, 'px-[var(--padding-wk-x-lg)]');
            $warnNoBody = ! $composed;
        }
    }
@endphp

<{{ $tag }}
    data-wk-card
    @if($warnNoBody)
        {{-- Debug-only composition warning. It cannot be an inline console.warn:
             under Alpine's CSP build naming `console` throws while BUILDING the
             component, which takes down the very element being warned about. --}}
        x-data="wirekitDevWarning({ message: {{ \Pushery\WireKit\Support\AlpinePayload::from('[wirekit] card: content sits directly in the card with no card.body — the card root is a padding-free frame, so this content renders flush against the border. Wrap it in card.body (or card.header / card.footer). See https://docs.wirekit.app/components/card.') }} })"
    @endif
    @if($href) href="{{ $href }}" @endif
    @if($animateAttr) {!! $animateAttr !!} data-replayable="true" @endif
    {{ $attributes->merge($opensNewTab ? ['rel' => $finalRel] : [])->class([$baseClasses, $variantClasses, $interactiveClasses]) }}
>
    {{ $slot }}
    @if($opensNewTab)
        <span class="sr-only">{{ __('wirekit::(opens in new tab)') }}</span>
    @endif
</{{ $tag }}>
