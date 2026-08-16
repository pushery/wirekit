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

    // Sanitize the <th scope> to the valid HTML set; anything else falls back to col.
    // Resolved BEFORE the class builder so a row-header can drop the column-header look.
    $headerScope = in_array($headerScope, ['col', 'row', 'colgroup', 'rowgroup'], true) ? $headerScope : 'col';
    $isRowHeader = $headerScope === 'row';

    // A ROW header labels its own data row (WCAG 1.3.1) and reads as a heading
    // for that row — NOT as a column header. The muted, nowrap column-header
    // treatment made a row header render small, greyed-out and clipped; it now
    // uses the regular text color and is allowed to wrap.
    $scopeText = $isRowHeader
        ? 'text-[color:var(--color-wk-text)]'
        : 'text-[color:var(--color-wk-text-muted)] whitespace-nowrap';

    // In sort-action mode the padding MOVES to the <button> instead of sitting on
    // the cell (see the button below). Measured at a coarse pointer, the button was
    // 20px tall inside a 36px cell whose padding this mode deliberately leaves
    // inert — under the 24px WCAG 2.5.8 (AA) minimum, with 16px of dead cell
    // around the only clickable thing.
    //
    // Padding on the button rather than duplicated onto it: growing the button with
    // `py` + a matching negative `my` reads like it should keep the row height, and
    // it does NOT. An inline-flex child grows the line box, so the cell's own
    // padding lands on top of the taller box — measured 52px where 36 was expected.
    // Moving the padding keeps the cell exactly as tall as before AND makes the
    // whole cell clickable, which is what the Alpine-sort mode always did.
    //
    // `$column === null` is load-bearing, not defensive. The button only renders in the
    // `@elseif($sortable)` branch below, and `@if($sortable && $column)` wins before it — so
    // in Alpine-sort mode the padding is REMOVED FROM THE CELL WITH NOTHING TO RECEIVE IT.
    // Measured on the combination: the header collapsed from 35.5px to 19.5px, under the 24px
    // WCAG 2.5.8 floor this change exists to reach, and the compact override went with it.
    // The docblock above calls `sortAction` "ignored in Alpine-sort mode"; a prop documented
    // as inert must not be able to break the cell.
    $padOnButton = $sortable && $column === null && $sortAction !== null;
    $cellPadding = $padOnButton
        ? ''
        : 'px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)]';

    // Base th styling — heading weight, scope-aware text, compact-aware padding
    // via table[data-wk-compact] selector
    $classes = WireKit::resolveClasses('table.th', 'base', implode(' ', [
        $cellPadding,
        'font-[number:var(--font-wk-heading-weight)]',
        'text-[length:var(--text-wk-sm)]',
        $scopeText,
        $alignClass,
        // Compact variant: reduce vertical padding
        $padOnButton ? '' : '[table[data-wk-compact]_&]:py-[var(--padding-wk-y-sm)]',
        // Sticky first column: freeze the leading header cell. It needs its own
        // background (it would otherwise show scrolling body cells through it) and
        // a z-index ABOVE the sticky header (z-10) so the top-left corner stays on top.
        '[table[data-wk-sticky-column]_&:first-child]:sticky',
        '[table[data-wk-sticky-column]_&:first-child]:left-0',
        '[table[data-wk-sticky-column]_&:first-child]:z-20',
        '[table[data-wk-sticky-column]_&:first-child]:bg-[var(--color-wk-bg-subtle)]',
        // Sortable headers get pointer cursor + hover color. EXCEPT in sort-action
        // mode: there the <button> below is the click target (it carries its own
        // cursor-pointer), so advertising cursor-pointer on the whole cell would be a
        // dead zone — the cell padding shows a pointer but is not clickable.
        // `$padOnButton` rather than `$sortAction` alone: the cursor belongs on the cell
        // whenever the cell is what you click. With a button present the button is the target,
        // so advertising a pointer on the whole cell would be a lie — but in Alpine-sort mode
        // there is no button and the cell IS clickable, and gating on `$sortAction` alone took
        // the pointer away there too. Same promise as the padding above: a prop documented as
        // ignored may not change this cell.
        $sortable ? ($padOnButton ? 'select-none hover:text-[color:var(--color-wk-text)]' : 'cursor-pointer select-none hover:text-[color:var(--color-wk-text)]') : '',
    ]), $scope);

    // ARIA: sortable columns expose their current sort state
    $ariaSort = match ($sortDirection) {
        'asc' => 'ascending',
        'desc' => 'descending',
        default => $sortable ? 'none' : null,
    };
@endphp

<th
    scope="{{ $headerScope }}"
    data-wk-table-th
    @if($column) data-wk-sort-column="{{ $column }}" @endif
    @if($sortable && $column)
        {{-- Alpine sort mode: bind click + aria-sort to parent wirekitTableSort state --}}
        @click="sortBy('{{ $column }}')"
        :aria-sort="getSortDirection('{{ $column }}') === 'asc' ? 'ascending' : getSortDirection('{{ $column }}') === 'desc' ? 'descending' : 'none'"
    @elseif($ariaSort)
        aria-sort="{{ $ariaSort }}"
    @endif
    {{ $attributes->class([$classes]) }}
>
    @if($sortable && $column)
        {{-- Alpine sort mode: dynamic direction indicator via x-show --}}
        <span class="inline-flex items-center gap-1">
            {{ $slot }}
            <svg x-show="getSortDirection('{{ $column }}') === 'asc'" aria-hidden="true" class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor"><path d="M6 3L2 8h8L6 3z"/></svg>
            <svg x-show="getSortDirection('{{ $column }}') === 'desc'" aria-hidden="true" class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor"><path d="M6 9L2 4h8L6 9z"/></svg>
            <svg x-show="!getSortDirection('{{ $column }}')" aria-hidden="true" class="h-3 w-3 opacity-40" viewBox="0 0 12 12" fill="currentColor"><path d="M6 2L3 5h6L6 2zM6 10L3 7h6L6 10z"/></svg>
        </span>
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
                class="flex w-full items-center gap-1 {{ $justifyClass }} px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)] [table[data-wk-compact]_&]:py-[var(--padding-wk-y-sm)] hover:text-[color:var(--color-wk-text)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-inset focus-visible:ring-[var(--color-wk-ring)] rounded-[var(--radius-wk-sm)] cursor-pointer"
            >
                {{ $slot }}
                {!! $sortIndicator !!}
            </button>
        @else
            <span class="inline-flex items-center gap-1">
                {{ $slot }}
                {!! $sortIndicator !!}
            </span>
        @endif
    @else
        {{ $slot }}
    @endif
</th>
