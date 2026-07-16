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
])

@php
    use Pushery\WireKit\WireKit;

    // Alignment maps to text-* utilities
    $alignClass = match ($align) {
        'center' => 'text-center',
        'right' => 'text-right',
        default => 'text-left',
    };

    // Base th styling — heading weight, muted text, compact-aware padding
    // via table[data-wk-compact] selector
    $classes = WireKit::resolveClasses('table.th', 'base', implode(' ', [
        'px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'text-[length:var(--text-wk-sm)]',
        'text-[color:var(--color-wk-text-muted)]',
        'whitespace-nowrap',
        $alignClass,
        // Compact variant: reduce vertical padding
        '[table[data-wk-compact]_&]:py-[var(--padding-wk-y-sm)]',
        // Sticky first column: freeze the leading header cell. It needs its own
        // background (it would otherwise show scrolling body cells through it) and
        // a z-index ABOVE the sticky header (z-10) so the top-left corner stays on top.
        '[table[data-wk-sticky-column]_&:first-child]:sticky',
        '[table[data-wk-sticky-column]_&:first-child]:left-0',
        '[table[data-wk-sticky-column]_&:first-child]:z-20',
        '[table[data-wk-sticky-column]_&:first-child]:bg-[var(--color-wk-bg-subtle)]',
        // Sortable headers get pointer cursor + hover color
        $sortable ? 'cursor-pointer select-none hover:text-[color:var(--color-wk-text)]' : '',
    ]), $scope);

    // ARIA: sortable columns expose their current sort state
    $ariaSort = match ($sortDirection) {
        'asc' => 'ascending',
        'desc' => 'descending',
        default => $sortable ? 'none' : null,
    };
@endphp

<th
    scope="col"
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
            <button
                type="button"
                wire:click="{{ $sortAction }}"
                class="inline-flex items-center gap-1 hover:text-[color:var(--color-wk-text)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] rounded-[var(--radius-wk-sm)] cursor-pointer"
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
