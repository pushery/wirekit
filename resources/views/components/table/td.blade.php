{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    // start | center | end, which follow the direction of the page, or left | right, which name a
    // physical side whatever the direction.
    'align' => 'start',
    'hideBelow' => null, // sm | md | lg | xl | 2xl — the same value as the column's header
    // Hide the column below a width of the table, not of the window.
    //
    // `hideBelow` asks the viewport, and beside a sidebar that is the wrong question: in an
    // app shell the table can have less room at a 1024px viewport than at 768px, because the
    // sidebar steps beside the content at `lg`, so a column that appears at `md` appears
    // exactly where the table has least room.
    //
    // The scale is the container scale and it is not the viewport one. Tailwind's container
    // sizes are their own ladder: `3xl` here is 48rem of table width, where `hide-below="md"`
    // is 48rem of window. Same numbers, different subject — so the two props are deliberately
    // not interchangeable and a call site should pick one.
    //
    // The container is named (`@container/wk-table` on the table's frame), so the column asks the
    // frame even where a cell holds a container of the caller's own. A name does not hide the
    // frame from a variant without one: inside the table such a variant asks the nearest
    // container, and that is the frame whenever the frame is one.
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
    // Stacked, the name stands on a line of its own above the value, which takes the card's whole
    // width: for a value of running text, an error message or a description, which the three fifths
    // beside the name would break into many short lines. The cell with the row's actions has no name.
    'labelAbove' => false,
    // The color and the size of the cell's text, with the values a `text` takes: intent is
    // default | muted | subtle | accent | success | warning | danger | inherit, size is
    // xs | sm | base | lg | xl | inherit, and `inherit` writes no class for its axis. A cell that
    // only sets one of them needs no `text` inside it.
    'intent' => 'default',
    'size' => 'base',
    'scope' => null,
])

