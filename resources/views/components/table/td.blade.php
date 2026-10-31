{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    'align' => 'left', // left | center | right
    'hideBelow' => null, // sm | md | lg | xl | 2xl — the same value as the column's header
    // Hide the column below a width of the TABLE, not of the window.
    //
    // `hideBelow` asks the viewport, and beside a sidebar that is the wrong question: in an
    // app shell the table can have less room at a 1024px viewport than at 768px, because the
    // sidebar steps beside the content at `lg`, so a column that appears at `md` appears
    // exactly where the table has least room.
    //
    // The scale is the container scale and it is not the viewport one. Tailwind's container
    // sizes are their own ladder: `3xl` here is 48rem of TABLE width, where `hide-below="md"`
    // is 48rem of WINDOW. Same numbers, different subject — so the two props are deliberately
    // not interchangeable and a call site should pick one.
    //
    // The container is NAMED (`@container/wk-table` on the table's frame) rather than
    // anonymous: an unnamed container would also become the measuring context for every
    // `@`-variant a caller nests inside the table, and silently retarget it.
    'hideBelowContainer' => null,
    // The window width from which `hideBelowContainer` applies: sm | md | lg | xl | 2xl. Below it the
    // column keeps its place, and the table scrolls in its own frame. A list that may scroll on a
    // phone but not on a desk takes `hide-below-container="5xl"` with this at `xl`: the column gives
    // way only in a window of 80rem and up. `table.stand-in` shows what stands in for it, there.
    'hideBelowContainerFrom' => null,
    // The name of the cell's column where the table stacks its rows (`stack-below` on the table).
    // Unset, the header above the column names it.
    'label' => null,
    // Stacked, the cell that holds the row's actions stands as the card's footer, without a name.
    // Unset, that is a cell aligned to the end that carries no label of its own. `false` keeps a
    // value aligned to the end, a column of figures, as a line named by its column, and `true`
    // makes any cell the footer.
    'actions' => null,
    'scope' => null,
])

