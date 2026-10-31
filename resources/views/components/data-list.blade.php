{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    'layout' => config('wirekit.components.data-list.layout', 'horizontal'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('data-list', $attributes->getAttributes());

    // An unknown `layout` is reported (Support\StrictnessGate) and renders as `horizontal`; a bound
    // `null` or empty string is `horizontal` without a word.
    $layout = \Pushery\WireKit\WireKit::validateProp('data-list', 'layout', \Pushery\WireKit\Support\NumericProp::text($layout ?: 'horizontal'), ['horizontal', 'stacked', 'grid', 'summary', 'detail', 'inline']);

    // Data list wraps native <dl> for semantic key-value pairs.
    // Layouts: horizontal (label left, value right), stacked, grid, summary.
    // Inline styles guarantee layout in environments where the developer's
    // Tailwind JIT may not see vendor view classes — preview iframes,
    // server-side renders without a Tailwind build step, or any context
    // outside the developer's source-tree scan.
    $layoutStyle = match ($layout) {
        // `--gap-wk-lg`, NOT `--gap-wk-md`, and the difference is the whole point of using a
        // token here at all. A fallback keeps the 1rem only while the token is unset, and it is
        // not: `dist/wirekit.css` ships `--gap-wk-md: 0.75rem`, so `md` would tighten every
        // stacked and grid list in every application from 1rem to 0.75rem. `--gap-wk-lg` IS
        // 1rem, so nothing moves and the gap becomes adjustable.
        'stacked' => 'display: flex; flex-direction: column; gap: var(--gap-wk-lg, 1rem);',
        // The columns of `grid` are in dist/wirekit.css, under `dl[data-layout="grid"]`: two, and
        // one on a narrow screen. An inline style cannot change at a breakpoint, and with the
        // columns written here the list stayed at two on a phone.
        'grid' => 'display: grid; gap: var(--gap-wk-lg, 1rem);',
        // `summary` is the totals shape: an invoice subtotal block, a cart
        // summary, an order overview. The LABEL takes whatever width is left
        // over; the VALUE is as wide as its own content and sits flush right,
        // so amounts line up under one another along a single column edge.
        //
        // Neither existing layout can express it, which is why this one exists
        // rather than being a preset of one of them. `grid` is
        // `repeat(2, 1fr)`, so the amount is handed half the row and floats in
        // the middle of its own whitespace. `horizontal` gives the label a
        // fixed 33% track, so a long label ("Zwischensumme ohne Versand")
        // wraps mid-word while the center of the row sits empty.
        //
        // The <dt>/<dd> pairs are the grid's own items — `data-list.item`
        // drops its wrapper box to `display: contents` in this layout — so the
        // value column is ONE track sized across every row, not a separate
        // size per row. That shared track is what makes the amounts a
        // column rather than a set of independently right-aligned strings.
        //
        // Row rhythm comes from the row gap rather than per-item padding, and
        // there are deliberately no row separators: a totals block rules the
        // line above the grand total, not every line. `align-items: baseline`
        // seats the smaller label text on the same baseline as the value.
        //
        // The value's track is `fit-content()`, not `max-content`. A `max-content` track is as
        // wide as its text unbroken, so one long token, a code or a reference with no space in
        // it, would take the whole row and leave the label next to nothing. `fit-content()` holds
        // the track at its content up to 10rem, or up to 40% of the row where that is wider, and a
        // longer value breaks inside it (`overflow-wrap: anywhere` on the cell). An amount stays
        // far below the limit, so it keeps its own width and one line. The 10rem floor is for a row
        // only as wide as its own content, in a popover or a box of `width: fit-content`: there
        // the 40% is taken of a width the list itself set, and a limit at a share alone would
        // break a short value that fits.
        'summary' => 'display: grid; grid-template-columns: 1fr fit-content(max(40%, 10rem)); '
            .'gap: var(--gap-wk-xs, 0.25rem) var(--gap-wk-md, 0.75rem); align-items: baseline;',
        // `detail` is `summary` with the columns swapped, and it is the commoner of the two:
        // "Customer: …", "Payment method: …" — the LABEL is as wide as its own text and the
        // VALUE takes the rest. An application builds more detail lists than totals blocks.
        //
        // `grid` is the obvious wrong choice for it. At `repeat(2, 1fr)` a label like
        // "Payment method" is handed half the row and the value the other half, so the two
        // stand far apart with the middle of the row empty. Everything else here — the gaps,
        // the baseline seating, the `display: contents` on the items — is identical to
        // `summary`; this is the swapped column declaration.
        //
        // The label's track takes the limit of the value's track in `summary`, for the same
        // reason: without it, a label with no space in it, a file name, would make the track wider
        // than a phone and leave the value a few pixels, broken letter by letter. A label within
        // the limit is as wide as its text; a longer one breaks inside its track.
        'detail' => 'display: grid; grid-template-columns: fit-content(max(40%, 10rem)) 1fr; '
            .'gap: var(--gap-wk-xs, 0.25rem) var(--gap-wk-md, 0.75rem); align-items: baseline;',
        // `inline` is the compact form for a few entries in a narrow place, a table cell under an
        // article's name: one line per entry, "Label: value", with no padding and no rules. The
        // entries are blocks, so the list needs no layout of its own.
        'inline' => 'display: block;',
        default => '', // horizontal uses border on items
    };

    // An inline display wins over every class of the caller's, `hidden` included: where the
    // caller's class reaches for one, it is left out here and comes back as its utility.
    [$layoutStyle, $layoutStyleClasses] = \Pushery\WireKit\Support\UtilityClasses::yieldStyleToCaller($attributes, (string) $layoutStyle);

    $classes = WireKit::resolveClasses('data-list', 'base', implode(' ', [
        'w-full',
        'font-[family-name:var(--font-wk-sans)]',
        $layout === 'inline' ? 'text-[length:var(--text-wk-sm)]' : 'text-[length:var(--text-wk-md)]',
    ]), $scope);
@endphp

{{-- Native <dl> element — screen readers announce as definition list --}}
<dl
    data-layout="{{ $layout }}"
    {{ $attributes->merge($layoutStyle ? ['style' => $layoutStyle] : [])->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes, $layoutStyleClasses => $layoutStyleClasses !== ''])) }}
>
    {{ $slot }}
</dl>
