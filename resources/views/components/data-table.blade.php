{{-- optimistic-ui: n/a — query
     Sorting, filtering and paging are query round trips. Nobody can show rows nobody has fetched; only the intent could be acknowledged, and that is a different state machine. --}}
@props([
    'rows' => [],                   // row objects (client mode)
    // [{key,label,sortable?,sortStart?,align?,cellType?,hrefKey?,intents?,subKey?,intentKey?,avatarKey?,prominence?}]
    //   cellType: text|number|badge|badges|code|link · intents: value -> success|warning|danger|neutral
    //   hrefKey: for a link column, the ROW field holding the URL. A row without one reads as text.
    //   subKey / intentKey / avatarKey: the ROW names its second line, its intent, its avatar.
    //   prominence: strong|muted — how loud the column reads. Absent is the middle.
    //   sortStart: asc|desc — the direction of the column's first click. Absent is asc.
    'columns' => [],
    'rowKey' => 'id',               // unique id field for selection + morph keying
    'selectable' => config('wirekit.components.data-table.selectable', false), // per-row + header selection checkboxes
    // Freeze the first column while the rest scroll sideways. In an overview the first column
    // NAMES the row, and on a phone it is the first thing a swipe takes away.
    //
    // With `selectable` BOTH leading columns freeze — the checkbox and the name — because a
    // checkbox that scrolls out from under its own row is worse than one that never moved.
    // The offset is knowable rather than measured: the selection column's width is set here,
    // by this component, at `w-10`.
    'stickyColumn' => config('wirekit.components.data-table.sticky-column', false),
    'searchable' => config('wirekit.components.data-table.searchable', false), // toolbar search box (client-side filter)
    'density' => config('wirekit.components.data-table.density', 'comfortable'), // comfortable | compact
    'columnManager' => false,       // show/hide-columns dropdown
    'hidden' => [],                 // initially-hidden column keys
    'server' => false,              // server-driven: stop local sort/filter, emit events only
    // The order the table starts in: a column key and a direction. In server mode, the order the
    // server already applied, so `aria-sort` and the arrow describe the rows on screen. Without
    // them every column read "none" over a sorted table, and a screen reader said so.
    'sortKey' => null,
    'sortDir' => 'asc',
    // The filter the server applied, for a server-mode table. It fills the search field on the
    // first render (a page opened as `?q=…` shows its filter) and whenever the server changes it
    // on its own, such as a "clear filters" control. Null leaves the field to the reader.
    'search' => null,
    // Milliseconds to wait after the last keystroke before announcing `search-change`.
    //
    // Only the OUTBOUND event waits. `x-model` still updates on every keystroke, so the
    // client-side filter stays instant and the box never feels laggy — what this delays
    // is the round trip a server-mode table makes, which was one request per CHARACTER.
    // Typing an eight-letter name asked the application eight questions and threw away
    // the first seven answers, each of them a query.
    //
    // Configurable because the right value is a property of the BACKEND, not of the
    // table: a local index wants it short, a rate-limited third-party search wants it
    // long. 0 disables the wait entirely, for a developer who throttles on their own side.
    'searchDebounce' => config('wirekit.components.data-table.search-debounce', 300),
    'searchPlaceholder' => __('wirekit::Search…'),
    'emptyText' => __('wirekit::No results'),
    // A wait the server declares. Server mode exists for datasets whose round trip is SLOW, and
    // every sort click and every keystroke in the search field starts one — meanwhile the table
    // keeps showing the previous page's rows with nothing to say a query is on its way. A sighted
    // reader cannot tell a slow query from an ignored click, and a screen-reader user gets no
    // WCAG 4.1.3 status until the rows change.
    //
    // The table marks itself busy for the round trips it starts (a sort, a search), from the
    // moment the commit that carries one is sent until it comes back. This prop is for a wait
    // the table cannot see: a server-side process the page is waiting on. A render that passes
    // `true` holds the table busy until a render passes `false`.
    'loading' => false,
    'caption' => null,              // accessible table caption / name
    // Accessible name for the bulk-action bar, and the switch that makes it a LANDMARK.
    //
    // Same rule as `caption` below the table: `role="region"` plus a name is a landmark,
    // and several tables on one page each emitted a bar called "Bulk actions" — one name
    // across N rotor entries, which is what axe reports as `landmark-unique`. So the role
    // waits for a name the CALLER chose. Nothing is lost without it: the bar is not a
    // scroll region (no `overflow-*`, no `max-h-*`), so it needs no `tabindex` for WCAG
    // 2.1.1, its controls are in the tab order on their own, and the selection count is
    // announced through the `aria-live` span rather than through the landmark.
    'bulkActionsLabel' => null,
    'name' => null,                 // hidden-input name mirroring the selected ids
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\AvatarPalette;
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\DomId;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('data-table', $attributes->getAttributes());

    // The selection readout is a COUNT that only exists in the browser, so the plural form
    // has to be chosen there: the server renders every form and `Intl.PluralRules` picks.
    // It shipped as `<span x-text="selectedCount"></span> selected` — a bare English word
    // inside an `aria-live` region, which is the one string in this toolbar a non-visual
    // reader is guaranteed to hear.
    $selectionPhrases = \Pushery\WireKit\Support\AlpinePayload::from(
        \Pushery\WireKit\Support\PluralPhrases::from('wirekit::{1} :count selected|[2,*] :count selected')
    );

    // BCP-47 for `Intl.PluralRules` — the APPLICATION's locale, not the browser's.
    $pluralLocale = \Pushery\WireKit\Support\AlpinePayload::from(str_replace('_', '-', app()->getLocale()));

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $columnManager = BooleanProp::from($columnManager, false);
    $server = BooleanProp::from($server, false);

    // The initial sort. Two directions exist, and an empty key is no key: a value the factory
    // cannot use must not reach it looking like one.
    $sortKey = filled($sortKey) ? (string) $sortKey : null;
    $sortDir = $sortDir === 'desc' ? 'desc' : 'asc';
    // Null stays null: "not passed" and "the server applied no filter" are two different things.
    $search = $search === null ? null : (string) $search;
    $loading = BooleanProp::from($loading, false);
    // Same contract for the two whose default is spelled as a `config()` fallback rather
    // than a literal. Both gate whole regions of the table — the checkbox column and the
    // search field — so an unbound `selectable="false"` rendered the very column it asked
    // to remove, and the row checkboxes came with it.
    $selectable = BooleanProp::from($selectable, false);
    $stickyColumn = BooleanProp::from($stickyColumn, config('wirekit.components.data-table.sticky-column', false));

    /*
     * The frozen leading cells.
     *
     * `inset-inline-start`, never `left`: under `dir="rtl"` the first column is on the right,
     * and a physical offset would freeze it against the wrong edge — the same correction the
     * sentinels and shadows already carry.
     *
     * The OFFSET for the second frozen cell is `w-10` — the selection column's width, set by
     * this component a few lines below. It is a constant here rather than a measurement
     * because this component owns both ends of it; a `ResizeObserver` for a width we wrote
     * ourselves would be machinery in place of a number.
     *
     * An opaque background is what makes freezing readable at all: without one the scrolling
     * columns pass visibly underneath. The base is the surface, and the row's two states are
     * repeated on the cell — `group-hover` for the pointer, and the selected class applied by
     * the same expression the row uses, because `background: inherit` would take the row's
     * SPECIFIED value and the row specifies nothing when it is neither.
     */
    $stickyCellBase = $stickyColumn
        ? WireKit::resolveClasses('data-table', 'sticky-cell', 'sticky z-[1] bg-[var(--color-wk-bg)] group-hover:bg-[var(--color-wk-bg-subtle)]', $scope)
        : '';

    // The selected fill, resolved here rather than written as a literal inside the `:class`
    // ternary below. `resolveClasses` runs at RENDER time in PHP and `:class` is a RUNTIME
    // binding, so appearance decided inside the ternary is out of reach of `WireKit::scope()`:
    // a theme could repaint every row and not the one cell that stays in view. Both branches are
    // resolved here and interpolated; the ternary only chooses between two finished strings.
    $stickySelectedFill = $stickyColumn
        ? WireKit::resolveClasses('data-table', 'sticky-cell-selected', 'bg-[var(--color-wk-bg-muted)]', $scope)
        : '';

    /*
     * The frozen column is CAPPED, and the cap is the difference between the feature helping and
     * replacing the problem it solves. Measured at 390 px with an ordinary company name: the
     * column took 165 px, 42% of the screen, and every further character is taken from the
     * columns the reader froze it in order to read. `--size-wk-table-sticky-column-max` is the
     * same token `<x-wirekit::table>` caps with — one knob for one concept, rather than a second
     * that drifts. A cap, not a width: a short label keeps its own.
     *
     * Capping only works with a cell that may wrap, so the frozen cells carry `whitespace-normal`
     * and every other cell keeps `whitespace-nowrap`. That decision is made HERE rather than in
     * the class attribute, because two `white-space` utilities on one element are resolved by
     * their order in the stylesheet, which is Tailwind's to choose and not ours to rely on.
     */
    $stickyCellCap = 'max-w-[var(--size-wk-table-sticky-column-max)] whitespace-normal';

    $stickySelectionCell = $stickyColumn ? $stickyCellBase.' start-0' : '';
    $stickyFirstDataCell = $stickyColumn
        ? $stickyCellBase.($selectable ? ' start-10' : ' start-0').' '.$stickyCellCap
        : 'whitespace-nowrap';

    // The header's frozen cells sit above the body's, or a scrolled row would paint over the
    // heading it belongs to. The heading has no hover state of its own.
    $stickyHeadCellBase = $stickyColumn
        ? WireKit::resolveClasses('data-table', 'sticky-head-cell', 'sticky z-[2] bg-[var(--color-wk-bg)]', $scope)
        : '';
    $stickyHeadSelection = $stickyColumn ? $stickyHeadCellBase.' start-0' : '';
    $stickyHeadFirstData = $stickyColumn
        ? $stickyHeadCellBase.($selectable ? ' start-10' : ' start-0').' '.$stickyCellCap
        : 'whitespace-nowrap';
    $searchable = BooleanProp::from($searchable, false);

    $density = WireKit::validateProp('data-table', 'density', $density, ['comfortable', 'compact']);
    // Server-driven mode (client | server) — derived from the boolean `server`
    // prop. Named `server` (not `mode`) to stay off the surface-treatment axis.
    $mode = filter_var($server, FILTER_VALIDATE_BOOLEAN) ? 'server' : 'client';
    // Seeded from `name`, not re-randomized per render: Livewire's morph matches on the
    // id, so a fresh one each render means destroy-and-rebuild — and the Alpine-only
    // state (sort order, hidden columns, open panels) goes with it on the next round trip.
    // Necessary and not sufficient: the `x-data` expression has to stay the same too, which is
    // why the rows no longer travel in it (see the state carrier below the root).
    $id = $attributes->get('id', \Pushery\WireKit\WireKit::stableId('data-table', $name ?? $attributes->get('name')));
    $name = $name ?? $attributes->get('name');
    $captionId = $id.'-caption';

    $rowsArr = $rows instanceof \Illuminate\Support\Collection ? $rows->values()->all() : array_values((array) $rows);
    $colsArr = $columns instanceof \Illuminate\Support\Collection ? $columns->values()->all() : array_values((array) $columns);
    $hiddenArr = array_values((array) $hidden);

    // Avatar tints are resolved HERE, in PHP, and that is the whole reason an
    // avatar cell is expressible at all. `AvatarPalette` hashes a key with crc32
    // across eight oklch entries; reimplementing that hash in the Alpine factory
    // would put TWO of them in one library, and on the day they disagree the same
    // person renders one color in a table cell and another on their profile
    // avatar — a divergence nothing would catch, because each side stays
    // internally consistent. The rows already travel through PHP on their way to
    // Alpine (`AlpinePayload::from($rowsArr)` below), so the pair is computed once
    // per DISTINCT key and handed over as ordinary data.
    //
    // Keyed by the value rather than merged into the row: a derived field would
    // have to invent a name no application already uses, and rows arrive here as
    // arrays AND as objects, which is two ways to write it and two to get wrong.
    $avatarTints = [];

    foreach ($colsArr as $col) {
        $avatarKey = data_get($col, 'avatarKey');

        if (! $avatarKey) {
            continue;
        }

        foreach ($rowsArr as $row) {
            $value = data_get($row, $avatarKey);

            if (filled($value) && ! isset($avatarTints[(string) $value])) {
                $avatarTints[(string) $value] = AvatarPalette::for((string) $value);
            }
        }
    }

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
    // The pill's SHAPE, named once. It is worn by two different cells — a `badge`
    // column, where every row is a pill, and a row that named its own intent below —
    // and two copies of this string is how they would come to differ by a quarter rem
    // without anyone editing either on purpose.
    // How loud a column reads. One axis, three positions, and the middle one is the absence of
    // an entry — a table where every column shouts says nothing, and one where none does gives
    // the eye no way in. `strong` is the column carrying the row's identity or its total;
    // `muted` is the one that is context rather than content, like an address beside a name.
    //
    // A map of PHP literals rather than a ternary in the template: Tailwind compiles what it
    // finds in this file and the drift inventory traces it, and a class assembled inside an
    // Alpine expression is invisible to both. It travels in the payload and the CELL calls a
    // method to read it — `??` is outside Alpine's CSP grammar, so an application on the CSP
    // bundle would have rendered the binding inert with no error at all.
    $prominenceClasses = [
        'strong' => 'font-semibold',
        'muted' => 'text-[color:var(--color-wk-text-muted)]',
    ];

    $pillClass = 'inline-flex items-center px-[var(--padding-wk-x-sm)] py-0.5 rounded-[var(--radius-wk-full)] text-[length:var(--text-wk-xs)] capitalize';

    // The link cell wears the link component's own default look, resolved through the link
    // component's personalization key, so a developer who restyled links restyles these too.
    // No focus ring of its own, like the link: the browser's focus indicator stays. The cell is
    // cloned per row by Alpine, so the component itself cannot render here.
    // DataTableLinkCellTest renders a default link and checks every one of its classes is on the
    // cell's anchor, which is what keeps this list and link.blade.php from drifting apart.
    $linkClass = WireKit::resolveClasses('link', 'base', implode(' ', [
        'font-[family-name:var(--font-wk-sans)]',
        'cursor-pointer',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'hover:opacity-80',
        'text-[color:var(--color-wk-accent-text)]',
        'underline underline-offset-2',
    ]), $scope);

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

    $checkboxClass = 'h-4 w-4 rounded-[var(--radius-wk-sm)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] accent-[var(--color-wk-accent)] cursor-pointer focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]';
    // The column popover names itself through its own trigger, so the pairing needs two
    // ids that survive a re-render. Counted, never random: the table is a natural
    // wire:poll surface, and a fresh id per render leaves `aria-controls` and
    // `aria-labelledby` pointing at the value from the render before — both halves
    // well-formed, each naming something the other no longer has.
    $columnsPanelId = DomId::unique(null, 'wk-data-table-columns-');
    $columnsButtonId = $columnsPanelId.'-button';

    $iconBtn = 'inline-flex items-center gap-1 px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] rounded-[var(--radius-wk-md)] hover:text-[color:var(--color-wk-text)] hover:bg-[var(--color-wk-bg-muted)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] transition-colors cursor-pointer';
@endphp

<div
    {{ $attributes->except(['id', 'name', 'class'])->whereDoesntStartWith('wire:model') }}
    id="{{ $id }}"
    x-data="wirekitDataTable({ columns: {{ \Pushery\WireKit\Support\AlpinePayload::from($colsArr) }}, rowKey: {{ \Pushery\WireKit\Support\AlpinePayload::string($rowKey) }}, mode: {{ \Pushery\WireKit\Support\AlpinePayload::string($mode) }}, density: {{ \Pushery\WireKit\Support\AlpinePayload::string($density) }}, hidden: {{ \Pushery\WireKit\Support\AlpinePayload::from($hiddenArr) }}, emptyText: {{ \Pushery\WireKit\Support\AlpinePayload::string($emptyText) }}, loadingText: {{ \Pushery\WireKit\Support\AlpinePayload::string(__('wirekit::Loading results')) }}, prominenceClasses: {{ \Pushery\WireKit\Support\AlpinePayload::from($prominenceClasses) }}, selectionPhrases: {{ $selectionPhrases }}, locale: {{ $pluralLocale }} })"
    {{ $attributes->only('class')->class([$base]) }}
>
    {{-- What changes from one render to the next travels HERE, not in `x-data`.

         The rows used to be part of the `x-data` expression. A Livewire render changes them,
         the morph writes the changed expression onto the same element, and Alpine re-evaluates
         it into a fresh component: measured across a search round trip, the element kept its
         identity and the scope did not. Every value only the browser holds went back to its
         start with it, the text in the search field, the density, the hidden columns, the
         selection. The expression above now carries only what a render does not change, and the
         factory reads this carrier when it starts and after every Livewire commit.

         A `<template>` so it has no box at all, and an attribute so Blade's own escaping
         applies. --}}
    <template data-wk-data-table-state="{{ \Pushery\WireKit\Support\AlpinePayload::from(['rows' => $rowsArr, 'avatarTints' => $avatarTints, 'sortKey' => $sortKey, 'sortDir' => $sortDir, 'search' => $search, 'loading' => $loading]) }}"></template>

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
                        @input{{ (int) $searchDebounce > 0 ? '.debounce.'.((int) $searchDebounce).'ms' : '' }}="onSearch()"
                        placeholder="{{ $searchPlaceholder }}"
                        aria-label="{{ $searchPlaceholder }}"
                        {{-- Search input is a form control (WCAG 1.4.11): its border
                             comes from the communicating --color-wk-border-strong token
                             (3:1), never the decorative --color-wk-border (~1.29:1) — the
                             category-scoped 2.17.0 sweep missed it because data-table is
                             not in the Form category. --}}
                        class="wk-field w-[16rem] max-w-full bg-[var(--color-wk-bg-input)] text-[color:var(--color-wk-text)] text-[length:var(--text-wk-sm)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border-strong)] rounded-[var(--radius-wk-md)] px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] transition-colors duration-[var(--transition-wk-duration)] hover:border-[var(--color-wk-border-strong-hover)] focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                    />
                @endif
            </div>
            <div class="flex items-center gap-[var(--space-wk-sm)]">
                {{ $toolbar ?? '' }}
                {{-- Density toggle --}}
                <div class="inline-flex rounded-[var(--radius-wk-md)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] overflow-hidden" role="group" aria-label="{{ __('wirekit::Row density') }}">
                    <button type="button" @click="setDensity('comfortable')" :aria-pressed="density === 'comfortable'" :class="density === 'comfortable' ? 'bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text)]' : 'text-[color:var(--color-wk-text-muted)]'" class="px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-sm)] cursor-pointer focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-inset">{{ __('wirekit::Comfortable') }}</button>
                    <button type="button" @click="setDensity('compact')" :aria-pressed="density === 'compact'" :class="density === 'compact' ? 'bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text)]' : 'text-[color:var(--color-wk-text-muted)]'" class="px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-sm)] cursor-pointer focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-inset">{{ __('wirekit::Compact') }}</button>
                </div>
                @if($columnManager)
                    {{-- Column manager — a self-contained popover (nested scope; inherits
                         toggleColumn / isColumnVisible / columns from the table scope). --}}
                    {{-- Disclosure + anchoring live in the factory
                         (resources/js/components/data-table-column-menu.js).
                         Neither the method shorthand nor the x-init arrow
                         parsed under Alpine's CSP build, so the whole nested
                         scope failed to build and the button toggled nothing. --}}
                    {{-- A DISCLOSURE, and it says so.

                         The panel used to announce itself as `aria-haspopup="menu"` +
                         `role="menu"`, and it is neither: its children are checkboxes, not
                         `menuitem`s, and nothing here implements a menu's keyboard model.
                         A reader was told "Columns, menu button", expected arrow keys and
                         menu items, and got an unnamed box of checkboxes — and `role="menu"`
                         without `menuitem` children fails ARIA's required-children rule
                         anyway. `aria-haspopup="true"` would not have helped: ARIA maps the
                         bare `true` onto `menu`, so it makes the same wrong promise.

                         What it really is: a button that discloses a group of checkboxes.
                         `aria-expanded` + `aria-controls` on the trigger, `role="group"` on
                         the panel, named by the trigger's own visible text rather than by an
                         invented label — the name a reader hears is then the word they read.
                         `group` is not a landmark, so a built-in name costs nothing here
                         (a landmark would make every table on the page the same region).
                         The scroll region keeps a keyboard model through that group role and
                         its focusable checkboxes, which the factory focuses on open. --}}
                    <div x-data="wirekitDataTableColumnMenu()" @click.outside="open = false" @keydown.escape="open = false" class="relative">
                        <button type="button" id="{{ $columnsButtonId }}" x-ref="colBtn" @click="open = !open" :aria-expanded="open" aria-controls="{{ $columnsPanelId }}" class="{{ $iconBtn }}">
                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 4h12M2 8h12M2 12h12"/></svg>
                            {{ __('wirekit::Columns') }}
                        </button>
                        <div x-show="open" x-cloak x-ref="colMenu" id="{{ $columnsPanelId }}" role="group" aria-labelledby="{{ $columnsButtonId }}" class="fixed z-[var(--z-wk-dropdown)] w-[12rem] max-h-[70vh] overflow-y-auto p-[var(--padding-wk-x-sm)] bg-[var(--color-wk-bg-elevated)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] rounded-[var(--radius-wk-md)] shadow-[var(--shadow-wk-lg)]">
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

    {{-- Bulk-action bar — appears when rows are selected.

         The LANDMARK is opt-in and its switch is `bulkActionsLabel`, exactly as the table's
         own region below is switched by `caption`. `filled()` rather than `??`: an
         interpolated caller value can arrive empty, and `role="region"` with an empty
         accessible name is not exposed as a landmark at all — the worst of the three
         outcomes, since it reads as named to the markup and as nothing to the reader. --}}
    @if($selectable)
        <div x-show="selectedCount > 0" x-cloak @if(filled($bulkActionsLabel)) role="region" aria-label="{{ $bulkActionsLabel }}" @endif class="flex flex-wrap items-center justify-between gap-[var(--space-wk-sm)] px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-sm)] bg-[var(--color-wk-bg-muted)] rounded-[var(--radius-wk-md)]">
            {{-- `aria-atomic="true"`, because the region holds ONE sentence that only makes sense
                     whole. Without it a polite region announces the part that changed, and the part
                     that changes here is the number — so going from "3 rows selected" to "4 rows
                     selected" could be read out as "4", with the noun the count belongs to left
                     behind. Every other live region in this catalog carries the pair. --}}
                <span class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]" aria-live="polite" aria-atomic="true" x-text="selectionSummary"></span>
            <div class="flex items-center gap-[var(--space-wk-sm)]">
                {{ $bulkActions ?? '' }}
                <button type="button" @click="clearSelection()" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] rounded-[var(--radius-wk-sm)] cursor-pointer">{{ __('wirekit::Clear') }}</button>
            </div>
        </div>
    @endif

    {{-- Table — keyboard-reachable scroll region (WCAG 2.1.1), unconditionally.

         The LANDMARK is opt-in and its switch is the caption. The `@else` arm used to name the
         region "Data table", which is what everything on the page already is: three tables on a
         dashboard were three identical rotor entries, and axe reports that as
         `landmark-unique`. With a caption the region points at it (`aria-labelledby`), which is
         a name the reader chose; without one there is no landmark to be ambiguous about. --}}
    {{-- A table that scrolls sideways shows a shadow at the edge it continues toward. On a phone
         there is no scrollbar until a drag is already underway, so a table cut off between two
         columns looked complete. The machinery is `table`'s: two one-pixel sentinels at the
         inline edges of the scroll content, an IntersectionObserver over them in
         `wirekitStickyPanelShadows`, and the `wk-scroll-shadow-start` / `-end` overlays.

         Its own component on a wrapper, not members of this one. Every name it adds lives in
         the shadow factory, none collides with this factory's, and a method called from inside
         still reaches the hidden selection input: `$refs` gathers the refs of every ancestor
         component. `w-full min-w-0` keeps a wide table from widening the page, as in `table`. --}}
    <div class="relative w-full min-w-0" x-data="wirekitStickyPanelShadows()">
    {{-- Positioned for the same reason as the scroller in `table`: the caption, the actions heading
         and the status region are visually hidden, so `position: absolute`, and they must resolve
         against the scroller rather than the wrapper outside it. The shadows below are siblings
         and keep the wrapper as theirs. --}}
    <div x-ref="scroller" @if(filled($caption)) role="region" aria-labelledby="{{ $captionId }}" @endif @if($loading) aria-busy="true" @endif x-bind:aria-busy="ariaBusy()" tabindex="0" class="relative w-full min-w-0 overflow-x-auto wk-scrollbar rounded-[var(--radius-wk-lg)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]">
        {{-- The sentinels need the table's inline edges, and this scroller also holds the empty
             state and the status region below the table, so the flex row is a wrapper around
             the table alone rather than the scroller itself. `w-fit min-w-full` sizes it the
             way the table sized itself before: the full width when the table fits, the
             table's own minimum width when it does not. Each sentinel is one real pixel taken
             back by a logical negative margin on the side facing the table, so the observer
             has an area to intersect and the layout pays nothing, in either writing direction. --}}
        <div class="flex w-fit min-w-full">
        <div x-ref="startSentinel" aria-hidden="true" class="w-px shrink-0 self-stretch -me-px"></div>
        {{-- `w-max` under `stickyColumn`, and it is the difference between the feature working and
             merely rendering: at `w-full` the table squeezes itself into the scroller, nothing
             ever overflows, and a frozen column freezes against an edge that never moves. --}}
        <table data-wk-prose-skip class="shrink-0 border-collapse text-[length:var(--text-wk-sm)] {{ $stickyColumn ? 'w-max min-w-full' : 'w-full' }}" @if($stickyColumn) data-wk-sticky-column @endif>
            @if($caption)
                <caption id="{{ $captionId }}" class="sr-only">{{ $caption }}</caption>
            @endif
            <thead>
                <tr class="border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)]">
                    @if($selectable)
                        <th data-wk-prose-skip scope="col" class="w-10 px-[var(--padding-wk-x-md)] {{ $stickyHeadSelection }}">
                            {{-- Tri-state header selection (indeterminate set reactively). --}}
                            <input type="checkbox" :checked="allSelected" @change="toggleSelectAll()" x-effect="$el.indeterminate = someSelected" aria-label="{{ __('wirekit::Select all rows') }}" class="{{ $checkboxClass }}" />
                        </th>
                    @endif
                    <template x-for="col in visibleColumns" :key="col.key">
                        <th data-wk-prose-skip
                            scope="col"
                            :aria-sort="ariaSort(col.key)"
                            {{-- The frozen classes land on the FIRST visible column only. Which one that
                                 is comes from `isFrozenColumn()` in the factory rather than from an
                                 index read here: the shape this needs — `visibleColumns[0]?.key` — does
                                 not parse under Alpine's CSP build, and an unparseable binding is inert
                                 rather than loud. Same call in the body cell below. --}}
                            :class="(col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left') + (density === 'compact' ? ' py-1' : ' py-[var(--padding-wk-y-sm)]') + (isFrozenColumn(col) ? {{ \Pushery\WireKit\Support\AlpinePayload::string(' '.$stickyHeadFirstData) }} : ' whitespace-nowrap')"
                            class="px-[var(--padding-wk-x-md)] text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text-muted)]"
                        >
                            <template x-if="col.sortable !== false">
                                <button type="button" @click="toggleSort(col.key)" class="inline-flex items-center gap-1 hover:text-[color:var(--color-wk-text)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] rounded-[var(--radius-wk-sm)] cursor-pointer">
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
                        <th data-wk-prose-skip scope="col" class="w-10 px-[var(--padding-wk-x-md)]"><span class="sr-only">{{ __('wirekit::Actions') }}</span></th>
                    @endisset
                </tr>
            </thead>
            <tbody>
                <template x-for="row in displayRows" :key="rowId(row)">
                    {{-- `group` so a frozen cell can repeat the row's hover. A sticky cell needs its
                         own opaque background, and `background: inherit` would take the row's
                         SPECIFIED value — which is nothing at all when the row is neither
                         selected nor hovered. --}}
                    <tr :class="isSelected(row) ? 'bg-[var(--color-wk-bg-muted)]' : 'hover:bg-[var(--color-wk-bg-subtle)]'" class="group border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)] transition-colors">
                        @if($selectable)
                            <td data-wk-prose-skip class="px-[var(--padding-wk-x-md)] {{ $stickySelectionCell }}" :class="(density === 'compact' ? 'py-1' : 'py-[var(--padding-wk-y-sm)]') + (isSelected(row) ? {{ \Pushery\WireKit\Support\AlpinePayload::string(' '.$stickySelectedFill) }} : '')">
                                {{-- Unique accessible name per row: prefix with the first
                                     column's value so a screen reader doesn't hear "Select
                                     row" N identical times (WCAG name uniqueness). --}}
                                <input type="checkbox" :checked="isSelected(row)" @change="toggleSelect(row)" :aria-label="columns.length ? {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Select row: :name')) }}.replace(':name', cellText(row, columns[0])) : {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Select row')) }}" class="{{ $checkboxClass }}" />
                            </td>
                        @endif
                        <template x-for="col in visibleColumns" :key="col.key">
                            <td data-wk-prose-skip
                                :class="(col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left') + (density === 'compact' ? ' py-1' : ' py-[var(--padding-wk-y-sm)]') + (isFrozenColumn(col) ? {{ \Pushery\WireKit\Support\AlpinePayload::string(' '.$stickyFirstDataCell) }} + (isSelected(row) ? {{ \Pushery\WireKit\Support\AlpinePayload::string(' '.$stickySelectedFill) }} : '') : ' whitespace-nowrap')"
                                class="px-[var(--padding-wk-x-md)] text-[color:var(--color-wk-text)]"
                            >
                                <template x-if="col.cellType === 'badge'">
                                    <span class="{{ $pillClass }}" :class="{{ \Pushery\WireKit\Support\AlpinePayload::from($badgeClasses) }}[badgeIntent(cellText(row, col), col)]" x-text="cellText(row, col)"></span>
                                </template>
                                {{-- A column whose VALUE is a list, drawn as one pill per entry. The tags cell of a
                                     customer table is the shape: a row carries none, one, or four of them, and the count
                                     is data rather than configuration. `badge` draws exactly one pill from one value and
                                     could never express it.

                                     A nested `x-for` rather than a cell slot: the entries are strings in the row, the
                                     intent of each comes from the same `intents` map the single badge reads, and nothing
                                     here needs markup the caller writes. Keyed by INDEX, not by value — a row may legally
                                     carry the same tag twice, and a duplicate key silently drops the second one.

                                     Deliberately NO cap with a "+2 more" affordance. That would be a second control with
                                     its own keyboard question, built against a guess about how many entries a real row
                                     carries; the four grids measured here top out at two. `flex-wrap` handles the rest. --}}
                                <template x-if="col.cellType === 'badges'">
                                    <span class="inline-flex flex-wrap items-center gap-[var(--gap-wk-xs)]">
                                        <template x-for="(item, index) in badgeItems(row, col)" :key="index">
                                            <span class="{{ $pillClass }}" :class="{{ \Pushery\WireKit\Support\AlpinePayload::from($badgeClasses) }}[badgeIntent(item, col)]" x-text="item"></span>
                                        </template>
                                    </span>
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
                                {{-- A row that names its own intent wears the pill, whatever base type the
                                     column declares; a row that names none renders in that base type. That is
                                     the admin table's threshold cell — `Out` in red, `3 low` in amber, a plain
                                     tabular `42` — and all three of the things it varies (the intent, the label,
                                     and whether there is a pill at all) are functions of the VALUE, which is why
                                     a value->intent map on the column could never express it.

                                     The threshold itself stays in the application, where it is ordinary PHP and
                                     can be tested. The alternative was a comparison dialect inside a config array
                                     (`['<', 10, 'warning']`) — a small language this component would have to parse,
                                     document and version, in return for the one dimension it could express. --}}
                                <template x-if="col.cellType !== 'badge' && col.cellType !== 'badges' && rowIntent(row, col)">
                                    <span class="{{ $pillClass }}" :class="{{ \Pushery\WireKit\Support\AlpinePayload::from($badgeClasses) }}[rowIntent(row, col)]" x-text="cellText(row, col)"></span>
                                </template>
                                <template x-if="col.cellType === 'number' && ! rowIntent(row, col)">
                                    <span :class="avatarText(row, col) ? 'inline-flex items-center gap-[var(--gap-wk-sm)]' : ''">
                                        <template x-if="avatarText(row, col)">
                                            <span aria-hidden="true" class="inline-flex shrink-0 items-center justify-center w-6 h-6 rounded-[var(--radius-wk-full)] text-[length:var(--text-wk-sm)] font-semibold" :style="avatarStyle(row, col)" x-text="avatarText(row, col)"></span>
                                        </template>
                                        <span>
                                            <span class="tabular-nums block" :class="prominenceClass(col)" x-text="cellText(row, col)"></span>
                                            <template x-if="subText(row, col)">
                                                <span class="tabular-nums block text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]" x-text="subText(row, col)"></span>
                                            </template>
                                        </span>
                                    </span>
                                </template>
                                {{-- A monospace cell. SKU, barcode, order id, hash — a table column of codes reads as a
                                     column only when the glyphs line up, and a proportional font puts an `I` and an `M` at
                                     different widths in the same position of two rows. It sits on the SAME axis as `number`
                                     rather than being a new concept: both say "these values are compared down the column,
                                     not read as prose", and both answer it by fixing the advance width. --}}
                                <template x-if="col.cellType === 'code' && ! rowIntent(row, col)">
                                    <span :class="avatarText(row, col) ? 'inline-flex items-center gap-[var(--gap-wk-sm)]' : ''">
                                        <template x-if="avatarText(row, col)">
                                            <span aria-hidden="true" class="inline-flex shrink-0 items-center justify-center w-6 h-6 rounded-[var(--radius-wk-full)] text-[length:var(--text-wk-sm)] font-semibold" :style="avatarStyle(row, col)" x-text="avatarText(row, col)"></span>
                                        </template>
                                        <span>
                                            <span class="font-mono block" :class="prominenceClass(col)" x-text="cellText(row, col)"></span>
                                            <template x-if="subText(row, col)">
                                                <span class="font-mono block text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]" x-text="subText(row, col)"></span>
                                            </template>
                                        </span>
                                    </span>
                                </template>
                                {{-- A link to the row's own page: the value as the text, the URL from the
                                     row field `hrefKey` names. A real anchor and nothing else, no click
                                     handler and no row navigation in JavaScript, so middle-click, the
                                     context menu and "copy link" work. The URL comes through cellHref(),
                                     which gives nothing for a row without one or for one that would run
                                     script, and that row falls through to the plain branch below. --}}
                                <template x-if="col.cellType === 'link' && cellHref(row, col) && ! rowIntent(row, col)">
                                    <span :class="avatarText(row, col) ? 'inline-flex items-center gap-[var(--gap-wk-sm)]' : ''">
                                        <template x-if="avatarText(row, col)">
                                            <span aria-hidden="true" class="inline-flex shrink-0 items-center justify-center w-6 h-6 rounded-[var(--radius-wk-full)] text-[length:var(--text-wk-sm)] font-semibold" :style="avatarStyle(row, col)" x-text="avatarText(row, col)"></span>
                                        </template>
                                        <span>
                                            <a data-wk-prose-skip :href="cellHref(row, col)" class="block w-fit {{ $linkClass }}" x-text="cellText(row, col)"></a>
                                            <template x-if="subText(row, col)">
                                                <span class="block text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]" x-text="subText(row, col)"></span>
                                            </template>
                                        </span>
                                    </span>
                                </template>
                                {{-- The plain branch is also the fallback: a link row without a URL, and a
                                     cellType this table does not know. Before isPlainCell() an unknown type
                                     matched no branch at all and the cell rendered empty. --}}
                                <template x-if="isPlainCell(row, col) && ! rowIntent(row, col)">
                                    <span :class="avatarText(row, col) ? 'inline-flex items-center gap-[var(--gap-wk-sm)]' : ''">
                                        <template x-if="avatarText(row, col)">
                                            <span aria-hidden="true" class="inline-flex shrink-0 items-center justify-center w-6 h-6 rounded-[var(--radius-wk-full)] text-[length:var(--text-wk-sm)] font-semibold" :style="avatarStyle(row, col)" x-text="avatarText(row, col)"></span>
                                        </template>
                                        <span>
                                            <span class="block" :class="prominenceClass(col)" x-text="cellText(row, col)"></span>
                                            <template x-if="subText(row, col)">
                                                <span class="block text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]" x-text="subText(row, col)"></span>
                                            </template>
                                        </span>
                                    </span>
                                </template>
                            </td>
                        </template>
                        @isset($rowActions)
                            <td data-wk-prose-skip class="px-[var(--padding-wk-x-md)] text-right" :class="density === 'compact' ? 'py-1' : 'py-[var(--padding-wk-y-sm)]'">
                                {{ $rowActions }}
                            </td>
                        @endisset
                    </tr>
                </template>
            </tbody>
        </table>
        <div x-ref="endSentinel" aria-hidden="true" class="w-px shrink-0 self-stretch -ms-px"></div>
        </div>

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
                <p data-wk-prose-skip class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $emptyText }}</p>
            @endisset
        </div>

        {{-- The result-set live region.

             Searching a table changes what is on screen without focus moving, which is a
             status message in the WCAG 4.1.3 sense — and until this existed the only thing
             this table ever announced was the selection count. A reader could type a query
             that matched nothing and hear silence.

             It sits OUTSIDE the empty block and carries its own text rather than the
             `role="status"` going on the block itself. A live region announces a CONTENT
             change; moving an element between `display: none` and visible is not a content
             change every screen reader reports, and the sentence in there never changes
             anyway. The text swap here is what makes the announcement happen.

             It also stays out of the `empty` slot's way: a slot holding a whole
             `<x-wirekit::empty-state>` would otherwise have its heading, its illustration
             and its call to action read out as one status message. --}}
        {{-- The pending sentence goes through the SAME region as the empty announcement, and
             wins while a request is out: two live regions competing would announce in an order
             nobody controls, and "loading" is the more urgent of the two.

             Bound through Alpine rather than rendered as static text. Static text would wait for
             the render that flips `loading`, and a Livewire response is rendered after the wait
             is over, so that render always arrives too late to announce it. The table knows when
             a round trip it started is out, and `loading` adds a wait the server declares. The
             static text below is only the first paint of a table rendered busy. --}}
        <p data-wk-prose-skip class="sr-only" role="status" aria-live="polite" x-text="statusAnnouncement">@if($loading){{ __('wirekit::Loading results') }}@endif</p>
    </div>
    {{-- aria-hidden: the shadows are for the eye. The scroller itself is focusable, and named
         when there is a caption. --}}
    {{-- Suppressed under `stickyColumn`, and that is a decision rather than an omission: this
         shadow marks content that has scrolled away past the start edge, and with a frozen
         column nothing HAS scrolled away there — the shadow would lie on top of the one column
         that never moves and say the opposite of what it means. The end shadow stays: content
         really does continue past that edge. --}}
    @unless($stickyColumn)
        <div aria-hidden="true" x-cloak x-show="startShadow" x-transition.opacity class="wk-scroll-shadow-start"></div>
    @endunless
    <div aria-hidden="true" x-cloak x-show="endShadow" x-transition.opacity class="wk-scroll-shadow-end"></div>
    </div>
</div>
