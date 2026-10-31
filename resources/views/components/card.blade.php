{{-- optimistic-ui: n/a — client-only
     Its only Alpine is a development-time warning about misuse. --}}
@props([
    // The canonical surface axis, spelled as on button: outline | elevated | flat. `variant`
    // below is its back-compat alias and keeps working unchanged.
    'surface' => null,
    'variant' => config('wirekit.components.card.variant', 'outlined'),
    // The color role of the border: neutral (the default) | info | success | warning | danger, in
    // the tones `callout` draws its own border in, so a card around a warning callout can carry the
    // same border. Only the border takes it; background and shadow stay those of the surface.
    'intent' => null,
    'as' => 'div',
    'href' => null,
    // Say at rest that the card leads somewhere: a chevron at the inline end. The pointer cursor and
    // the hover shadow are the only other signs, and a phone shows neither, so there a card with
    // `href` looked exactly like one with nothing behind it. Opt-in, because drawing it by default
    // would change the layout of every clickable card that already exists. Read only on a card that
    // links or runs an action.
    'chevron' => false,
    // Optional reveal animation when card scrolls into view. One of 11 base presets
    // (or any -in / -out variant). Null = no animation (default).
    'animateIn' => null,
    // What the card does with a child that reaches past its box. The default clips,
    // which is what makes the rounded corners cut the content inside them — but it
    // also clips a badge pinned to a corner, an avatar that scales on hover, or a
    // focus ring that extends past the edge. `visible` releases all of it.
    //
    // A prop rather than an !important utility override on the base class, because that
    // override is not the same thing: it removes the corner clipping for EVERY
    // child, and nothing says so until someone drops a full-bleed image or a table
    // into that card.
    'overflow' => 'hidden',
    // Fold the card under its own head: the head becomes the trigger, and everything in the slot,
    // body and footer, folds away beneath it. Without it the card has no head, no region and no
    // script of its own.
    'collapsible' => false,
    // Start a collapsible card open. A fold the reader made on an earlier visit never closes it on
    // load, so a card that needs the reader is open every time. Read when the card first renders:
    // during the visit the state is the reader's. Null unless given: a card that does not fold
    // hands an `open` it was given to its element, as `as="details"` needs.
    'open' => null,
    // The reasons a collapsible card stands open for, as the server sees them now: a list of names, or
    // a map of each name to whether it holds. With one the card starts open, and after a response
    // only a reason it did not have opens it again, so a card the reader folded stays folded.
    'openWhen' => [],
    // A key under which the browser remembers whether the reader left the card open. An application
    // that keeps this in its own store passes it as `open` and saves `wirekit:card-toggled` instead.
    'persist' => null,
    // The name `wirekit:card-open` opens the card by, reported in `wirekit:card-toggled` as well.
    // Handed to the element on a card that does not fold, as `open` is.
    'name' => null,
    // The text of a collapsible card's trigger. The `heading` slot takes markup instead.
    'heading' => null,
    // The level of the heading the trigger stands in, 2 to 6.
    'level' => 3,
    // The line under the heading, shown whether the card is open or closed. The `description` slot
    // takes markup instead.
    'description' => null,
    // Closed content stays findable by the browser's find in page, which opens the card. Switch it
    // off for content that must stay out of sight until the reader opens it.
    'findable' => true,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('card', $attributes->getAttributes());

    // An unknown `overflow` is reported (Support\StrictnessGate) and renders as `hidden`; a bound
    // `null` or empty string is `hidden` without a word.
    $overflow = \Pushery\WireKit\WireKit::validateProp('card', 'overflow', \Pushery\WireKit\Support\NumericProp::text($overflow ?: 'hidden'), ['hidden', 'visible', 'auto', 'clip']);

    // Echoed into the tag or bound, the URL is written escaped once (Support\UrlProp).
    $href = \Pushery\WireKit\Support\UrlProp::text($href);

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
    // `auto` is the one arm that turns the card's own box into a SCROLL CONTAINER, so it
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
        'auto' => 'relative overflow-auto focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-inset focus-visible:ring-[var(--color-wk-ring)]',
        'clip' => 'overflow-clip',
        default => 'overflow-hidden',
    };

    // Base classes: radius, overflow, transition for interactive cards
    $baseClasses = WireKit::resolveClasses('card', 'base', implode(' ', [
        // A typed token with no break point, an email address or a URL, breaks rather than running
        // out of the box. Inherited by everything inside; `break-word` leaves every min-content width
        // alone, so a table or a flex row in the content keeps the layout it had.
        '[overflow-wrap:break-word]',
        'bg-[var(--color-wk-bg-elevated)]',
        'text-[color:var(--color-wk-text)]',
        'rounded-[var(--radius-wk-lg)]',
        $overflowClass,
        'transition-shadow',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
    ]), $scope);

    // accept `outline` AND `outlined` for the bordered-only treatment.
    // Button uses `surface="outline"` (no trailing -d); card takes
    // `variant="outlined"` (with trailing -d). Same visual concept,
    // different vocabulary — the alias here gives the two components the
    // same word. Card's own spelling stays `outlined`, so code written
    // against it keeps working.
    // `surface` first: it is the name the kit's vocabulary is moving to, and a call site that
    // sets both is half migrated. Preferring the older name there would make that call site
    // look finished to whoever is doing the migration.
    $axis = $surface !== null ? 'surface' : 'variant';
    $variant = $surface ?? $variant;

    $variantAliases = ['outline' => 'outlined'];
    // A float beyond the integer range ends a request as an array key on PHP 8.5: read as text.
    $variant = \Pushery\WireKit\Support\NumericProp::key($variant);
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
    // the outcome is arbitrary with respect to what the developer meant: an accent border
    // can sort before the component's own, and the selected card then looks exactly like an
    // unselected one.
    //
    // With the seam an application REPLACES the block in its own scope instead of hanging
    // something beside it, which is the one shape that cannot be decided by an ordering
    // nobody controls.
    //
    // The border color of an intent takes the place of the surface's own for the same reason: set
    // beside it, the color that sorts later in the stylesheet would draw, whichever the call site
    // asked for. Each mix is the one `callout` draws its border with.
    $intent = \Pushery\WireKit\Support\NumericProp::key($intent);
    $intentBorder = match ($intent) {
        // A bound `null` or empty string is the neutral border without a word.
        null, '', 'neutral' => null,
        'info' => 'border-[color-mix(in_srgb,var(--color-wk-info-tone)_40%,var(--color-wk-border))]',
        'success' => 'border-[color-mix(in_srgb,var(--color-wk-success)_40%,var(--color-wk-border))]',
        'warning' => 'border-[color-mix(in_srgb,var(--color-wk-warning)_40%,var(--color-wk-border))]',
        'danger' => 'border-[color-mix(in_srgb,var(--color-wk-danger)_40%,var(--color-wk-border))]',
        // An unknown value is reported; what comes back is the first allowed value, and the card
        // keeps the border of its surface.
        default => match (WireKit::validateProp('card', 'intent', \Pushery\WireKit\Support\NumericProp::text($intent), ['neutral', 'info', 'success', 'warning', 'danger'])) {
            'neutral' => null,
        },
    };
    $outlinedClasses = implode(' ', [
        'border-[length:var(--border-wk-width)]',
        $intentBorder ?? 'border-[var(--color-wk-border)]',
    ]);
    $variantClasses = WireKit::resolveClasses('card', 'variant', match ($variant) {
        'outlined' => $outlinedClasses,
        'elevated' => implode(' ', [
            'shadow-[var(--shadow-wk-md)]',
            'border-[length:var(--border-wk-width)]',
            $intentBorder ?? 'border-transparent',
        ]),
        'flat' => implode(' ', [
            'bg-[var(--color-wk-bg-subtle)]',
            'border-[length:var(--border-wk-width)]',
            $intentBorder ?? 'border-transparent',
        ]),
        // Named after the prop the CALL SITE used, and listing the values in that prop's own
        // spelling: a developer who wrote `surface` is told about `outline`, which is what
        // button takes, rather than about a `variant` they never set.
        //
        // What comes back is the first allowed value in that spelling, which is the outlined card
        // either way; it is drawn as one rather than emitted as a class name (`outline` is a real
        // utility, and drew an outline around the card).
        default => match (WireKit::validateProp('card', $axis, $variant, $axis === 'surface'
            ? ['outline', 'elevated', 'flat']
            : ['outlined', 'elevated', 'flat'])) {
            'outline', 'outlined' => $outlinedClasses,
        },
    }, $scope);

    // A flat card's background is its variant's, so the base block's background color comes
    // out. Beside it the stylesheet's order decided, and the flat card only came out right
    // because `--color-wk-bg-subtle` sorts after `--color-wk-bg-elevated` — the same ordering
    // nobody controls that the comment above describes for the border. Read from the RESOLVED
    // base block, so a personalized base loses its background color on a flat card too.
    if ($variant === 'flat') {
        $baseClasses = \Pushery\WireKit\Support\UtilityClasses::without(
            $baseClasses,
            \Pushery\WireKit\Support\UtilityClasses::BACKGROUND_COLOR,
        );
    }

    // The interactive treatment is keyed on whether the card DOES something when it is
    // clicked, not on whether it happens to render an `<a>`. Keyed on `$href` alone,
    // `as="button"` would get the card frame and no affordance at all, so a card that
    // acts on click would look exactly like a card that does nothing: an element that
    // does not answer the pointer reads as not clickable.
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

    $isInteractive = $href !== null
        || (string) $as === 'button'
        || $attributes->get('role') === 'button'
        || $hasClickBinding;

    $chevron = BooleanProp::from($chevron, false, 'card', 'chevron');

    // A chevron on a card that does nothing would promise a destination that is not there, so the
    // prop is read only where the card is interactive, and a static card that asks for one is told
    // so in debug.
    $showChevron = $chevron && $isInteractive;

    if ($chevron && ! $isInteractive && config('app.debug')) {
        logger()->debug('WireKit card: `chevron` is set on a card that neither links nor runs an action, so no chevron is drawn.');
    }

    // A card that folds. Its own head is the trigger and its whole slot, body and footer, is the part
    // that folds, so the head is drawn here from the card's own props rather than by `card.header`:
    // a child reads its parent through `@aware`, which walks outward past a card whose tag does not
    // carry the key, and a plain card inside `sidebar collapsible` would read the sidebar's
    // `collapsible` and draw a trigger with no state behind it.
    $collapsible = BooleanProp::from($collapsible, false, 'card', 'collapsible');
    $findable = BooleanProp::from($findable, true, 'card', 'findable');

    // A card that links or runs an action is a control already, and a control cannot hold the
    // button of a head. Reported, and drawn without folding.
    if ($collapsible && $isInteractive) {
        \Pushery\WireKit\Support\StrictnessGate::reject('card', 'collapsible', 'true', 'a card without href or an action of its own', '');
        $collapsible = false;
    }

    // `open` and `name` are attributes of some elements (`as="details"`, `as="fieldset"`), so a card
    // that does not fold hands one it was given to its element, where the element reads it.
    if (! $collapsible) {
        $attributes = $attributes->merge(array_filter(['open' => $open, 'name' => $name], static fn ($value): bool => $value !== null));
    }

    // The reasons, each a name once: from a list, or the names a map holds true. A name is one word on
    // the element, so the spaces inside it become hyphens.
    $openReasons = [];
    $openWhenValue = \Pushery\WireKit\Support\ListProp::from($openWhen);

    foreach (is_array($openWhenValue) ? $openWhenValue : (filled($openWhenValue) ? [$openWhenValue] : []) as $reasonKey => $reasonValue) {
        $reason = is_string($reasonKey) ? (BooleanProp::from($reasonValue, false) ? $reasonKey : null) : $reasonValue;

        if (is_scalar($reason) && trim((string) $reason) !== '') {
            $openReasons[] = (string) preg_replace('/\s+/u', '-', trim((string) $reason));
        }
    }

    $openReasons = array_values(array_unique($openReasons));
    $openNow = $collapsible && (BooleanProp::from($open, false, 'card', 'open') || $openReasons !== []);

    // The head is drawn only on a card that folds, so these say nothing on any other card; debug says so.
    if (! $collapsible && (filled($heading) || filled($description) || filled($persist) || $openReasons !== []) && config('app.debug')) {
        logger()->debug('WireKit card: `heading`, `description`, `persist` and `open-when` are read only on a card with `collapsible`; a card that does not fold takes its head from card.header.');
    }

    if ($collapsible) {
        // The trigger needs a name, and the heading is it.
        if (blank($heading)) {
            WireKit::validateProp('card', 'heading', '', ['a heading for the trigger']);
        }

        // A bad level falls back to 3, never to the page's own heading.
        $levelValue = in_array(\Pushery\WireKit\Support\NumericProp::int($level, 0), [2, 3, 4, 5, 6], true) ? \Pushery\WireKit\Support\NumericProp::int($level, 0) : 3;

        if ($levelValue !== \Pushery\WireKit\Support\NumericProp::int($level, 0)) {
            WireKit::validateProp('card', 'level', \Pushery\WireKit\Support\NumericProp::text($level), ['2', '3', '4', '5', '6']);
        }

        // Stable across a Livewire round trip, and apart on a page with two cards of one name or one
        // heading: from the caller's id, else the name, else the text of the heading.
        $idSeed = filled($name) ? (string) $name : (is_string($heading) ? $heading : null);
        // A caller's id keeps its place on the root as written; the ids built from it hold no
        // whitespace (Support\DomId::valid()), or the trigger's `aria-controls` would name two ids.
        $idBase = filled($attributes->get('id'))
            ? (string) \Pushery\WireKit\Support\DomId::valid((string) $attributes->get('id'))
            : \Pushery\WireKit\Support\DomId::distinct(WireKit::stableId('wk-card', $idSeed));
        $triggerId = $idBase.'-trigger';
        $panelId = $idBase.'-panel';
        $hintsId = $idBase.'-hints';
        $hasHints = isset($hints) && $hints->hasActualContent();

        // The head: the padding and type of `card.header`, positioned so the trigger can stretch
        // over all of it.
        $headClasses = WireKit::resolveClasses('card', 'head', implode(' ', [
            'relative',
            'px-[var(--padding-wk-x-lg)]',
            'py-[var(--padding-wk-x-lg)]',
            'font-[family-name:var(--font-wk-sans)]',
            'font-[number:var(--font-wk-heading-weight)]',
            'text-[length:var(--text-wk-lg)]',
            'text-[color:var(--color-wk-text)]',
        ]), $scope);

        // The whole head answers the pointer and a finger: the button stretches over it with its
        // `::after`, and the focus ring outlines the head inside the card, which clips its own box.
        $triggerClasses = WireKit::resolveClasses('card', 'trigger', implode(' ', [
            'w-full text-start cursor-pointer',
            'after:absolute after:inset-0',
            'focus-visible:outline-hidden',
            'focus-visible:after:ring-[length:var(--ring-wk-width)]',
            'focus-visible:after:ring-inset',
            'focus-visible:after:ring-[var(--color-wk-ring)]',
        ]), $scope);

        $descriptionClasses = WireKit::resolveClasses('card', 'description', implode(' ', [
            'mt-[var(--space-wk-xs)]',
            'font-[number:var(--font-wk-body-weight)]',
            'text-[length:var(--text-wk-sm)]',
            'text-[color:var(--color-wk-text-muted)]',
        ]), $scope);

        // On a line of their own the hints are no wider than the head, and their badges wrap.
        $hintsClasses = WireKit::resolveClasses('card', 'hints', implode(' ', [
            'shrink-0 max-w-full flex flex-wrap items-center gap-[var(--gap-wk-xs)]',
            'font-[number:var(--font-wk-body-weight)]',
            'text-[length:var(--text-wk-sm)]',
        ]), $scope);

        // The line between head and content belongs to the content, so a closed card shows none.
        $contentClasses = WireKit::resolveClasses('card', 'content', implode(' ', [
            'border-t-[length:var(--border-wk-width)]',
            'border-[var(--color-wk-border-subtle)]',
        ]), $scope);

        // The chevron stands in a circle the size of a small control, so a closed card reads as one
        // that holds more than its head. The arrow inside turns with the state and takes its color
        // from the circle.
        $chevronClasses = WireKit::resolveClasses('card', 'chevron', implode(' ', [
            'shrink-0 inline-flex items-center justify-center',
            'size-[var(--size-wk-xs)]',
            'rounded-full',
            'border-[length:var(--border-wk-width)]',
            'border-[var(--color-wk-border-strong)]',
            'text-[color:var(--color-wk-text-muted)]',
        ]), $scope);
    }

    // `block` stays with the anchor and is deliberately NOT part of the interactive set:
    // an `<a>` is inline and needs it to fill the card's box, which makes it a display
    // fix rather than an affordance. Putting it on the interactive branch would change
    // the width of every card that already renders as a `<button>` (inline-block by
    // default) — a layout change nobody asked for, in a release that may not break.
    //
    // With a chevron the card lays its content and the chevron out side by side instead, on either
    // tag. That changes the width of a button card as well, which the prop asks for by being set.
    // The card's inline-end padding holds the chevron in from the edge by the body's own inset, so
    // the distance moves with the padding scale it matches.
    $cardLayoutClass = match (true) {
        $showChevron => 'flex items-center pe-[var(--padding-wk-x-lg)]',
        $href !== null => 'block',
        default => '',
    };

    // The kit's focus ring on a card that acts, standing off the card like a button's, rather than
    // the browser's, which follows neither the ring tokens nor a preset. A match arm for the drift
    // auditor, as with the overflow arm above, which already draws the ring inside the box when
    // the card scrolls.
    $focusRingClass = match (true) {
        $isInteractive && $overflow !== 'auto' => 'focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-offset-[length:var(--ring-wk-offset)] focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
        default => '',
    };

    $interactiveClasses = implode(' ', array_filter([
        $isInteractive ? 'hover:shadow-[var(--shadow-wk-lg)] cursor-pointer' : '',
        $cardLayoutClass,
        $focusRingClass,
    ]));

    // Render as <a> when href given, otherwise use $as tag (default: div)
    $tag = $href !== null ? 'a' : $as;

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
    $rootIsNativelyFocusable = $href !== null
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
    $opensNewTab = $href !== null && str_contains($targetAttr, '_blank');
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

<{{ $tag }} data-wk-prose-skip
    data-wk-card
    @if($collapsible)
        {{-- The card's own state. A field the browser finds invalid opens it in the capture phase,
             before the browser tries to focus the field; `wirekit:card-open` opens it from inside
             or, addressing it, from anywhere, and `wirekit:card-close` folds it the same two ways;
             an address that names an element inside opens it on load and on `hashchange`. The
             reasons are read again after every response, so a reason the card did not have opens
             it. The server's `open` is an attribute the factory reads once rather than a value in
             `x-data`, which must not change between responses: Alpine builds a component anew from
             a changed expression, and the reader's state would go with it. The composition warning
             rides in the same factory, since an element cannot carry two `x-data`. --}}
        data-wk-card-collapsible
        @if($openNow) data-wk-card-open @endif
        @if($openReasons !== []) data-wk-card-reasons="{{ implode(' ', $openReasons) }}" @endif
        x-data="wirekitCardDisclosure({ persist: {{ \Pushery\WireKit\Support\AlpinePayload::from(filled($persist) ? (string) $persist : null) }}, name: {{ \Pushery\WireKit\Support\AlpinePayload::from(filled($name) ? (string) $name : null) }}, warning: {{ \Pushery\WireKit\Support\AlpinePayload::from($warnNoBody ? '[wirekit] card: content sits directly in the card with no card.body — the card root is a padding-free frame, so this content renders flush against the border. Wrap it in card.body (or card.footer). See https://docs.wirekit.app/components/card.' : null) }} })"
        x-on:invalid.capture="revealForInvalid()"
        x-on:wirekit:card-open="openFrom()"
        x-on:wirekit:card-open.window="openByName($event)"
        x-on:wirekit:card-close="closeFrom($event)"
        x-on:wirekit:card-close.window="closeByName($event)"
        x-on:hashchange.window="openForFragment()"
    @elseif($warnNoBody)
        {{-- Debug-only composition warning. It cannot be an inline console.warn:
             under Alpine's CSP build naming `console` throws while BUILDING the
             component, which takes down the very element being warned about. --}}
        x-data="wirekitDevWarning({ message: {{ \Pushery\WireKit\Support\AlpinePayload::from('[wirekit] card: content sits directly in the card with no card.body — the card root is a padding-free frame, so this content renders flush against the border. Wrap it in card.body (or card.header / card.footer). See https://docs.wirekit.app/components/card.') }} })"
    @endif
    @if($href !== null) href="{{ $href }}" @endif
    @if($animateAttr) {!! $animateAttr !!} data-replayable="true" @endif
    @if($computedRel) rel="{{ $computedRel }}" @endif
    {{-- Same rule as link: see that component for why the value is read out of the bag
         instead of written before it. --}}
    @if($tag === 'button') type="{{ $attributes->get('type', 'button') }}" @endif
    {{ $attributes->except($tag === 'button' ? ['rel', 'type'] : ['rel'])->merge($scrollKeyboardModel)->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$baseClasses, $variantClasses, $interactiveClasses, 'wk-card-collapsible' => $collapsible])) }}
