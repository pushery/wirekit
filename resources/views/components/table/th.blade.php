{{-- optimistic-ui: n/a — query. The wire:click this component emits is a SORT: a
     query round trip, not a mutation. We cannot show its result before the server
     answers, because nobody knows the result — only the intent could be
     acknowledged, and that is a different state machine and deliberately out of
     scope. --}}
@props([
    'sortable' => false,
    'sortDirection' => null, // null | 'asc' | 'desc' — current sort state (Livewire mode)
    'column' => null, // column identifier for Alpine sort mode (pairs with table alpine-sort)
    // Livewire-sort mode only: the wire:click method call for a keyboard-operable
    // sort button, e.g. "sortBy('name')". When set, the header label + indicator
    // are wrapped in a <button wire:click> so the sort is reachable by keyboard
    // (WCAG 2.1.1) — the plain <th> click on a cursor-pointer cell is mouse-only.
    // Null keeps a static <span> (the developer supplies their own control
    // via $attributes). Ignored in
    // Alpine-sort mode (the `column` prop already renders its own button model).
    'sortAction' => null,
    'align' => 'left', // left | center | right
    'scope' => null,
    // HTML <th scope> attribute — 'col' (default) for a column header, 'row' for a
    // per-row header cell (WCAG 1.3.1: a row-header cell identifies its data row).
    // Distinct from the `scope` prop above, which is WireKit's token-scope override —
    // overloading that would break scoped theming for the 200+ components that share it.
    'headerScope' => 'col',
    // sm | md | lg | xl | 2xl — hide this column below that breakpoint; its cells take the same value.
    'hideBelow' => null,
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
    // Let a long header break instead of holding its column as wide as the longest word.
    //
    // A column header is `whitespace-nowrap` by default, and that is right for the usual
    // one-word head. It is wrong for a column whose NAME is long and whose CONTENT is not:
    // a permissions table with heads like "Nutzungsbedingungen" over icons that need 16px
    // holds each column at the width of its word, and the table runs past its frame rather
    // than the heads taking two lines.
    //
    // `wrap` breaks ANYWHERE, which is what a long compound over a narrow icon column needs.
    // `wrap="words"` breaks only between words and evens the lines up: over a column of numbers
    // there is nothing to hold the column wider than a digit, and a head that may break anywhere
    // fell to that width ("Line / s", "to 2 / %"). Between words, the longest word is the floor.
    'wrap' => false,
    // Turn the label on its side, reading from bottom to top on one line.
    //
    // For a column whose NAME is long and whose values are a mark or a glyph, next to a column
    // that takes the rest of the width, such as a user list with one column per legal text.
    // `wrap` cannot help there, since
    // the wide column claims everything the heads give up and they fall to one letter per line.
    // A turned head is as wide as one line of text in any language, and the row grows instead.
    //
    // Every head in that row then stands on the row's floor (the stylesheet does that, from the
    // row), and a sort indicator stays upright under the label. A turned head is one line, so it
    // outranks `wrap`.
    'sideways' => false,
    // The type of the head: `header` (the default), the size and weight of a column head, or
    // `cell`, the size and weight of a body cell. A row header (`headerScope="row"`) is often a
    // value of its row, a product name or a budget, and reads as one with `cell`. It changes the
    // type alone: the scope, the alignment and the responsive hiding stay what the other props say.
    'variant' => 'header', // header | cell
    // The help of a column: the question mark a `label` shows beside a field, beside the head, with
    // this text in its tooltip. It stands beside a sort button and never inside it, so its click
    // opens the help without sorting, and the button's name stays the head.
    'help' => null,
    // The id of a hidden copy of `help`, which a field in each row of the column lists in its
    // `aria-describedby`. Without it the help is the tooltip alone.
    'helpId' => null,
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
    \Pushery\WireKit\WireKit::warnUnknownProps('table.th', $attributes->getAttributes());

    // An unknown `headerScope` is reported (Support\StrictnessGate) and renders as `col`; a bound
    // `null` or empty string is `col` without a word.
    $headerScope = \Pushery\WireKit\WireKit::validateProp('table.th', 'headerScope', \Pushery\WireKit\Support\NumericProp::text($headerScope ?: 'col'), ['col', 'row', 'colgroup', 'rowgroup']);

    // No `sortDirection`, or one of these. An unknown one is reported (Support\StrictnessGate) and
    // leaves the sortDirection unset, as no `sortDirection` does, rather than taking the first of
    // the list.
    $sortDirection = filled($sortDirection) ? \Pushery\WireKit\Support\NumericProp::text($sortDirection) : null;
    if ($sortDirection !== null && \Pushery\WireKit\WireKit::validateProp('table.th', 'sortDirection', $sortDirection, ['asc', 'desc']) !== $sortDirection) {
        $sortDirection = null;
    }

    // `true`, `false` or `words`. Another word is reported (Support\StrictnessGate) and wraps as
    // `true` does; a word such as `yes` or `0` reads as the switch it names.
    if (is_string($wrap) && trim($wrap) !== '' && \Pushery\WireKit\Support\BooleanProp::orNull($wrap) === null) {
        $wrap = \Pushery\WireKit\WireKit::validateProp('table.th', 'wrap', $wrap, ['true', 'false', 'words']);
    }

    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // `@aware` leaves the key in the bag, so a tag that also wrote it would render it as an attribute.
    $attributes = $attributes->except(['stackBelow', 'stack-below', 'stackBelowContainer', 'stack-below-container']);

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $sortable = BooleanProp::from($sortable, false, 'table.th', 'sortable');
    $sideways = BooleanProp::from($sideways, false, 'table.th', 'sideways');

    // Alignment maps to text-* utilities. An unknown value is reported through the strictness gate
    // and, where the gate does not throw, aligns left.
    $align = filled($align) ? WireKit::validateProp('table.th', 'align', (string) $align, ['left', 'center', 'right']) : 'left';

    $alignClass = match ($align) {
        'center' => 'text-center',
        'right' => 'text-right',
        default => 'text-left',
    };

    // The size and weight of a column head, or of a body cell with `variant="cell"`: the same two
    // utilities `table.td` sets. An unknown value is reported through the strictness gate and,
    // where the gate does not throw, keeps the head's type.
    $variant = filled($variant) ? WireKit::validateProp('table.th', 'variant', (string) $variant, ['header', 'cell']) : 'header';
    $typeClasses = $variant === 'cell'
        ? 'font-[number:var(--font-wk-body-weight)] text-[length:var(--text-wk-md)]'
        : 'font-[number:var(--font-wk-heading-weight)] text-[length:var(--text-wk-sm)]';

    // The same alignment as a flex justification, for the sort-action button: it is
    // `w-full` so its hit area covers the cell, and a full-width flex container
    // ignores the cell's text-align — the label would snap left in a right-aligned
    // numeric column without this.
    $justifyClass = match ($align) {
        'center' => 'justify-center',
        'right' => 'justify-end',
        default => 'justify-start',
    };

    // A turned head lays the sort button out as a column: the label, then the indicator under
    // it, upright, so its arrow still points the way the order runs. Across the column the pair
    // follows `align`, as a label does in any head.
    $sortLayout = $sideways
        ? 'flex-col justify-end gap-1 '.match ($align) {
            'center' => 'items-center',
            'right' => 'items-end',
            default => 'items-start',
        }
        : 'items-center gap-1 '.$justifyClass;

    // The label itself. Only the text turns; the indicator beside it in the markup below is
    // outside this span, which is what keeps it upright.
    $label = $sideways
        ? new \Illuminate\Support\HtmlString('<span class="wk-sideways">'.$slot.'</span>')
        : $slot;

    // The column's help, named by the head as the page shows it. Its click goes no further than
    // the help: a head that sorts on a click of its own, a `wire:click` on the cell, would sort
    // otherwise. A click reports the column as the field it explains.
    $helpPartial = filled($help) ? [
        'helpText' => $help,
        'helpName' => \Pushery\WireKit\Support\SlotContent::plain($slot),
        'helpId' => filled($helpId) ? (string) $helpId : null,
        'helpField' => (string) ($column ?? ''),
        'helpStopsClick' => true,
    ] : null;

    // A sort button fills its cell, so its box is the cell. With help, the button and the help are
    // two boxes on one line, placed by the cell's text-align like any head: the button takes the
    // start padding, the help stands one gap after it, as beside a field's label, and the cell
    // keeps its end padding past the help. On paper the button gives up its box and the print
    // rules pad the cell on both sides, which draws the same head. A turned head keeps its
    // full-width button and takes the help under it, across the cell where its label stands.
    $helpBeside = $helpPartial !== null && ! $sideways;
    $sortButtonBox = $helpBeside ? 'inline-flex align-middle' : 'flex w-full';
    $helpBesideBox = 'inline-flex align-middle ps-[var(--gap-wk-xs)]';
    $helpItems = match ($align) {
        'center' => 'items-center',
        'right' => 'items-end',
        default => 'items-start',
    };

    // Read BEFORE the class builder so a row header can drop the column-header look.
    $isRowHeader = $headerScope === 'row';

    // A ROW header labels its own data row (WCAG 1.3.1) and reads as a heading
    // for that row — NOT as a column header. The muted, nowrap column-header
    // treatment made a row header render small, greyed-out and clipped; it now
    // uses the regular text color and is allowed to wrap.
    // `words` is its own mode; any other value is the boolean it always was.
    $wrapWords = $wrap === 'words';
    $wrap = $wrapWords || BooleanProp::from($wrap, false);

    /*
     * `whitespace-normal` is emitted explicitly rather than by leaving `nowrap` off: two Tailwind
     * utilities for one property are decided by their order in the generated stylesheet, not by
     * the order in the attribute, so a developer's own `class="whitespace-normal"` does not
     * reliably win.
     *
     * `[overflow-wrap:anywhere]` comes with the wrap, the same pairing `button`'s `wrap-label`
     * ships and for the same reason: `whitespace-normal` only permits a break at a space or a
     * hyphen, and a long compound noun has neither. Without it the head stays exactly as wide
     * as its longest word.
     *
     * Deliberately not the `hyphens` utility. It depends on a dictionary the browser may not
     * have — Chromium ships hyphenation as a downloadable component on Linux. A break rule that works everywhere beats a
     * prettier one that is a different rule in CI than on a laptop.
     *
     * The utility is described rather than spelled: Tailwind scans raw files, so naming a
     * class in a comment would compile a utility nothing uses.
     */
    $scopeText = $isRowHeader
        ? 'text-[color:var(--color-wk-text)]'
        : 'text-[color:var(--color-wk-text-muted)] '.match (true) {
            $wrapWords => 'whitespace-normal wk-lines-balanced',
            $wrap => 'whitespace-normal [overflow-wrap:anywhere]',
            default => 'whitespace-nowrap',
        };

    // Whenever a sort <button> renders, the padding moves onto it instead of sitting
    // on the cell (see both buttons below). Left on the cell, it would leave the button
    // a target only as tall as its text inside a taller cell whose padding does nothing,
    // under the 24px WCAG 2.5.8 (AA) minimum.
    //
    // Padding on the button rather than duplicated onto it: growing the button with
    // `py` + a matching negative `my` reads like it should keep the row height, and
    // it does not. An inline-flex child grows the line box, so the cell's own padding
    // lands on top of the taller box. Moving the padding keeps the cell exactly as
    // tall and keeps the whole cell clickable.
    //
    // The gate is "does a button render", not "which sort mode is this": both modes
    // render one (Alpine-sort binds `@click`, Livewire-sort binds `wire:click`), so
    // the relocated padding always has something to land on. A gate on a narrower
    // condition would strip the padding from a header that renders no button, and
    // collapse it under the floor the relocation exists to reach.
    $padOnButton = $sortable && ($column !== null || $sortAction !== null);
    $cellPadding = $padOnButton
        ? ($helpBeside ? 'pe-[var(--padding-wk-x-md)]' : '')
        : 'px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)]';

    // `hideBelow` takes a column off a narrow screen. The classes are written out per breakpoint
    // because Tailwind reads literal class names and never an assembled one, and a `max-*` variant
    // hides only below the breakpoint, so above it the cell keeps the display the table gives it
    // instead of one this component would have to restate. The header and every cell of the column
    // take the same value: a header hidden without its cells, or cells without their header, would
    // put every value under the wrong heading. An unknown value is reported through the strictness
    // gate and, where the gate does not throw, hides nothing.
    $hideBelow = filled($hideBelow) ? (string) $hideBelow : null;
    if ($hideBelow !== null && ! in_array($hideBelow, ['sm', 'md', 'lg', 'xl', '2xl'], true)) {
        WireKit::validateProp('table.th', 'hideBelow', $hideBelow, ['sm', 'md', 'lg', 'xl', '2xl']);
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
        WireKit::validateProp('table.th', 'hideBelowContainer', $hideBelowContainer, ['sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl']);
        $hideBelowContainer = null;
    }
    /*
     * From a window width up only: the container question asked behind a viewport one, every pair
     * written out for the same reason. Without `hideBelowContainer` there is nothing to ask it of,
     * and the prop is reported.
     */
    $hideBelowContainerFrom = filled($hideBelowContainerFrom) ? (string) $hideBelowContainerFrom : null;
    if ($hideBelowContainerFrom !== null && ! in_array($hideBelowContainerFrom, ['sm', 'md', 'lg', 'xl', '2xl'], true)) {
        WireKit::validateProp('table.th', 'hideBelowContainerFrom', $hideBelowContainerFrom, ['sm', 'md', 'lg', 'xl', '2xl']);
        $hideBelowContainerFrom = null;
    }
    if ($hideBelowContainerFrom !== null && $hideBelowContainer === null) {
        \Pushery\WireKit\Support\StrictnessGate::reject('table.th', 'hideBelowContainerFrom', $hideBelowContainerFrom, 'a header with hideBelowContainer', '');
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

    // Base th styling — heading weight, scope-aware text, compact-aware padding
    // via table[data-wk-compact] selector
    $classes = WireKit::resolveClasses('table.th', 'base', implode(' ', [
        $cellPadding,
        $typeClasses,
        $scopeText,
        $alignClass,
        $hideClasses,
        // Each switch is read at the nearest table, through child combinators: a table in one of
        // this table's cells is a descendant of it as well, and keeps its own switches. The parser
        // always puts a row in a section and a cell in a row, so the path is exact.
        // Compact variant: reduce vertical padding
        $padOnButton ? '' : '[table[data-wk-compact]>*>tr>&]:py-[var(--padding-wk-y-sm)]',
        // Sticky first column: freeze the leading header cell. It needs its own
        // background (it would otherwise show scrolling body cells through it) and
        // a z-index ABOVE the sticky header (z-10) so the top-left corner stays on top.
        '[table[data-wk-sticky-column]>*>tr>&:first-child]:sticky',
        // LOGICAL, not the physical `left` inset. Writing direction does not move a physical edge:
        // under `dir="rtl"` the first column renders on the right, and `left: 0` pinned it to the
        // far END of the row while the scrolling columns ran under it. An LTR screenshot of that
        // looks exactly like a correct one. Named as the CSS property rather than the utility on
        // purpose: Tailwind reads comments too, so a utility spelled here is compiled into the sheet.
        '[table[data-wk-sticky-column]>*>tr>&:first-child]:start-0',
        '[table[data-wk-sticky-column]>*>tr>&:first-child]:z-20',
        '[table[data-wk-sticky-column]>*>tr>&:first-child]:bg-[var(--color-wk-bg-subtle)]',
        // The frozen column is capped, or a sticky-column table, which takes its max-content
        // width to overflow at all, holds a long label on one line and the column takes most of
        // a phone. A max-width on a table cell bounds its column in automatic layout
        // in every engine, and spare width is still handed out, so a table with room wraps
        // nothing. The column header has to be allowed to wrap as well: its nowrap would keep
        // the column exactly as wide as its label, cap or not.
        '[table[data-wk-sticky-column]>*>tr>&:first-child]:max-w-[var(--size-wk-table-sticky-column-max)]',
        '[table[data-wk-sticky-column]>*>tr>&:first-child]:whitespace-normal',
        // Sortable headers get the hover color; the pointer cursor goes wherever the
        // click target actually is. `$padOnButton` rather than `$sortAction` alone: with a
        // button present the button is the target and carries its own cursor-pointer, and
        // it fills the cell, so repeating the pointer on the <th> would either duplicate it
        // or — where the button did not reach the cell edges — advertise a pointer over a
        // dead zone. A sortable header with NO button (Livewire-sort without `sortAction`,
        // where the developer supplies the control) keeps the pointer on the cell.
        $padOnButton ? 'select-none hover:text-[color:var(--color-wk-text)]' : '',
    ]), $scope);

    // A font size or weight in the caller's `class` replaces the head's own. Beside it the
    // stylesheet's order would decide which one wins, not the attribute. A size or weight under a
    // variant is left alone, because it applies in a state or at a width of its own.
    $callerClass = \Illuminate\Support\Arr::toCssClasses($attributes->get('class', ''));
    foreach ([\Pushery\WireKit\Support\UtilityClasses::FONT_SIZE, \Pushery\WireKit\Support\UtilityClasses::FONT_WEIGHT] as $property) {
        if (\Pushery\WireKit\Support\UtilityClasses::has($callerClass, $property)) {
            $classes = \Pushery\WireKit\Support\UtilityClasses::without($classes, $property);
        }
    }

    // ARIA: sortable columns expose their current sort state.
    //
    // `$sortable` alone is not a sortable column. Both operable shapes need something to
    // sort BY — Alpine mode needs `column`, Livewire mode needs `sortAction` — and with
    // neither the header would render `aria-sort="none"`, a cursor-pointer and a hover state
    // while emitting no button at all. That is the precise combination WCAG 2.1.1 is about:
    // the cell ANNOUNCES a sort order and a keyboard or switch user can hear it and never
    // change it, while the pointer affordance says they should be able to.
    //
    // `$padOnButton` already computes exactly this condition for a layout reason; it is
    // reused rather than recomputed so the two cannot drift apart.
    $ariaSort = match ($sortDirection) {
        'asc' => 'ascending',
        'desc' => 'descending',
        default => $padOnButton ? 'none' : null,
    };
@endphp

<th data-wk-prose-skip
    scope="{{ $headerScope }}"
    data-wk-table-th
    {{-- Stacked, each cell of the column is named by this header, by its words as the page
         shows them, without the state a sort button adds for a screen reader alone. --}}
    @if(filled($stackBelow))
        role="{{ $headerScope === 'row' || $headerScope === 'rowgroup' ? 'rowheader' : 'columnheader' }}"
        data-wk-th-label="{{ \Pushery\WireKit\Support\SlotContent::plain($slot) }}"
    @endif
    {{-- A head names the cells of its column, and with help inside it its name from content would
         end in the help button's name, which a screen reader then reads with every cell. Named by
         its words instead, unless the caller names it. --}}
    @if($helpPartial !== null && $helpPartial['helpName'] !== '' && ! $attributes->has('aria-label') && ! $attributes->has('aria-labelledby'))
        aria-label="{{ $helpPartial['helpName'] }}"
    @endif
    @if($column) data-wk-sort-column="{{ $column }}" @endif
    @if($sortable && $column)
        {{-- Alpine sort mode: bind aria-sort to parent wirekitTableSort state. The click
             lives on the <button> below, not here — a cursor-pointer cell with an @click
             is mouse-only, and this cell ANNOUNCES its sort state, so a keyboard or switch
             user could hear the order and never be able to change it (WCAG 2.1.1). --}}
        :aria-sort="getSortDirection({{ \Pushery\WireKit\Support\AlpinePayload::string($column) }}) === 'asc' ? 'ascending' : getSortDirection({{ \Pushery\WireKit\Support\AlpinePayload::string($column) }}) === 'desc' ? 'descending' : 'none'"
    @elseif($ariaSort)
        aria-sort="{{ $ariaSort }}"
    @endif
    {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}
>
    @if($sortable && $column)
        {{-- Alpine sort mode. The click sits on a real <button>, the same shape the
             Livewire-sort branch below uses, so the sort is operable by keyboard and by
             switch — a native button activates on Enter and Space with no key handler of
             our own, which is why this is a button rather than a tabindex + @keydown on
             the cell. It carries the cell's relocated padding so its box IS the cell (the
             24px AA target on both axes, no inert cell area); `w-full` plus a justify
             matching the column alignment keeps the label exactly where the text-align
             put it; and the focus ring is inset because the table's scroll wrapper
             (`overflow-x-auto`, which computes `overflow-y: auto`) would clip an outset
             ring on the header row. The direction indicator stays reactive via x-show.
             `wk-table-sort` gives it the 44px height floor of a button on a coarse pointer
             and inside `data-wk-touch`, which a plain button with this padding never reaches. --}}
        <button
            type="button"
            @click="sortBy({{ \Pushery\WireKit\Support\AlpinePayload::string($column) }})"
            class="{{ $sortButtonBox }} {{ $sortLayout }} {{ $helpBeside ? 'ps-[var(--padding-wk-x-md)]' : 'px-[var(--padding-wk-x-md)]' }} py-[var(--padding-wk-y-md)] [table[data-wk-compact]>*>tr>th>&]:py-[var(--padding-wk-y-sm)] hover:text-[color:var(--color-wk-text)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-inset focus-visible:ring-[var(--color-wk-ring)] rounded-[var(--radius-wk-sm)] cursor-pointer wk-table-sort"
        >
            {{ $label }}
            <svg x-show="getSortDirection({{ \Pushery\WireKit\Support\AlpinePayload::string($column) }}) === 'asc'" x-cloak aria-hidden="true" class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor"><path d="M6 3L2 8h8L6 3z"/></svg>
            <svg x-show="getSortDirection({{ \Pushery\WireKit\Support\AlpinePayload::string($column) }}) === 'desc'" x-cloak aria-hidden="true" class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor"><path d="M6 9L2 4h8L6 9z"/></svg>
            <svg x-show="!getSortDirection({{ \Pushery\WireKit\Support\AlpinePayload::string($column) }})" aria-hidden="true" class="h-3 w-3 opacity-40" viewBox="0 0 12 12" fill="currentColor"><path d="M6 2L3 5h6L6 2zM6 10L3 7h6L6 10z"/></svg>
        </button>@if($helpBeside)<span class="{{ $helpBesideBox }}">@include('wirekit::components.partials.field-help', $helpPartial)</span>@elseif($helpPartial !== null)<span class="flex {{ $justifyClass }} px-[var(--padding-wk-x-md)] pb-[var(--padding-wk-y-md)]">@include('wirekit::components.partials.field-help', $helpPartial)</span>@endif
    @elseif($sortable)
        {{-- Livewire sort mode. With `sortAction`, the label + indicator sit in a
             keyboard-operable <button wire:click> (WCAG 2.1.1 — the cursor-pointer
             cell alone is mouse-only); the button carries the focus ring, aria-sort
             stays on the <th>. Without it, the label renders in a static <span>.
             The direction indicator is shared between both shapes. --}}
        @php
            $sortIndicator = match ($sortDirection) {
                'asc' => '<svg aria-hidden="true" class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor"><path d="M6 3L2 8h8L6 3z"/></svg>',
                'desc' => '<svg aria-hidden="true" class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor"><path d="M6 9L2 4h8L6 9z"/></svg>',
                default => '<svg aria-hidden="true" class="h-3 w-3 opacity-40" viewBox="0 0 12 12" fill="currentColor"><path d="M6 2L3 5h6L6 2zM6 10L3 7h6L6 10z"/></svg>',
            };
        @endphp
        @if($sortAction)
            {{-- The button carries the cell's padding (the `$padOnButton` branch
                 above drops it from the <th>), so its box IS the cell: the target
                 reaches the 24px AA minimum on both axes and no part of the cell is
                 inert. `w-full` + a justify matching the column's alignment keeps
                 the label exactly where the text-align put it.

                 ring-inset because the box now reaches the cell's edges, and the
                 table's scroll wrapper (`overflow-x-auto`, which computes
                 `overflow-y: auto`) would clip an outset ring on the header row.

                 `wk-table-sort` takes the touch height floor, as in the Alpine branch above. --}}
            <button
                type="button"
                wire:click="{{ $sortAction }}"
                class="{{ $sortButtonBox }} {{ $sortLayout }} {{ $helpBeside ? 'ps-[var(--padding-wk-x-md)]' : 'px-[var(--padding-wk-x-md)]' }} py-[var(--padding-wk-y-md)] [table[data-wk-compact]>*>tr>th>&]:py-[var(--padding-wk-y-sm)] hover:text-[color:var(--color-wk-text)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-inset focus-visible:ring-[var(--color-wk-ring)] rounded-[var(--radius-wk-sm)] cursor-pointer wk-table-sort"
            >
                {{ $label }}
                {!! $sortIndicator !!}
            </button>@if($helpBeside)<span class="{{ $helpBesideBox }}">@include('wirekit::components.partials.field-help', $helpPartial)</span>@elseif($helpPartial !== null)<span class="flex {{ $justifyClass }} px-[var(--padding-wk-x-md)] pb-[var(--padding-wk-y-md)]">@include('wirekit::components.partials.field-help', $helpPartial)</span>@endif
        @else
            <span class="{{ $sideways ? 'inline-flex flex-col items-center gap-1' : 'inline-flex items-center gap-1' }}">
                {{ $label }}
                {!! $sortIndicator !!}
                @if($helpPartial !== null)
                    @include('wirekit::components.partials.field-help', $helpPartial)
                @endif
            </span>
        @endif
    @elseif($helpPartial !== null)
        {{-- The head and its help as one unit, which the cell's text-align places; a turned head
             takes the help under its label. --}}
        <span class="inline-flex gap-[var(--gap-wk-xs)] {{ $sideways ? 'flex-col align-bottom '.$helpItems : 'items-center align-middle' }}">
            {{ $label }}
            @include('wirekit::components.partials.field-help', $helpPartial)
        </span>
    @else
        {{ $label }}
    @endif
</th>
