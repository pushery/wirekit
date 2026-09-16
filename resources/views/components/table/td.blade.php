{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'align' => 'left', // left | center | right
    'hideBelow' => null, // sm | md | lg | xl | 2xl — the same value as the column's header
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('table.td', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    $alignClass = match ($align) {
        'center' => 'text-center',
        'right' => 'text-right',
        default => 'text-left',
    };

    // `hideBelow` takes a column off a narrow screen. The classes are written out per breakpoint
    // because Tailwind reads literal class names and never an assembled one, and a `max-*` variant
    // hides only below the breakpoint, so above it the cell keeps the display the table gives it
    // instead of one this component would have to restate. The header and every cell of the column
    // take the same value: a header hidden without its cells, or cells without their header, would
    // put every value under the wrong heading. An unknown value throws in debug and hides nothing
    // in production.
    $hideBelow = filled($hideBelow) ? (string) $hideBelow : null;
    if ($hideBelow !== null && ! in_array($hideBelow, ['sm', 'md', 'lg', 'xl', '2xl'], true)) {
        WireKit::validateProp('table.td', 'hideBelow', $hideBelow, ['sm', 'md', 'lg', 'xl', '2xl']);
        $hideBelow = null;
    }
    $hideClasses = match ($hideBelow) {
        'sm' => 'max-sm:hidden',
        'md' => 'max-md:hidden',
        'lg' => 'max-lg:hidden',
        'xl' => 'max-xl:hidden',
        '2xl' => 'max-2xl:hidden',
        default => '',
    };

    // Base td styling — standard padding, body text weight, compact-aware padding
    $classes = WireKit::resolveClasses('table.td', 'base', implode(' ', [
        'px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text)]',
        'font-[number:var(--font-wk-body-weight)]',
        $alignClass,
        $hideClasses,
        // Compact variant: reduce vertical padding to match th
        '[table[data-wk-compact]_&]:py-[var(--padding-wk-y-sm)]',
        // Sticky first column: freeze the leading body cell. Solid background so
        // scrolling cells don't show through (the frozen column reads as solid even
        // on striped tables — the standard frozen-column convention).
        '[table[data-wk-sticky-column]_&:first-child]:sticky',
        // LOGICAL, not the physical `left` inset. Writing direction does not move a physical edge:
        // under `dir="rtl"` the first column renders on the right, and `left: 0` pinned it to the
        // far END of the row while the scrolling columns ran under it. An LTR screenshot of that
        // looks exactly like a correct one. Named as the CSS property rather than the utility on
        // purpose: Tailwind reads comments too, so a utility spelled here is compiled into the sheet.
        '[table[data-wk-sticky-column]_&:first-child]:start-0',
        '[table[data-wk-sticky-column]_&:first-child]:z-[1]',
        '[table[data-wk-sticky-column]_&:first-child]:bg-[var(--color-wk-bg)]',
        // Capped with the header cell above it, so a long label wraps rather than taking the
        // width the other columns scroll in. The reasoning is next to the same line in th.
        '[table[data-wk-sticky-column]_&:first-child]:max-w-[var(--size-wk-table-sticky-column-max)]',
    ]), $scope);
@endphp

<td data-wk-prose-skip data-wk-table-td {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</td>
