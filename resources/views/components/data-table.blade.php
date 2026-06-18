@props([
    'rows' => [],                   // row objects (client mode)
    'columns' => [],                // [{key,label,sortable?,align?,cellType?}] — cellType: text|number|badge
    'rowKey' => 'id',               // unique id field for selection + morph keying
    'selectable' => config('wirekit.components.data-table.selectable', false), // per-row + header selection checkboxes
    'searchable' => config('wirekit.components.data-table.searchable', false), // toolbar search box (client-side filter)
    'density' => config('wirekit.components.data-table.density', 'comfortable'), // comfortable | compact
    'columnManager' => false,       // show/hide-columns dropdown
    'hidden' => [],                 // initially-hidden column keys
    'server' => false,              // server-driven: stop local sort/filter, emit events only
    'searchPlaceholder' => 'Search…',
    'emptyText' => 'No results',
    'caption' => null,              // accessible table caption / name
    'name' => null,                 // hidden-input name mirroring the selected ids
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;
    use Illuminate\Support\Str;

    $density = WireKit::validateProp('data-table', 'density', $density, ['comfortable', 'compact']);
    // Server-driven mode (client | server) — derived from the boolean `server`
    // prop. Named `server` (not `mode`) to stay off the surface-treatment axis.
    $mode = filter_var($server, FILTER_VALIDATE_BOOLEAN) ? 'server' : 'client';
    $id = $attributes->get('id', 'data-table-'.Str::random(6));
    $name = $name ?? $attributes->get('name');
    $captionId = $id.'-caption';

    $rowsArr = $rows instanceof \Illuminate\Support\Collection ? $rows->values()->all() : array_values((array) $rows);
    $colsArr = $columns instanceof \Illuminate\Support\Collection ? $columns->values()->all() : array_values((array) $columns);
    $hiddenArr = array_values((array) $hidden);

    // Soft tinted intent pills for `cellType: 'badge'` columns. Defined here (PHP
    // string literals) so Tailwind compiles them AND the drift inventory traces
    // them; the cell binds `:class="badgeClasses[badgeIntent(...)]"`.
    $badgeClasses = [
        'success' => 'bg-[color-mix(in_oklch,var(--color-wk-success)_15%,transparent)] text-[color:var(--color-wk-success-text)]',
        'warning' => 'bg-[color-mix(in_oklch,var(--color-wk-warning)_15%,transparent)] text-[color:var(--color-wk-warning-text)]',
        'danger' => 'bg-[color-mix(in_oklch,var(--color-wk-danger)_15%,transparent)] text-[color:var(--color-wk-danger-text)]',
        'neutral' => 'bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text-muted)]',
    ];

    $base = WireKit::resolveClasses('data-table', 'base', 'w-full font-[family-name:var(--font-wk-sans)] space-y-[var(--space-wk-sm)]', $scope);

    $checkboxClass = 'h-4 w-4 rounded-[var(--radius-wk-sm)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] accent-[var(--color-wk-accent)] cursor-pointer focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]';
    $iconBtn = 'inline-flex items-center gap-1 px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] rounded-[var(--radius-wk-md)] hover:text-[color:var(--color-wk-text)] hover:bg-[var(--color-wk-bg-muted)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] transition-colors cursor-pointer';
@endphp

<div
    {{ $attributes->except(['id', 'name', 'class'])->whereDoesntStartWith('wire:model') }}
    id="{{ $id }}"
    x-data="wirekitDataTable({ rows: @js($rowsArr), columns: @js($colsArr), rowKey: '{{ $rowKey }}', mode: '{{ $mode }}', density: '{{ $density }}', hidden: @js($hiddenArr) })"
    {{ $attributes->only('class')->class([$base]) }}
>
    @if($selectable && $name)
        {{-- Selection bridge for wire:model / form submission. --}}
        <input type="hidden" x-ref="selModel" name="{{ $name }}" {{ $attributes->whereStartsWith('wire:model') }} :value="JSON.stringify(selected)" />
    @endif

    {{-- Toolbar: search + density toggle + column manager + caller actions. --}}
    @if($searchable || $columnManager || isset($toolbar))
        <div class="flex flex-wrap items-center justify-between gap-[var(--space-wk-sm)]">
            <div class="flex items-center gap-[var(--space-wk-sm)]">
                @if($searchable)
                    <input
                        type="search"
                        x-model="search"
                        @input="onSearch()"
                        placeholder="{{ $searchPlaceholder }}"
                        aria-label="{{ $searchPlaceholder }}"
                        class="wk-field w-[16rem] max-w-full bg-[var(--color-wk-bg-input)] text-[color:var(--color-wk-text)] text-[length:var(--text-wk-sm)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] rounded-[var(--radius-wk-md)] px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                    />
                @endif
            </div>
            <div class="flex items-center gap-[var(--space-wk-sm)]">
                {{ $toolbar ?? '' }}
                {{-- Density toggle --}}
                <div class="inline-flex rounded-[var(--radius-wk-md)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] overflow-hidden" role="group" aria-label="Row density">
                    <button type="button" @click="setDensity('comfortable')" :aria-pressed="density === 'comfortable'" :class="density === 'comfortable' ? 'bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text)]' : 'text-[color:var(--color-wk-text-muted)]'" class="px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-sm)] cursor-pointer focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-inset">Comfortable</button>
                    <button type="button" @click="setDensity('compact')" :aria-pressed="density === 'compact'" :class="density === 'compact' ? 'bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text)]' : 'text-[color:var(--color-wk-text-muted)]'" class="px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-sm)] cursor-pointer focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-inset">Compact</button>
                </div>
                @if($columnManager)
                    {{-- Column manager — a self-contained popover (nested scope; inherits
                         toggleColumn / isColumnVisible / columns from the table scope). --}}
                    <div x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false" class="relative">
                        <button type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="menu" class="{{ $iconBtn }}">
                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 4h12M2 8h12M2 12h12"/></svg>
                            Columns
                        </button>
                        <div x-show="open" x-cloak role="menu" class="absolute right-0 z-[var(--z-wk-dropdown)] mt-1 w-[12rem] p-[var(--padding-wk-x-sm)] bg-[var(--color-wk-bg-elevated)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] rounded-[var(--radius-wk-md)] shadow-[var(--shadow-wk-lg)]">
                            <template x-for="col in columns" :key="col.key">
                                <label class="flex items-center gap-2 px-[var(--padding-wk-x-sm)] py-1 text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)] rounded-[var(--radius-wk-sm)] hover:bg-[var(--color-wk-bg-muted)] cursor-pointer">
                                    <input type="checkbox" :checked="isColumnVisible(col.key)" @change="toggleColumn(col.key)" class="{{ $checkboxClass }}" />
                                    <span x-text="col.label"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Bulk-action bar — appears when rows are selected. --}}
    @if($selectable)
        <div x-show="selectedCount > 0" x-cloak role="region" aria-label="Bulk actions" class="flex flex-wrap items-center justify-between gap-[var(--space-wk-sm)] px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-sm)] bg-[var(--color-wk-bg-muted)] rounded-[var(--radius-wk-md)]">
            <span class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]" aria-live="polite"><span x-text="selectedCount"></span> selected</span>
            <div class="flex items-center gap-[var(--space-wk-sm)]">
                {{ $bulkActions ?? '' }}
                <button type="button" @click="clearSelection()" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] rounded-[var(--radius-wk-sm)] cursor-pointer">Clear</button>
            </div>
        </div>
    @endif

    {{-- Table — labeled, keyboard-reachable scroll region (WCAG 2.1.1). --}}
    <div role="region" @if($caption) aria-labelledby="{{ $captionId }}" @else aria-label="Data table" @endif tabindex="0" class="w-full overflow-x-auto wk-scrollbar rounded-[var(--radius-wk-lg)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]">
        <table class="w-full border-collapse text-[length:var(--text-wk-sm)]">
            @if($caption)
                <caption id="{{ $captionId }}" class="sr-only">{{ $caption }}</caption>
            @endif
            <thead>
                <tr class="border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)]">
                    @if($selectable)
                        <th scope="col" class="w-10 px-[var(--padding-wk-x-md)]">
                            {{-- Tri-state header selection (indeterminate set reactively). --}}
                            <input type="checkbox" :checked="allSelected" @change="toggleSelectAll()" x-effect="$el.indeterminate = someSelected" aria-label="Select all rows" class="{{ $checkboxClass }}" />
                        </th>
                    @endif
                    <template x-for="col in visibleColumns" :key="col.key">
                        <th
                            scope="col"
                            :aria-sort="ariaSort(col.key)"
                            :class="(col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left') + (density === 'compact' ? ' py-1' : ' py-[var(--padding-wk-y-sm)]')"
                            class="px-[var(--padding-wk-x-md)] text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text-muted)] whitespace-nowrap"
                        >
                            <template x-if="col.sortable !== false">
                                <button type="button" @click="toggleSort(col.key)" class="inline-flex items-center gap-1 hover:text-[color:var(--color-wk-text)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] rounded-[var(--radius-wk-sm)] cursor-pointer">
                                    <span x-text="col.label"></span>
                                    <svg x-show="sortKey === col.key && sortDir === 'asc'" x-cloak aria-hidden="true" class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor"><path d="M6 3l3 4H3z"/></svg>
                                    <svg x-show="sortKey === col.key && sortDir === 'desc'" x-cloak aria-hidden="true" class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor"><path d="M6 9L3 5h6z"/></svg>
                                    <svg x-show="sortKey !== col.key" x-cloak aria-hidden="true" class="h-3 w-3 opacity-40" viewBox="0 0 12 12" fill="currentColor"><path d="M6 2l2.5 3h-5zM6 10L3.5 7h5z"/></svg>
                                </button>
                            </template>
                            <template x-if="col.sortable === false">
                                <span x-text="col.label"></span>
                            </template>
                        </th>
                    </template>
                    @isset($rowActions)
                        <th scope="col" class="w-10 px-[var(--padding-wk-x-md)]"><span class="sr-only">Actions</span></th>
                    @endisset
                </tr>
            </thead>
            <tbody>
                <template x-for="row in displayRows" :key="rowId(row)">
                    <tr :class="isSelected(row) ? 'bg-[var(--color-wk-bg-muted)]' : 'hover:bg-[var(--color-wk-bg-subtle)]'" class="border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)] transition-colors">
                        @if($selectable)
                            <td class="px-[var(--padding-wk-x-md)]" :class="density === 'compact' ? 'py-1' : 'py-[var(--padding-wk-y-sm)]'">
                                {{-- Unique accessible name per row: prefix with the first
                                     column's value so a screen reader doesn't hear "Select
                                     row" N identical times (WCAG name uniqueness). --}}
                                <input type="checkbox" :checked="isSelected(row)" @change="toggleSelect(row)" :aria-label="columns.length ? 'Select row: ' + cellText(row, columns[0]) : 'Select row'" class="{{ $checkboxClass }}" />
                            </td>
                        @endif
                        <template x-for="col in visibleColumns" :key="col.key">
                            <td
                                :class="(col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left') + (density === 'compact' ? ' py-1' : ' py-[var(--padding-wk-y-sm)]')"
                                class="px-[var(--padding-wk-x-md)] text-[color:var(--color-wk-text)] whitespace-nowrap"
                            >
                                <template x-if="col.cellType === 'badge'">
                                    <span class="inline-flex items-center px-[var(--padding-wk-x-sm)] py-0.5 rounded-[var(--radius-wk-full)] text-[length:var(--text-wk-xs)] capitalize" :class="@js($badgeClasses)[badgeIntent(cellText(row, col))]" x-text="cellText(row, col)"></span>
                                </template>
                                <template x-if="col.cellType === 'number'">
                                    <span class="tabular-nums" x-text="cellText(row, col)"></span>
                                </template>
                                <template x-if="!col.cellType || col.cellType === 'text'">
                                    <span x-text="cellText(row, col)"></span>
                                </template>
                            </td>
                        </template>
                        @isset($rowActions)
                            <td class="px-[var(--padding-wk-x-md)] text-right" :class="density === 'compact' ? 'py-1' : 'py-[var(--padding-wk-y-sm)]'">
                                {{ $rowActions }}
                            </td>
                        @endisset
                    </tr>
                </template>
            </tbody>
        </table>

        {{-- Empty state --}}
        <div x-show="isEmpty" x-cloak class="flex flex-col items-center justify-center gap-1 px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-xl)] text-center">
            <p class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $emptyText }}</p>
        </div>
    </div>
</div>