{{-- The table's `stack-below`: a table that stacks its rows writes its roles out. --}}
@aware([
    'stackBelow' => null,
    'stackBelowContainer' => null,
])

@php
    // At the head of the block: Blaze, which an application may let compile this view into a
    // function, lifts a `use` out of it only from there (Support\BlazeRender).
    use Pushery\WireKit\Support\AwareProp;
    use Pushery\WireKit\Support\BlazeRender;
    use Pushery\WireKit\Support\PropText;
    use Pushery\WireKit\WireKit;

    // Under Blaze no composer runs for this view, so it does their work on its bag and on its
    // text here, and it stands on no stack of the view factory: the tag data the factory keeps at
    // its depth belongs to another component (Support\BlazeRender).
    $underBlaze = BlazeRender::active($attributes);

    if ($underBlaze) {
        BlazeRender::normalize($attributes);
        $label = PropText::text($label);
    }

    // `@aware` takes a key from any ancestor that holds it; these are read from the family only
    // (Support\AwareProp). The table and its rows render through the view factory under Blaze as
    // well, so the search finds the same table under both engines, in a table inside a cell too.
    $stackBelow = AwareProp::read('stackBelow', ['table'], null, ownTag: ! $underBlaze);
    // A table that stacks by its own width writes the same roles out as one that stacks by the
    // window's.
    $stackBelow = filled($stackBelow) ? $stackBelow : AwareProp::read('stackBelowContainer', ['table'], null, ownTag: ! $underBlaze);

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('table.td', $attributes->getAttributes());

    // `@aware` leaves the key in the bag, so a tag that also wrote it would render it as an attribute.
    $attributes = $attributes->except(['stackBelow', 'stack-below', 'stackBelowContainer', 'stack-below-container']);

    // An unknown value is reported through the strictness gate and, where the gate does not throw,
    // aligns to the start of the line.
    $align = filled($align) ? WireKit::validateProp('table.td', 'align', (string) $align, ['start', 'center', 'end', 'left', 'right']) : 'start';

    // Which cell holds the row's actions where the table stacks: the one the caller names, or else
    // a cell aligned to the end, unless a label of its own names its value.
    $isActions = $actions === null
        ? in_array($align, ['end', 'right'], true) && ! filled($label)
        : \Pushery\WireKit\Support\BooleanProp::from($actions, false, 'table.td', 'actions');

    $labelAbove = \Pushery\WireKit\Support\BooleanProp::from($labelAbove, false, 'table.td', 'labelAbove') && ! $isActions;

    $alignClass = match ($align) {
        'center' => 'text-center',
        'end' => 'text-end',
        'left' => 'text-left',
        'right' => 'text-right',
        default => 'text-start',
    };

    // `hideBelow` takes a column off a narrow screen. The classes are written out per breakpoint
    // because Tailwind reads literal class names and never an assembled one, and a `max-*` variant
    // hides only below the breakpoint, so above it the cell keeps the display the table gives it
    // instead of one this component would have to restate. The header and every cell of the column
    // take the same value: a header hidden without its cells, or cells without their header, would
    // put every value under the wrong heading. An unknown value is reported through the strictness
    // gate and, where the gate does not throw, hides nothing.
    //
    // Every `hidden` here comes with `--wk-table-cell-display: none` under the same variant. A
    // stacked table gives each cell its display from the stylesheet, which is unlayered and so
    // outranks a utility, and that rule reads the property before it falls back to its own display.
    $hideBelow = filled($hideBelow) ? (string) $hideBelow : null;
    if ($hideBelow !== null && ! in_array($hideBelow, ['sm', 'md', 'lg', 'xl', '2xl'], true)) {
        WireKit::validateProp('table.td', 'hideBelow', $hideBelow, ['sm', 'md', 'lg', 'xl', '2xl']);
        $hideBelow = null;
    }
    $hideClasses = match ($hideBelow) {
        'sm' => 'max-sm:hidden max-sm:[--wk-table-cell-display:none]',
        'md' => 'max-md:hidden max-md:[--wk-table-cell-display:none]',
        'lg' => 'max-lg:hidden max-lg:[--wk-table-cell-display:none]',
        'xl' => 'max-xl:hidden max-xl:[--wk-table-cell-display:none]',
        '2xl' => 'max-2xl:hidden max-2xl:[--wk-table-cell-display:none]',
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
        'sm' => '@max-sm/wk-table:hidden @max-sm/wk-table:[--wk-table-cell-display:none]',
        'md' => '@max-md/wk-table:hidden @max-md/wk-table:[--wk-table-cell-display:none]',
        'lg' => '@max-lg/wk-table:hidden @max-lg/wk-table:[--wk-table-cell-display:none]',
        'xl' => '@max-xl/wk-table:hidden @max-xl/wk-table:[--wk-table-cell-display:none]',
        '2xl' => '@max-2xl/wk-table:hidden @max-2xl/wk-table:[--wk-table-cell-display:none]',
        '3xl' => '@max-3xl/wk-table:hidden @max-3xl/wk-table:[--wk-table-cell-display:none]',
        '4xl' => '@max-4xl/wk-table:hidden @max-4xl/wk-table:[--wk-table-cell-display:none]',
        '5xl' => '@max-5xl/wk-table:hidden @max-5xl/wk-table:[--wk-table-cell-display:none]',
        default => '',
    } : match ($hideBelowContainerFrom.' '.$hideBelowContainer) {
        'sm sm' => 'sm:@max-sm/wk-table:hidden sm:@max-sm/wk-table:[--wk-table-cell-display:none]',
        'sm md' => 'sm:@max-md/wk-table:hidden sm:@max-md/wk-table:[--wk-table-cell-display:none]',
        'sm lg' => 'sm:@max-lg/wk-table:hidden sm:@max-lg/wk-table:[--wk-table-cell-display:none]',
        'sm xl' => 'sm:@max-xl/wk-table:hidden sm:@max-xl/wk-table:[--wk-table-cell-display:none]',
        'sm 2xl' => 'sm:@max-2xl/wk-table:hidden sm:@max-2xl/wk-table:[--wk-table-cell-display:none]',
        'sm 3xl' => 'sm:@max-3xl/wk-table:hidden sm:@max-3xl/wk-table:[--wk-table-cell-display:none]',
        'sm 4xl' => 'sm:@max-4xl/wk-table:hidden sm:@max-4xl/wk-table:[--wk-table-cell-display:none]',
        'sm 5xl' => 'sm:@max-5xl/wk-table:hidden sm:@max-5xl/wk-table:[--wk-table-cell-display:none]',
        'md sm' => 'md:@max-sm/wk-table:hidden md:@max-sm/wk-table:[--wk-table-cell-display:none]',
        'md md' => 'md:@max-md/wk-table:hidden md:@max-md/wk-table:[--wk-table-cell-display:none]',
        'md lg' => 'md:@max-lg/wk-table:hidden md:@max-lg/wk-table:[--wk-table-cell-display:none]',
        'md xl' => 'md:@max-xl/wk-table:hidden md:@max-xl/wk-table:[--wk-table-cell-display:none]',
        'md 2xl' => 'md:@max-2xl/wk-table:hidden md:@max-2xl/wk-table:[--wk-table-cell-display:none]',
        'md 3xl' => 'md:@max-3xl/wk-table:hidden md:@max-3xl/wk-table:[--wk-table-cell-display:none]',
        'md 4xl' => 'md:@max-4xl/wk-table:hidden md:@max-4xl/wk-table:[--wk-table-cell-display:none]',
        'md 5xl' => 'md:@max-5xl/wk-table:hidden md:@max-5xl/wk-table:[--wk-table-cell-display:none]',
        'lg sm' => 'lg:@max-sm/wk-table:hidden lg:@max-sm/wk-table:[--wk-table-cell-display:none]',
        'lg md' => 'lg:@max-md/wk-table:hidden lg:@max-md/wk-table:[--wk-table-cell-display:none]',
        'lg lg' => 'lg:@max-lg/wk-table:hidden lg:@max-lg/wk-table:[--wk-table-cell-display:none]',
        'lg xl' => 'lg:@max-xl/wk-table:hidden lg:@max-xl/wk-table:[--wk-table-cell-display:none]',
        'lg 2xl' => 'lg:@max-2xl/wk-table:hidden lg:@max-2xl/wk-table:[--wk-table-cell-display:none]',
        'lg 3xl' => 'lg:@max-3xl/wk-table:hidden lg:@max-3xl/wk-table:[--wk-table-cell-display:none]',
        'lg 4xl' => 'lg:@max-4xl/wk-table:hidden lg:@max-4xl/wk-table:[--wk-table-cell-display:none]',
        'lg 5xl' => 'lg:@max-5xl/wk-table:hidden lg:@max-5xl/wk-table:[--wk-table-cell-display:none]',
        'xl sm' => 'xl:@max-sm/wk-table:hidden xl:@max-sm/wk-table:[--wk-table-cell-display:none]',
        'xl md' => 'xl:@max-md/wk-table:hidden xl:@max-md/wk-table:[--wk-table-cell-display:none]',
        'xl lg' => 'xl:@max-lg/wk-table:hidden xl:@max-lg/wk-table:[--wk-table-cell-display:none]',
        'xl xl' => 'xl:@max-xl/wk-table:hidden xl:@max-xl/wk-table:[--wk-table-cell-display:none]',
        'xl 2xl' => 'xl:@max-2xl/wk-table:hidden xl:@max-2xl/wk-table:[--wk-table-cell-display:none]',
        'xl 3xl' => 'xl:@max-3xl/wk-table:hidden xl:@max-3xl/wk-table:[--wk-table-cell-display:none]',
        'xl 4xl' => 'xl:@max-4xl/wk-table:hidden xl:@max-4xl/wk-table:[--wk-table-cell-display:none]',
        'xl 5xl' => 'xl:@max-5xl/wk-table:hidden xl:@max-5xl/wk-table:[--wk-table-cell-display:none]',
        '2xl sm' => '2xl:@max-sm/wk-table:hidden 2xl:@max-sm/wk-table:[--wk-table-cell-display:none]',
        '2xl md' => '2xl:@max-md/wk-table:hidden 2xl:@max-md/wk-table:[--wk-table-cell-display:none]',
        '2xl lg' => '2xl:@max-lg/wk-table:hidden 2xl:@max-lg/wk-table:[--wk-table-cell-display:none]',
        '2xl xl' => '2xl:@max-xl/wk-table:hidden 2xl:@max-xl/wk-table:[--wk-table-cell-display:none]',
        '2xl 2xl' => '2xl:@max-2xl/wk-table:hidden 2xl:@max-2xl/wk-table:[--wk-table-cell-display:none]',
        '2xl 3xl' => '2xl:@max-3xl/wk-table:hidden 2xl:@max-3xl/wk-table:[--wk-table-cell-display:none]',
        '2xl 4xl' => '2xl:@max-4xl/wk-table:hidden 2xl:@max-4xl/wk-table:[--wk-table-cell-display:none]',
        '2xl 5xl' => '2xl:@max-5xl/wk-table:hidden 2xl:@max-5xl/wk-table:[--wk-table-cell-display:none]',
        default => '',
    }));

    // The text of the cell takes the classes a `text` writes for the same value, so a cell and a
    // `text` inside one read alike. The defaults skip the check: a long table renders thousands of
    // cells, and most of them set neither. An unknown value is reported through the strictness gate
    // and, where the gate does not throw, takes the default.
    $intent = filled($intent) ? (string) $intent : 'default';
    $intentClass = match ($intent === 'default' ? 'default' : WireKit::validateProp('table.td', 'intent', $intent, ['default', 'muted', 'subtle', 'accent', 'success', 'warning', 'danger', 'inherit'])) {
        'inherit' => '',
        'muted' => 'text-[color:var(--color-wk-text-muted)]',
        'subtle' => 'text-[color:var(--color-wk-text-subtle)]',
        'accent' => 'text-[color:var(--color-wk-accent-text)]',
        'success' => 'text-[color:var(--color-wk-success-text)]',
        'warning' => 'text-[color:var(--color-wk-warning-text)]',
        'danger' => 'text-[color:var(--color-wk-danger-text)]',
        default => 'text-[color:var(--color-wk-text)]',
    };
    $size = filled($size) ? (string) $size : 'base';
    $sizeClass = match ($size === 'base' ? 'base' : WireKit::validateProp('table.td', 'size', $size, ['base', 'xs', 'sm', 'lg', 'xl', 'inherit'])) {
        'inherit' => '',
        'xs' => 'text-[length:var(--text-wk-xs,0.75rem)]',
        'sm' => 'text-[length:var(--text-wk-sm)]',
        'lg' => 'text-[length:var(--text-wk-lg)]',
        'xl' => 'text-[length:var(--text-wk-xl,1.25rem)]',
        default => 'text-[length:var(--text-wk-md)]',
    };

    // Base td styling — standard padding, body text weight, compact-aware padding
    $classes = WireKit::resolveClasses('table.td', 'base', implode(' ', [
        'px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)]',
        $sizeClass,
        $intentClass,
        'font-[number:var(--font-wk-body-weight)]',
        $alignClass,
        $hideClasses,
        // The table's switches are not written here. The stylesheet reads `compact` and the frozen
        // first column at the nearest table, through child combinators, so a table in one of these
        // cells keeps its own, and a cell carries only its own classes however long its table is.
    ]), $scope);
@endphp

<td data-wk-table-td @if($hideBelowContainer !== null) data-wk-table-measures @endif
    {{-- Stacked, the cell with the row's actions stands as the card's footer, and every other cell
         is a line named by its column, or by a label of its own, which wins over its column's. --}}
    @if(filled($stackBelow))
        role="cell"
        @if($isActions) data-wk-cell-actions @endif
        @if($labelAbove) data-wk-label-above @endif
        @if(filled($label)) data-label="{{ $label }}" data-wk-label-own @endif
    @endif
    {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}>
    {{ $slot }}
</td>
