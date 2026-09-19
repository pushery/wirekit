{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'align' => 'left', // left | center | right
    'hideBelow' => null, // sm | md | lg | xl | 2xl — the same value as the column's header
    // Hide the column below a width of the TABLE, not of the window.
    //
    // ⚠️ `hideBelow` asks the viewport, and beside a sidebar that is the wrong question.
    // Measured in an app shell: the table has 649px at a 1024px viewport and 702px at 768px,
    // because the sidebar steps beside the content at `lg`. A column that appears at `md`
    // therefore appears exactly where the table has least room — a reported table ran 141px
    // past its frame at 1024px for that reason.
    //
    // ⚠️ THE SCALE IS THE CONTAINER SCALE AND IT IS NOT THE VIEWPORT ONE. Tailwind's container
    // sizes are their own ladder: `3xl` here is 48rem of TABLE width, where `hide-below="md"`
    // is 48rem of WINDOW. Same numbers, different subject — so the two props are deliberately
    // not interchangeable and a call site should pick one.
    //
    // The container is NAMED (`@container/wk-table` on the table's frame) rather than
    // anonymous: an unnamed container would also become the measuring context for every
    // `@`-variant a caller nests inside the table, and silently retarget it.
    'hideBelowContainer' => null,
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

    /*
     * The container variant, written out per size for the same reason the viewport one is:
     * Tailwind reads a literal class name and never an assembled one.
     *
     * Appended rather than replacing: a column may legitimately answer both questions, and
     * `hidden` from either one wins on its own.
     */
    $hideBelowContainer = filled($hideBelowContainer) ? (string) $hideBelowContainer : null;
    if ($hideBelowContainer !== null && ! in_array($hideBelowContainer, ['sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl'], true)) {
        WireKit::validateProp('table.td', 'hideBelowContainer', $hideBelowContainer, ['sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl']);
        $hideBelowContainer = null;
    }
    $hideClasses = trim($hideClasses.' '.(match ($hideBelowContainer) {
        'sm' => '@max-sm/wk-table:hidden',
        'md' => '@max-md/wk-table:hidden',
        'lg' => '@max-lg/wk-table:hidden',
        'xl' => '@max-xl/wk-table:hidden',
        '2xl' => '@max-2xl/wk-table:hidden',
        '3xl' => '@max-3xl/wk-table:hidden',
        '4xl' => '@max-4xl/wk-table:hidden',
        '5xl' => '@max-5xl/wk-table:hidden',
        default => '',
    }));

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
