{{-- optimistic-ui: n/a — client-only
     Its only Alpine is a development-time warning about misuse. --}}
@props([
    // The canonical surface axis, spelled as on button: outline | elevated | flat. `variant`
    // below is its back-compat alias and keeps working unchanged.
    'surface' => null,
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
    //
    // ⚠️ `auto` is the one arm that turns the card's own box into a SCROLL CONTAINER, so it
    // carries the keyboard contract with it. WCAG 2.1.1 asks that a region which scrolls be
    // reachable and operable without a mouse, and a `<div>` that scrolls is neither: there is
    // no tab stop on it, so the content past the fold cannot be panned at all. The ring rides
    // in this arm rather than in a ternary for the same reason attachment-group's does — the
    // drift auditor harvests match-arm class strings, and a ternary hides them from it.
    // `ring-inset` because the card clips its own box: an outset ring would be cut off by the
    // very overflow that makes the ring necessary. The `tabindex` half is below, next to
    // `$tag`, because it depends on which element this ends up being.
    $overflowClass = match ($overflow) {
        'visible' => 'overflow-visible',
        'auto' => 'overflow-auto focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-inset focus-visible:ring-[var(--color-wk-ring)]',
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
    // `surface` first: it is the name the kit's vocabulary is moving to, and a call site that
    // sets both is half migrated. Preferring the older name there would make that call site
    // look finished to whoever is doing the migration.
    $axis = $surface !== null ? 'surface' : 'variant';
    $variant = $surface ?? $variant;

    $variantAliases = ['outline' => 'outlined'];
    $variant = $variantAliases[$variant] ?? $variant;

    // Variant classes: border/shadow combinations for visual weight.
    //
    // Through the SAME seam as `base`, and that is not symmetry for its own sake. Every
    // variant sets `border-color` and none leaves it off, so an application that wants a
    // selected state — a metric card that sets a filter, say — cannot express one by
    // appending: its class lands in the same attribute at the same specificity, and the
    // winner is then decided by EMISSION ORDER in the built stylesheet.
    //
    // Tailwind v4 derives that order from the class name rather than from the intent, so
    // the outcome is arbitrary with respect to what the developer meant. Measured in a
    // consuming application's built CSS: `border-[var(--color-wk-accent)]` at offset 50367,
    // `border-[var(--color-wk-border)]` at 50600 — the component's border wins, and the
    // selected card looks exactly like an unselected one. `border-transparent` sits further
    // back still, so `flat` and `elevated` lose by more.
    //
    // With the seam an application REPLACES the block in its own scope instead of hanging
    // something beside it, which is the one shape that cannot be decided by an ordering
    // nobody controls.
    $variantClasses = WireKit::resolveClasses('card', 'variant', match ($variant) {
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
        // Named after the prop the CALL SITE used, and listing the values in that prop's own
        // spelling: a developer who wrote `surface` is told about `outline`, which is what
        // button takes, rather than about a `variant` they never set.
        default => WireKit::validateProp('card', $axis, $variant, $axis === 'surface'
            ? ['outline', 'elevated', 'flat']
            : ['outlined', 'elevated', 'flat']),
    }, $scope);

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

    // The other half of the `overflow="auto"` contract: the tab stop.
    //
    // Deliberately NOT the shape attachment-group uses (`tabindex="{{ $isRow ? '0' : '-1' }}"`,
    // the condition in the VALUE). That works there because its root is always a `<div>`; this
    // root is not. With `href` the card renders as an `<a>`, which is already a tab stop, and
    // writing `-1` onto it would take a link card OUT of the tab order — a keyboard regression
    // dressed as a keyboard fix. And `-1` on the DEFAULT path would make every card in the
    // library click-focusable, which is a behavior change nobody asked for on a clipping card
    // that scrolls nothing.
    //
    // So three states rather than two, and the two that need no attribute get none.
    //
    // `a` is absent from the tag list ON PURPOSE and the `$href` term is not a duplicate of
    // it: an anchor without an `href` is not focusable at all, so `as="a"` on its own still
    // needs the stop.
    $rootIsNativelyFocusable = (bool) $href
        || in_array(strtolower((string) $tag), ['button', 'input', 'select', 'textarea'], true);

    // Merged through the bag rather than written into the tag, so a caller who manages focus
    // themselves keeps their own value — `merge()` treats this as a default, which is the
    // right way round here and is exactly what `rel` below must NOT do.
    $scrollKeyboardModel = $overflow === 'auto' && ! $rootIsNativelyFocusable
        ? ['tabindex' => '0']
        : [];

    // Auto-inject rel="noopener noreferrer" + SR hint when target="_blank".
    // The rel is rendered EXPLICITLY and the bag echoes with except('rel'):
    // $attributes->merge() treats a non-class attribute as a DEFAULT, so a
    // caller writing rel="prev" silently replaced the computed value and took
    // the protection with it. See dropdown/item.blade.php for the same shape.
    $targetAttr = $attributes->get('target', '');
    $opensNewTab = $href && str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr . ' noopener noreferrer')
        : $relAttr;
    $computedRel = $opensNewTab ? $finalRel : ($relAttr ?: null);

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
    @if($computedRel) rel="{{ $computedRel }}" @endif
    {{-- Same rule as link: see that component for why the value is read out of the bag
         instead of written before it. --}}
    @if($tag === 'button') type="{{ $attributes->get('type', 'button') }}" @endif
    {{ $attributes->except($tag === 'button' ? ['rel', 'type'] : ['rel'])->merge($scrollKeyboardModel)->class([$baseClasses, $variantClasses, $interactiveClasses]) }}
>
    {{ $slot }}
    @if($opensNewTab)
        <span class="sr-only">{{ __('wirekit::(opens in new tab)') }}</span>
    @endif
</{{ $tag }}>
