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
        'stacked' => 'display: flex; flex-direction: column; gap: 1rem;',
        'grid' => 'display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;',
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
