@props([
    'sortable' => false,
    'sortDirection' => null, // null | 'asc' | 'desc' — current sort state (Livewire mode)
    'column' => null, // column identifier for Alpine sort mode (pairs with table alpine-sort)
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
        {{-- Livewire sort mode: static Blade-rendered direction indicator --}}
        <span class="inline-flex items-center gap-1">
            {{ $slot }}
            @if($sortDirection === 'asc')
                <svg aria-hidden="true" class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor"><path d="M6 3L2 8h8L6 3z"/></svg>
            @elseif($sortDirection === 'desc')
                <svg aria-hidden="true" class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor"><path d="M6 9L2 4h8L6 9z"/></svg>
            @else
                {{-- Unsorted placeholder keeps column width stable --}}
                <svg aria-hidden="true" class="h-3 w-3 opacity-40" viewBox="0 0 12 12" fill="currentColor"><path d="M6 2L3 5h6L6 2zM6 10L3 7h6L6 10z"/></svg>
            @endif
        </span>
    @else
        {{ $slot }}
    @endif
</th>