>
    @if($collapsible)
        {{-- The head. The trigger stands in a heading of the given level and names the folding part
             with `aria-controls`; the hints beside it are its description, read with it; the line
             under the heading stays visible in both states. The chevron's turn is motion, and the
             `wk-card-collapsible` marker on the card puts it under the reduced-motion rule. --}}
        <div data-wk-card-head class="{{ $headClasses }}">
            <div class="flex items-center gap-[var(--gap-wk-sm)]">
                {{-- The heading asks for 16rem, and the hints stand beside it while the head has room
                     and drop under it where it has not, as the buttons of `card.header` do. The break
                     is the head's own width, not the window's, and the chevron keeps to the end. --}}
                <div data-wk-card-head-line class="flex min-w-0 flex-1 flex-wrap items-center gap-x-[var(--gap-wk-sm)] gap-y-[var(--gap-wk-xs)]">
                    <h{{ $levelValue }} data-wk-prose-skip class="m-0 min-w-0 grow basis-[16rem]">
                        <button
                            type="button"
                            id="{{ $triggerId }}"
                            aria-controls="{{ $panelId }}"
                            aria-expanded="{{ $openNow ? 'true' : 'false' }}"
                            :aria-expanded="isOpen ? 'true' : 'false'"
                            @if($hasHints) aria-describedby="{{ $hintsId }}" @endif
                            x-on:click="toggle()"
                            class="wk-card-trigger {{ $triggerClasses }}"
                        >{{ $heading }}</button>
                    </h{{ $levelValue }}>
                    {{-- The trigger's area lies over the whole head, so a link or a button in the hints
                         and in the description stands above it and takes its own click, and the text
                         around it stays part of the head. The lift sits outside the class block, so a
                         personalization keeps it. --}}
                    @if($hasHints)
                        <span id="{{ $hintsId }}" class="{{ $hintsClasses }} [&_a]:relative [&_a]:z-10 [&_button]:relative [&_button]:z-10">{{ $hints }}</span>
                    @endif
                </div>
                {{-- The circle and the arrow take no click: the trigger's area lies under them, and a
                     turned arrow is painted above that area, so it took the click itself and the
                     card stayed open. `pointer-events-none` and the marker sit outside the class
                     block, so a personalization keeps both. --}}
                <span data-wk-card-chevron-frame aria-hidden="true" class="pointer-events-none {{ $chevronClasses }}">
                    <svg
                        class="wk-card-chevron shrink-0 w-4 h-4 transition-transform duration-[var(--transition-wk-duration)]"
                        :class="isOpen ? 'rotate-180' : ''"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 111.08 1.04l-4.25 4.39a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </span>
            </div>
            @if(filled($description))
                <div data-wk-card-description class="{{ $descriptionClasses }} [&_a]:relative [&_a]:z-10 [&_button]:relative [&_button]:z-10">{{ $description }}</div>
            @endif
        </div>
        {{-- The part that folds, body and footer together, one box that is never `display:
             contents`. Findable, it is `hidden="until-found"` while closed where the engine
             supports it, so find in page opens it; the line and the padding sit on the elements
             inside, because a closed until-found element keeps its box. See utils/findable.js. --}}
        @if($findable)
            <div
                id="{{ $panelId }}"
                x-ref="region"
                data-wk-card-region
                x-wk-findable.collapse="isOpen"
                x-on:beforematch="reveal()"
                @unless($openNow) hidden="until-found" @endunless
            >
                <div data-wk-card-content class="{{ $contentClasses }}">{{ $slot }}</div>
            </div>
        @else
            <div id="{{ $panelId }}" x-ref="region" data-wk-card-region x-show="isOpen" x-collapse @unless($openNow) x-cloak @endunless>
                <div data-wk-card-content class="{{ $contentClasses }}">{{ $slot }}</div>
            </div>
        @endif
    @elseif($showChevron)
        {{-- The content takes the column that can shrink and the chevron the one that cannot, so no
             text ever runs under it. Decorative: the link's own text already names where it goes. --}}
        <div class="flex-1 min-w-0">{{ $slot }}</div>
        <span data-wk-card-chevron aria-hidden="true" class="shrink-0 inline-flex text-[color:var(--color-wk-text-muted)] rtl:-scale-x-100">
            <x-wirekit::icon name="chevron-right" size="sm" />
        </span>
    @else
        {{ $slot }}
    @endif
    @if($opensNewTab)
        <span class="sr-only">{{ __('wirekit::(opens in new tab)') }}</span>
    @endif
</{{ $tag }}>