{{-- The table's `stack-below`: a table that stacks its rows writes its roles out. --}}
@aware([
    'stackBelow' => null,
    'stackBelowContainer' => null,
])

@php
    // `@aware` takes a key from any ancestor that holds it; these are read from the family only
    // (Support\AwareProp).
    $stackBelow = \Pushery\WireKit\Support\AwareProp::read('stackBelow', ['table'], null);
    // A table that stacks by its own width writes the same roles out as one that stacks by the window's.
    $stackBelow = filled($stackBelow) ? $stackBelow : \Pushery\WireKit\Support\AwareProp::read('stackBelowContainer', ['table'], null);

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('table.td', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // `@aware` leaves the key in the bag, so a tag that also wrote it would render it as an attribute.
    $attributes = $attributes->except(['stackBelow', 'stack-below', 'stackBelowContainer', 'stack-below-container']);

    // An unknown value is reported through the strictness gate and, where the gate does not throw,
    // aligns left.
    $align = filled($align) ? WireKit::validateProp('table.td', 'align', (string) $align, ['left', 'center', 'right']) : 'left';

    // Which cell holds the row's actions where the table stacks: the one the caller names, or else
    // a cell aligned to the end, unless a label of its own names its value.
    $isActions = $actions === null
        ? $align === 'right' && ! filled($label)
        : \Pushery\WireKit\Support\BooleanProp::from($actions, false, 'table.td', 'actions');

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
    // put every value under the wrong heading. An unknown value is reported through the strictness
    // gate and, where the gate does not throw, hides nothing.
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
    /*
     * From a window width up only: the container question asked behind a viewport one, every pair
     * written out for the same reason. Without `hideBelowContainer` there is nothing to ask it of,
     * and the prop is reported.
     */
    $hideBelowContainerFrom = filled($hideBelowContainerFrom) ? (string) $hideBelowContainerFrom : null;
    if ($hideBelowContainerFrom !== null && ! in_array($hideBelowContainerFrom, ['sm', 'md', 'lg', 'xl', '2xl'], true)) {
        WireKit::validateProp('table.td', 'hideBelowContainerFrom', $hideBelowContainerFrom, ['sm', 'md', 'lg', 'xl', '2xl']);
        $hideBelowContainerFrom = null;
    }
    if ($hideBelowContainerFrom !== null && $hideBelowContainer === null) {
        \Pushery\WireKit\Support\StrictnessGate::reject('table.td', 'hideBelowContainerFrom', $hideBelowContainerFrom, 'a cell with hideBelowContainer', '');
        $hideBelowContainerFrom = null;
    }
    $hideClasses = trim($hideClasses.' '.($hideBelowContainerFrom === null ? match ($hideBelowContainer) {
        'sm' => '@max-sm/wk-table:hidden',
        'md' => '@max-md/wk-table:hidden',
        'lg' => '@max-lg/wk-table:hidden',
        'xl' => '@max-xl/wk-table:hidden',
        '2xl' => '@max-2xl/wk-table:hidden',
        '3xl' => '@max-3xl/wk-table:hidden',
        '4xl' => '@max-4xl/wk-table:hidden',
        '5xl' => '@max-5xl/wk-table:hidden',
        default => '',
    } : match ($hideBelowContainerFrom.' '.$hideBelowContainer) {
        'sm sm' => 'sm:@max-sm/wk-table:hidden',
        'sm md' => 'sm:@max-md/wk-table:hidden',
        'sm lg' => 'sm:@max-lg/wk-table:hidden',
        'sm xl' => 'sm:@max-xl/wk-table:hidden',
        'sm 2xl' => 'sm:@max-2xl/wk-table:hidden',
        'sm 3xl' => 'sm:@max-3xl/wk-table:hidden',
        'sm 4xl' => 'sm:@max-4xl/wk-table:hidden',
        'sm 5xl' => 'sm:@max-5xl/wk-table:hidden',
        'md sm' => 'md:@max-sm/wk-table:hidden',
        'md md' => 'md:@max-md/wk-table:hidden',
        'md lg' => 'md:@max-lg/wk-table:hidden',
        'md xl' => 'md:@max-xl/wk-table:hidden',
        'md 2xl' => 'md:@max-2xl/wk-table:hidden',
        'md 3xl' => 'md:@max-3xl/wk-table:hidden',
        'md 4xl' => 'md:@max-4xl/wk-table:hidden',
        'md 5xl' => 'md:@max-5xl/wk-table:hidden',
        'lg sm' => 'lg:@max-sm/wk-table:hidden',
        'lg md' => 'lg:@max-md/wk-table:hidden',
        'lg lg' => 'lg:@max-lg/wk-table:hidden',
        'lg xl' => 'lg:@max-xl/wk-table:hidden',
        'lg 2xl' => 'lg:@max-2xl/wk-table:hidden',
        'lg 3xl' => 'lg:@max-3xl/wk-table:hidden',
        'lg 4xl' => 'lg:@max-4xl/wk-table:hidden',
        'lg 5xl' => 'lg:@max-5xl/wk-table:hidden',
        'xl sm' => 'xl:@max-sm/wk-table:hidden',
        'xl md' => 'xl:@max-md/wk-table:hidden',
        'xl lg' => 'xl:@max-lg/wk-table:hidden',
        'xl xl' => 'xl:@max-xl/wk-table:hidden',
        'xl 2xl' => 'xl:@max-2xl/wk-table:hidden',
        'xl 3xl' => 'xl:@max-3xl/wk-table:hidden',
        'xl 4xl' => 'xl:@max-4xl/wk-table:hidden',
        'xl 5xl' => 'xl:@max-5xl/wk-table:hidden',
        '2xl sm' => '2xl:@max-sm/wk-table:hidden',
        '2xl md' => '2xl:@max-md/wk-table:hidden',
        '2xl lg' => '2xl:@max-lg/wk-table:hidden',
        '2xl xl' => '2xl:@max-xl/wk-table:hidden',
        '2xl 2xl' => '2xl:@max-2xl/wk-table:hidden',
        '2xl 3xl' => '2xl:@max-3xl/wk-table:hidden',
        '2xl 4xl' => '2xl:@max-4xl/wk-table:hidden',
        '2xl 5xl' => '2xl:@max-5xl/wk-table:hidden',
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
        // Each switch is read at the nearest table, through child combinators: a table in one of
        // this table's cells is a descendant of it as well, and keeps its own switches. The parser
        // always puts a row in a section and a cell in a row, so the path is exact.
        // Compact variant: reduce vertical padding to match th
        '[table[data-wk-compact]>*>tr>&]:py-[var(--padding-wk-y-sm)]',
        // Sticky first column: freeze the leading body cell. Solid background so
        // scrolling cells don't show through. The stylesheet paints it in the surface the
        // cell stands on, so on a striped or a hovered row it wears the row's color, and it
        // lets the table's own lines show through its edges (dist/wirekit.css, the frozen
        // cells of the component's own tables).
        '[table[data-wk-sticky-column]>*>tr>&:first-child]:sticky',
        // LOGICAL, not the physical `left` inset. Writing direction does not move a physical edge:
        // under `dir="rtl"` the first column renders on the right, and `left: 0` pinned it to the
        // far END of the row while the scrolling columns ran under it. An LTR screenshot of that
        // looks exactly like a correct one. Named as the CSS property rather than the utility on
        // purpose: Tailwind reads comments too, so a utility spelled here is compiled into the sheet.
        '[table[data-wk-sticky-column]>*>tr>&:first-child]:start-0',
        '[table[data-wk-sticky-column]>*>tr>&:first-child]:z-[1]',
        '[table[data-wk-sticky-column]>*>tr>&:first-child]:bg-[var(--color-wk-bg)]',
        // Capped with the header cell above it, so a long label wraps rather than taking the
        // width the other columns scroll in. The reasoning is next to the same line in th.
        '[table[data-wk-sticky-column]>*>tr>&:first-child]:max-w-[var(--size-wk-table-sticky-column-max)]',
    ]), $scope);
@endphp

<td data-wk-prose-skip data-wk-table-td
    {{-- Stacked, the cell with the row's actions stands as the card's footer, and every other cell
         is a line named by its column, or by a label of its own, which wins over its column's. --}}
    @if(filled($stackBelow))
        role="cell"
        @if($isActions) data-wk-cell-actions @endif
        @if(filled($label)) data-label="{{ $label }}" data-wk-label-own @endif
    @endif
    {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}>
    {{ $slot }}
</td>
