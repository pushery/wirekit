{{-- optimistic-ui: n/a — query
     Sorting, filtering and paging are query round trips. Nobody can show rows nobody has fetched; only the intent could be acknowledged, and that is a different state machine. --}}
@props([
    'rows' => [],                   // row objects (client mode)
    'columns' => [],                // [{key,label,sortable?,align?,cellType?,intents?}] — cellType: text|number|badge; intents maps a value to success|warning|danger|neutral
    'rowKey' => 'id',               // unique id field for selection + morph keying
    'selectable' => config('wirekit.components.data-table.selectable', false), // per-row + header selection checkboxes
    'searchable' => config('wirekit.components.data-table.searchable', false), // toolbar search box (client-side filter)
    'density' => config('wirekit.components.data-table.density', 'comfortable'), // comfortable | compact
    'columnManager' => false,       // show/hide-columns dropdown
    'hidden' => [],                 // initially-hidden column keys
    'server' => false,              // server-driven: stop local sort/filter, emit events only
    'searchPlaceholder' => 'Search…',
    'emptyText' => __('wirekit::No results'),
    'caption' => null,              // accessible table caption / name
    'name' => null,                 // hidden-input name mirroring the selected ids
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;
    use Illuminate\Support\Str;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('data-table', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $columnManager = BooleanProp::from($columnManager, false);
    $server = BooleanProp::from($server, false);

    $density = WireKit::validateProp('data-table', 'density', $density, ['comfortable', 'compact']);
    // Server-driven mode (client | server) — derived from the boolean `server`
    // prop. Named `server` (not `mode`) to stay off the surface-treatment axis.
    $mode = filter_var($server, FILTER_VALIDATE_BOOLEAN) ? 'server' : 'client';
    // Seeded from `name`, not re-randomized per render: Livewire's morph matches on the
    // id, so a fresh one each render means destroy-and-rebuild — and the Alpine-only
    // state (sort order, hidden columns, open panels) goes with it on the next round trip.
    $id = $attributes->get('id', \Pushery\WireKit\WireKit::stableId('data-table', $name ?? $attributes->get('name')));
    $name = $name ?? $attributes->get('name');
    $captionId = $id.'-caption';

    $rowsArr = $rows instanceof \Illuminate\Support\Collection ? $rows->values()->all() : array_values((array) $rows);
    $colsArr = $columns instanceof \Illuminate\Support\Collection ? $columns->values()->all() : array_values((array) $columns);
    $hiddenArr = array_values((array) $hidden);

    // Soft tinted intent pills for `cellType: 'badge'` columns. Defined here (PHP
    // string literals) so Tailwind compiles them AND the drift inventory traces
    // them; the cell binds `:class="badgeClasses[badgeIntent(...)]"`.
    //
    // ⚠️ ALL SEVEN of `badge`'s intents, and the count is the point. This map and
    // `badgeIntent()` in the JS both carried FOUR — `success`, `warning`, `danger`,
    // `neutral` — while `<x-wirekit::badge>` validates against seven. A column
    // declaring `'intents' => ['processing' => 'accent']` therefore named a value the
    // badge component accepts, and this table silently rendered it `neutral`. Nothing
    // warned; the pill simply came out gray, which reads as "no status" rather than as
    // a rejected value.
    //
    // `primary` and `info` have no base color of their own — they are soft accents, and
    // `badge` says so in its own branch. The strengths below mirror badge's exactly
    // (info 8%, primary 12%, accent the solid fill) so the same word means the same
    // thing in a table cell as on a badge. That equivalence IS the fix; three
    // indistinguishable tints of one hue would have closed the vocabulary gap and
    // reopened it as a visual one.
    $badgeClasses = [
        'primary' => 'bg-[color-mix(in_srgb,var(--color-wk-accent)_12%,var(--color-wk-bg))] text-[color:var(--color-wk-accent-content)]',
        'accent' => 'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]',
        'info' => 'bg-[color-mix(in_srgb,var(--color-wk-accent)_8%,var(--color-wk-bg))] text-[color:var(--color-wk-accent-content)]',
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
    x-data="wirekitDataTable({ rows: {{ \Pushery\WireKit\Support\AlpinePayload::from($rowsArr) }}, columns: {{ \Pushery\WireKit\Support\AlpinePayload::from($colsArr) }}, rowKey: {{ \Pushery\WireKit\Support\AlpinePayload::string($rowKey) }}, mode: {{ \Pushery\WireKit\Support\AlpinePayload::string($mode) }}, density: {{ \Pushery\WireKit\Support\AlpinePayload::string($density) }}, hidden: {{ \Pushery\WireKit\Support\AlpinePayload::from($hiddenArr) }} })"
    {{ $attributes->only('class')->class([$base]) }}
>
    @if($selectable && $name)
        {{-- Selection bridge for wire:model / form submission. --}}
        {{-- Static value as well as the bound one: the field is empty until Alpine
             boots, and a form submitted in that window sends nothing while the
             visible control already shows the value. The serialization matches
             what the factory's own getter produces from the same data. --}}
        <input type="hidden" x-ref="selModel" name="{{ $name }}" {{ $attributes->whereStartsWith('wire:model') }} value="[]" :value="selectedJson()" />
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
                        {{-- Search input is a form control (WCAG 1.4.11): its border
                             comes from the communicating --color-wk-border-strong token
                             (3:1), never the decorative --color-wk-border (~1.29:1) — the
                             category-scoped 2.17.0 sweep missed it because data-table is
                             not in the Form category. --}}
                        class="wk-field w-[16rem] max-w-full bg-[var(--color-wk-bg-input)] text-[color:var(--color-wk-text)] text-[length:var(--text-wk-sm)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border-strong)] rounded-[var(--radius-wk-md)] px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] transition-colors duration-[var(--transition-wk-duration)] hover:border-[var(--color-wk-border-strong-hover)] focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                    />
                @endif
            </div>
            <div class="flex items-center gap-[var(--space-wk-sm)]">
                {{ $toolbar ?? '' }}
                {{-- Density toggle --}}
                <div class="inline-flex rounded-[var(--radius-wk-md)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] overflow-hidden" role="group" aria-label="{{ __('wirekit::Row density') }}">
                    <button type="button" @click="setDensity('comfortable')" :aria-pressed="density === 'comfortable'" :class="density === 'comfortable' ? 'bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text)]' : 'text-[color:var(--color-wk-text-muted)]'" class="px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-sm)] cursor-pointer focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-inset">Comfortable</button>
                    <button type="button" @click="setDensity('compact')" :aria-pressed="density === 'compact'" :class="density === 'compact' ? 'bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text)]' : 'text-[color:var(--color-wk-text-muted)]'" class="px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-sm)] cursor-pointer focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-inset">Compact</button>
                </div>
                @if($columnManager)
                    {{-- Column manager — a self-contained popover (nested scope; inherits
                         toggleColumn / isColumnVisible / columns from the table scope). --}}
                    {{-- Disclosure + anchoring live in the factory
                         (resources/js/components/data-table-column-menu.js).
                         Neither the method shorthand nor the x-init arrow
                         parsed under Alpine's CSP build, so the whole nested
                         scope failed to build and the button toggled nothing. --}}
                    <div x-data="wirekitDataTableColumnMenu()" @click.outside="open = false" @keydown.escape="open = false" class="relative">
                        <button type="button" x-ref="colBtn" @click="open = !open" :aria-expanded="open" aria-haspopup="menu" class="{{ $iconBtn }}">
                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 4h12M2 8h12M2 12h12"/></svg>
                            Columns
                        </button>
                        <div x-show="open" x-cloak x-ref="colMenu" role="menu" class="fixed z-[var(--z-wk-dropdown)] w-[12rem] max-h-[70vh] overflow-y-auto p-[var(--padding-wk-x-sm)] bg-[var(--color-wk-bg-elevated)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] rounded-[var(--radius-wk-md)] shadow-[var(--shadow-wk-lg)]">
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
        <div x-show="selectedCount > 0" x-cloak role="region" aria-label="{{ __('wirekit::Bulk actions') }}" class="flex flex-wrap items-center justify-between gap-[var(--space-wk-sm)] px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-sm)] bg-[var(--color-wk-bg-muted)] rounded-[var(--radius-wk-md)]">
            <span class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]" aria-live="polite"><span x-text="selectedCount"></span> selected</span>
            <div class="flex items-center gap-[var(--space-wk-sm)]">
                {{ $bulkActions ?? '' }}
                <button type="button" @click="clearSelection()" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] rounded-[var(--radius-wk-sm)] cursor-pointer">Clear</button>
            </div>
        </div>
    @endif

    {{-- Table — keyboard-reachable scroll region (WCAG 2.1.1), unconditionally.

         The LANDMARK is opt-in and its switch is the caption. The `@else` arm used to name the
         region "Data table", which is what everything on the page already is: three tables on a
         dashboard were three identical rotor entries, and axe reports that as
         `landmark-unique`. With a caption the region points at it (`aria-labelledby`), which is
         a name the reader chose; without one there is no landmark to be ambiguous about. --}}
    <div @if(filled($caption)) role="region" aria-labelledby="{{ $captionId }}" @endif tabindex="0" class="w-full overflow-x-auto wk-scrollbar rounded-[var(--radius-wk-lg)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]">
        <table class="w-full border-collapse text-[length:var(--text-wk-sm)]">
            @if($caption)
                <caption id="{{ $captionId }}" class="sr-only">{{ $caption }}</caption>
            @endif
            <thead>
                <tr class="border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)]">
                    @if($selectable)
                        <th scope="col" class="w-10 px-[var(--padding-wk-x-md)]">
                            {{-- Tri-state header selection (indeterminate set reactively). --}}
                            <input type="checkbox" :checked="allSelected" @change="toggleSelectAll()" x-effect="$el.indeterminate = someSelected" aria-label="{{ __('wirekit::Select all rows') }}" class="{{ $checkboxClass }}" />
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
                        <th scope="col" class="w-10 px-[var(--padding-wk-x-md)]"><span class="sr-only">{{ __('wirekit::Actions') }}</span></th>
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
                                <input type="checkbox" :checked="isSelected(row)" @change="toggleSelect(row)" :aria-label="columns.length ? {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Select row: :name')) }}.replace(':name', cellText(row, columns[0])) : {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Select row')) }}" class="{{ $checkboxClass }}" />
                            </td>
                        @endif
                        <template x-for="col in visibleColumns" :key="col.key">
                            <td
                                :class="(col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left') + (density === 'compact' ? ' py-1' : ' py-[var(--padding-wk-y-sm)]')"
                                class="px-[var(--padding-wk-x-md)] text-[color:var(--color-wk-text)] whitespace-nowrap"
                            >
                                <template x-if="col.cellType === 'badge'">
                                    <span class="inline-flex items-center px-[var(--padding-wk-x-sm)] py-0.5 rounded-[var(--radius-wk-full)] text-[length:var(--text-wk-xs)] capitalize" :class="{{ \Pushery\WireKit\Support\AlpinePayload::from($badgeClasses) }}[badgeIntent(cellText(row, col), col)]" x-text="cellText(row, col)"></span>
                                </template>
                                {{-- A column may carry a `subKey`, and the cell then reads as two lines:
                                     the value over a quieter second one. That is the ordinary shape of an
                                     admin table — order number over date, customer over email, product over
                                     SKU — and without it between a third and a half of the cells on a real
                                     data grid cannot be expressed, which is why those pages are still built
                                     on the plain table.

                                     The wrapper stays a `span` with `block` children rather than becoming a
                                     stack component: the body is an Alpine template, so a WireKit tag here
                                     would be rendered once by Blade and then cloned per row with the same
                                     resolved classes — the composition would be a lie about where it came
                                     from. Two spans and two tokens say what they are.

                                     An empty second value renders nothing at all, not an empty line: whether
                                     a row HAS the second value is data, and a gap where a row happens to
                                     lack one reads as a rendering fault. --}}
                                <template x-if="col.cellType === 'number'">
                                    <span>
                                        <span class="tabular-nums block" x-text="cellText(row, col)"></span>
                                        <template x-if="subText(row, col)">
                                            <span class="tabular-nums block text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]" x-text="subText(row, col)"></span>
                                        </template>
                                    </span>
                                </template>
                                <template x-if="!col.cellType || col.cellType === 'text'">
                                    <span>
                                        <span class="block" x-text="cellText(row, col)"></span>
                                        <template x-if="subText(row, col)">
                                            <span class="block text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]" x-text="subText(row, col)"></span>
                                        </template>
                                    </span>
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

        {{-- Empty state.

             The `empty` slot replaces the line, it does not sit beside it. The screen a new
             user sees FIRST is this one, and a single muted sentence can only say that
             nothing is here — it cannot say what to do about it, which is the whole job of
             that screen. `emptyText` stays the default so nothing changes for a table that
             does not care.

             The container keeps its own centering and padding either way, so a slot holding
             an `<x-wirekit::empty-state>` lands where the sentence did rather than needing
             the caller to re-center it. --}}
        <div x-show="isEmpty" x-cloak class="flex flex-col items-center justify-center gap-1 px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-xl)] text-center">
            @isset($empty)
                {{ $empty }}
            @else
                <p class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $emptyText }}</p>
            @endisset
        </div>
    </div>
</div>
