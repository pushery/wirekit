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
        // Sortable headers get the hover color; the pointer cursor goes wherever the
        // click target actually is. `$padOnButton` rather than `$sortAction` alone: with a
        // button present the button is the target and carries its own cursor-pointer, and
        // it fills the cell, so repeating the pointer on the <th> would either duplicate it
        // or — where the button did not reach the cell edges — advertise a pointer over a
        // dead zone. A sortable header with NO button (Livewire-sort without `sortAction`,
        // where the developer supplies the control) keeps the pointer on the cell.
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
            class="flex w-full items-center gap-1 {{ $justifyClass }} px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)] [table[data-wk-compact]_&]:py-[var(--padding-wk-y-sm)] hover:text-[color:var(--color-wk-text)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-inset focus-visible:ring-[var(--color-wk-ring)] rounded-[var(--radius-wk-sm)] cursor-pointer"
        >
            {{ $slot }}
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
