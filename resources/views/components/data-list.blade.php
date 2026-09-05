{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
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

    // Data list wraps native <dl> for semantic key-value pairs.
    // Layouts: horizontal (label left, value right), stacked, grid, summary.
    // Inline styles guarantee layout in environments where the developer's
    // Tailwind JIT may not see vendor view classes — preview iframes,
    // server-side renders without a Tailwind build step, or any context
    // outside the developer's source-tree scan.
    $layoutStyle = match ($layout) {
        // `--gap-wk-lg`, NOT `--gap-wk-md`, and the difference is the whole point of using a
        // token here at all. The reporting application proposed `md` on the reasoning that a
        // fallback keeps today's behavior — which holds only while the token is unset, and it
        // is not: `dist/wirekit.css` ships `--gap-wk-md: 0.75rem`. Swapping to it would have
        // tightened every stacked and grid list in every application from 1rem to 0.75rem, on
        // a change described as non-breaking. `--gap-wk-lg` IS 1rem, so nothing moves and the
        // gap becomes adjustable, which was the actual request.
        'stacked' => 'display: flex; flex-direction: column; gap: var(--gap-wk-lg, 1rem);',
        'grid' => 'display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--gap-wk-lg, 1rem);',
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
        // value column is ONE track measured across every row, not a separate
        // measurement per row. That shared track is what makes the amounts a
        // column rather than a set of independently right-aligned strings.
        //
        // Row rhythm comes from the row gap rather than per-item padding, and
        // there are deliberately no row separators: a totals block rules the
        // line above the grand total, not every line. `align-items: baseline`
        // seats the smaller label text on the same baseline as the value.
        'summary' => 'display: grid; grid-template-columns: 1fr max-content; '
            .'gap: var(--gap-wk-xs, 0.25rem) var(--gap-wk-md, 0.75rem); align-items: baseline;',
        // `detail` is `summary` with the columns swapped, and it is the commoner of the two:
        // "Customer: …", "Payment method: …" — the LABEL is as wide as its own text and the
        // VALUE takes the rest. An application builds more detail lists than totals blocks.
        //
        // `grid` is the obvious wrong choice for it. At `repeat(2, 1fr)` a label like
        // "Payment method" is handed half the row and the value the other half, so the two
        // stand far apart with the middle of the row empty. Everything else here — the gaps,
        // the baseline seating, the `display: contents` on the items — is identical to
        // `summary`; this is literally the swapped column declaration.
        'detail' => 'display: grid; grid-template-columns: max-content 1fr; '
            .'gap: var(--gap-wk-xs, 0.25rem) var(--gap-wk-md, 0.75rem); align-items: baseline;',
        default => '', // horizontal uses border on items
    };

    $classes = WireKit::resolveClasses('data-list', 'base', implode(' ', [
        'w-full',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[length:var(--text-wk-md)]',
    ]), $scope);
@endphp

{{-- Native <dl> element — screen readers announce as definition list --}}
<dl
    data-layout="{{ $layout }}"
    {{ $attributes->merge($layoutStyle ? ['style' => $layoutStyle] : [])->class([$classes]) }}
>
    {{ $slot }}
</dl>
