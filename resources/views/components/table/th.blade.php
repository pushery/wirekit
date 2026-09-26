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
    // Null keeps today's static <span> (the developer supplies their own control
    // via $attributes), so existing markup renders byte-identically. Ignored in
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
    // Let a long header break instead of holding its column as wide as the longest word.
    //
    // A column header is `whitespace-nowrap` by default, and that is right for the usual
    // one-word head. It is wrong for a column whose NAME is long and whose CONTENT is not:
    // reported from a permissions table where "Nutzungsbedingungen" and
    // "Datenschutzerklaerung" held their columns at 144 and 142px over icons that need 16,
    // and the table ran past its frame rather than the heads taking two lines.
    //
    // `wrap` breaks ANYWHERE, which is what a long compound over a narrow icon column needs.
    // `wrap="words"` breaks only between words and evens the lines up: over a column of numbers
    // there is nothing to hold the column wider than a digit, and a head that may break anywhere
    // fell to that width ("Line / s", "to 2 / %"). Between words, the longest word is the floor.
    'wrap' => false,
    // Turn the label on its side, reading from bottom to top on one line.
    //
    // For a column whose NAME is long and whose values are a mark or a glyph, next to a column
    // that takes the rest of the width: reported from a user list with one column per legal text,
    // where six one-word heads took 570px over cells that need 20. `wrap` cannot help there, since
    // the wide column claims everything the heads give up and they fall to one letter per line.
    // A turned head is as wide as one line of text in any language, and the row grows instead.
    //
    // Every head in that row then stands on the row's floor (the stylesheet does that, from the
    // row), and a sort indicator stays upright under the label. A turned head is one line, so it
    // outranks `wrap`.
    'sideways' => false,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('table.th', $attributes->getAttributes());

    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $sortable = BooleanProp::from($sortable, false);
    $sideways = BooleanProp::from($sideways, false);

    // Alignment maps to text-* utilities
    $alignClass = match ($align) {
        'center' => 'text-center',
        'right' => 'text-right',
        default => 'text-left',
    };

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

    // Sanitize the <th scope> to the valid HTML set; anything else falls back to col.
    // Resolved BEFORE the class builder so a row-header can drop the column-header look.
    $headerScope = in_array($headerScope, ['col', 'row', 'colgroup', 'rowgroup'], true) ? $headerScope : 'col';
    $isRowHeader = $headerScope === 'row';

    // A ROW header labels its own data row (WCAG 1.3.1) and reads as a heading
    // for that row — NOT as a column header. The muted, nowrap column-header
    // treatment made a row header render small, greyed-out and clipped; it now
    // uses the regular text color and is allowed to wrap.
    // `words` is its own mode; any other value is the boolean it always was.
    $wrapWords = $wrap === 'words';
    $wrap = $wrapWords || BooleanProp::from($wrap, false);

    /*
     * `whitespace-normal` is emitted EXPLICITLY rather than by leaving `nowrap` off, and that is
     * the half the report asked for without naming it: two Tailwind utilities for one property are
     * decided by their order in the generated stylesheet, not by the order in the attribute — so a
     * developer's own `class="whitespace-normal"` does not reliably win, which is why the adopting
     * application had to wrap its header text in a `<span>` instead.
     *
     * `[overflow-wrap:anywhere]` comes with the wrap, the same pairing `button`'s `wrap-label`
     * ships and for the same measured reason: `whitespace-normal` only permits a break at a space
     * or a hyphen, and a long compound noun has neither. Without it the head stays exactly as wide
     * as its longest word, which is the width the report measured.
     *
     * ⚠️ Deliberately NOT the `hyphens` utility the report proposed. It depends on a dictionary
     * the browser may not have — Chromium ships hyphenation as a downloadable component on Linux,
     * and the report could not measure that from macOS. A break rule that works everywhere beats a
     * prettier one that is a different rule in CI than on a laptop.
     *
     * ⚠️ The utility is described rather than spelled, and that is not squeamishness: Tailwind
     * scans RAW FILES, so naming a class in a comment compiles it. Spelled out here, this
     * paragraph emitted a utility nothing uses — and the drift guard then reported it as a
     * compiled selector with no source, which is exactly what it is for.
     */
    $scopeText = $isRowHeader
        ? 'text-[color:var(--color-wk-text)]'
        : 'text-[color:var(--color-wk-text-muted)] '.match (true) {
            $wrapWords => 'whitespace-normal wk-lines-balanced',
            $wrap => 'whitespace-normal [overflow-wrap:anywhere]',
            default => 'whitespace-nowrap',
        };

    // Whenever a sort <button> renders, the padding MOVES onto it instead of sitting
    // on the cell (see both buttons below). Measured at a coarse pointer, the button
    // was 20px tall inside a 36px cell whose padding this mode deliberately leaves
    // inert — under the 24px WCAG 2.5.8 (AA) minimum, with 16px of dead cell
    // around the only clickable thing.
    //
    // Padding on the button rather than duplicated onto it: growing the button with
    // `py` + a matching negative `my` reads like it should keep the row height, and
    // it does NOT. An inline-flex child grows the line box, so the cell's own
    // padding lands on top of the taller box — measured 52px where 36 was expected.
    // Moving the padding keeps the cell exactly as tall as before AND keeps the whole
    // cell clickable, which is what the Alpine-sort mode did before it had a button.
    //
    // The gate is "does a button render", not "which sort mode is this": both modes
    // render one now (Alpine-sort binds `@click`, Livewire-sort binds `wire:click`), so
    // the relocated padding always has something to land on. That is not a formality —
    // while the Alpine branch still emitted a bare <span>, this gate read
    // `$sortable && $sortAction !== null` and a header given BOTH props had its padding
    // removed with nothing to receive it: the header collapsed from 35.5px to 19.5px,
    // under the very 24px floor the relocation exists to reach, and the compact-density
    // override went with it.
    $padOnButton = $sortable && ($column !== null || $sortAction !== null);
    $cellPadding = $padOnButton
        ? ''
        : 'px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)]';

    // `hideBelow` takes a column off a narrow screen. The classes are written out per breakpoint
    // because Tailwind reads literal class names and never an assembled one, and a `max-*` variant
    // hides only below the breakpoint, so above it the cell keeps the display the table gives it
    // instead of one this component would have to restate. The header and every cell of the column
    // take the same value: a header hidden without its cells, or cells without their header, would
    // put every value under the wrong heading. An unknown value throws in debug and hides nothing
    // in production.
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

    // Base th styling — heading weight, scope-aware text, compact-aware padding
    // via table[data-wk-compact] selector
    $classes = WireKit::resolveClasses('table.th', 'base', implode(' ', [
        $cellPadding,
        'font-[number:var(--font-wk-heading-weight)]',
        'text-[length:var(--text-wk-sm)]',
        $scopeText,
        $alignClass,
        $hideClasses,
        // Compact variant: reduce vertical padding
        $padOnButton ? '' : '[table[data-wk-compact]_&]:py-[var(--padding-wk-y-sm)]',
        // Sticky first column: freeze the leading header cell. It needs its own
        // background (it would otherwise show scrolling body cells through it) and
        // a z-index ABOVE the sticky header (z-10) so the top-left corner stays on top.
        '[table[data-wk-sticky-column]_&:first-child]:sticky',
        // LOGICAL, not the physical `left` inset. Writing direction does not move a physical edge:
        // under `dir="rtl"` the first column renders on the right, and `left: 0` pinned it to the
        // far END of the row while the scrolling columns ran under it. An LTR screenshot of that
        // looks exactly like a correct one. Named as the CSS property rather than the utility on
        // purpose: Tailwind reads comments too, so a utility spelled here is compiled into the sheet.
        '[table[data-wk-sticky-column]_&:first-child]:start-0',
        '[table[data-wk-sticky-column]_&:first-child]:z-20',
        '[table[data-wk-sticky-column]_&:first-child]:bg-[var(--color-wk-bg-subtle)]',
        // The frozen column is capped, or a sticky-column table, which takes its max-content
        // width to overflow at all, holds a long label on one line and the column takes most of
        // a phone. A max-width on a table cell bounds its column in automatic layout
        // in every engine, and spare width is still handed out, so a table with room wraps
        // nothing. The column header has to be allowed to wrap as well: its nowrap would keep
        // the column exactly as wide as its label, cap or not.
        '[table[data-wk-sticky-column]_&:first-child]:max-w-[var(--size-wk-table-sticky-column-max)]',
        '[table[data-wk-sticky-column]_&:first-child]:whitespace-normal',
        // Sortable headers get the hover color; the pointer cursor goes wherever the
        // click target actually is. `$padOnButton` rather than `$sortAction` alone: with a
        // button present the button is the target and carries its own cursor-pointer, and
        // it fills the cell, so repeating the pointer on the <th> would either duplicate it
        // or — where the button did not reach the cell edges — advertise a pointer over a
        // dead zone. A sortable header with NO button (Livewire-sort without `sortAction`,
        // where the developer supplies the control) keeps the pointer on the cell.
        $padOnButton ? 'select-none hover:text-[color:var(--color-wk-text)]' : '',
    ]), $scope);

    // ARIA: sortable columns expose their current sort state.
    //
    // ⚠️ `$sortable` ALONE is not a sortable column. Both operable shapes need something to
    // sort BY — Alpine mode needs `column`, Livewire mode needs `sortAction` — and with
    // neither the header rendered `aria-sort="none"`, a cursor-pointer and a hover state
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
    {{ $attributes->class([$classes]) }}
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
             ring on the header row. The direction indicator stays reactive via x-show. --}}
        <button
            type="button"
            @click="sortBy({{ \Pushery\WireKit\Support\AlpinePayload::string($column) }})"
            class="flex w-full {{ $sortLayout }} px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)] [table[data-wk-compact]_&]:py-[var(--padding-wk-y-sm)] hover:text-[color:var(--color-wk-text)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-inset focus-visible:ring-[var(--color-wk-ring)] rounded-[var(--radius-wk-sm)] cursor-pointer"
        >
            {{ $label }}
            <svg x-show="getSortDirection({{ \Pushery\WireKit\Support\AlpinePayload::string($column) }}) === 'asc'" aria-hidden="true" class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor"><path d="M6 3L2 8h8L6 3z"/></svg>
            <svg x-show="getSortDirection({{ \Pushery\WireKit\Support\AlpinePayload::string($column) }}) === 'desc'" aria-hidden="true" class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor"><path d="M6 9L2 4h8L6 9z"/></svg>
            <svg x-show="!getSortDirection({{ \Pushery\WireKit\Support\AlpinePayload::string($column) }})" aria-hidden="true" class="h-3 w-3 opacity-40" viewBox="0 0 12 12" fill="currentColor"><path d="M6 2L3 5h6L6 2zM6 10L3 7h6L6 10z"/></svg>
        </button>
    @elseif($sortable)
        {{-- Livewire sort mode. With `sortAction`, the label + indicator sit in a
             keyboard-operable <button wire:click> (WCAG 2.1.1 — the cursor-pointer
             cell alone is mouse-only); the button carries the focus ring, aria-sort
             stays on the <th>. Without it, the static <span> renders exactly as
             before. The direction indicator is shared between both shapes. --}}
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
                 `overflow-y: auto`) would clip an outset ring on the header row. --}}
            <button
                type="button"
                wire:click="{{ $sortAction }}"
                class="flex w-full {{ $sortLayout }} px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)] [table[data-wk-compact]_&]:py-[var(--padding-wk-y-sm)] hover:text-[color:var(--color-wk-text)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-inset focus-visible:ring-[var(--color-wk-ring)] rounded-[var(--radius-wk-sm)] cursor-pointer"
            >
                {{ $label }}
                {!! $sortIndicator !!}
            </button>
        @else
            <span class="{{ $sideways ? 'inline-flex flex-col items-center gap-1' : 'inline-flex items-center gap-1' }}">
                {{ $label }}
                {!! $sortIndicator !!}
            </span>
        @endif
    @else
        {{ $label }}
    @endif
</th>
