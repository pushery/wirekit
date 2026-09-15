{{-- optimistic-ui: n/a — client-only
     Hover and tooltip state over data the server sent. --}}
@props([
    // The empty state. `empty` REPLACES the body rather than sitting beside it: the screen a
    // new user sees FIRST is the one with no data, and a single muted sentence can only say
    // that nothing is here — it cannot say what to do about it, which is the whole job of that
    // screen. `emptyText` is the default, so a caller that does not care changes nothing.
    // Same shape as data-table, which is where the reasoning was first written down.
    'emptyText' => __('wirekit::Nothing here yet'),
    'rows' => [],                   // [{key,label}] — the matrix rows (left axis)
    'columns' => [],                // [{key,label}] — the matrix columns (top axis)
    'cells' => [],                  // value map: ["rowKey:colKey" => value] OR [rowKey => [colKey => value]]
    'cellType' => config('wirekit.components.status-matrix.cell-type', 'status'), // tristate | toggle | status | heat
    'editable' => false,            // tristate / toggle become interactive
    'cornerLabel' => '',            // top-left header cell label (the row-axis name)
    // Accessible name for the grid, and the switch that makes the scroll wrapper a LANDMARK.
    //
    // Two elements read this prop, and they treat it differently on purpose. The inner
    // `<table>` is ALWAYS named — a table with no accessible name is a worse outcome than a
    // duplicated region, so the fallback below survives for it. The outer scroll wrapper only
    // becomes `role="region"` when the CALLER named it: unnamed, three matrices on one page
    // were three rotor entries called "Status matrix" (axe: `landmark-unique`).
    'ariaLabel' => null,
    'name' => null,                 // hidden-input name for form submission (editable)
    // Heat scale endpoints — a cold→hot ramp. Defaults map to the existing
    // state tokens (warning = amber "cold-warm" → danger = red "hot"), so the
    // grid reads as actual heat (not an achromatic fade) and stays themeable.
    // Override for a different ramp, e.g. heat-from="var(--color-wk-success)"
    // for a green→red scale. The value is always printed, so the grid stays
    // legible for colorblind readers regardless of the ramp.
    'heatFrom' => 'var(--color-wk-warning)', // low end (cold)
    'heatTo' => 'var(--color-wk-danger)',    // high end (hot)
    'heatMin' => 0,                 // heat scale lower bound
    'heatMax' => 100,               // heat scale upper bound
    'heatUnit' => '',               // suffix on heat value labels (e.g. '%')
    'legend' => config('wirekit.components.status-matrix.legend', true), // render the cell-type legend
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('status-matrix', $attributes->getAttributes());

    // The DISPLAY name, resolved once. The raw `$ariaLabel` stays the record of whether the
    // caller supplied one, which is what the region's role is gated on below; this variable is
    // what every element that must always be named actually renders.
    $ariaLabelResolved = filled($ariaLabel) ? $ariaLabel : __('wirekit::Status matrix');

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $editable = BooleanProp::from($editable, false);
    // Same contract, different spelling of the default: a `config()` fallback declares a
    // boolean as surely as a literal does. `legend` defaults ON, so `legend="false"` is a
    // switch a developer sets deliberately — and the legend it asked to drop stayed drawn.
    $legend = BooleanProp::from($legend, true);

    $cellType = WireKit::validateProp('status-matrix', 'cellType', $cellType, ['tristate', 'toggle', 'status', 'heat']);
    $isEditable = filter_var($editable, FILTER_VALIDATE_BOOLEAN) && in_array($cellType, ['tristate', 'toggle'], true);

    // Is this matrix a COMPOSITE WIDGET, or a data table?
    //
    // `role="grid"` is a promise of a keyboard model: exactly one tab stop inside
    // the grid, from which the arrows navigate. Only the two interactive cell types
    // render anything that can hold it — `tristate` and `toggle` emit a <button>
    // with the roving tabindex and `moveFocus`. The `heat` cell is a <div> with a
    // <span>, and the default `status` cell is a badge; neither is focusable.
    //
    // Declared unconditionally, the SHIPPED DEFAULT was therefore a grid with zero
    // tab stops. A reader reaches the scroll region, hears "grid, N rows, M
    // columns", and no key takes them in. Zero tab stops is not a reduced grid; it
    // is a role announcing a navigation that does not exist — and `axe-core` has no
    // rule for it, so both accessibility lanes ran green over it throughout.
    //
    // The role is withdrawn rather than the tab stop retrofitted, and that is the
    // better outcome rather than the smaller change. `role="grid"` REPLACES the
    // screen reader's own table-navigation mode — the one that announces the row
    // and column header as you move — with an application-widget model. A read-only
    // matrix IS a data table, so the native semantics are the right ones for it; and
    // the alternative would have made every cell of a routinely 10x12 table a focus
    // stop in exchange for reaching data the reader could already reach.
    //
    // Gated on the CELL TYPE, not on `$isEditable`: a read-only tristate matrix
    // still navigates by arrow key, so it is still a grid. That is exactly what the
    // note on the tristate cell below argues, and it stays true where it was written.
    //
    // WCAG 2.1.1 is unaffected — the scroll wrapper carries an unconditional
    // `tabindex="0"` and a focus ring, which is the house shape for a generic
    // scroller. The grid role was never what made this region reachable.
    $isCompositeGrid = in_array($cellType, ['tristate', 'toggle'], true);

    // Seeded from `name`, not re-randomized per render: Livewire's morph matches on the
    // id, so a fresh one each render means destroy-and-rebuild — and the Alpine-only
    // state (sort order, hidden columns, open panels) goes with it on the next round trip.
    $id = $attributes->get('id', \Pushery\WireKit\WireKit::stableId('status-matrix', $name ?? $attributes->get('name')));
    $name = $name ?? $attributes->get('name');

    // Normalize axes to plain arrays of {key,label}.
    $toAxis = function ($items) {
        $items = $items instanceof \Illuminate\Support\Collection ? $items->all() : (array) $items;
        return array_values(array_map(function ($it) {
            $it = (array) $it;
            return ['key' => (string) ($it['key'] ?? $it['label'] ?? ''), 'label' => (string) ($it['label'] ?? $it['key'] ?? '')];
        }, $items));
    };
    $rowList = $toAxis($rows);
    $colList = $toAxis($columns);

    // The grid role needs CELLS as well as an editable cell type. Gated on the type alone,
    // an empty `rows`/`columns` set still produced a `role="grid"` with no gridcell in it —
    // which axe reports as `aria-required-children`, and which promises a reader a keyboard
    // model with nothing to move between. An empty matrix is a plain `<table>`, and that is
    // the honest answer. Computed here rather than beside the type check above, because the
    // axes do not exist until this line.
    $isCompositeGrid = $isCompositeGrid && $rowList !== [] && $colList !== [];

    // Normalize cells into a flat ["row:col" => value] lookup (accepts nested too).
    $cellsArr = $cells instanceof \Illuminate\Support\Collection ? $cells->all() : (array) $cells;
    $flatCells = [];
    foreach ($cellsArr as $k => $v) {
        if (is_array($v)) {
            foreach ($v as $ck => $cv) {
                $flatCells[$k.':'.$ck] = $cv;
            }
        } else {
            $flatCells[$k] = $v;
        }
    }
    $cellAt = fn ($r, $c) => $flatCells[$r.':'.$c] ?? null;

    // Map a status value to a semantic intent for the badge (sensible defaults).
    $statusIntent = function ($value) {
        $v = strtolower((string) $value);
        return match (true) {
            in_array($v, ['met', 'pass', 'passed', 'ok', 'active', 'compliant', 'done', 'on'], true) => 'success',
            in_array($v, ['at-risk', 'at risk', 'warning', 'pending', 'partial', 'review'], true) => 'warning',
            in_array($v, ['failing', 'fail', 'failed', 'error', 'inactive', 'breach', 'off'], true) => 'danger',
            default => 'neutral',
        };
    };

    // Heat ratio (server-side initial paint; heat is read-only).
    $heatRatio = function ($value) use ($heatMin, $heatMax) {
        $min = (float) $heatMin;
        $max = (float) $heatMax;
        if ($max <= $min) {
            return 0.0;
        }
        $r = ((float) $value - $min) / ($max - $min);
        return max(0.0, min(1.0, $r));
    };

    $base = WireKit::resolveClasses('status-matrix', 'base', 'w-full font-[family-name:var(--font-wk-sans)]', $scope);

    // Sticky header cell + sticky first column share a token surface so the
    // frozen edges read as chrome against the scrolling body.
    // NOTE: no default text-align here — the corner cell is explicitly text-left
    // (it labels the row axis, aligning with the left-aligned row headers) while
    // the data-column headers are explicitly text-center (aligning with the
    // centered cell content). Baking text-left into the shared class let it win
    // over a later text-center via Tailwind's source order, so the data headers
    // rendered left-aligned despite the text-center utility.
    // NOTE: no z-index here either — it's owned per cell so the top-left CORNER
    // can sit ABOVE the data headers. The corner is sticky on BOTH axes
    // (top-0 + left-0) at sticky-z plus one; the data headers sit at base
    // sticky-z. Baking the sticky z utility into the shared class gave the
    // corner two equal-specificity z classes, so on mobile the data headers
    // (painted later in the DOM) scrolled OVER the sticky corner — the
    // first-column header "Cohort" vanished while the body first column
    // stayed (the first-column label scrolled away on mobile). Spelled WITHOUT
    // the bracket shorthand: Tailwind's scanner reads comments, and the
    // class-shaped shorthand minted a phantom utility in the sample build
    // (caught by the drift reverse-diff).
    $headCell = 'sticky top-0 bg-[var(--color-wk-bg-elevated)] px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text-muted)] whitespace-nowrap border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)]';
    // border-b continues the row separator THROUGH the frozen first column so a
    // row label visually connects to its row (otherwise the label column had no
    // horizontal rules and you couldn't tell which label belonged to which row).
    $rowHead = 'sticky left-0 z-[var(--z-wk-sticky)] bg-[var(--color-wk-bg-elevated)] px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)] text-left whitespace-nowrap border-r-[length:var(--border-wk-width)] border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)]';
    $cellBox = 'px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] text-center border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)]';

    // A focusable interactive cell button (tristate / toggle).
    $cellButton = 'inline-flex items-center justify-center min-w-[var(--size-wk-sm)] h-[var(--size-wk-sm)] rounded-[var(--radius-wk-md)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] transition-colors '.($isEditable ? 'cursor-pointer hover:bg-[var(--color-wk-bg-muted)]' : 'cursor-default');

    // ── The strings that reach a reader from JAVASCRIPT ───────────────────
    //
    // Translated HERE and handed to the factory. A literal inside an Alpine
    // plugin passes through no `__()`, so it is localizable by NO ONE — not
    // even by publishing the views, which is the escape hatch the rest of this
    // package leans on. `stream` records the same class as a fixed bug, and the
    // toggle cell below already takes exactly this route for On / Off.
    //
    // For the tristate cell the word IS the state: its three SVGs are
    // `aria-hidden`, so `tristateLabel()` is the ONLY channel a screen reader
    // has, and it used to answer in English whatever the application's locale.
    $stateLabels = \Pushery\WireKit\Support\AlpinePayload::from([
        'allow' => __('wirekit::Allowed'),
        'deny' => __('wirekit::Denied'),
        'inherit' => __('wirekit::Inherited'),
    ]);

    // The unsaved-changes counter is a COUNT that only exists in the browser, so
    // the plural form has to be chosen there — the server renders every form and
    // `Intl.PluralRules` picks. It read `N unsaved change(s)`, and the
    // parenthesized `(s)` is an English shorthand no locale can express: German
    // inflects the adjective, Polish has three categories, and none of them has
    // a form that is the singular with a letter stuck on the end.
    $changePhrases = \Pushery\WireKit\Support\AlpinePayload::from(
        \Pushery\WireKit\Support\PluralPhrases::from('wirekit::{1} :count unsaved change|[2,*] :count unsaved changes')
    );

    // BCP-47 for `Intl.PluralRules` — the APPLICATION's locale, not the browser's.
    $pluralLocale = \Pushery\WireKit\Support\AlpinePayload::from(str_replace('_', '-', app()->getLocale()));
@endphp

<div
    {{ $attributes->except(['id', 'name', 'class'])->whereDoesntStartWith('wire:model') }}
    id="{{ $id }}"
    x-data="wirekitStatusMatrix({ cells: {{ \Pushery\WireKit\Support\AlpinePayload::from($flatCells) }}, cellType: {{ \Pushery\WireKit\Support\AlpinePayload::string($cellType) }}, editable: {{ $isEditable ? 'true' : 'false' }}, rowCount: {{ count($rowList) }}, colCount: {{ count($colList) }}, heatMin: {{ (float) $heatMin }}, heatMax: {{ (float) $heatMax }}, stateLabels: {{ $stateLabels }}, changePhrases: {{ $changePhrases }}, locale: {{ $pluralLocale }} })"
    {{ $attributes->only('class')->class([$base]) }}
>
    @if($isEditable)
        {{-- JSON bridge for wire:model / form submission of the edited cell map. --}}
        {{-- Static value as well as the bound one: the field is empty until Alpine
             boots, and a form submitted in that window sends nothing while the
             visible control already shows the value. The serialization matches
             what the factory's own getter produces from the same data. --}}
        <input type="hidden" x-ref="model" @if($name) name="{{ $name }}" @endif {{ $attributes->whereStartsWith('wire:model') }} value="{{ json_encode((object) $flatCells, JSON_THROW_ON_ERROR) }}" :value="cellsJson()" />
    @endif

    {{-- Scroll region — keyboard-reachable per WCAG 2.1.1 (tabindex + focus ring, both
         unconditional). It becomes a LANDMARK only when the caller named the matrix; the inner
         table is the role=grid composite and keeps its name either way. --}}
    <div
        @if(filled($ariaLabel)) role="region" aria-label="{{ $ariaLabel }}" @endif
        tabindex="0"
        class="w-full overflow-x-auto wk-scrollbar rounded-[var(--radius-wk-lg)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
    >
        {{-- The role is a promise of a keyboard model — see `$isCompositeGrid` above.
             Named either way: `aria-label` names a plain <table> exactly as it named
             the grid, so nothing is lost by dropping to the native semantics. --}}
        @if($rowList === [] || $colList === [])
            {{-- The empty state REPLACES the table. A `<table>` with a header row and no body
                 rows reads as a broken render rather than as "nothing to show" — and the
                 composite-grid role is already switched off for this case a hundred lines up,
                 which is the same judgment one step earlier. The `empty` slot replaces the
                 sentence; see data-table for why it replaces rather than accompanies it. --}}
            <div class="flex flex-col items-center justify-center gap-1 px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-xl)] text-center">
                @isset($empty)
                    {{ $empty }}
                @else
                    <p data-wk-prose-skip class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $emptyText }}</p>
                @endisset
            </div>
        @else
        <table data-wk-prose-skip @if($isCompositeGrid) role="grid" @endif class="w-full border-collapse" aria-label="{{ $ariaLabelResolved }}">
            <thead>
                <tr>
                    {{-- Top-left corner: the row-axis label — left-aligned to match
                         the row headers below it. --}}
                    <th data-wk-prose-skip scope="col" class="{{ $headCell }} text-left left-0 z-[calc(var(--z-wk-sticky)+1)]">{{ $cornerLabel }}</th>
                    @foreach($colList as $col)
                        <th data-wk-prose-skip scope="col" class="{{ $headCell }} text-center z-[var(--z-wk-sticky)]">{{ $col['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($rowList as $ri => $row)
                    <tr>
                        <th data-wk-prose-skip scope="row" class="{{ $rowHead }}">{{ $row['label'] }}</th>
                        @foreach($colList as $ci => $col)
                            @php
                                $rk = $row['key'];
                                $ck = $col['key'];
                                $val = $cellAt($rk, $ck);
                                // Single tab stop into the grid (roving entry); arrows navigate.
                                $tabindex = ($ri === 0 && $ci === 0) ? '0' : '-1';
                            @endphp
                            {{-- `gridcell` only inside a grid: the role is defined as a cell
                                 of one, and a <td> outside a grid is already a cell. --}}
                            <td data-wk-prose-skip @if($isCompositeGrid) role="gridcell" @endif class="{{ $cellBox }}">
                                @switch($cellType)
                                    @case('tristate')
                                        <button
                                            type="button"
                                            data-r="{{ $ri }}" data-c="{{ $ci }}"
                                            tabindex="{{ $tabindex }}"
                                            {{-- Navigation is unconditional and activation is not. A read-only
                                                 matrix still puts a focusable cell inside a role="grid", and a
                                                 grid whose arrows do nothing is a dead tab stop that promises
                                                 movement. `aria-disabled` rather than `disabled`: the cell must
                                                 stay focusable to be read and navigated from, which a disabled
                                                 button is not. --}}
                                            @keydown="moveFocus($event, {{ $ri }}, {{ $ci }})"
                                            @unless($isEditable) aria-disabled="true" @endunless
                                            @if($isEditable)
                                                @click="activate({{ \Pushery\WireKit\Support\AlpinePayload::string($rk) }}, {{ \Pushery\WireKit\Support\AlpinePayload::string($ck) }})"
                                                @keydown.enter.prevent="activate({{ \Pushery\WireKit\Support\AlpinePayload::string($rk) }}, {{ \Pushery\WireKit\Support\AlpinePayload::string($ck) }})"
                                                @keydown.space.prevent="activate({{ \Pushery\WireKit\Support\AlpinePayload::string($rk) }}, {{ \Pushery\WireKit\Support\AlpinePayload::string($ck) }})"
                                            @endif
                                            :aria-label="{{ \Pushery\WireKit\Support\AlpinePayload::string($row['label'].', '.$col['label'].': ') }} + tristateLabel({{ \Pushery\WireKit\Support\AlpinePayload::string($rk) }}, {{ \Pushery\WireKit\Support\AlpinePayload::string($ck) }})"
                                            :class="isChanged({{ \Pushery\WireKit\Support\AlpinePayload::string($rk) }}, {{ \Pushery\WireKit\Support\AlpinePayload::string($ck) }}) ? 'ring-[length:var(--ring-wk-width)] ring-[var(--color-wk-warning)]' : ''"
                                            class="{{ $cellButton }}"
                                        >
                                            {{-- Shape differentiates the three states (colorblind-safe);
                                                 color is redundant reinforcement; the text state lives
                                                 in aria-label. --}}
                                            <svg x-show="tristateValue({{ \Pushery\WireKit\Support\AlpinePayload::string($rk) }}, {{ \Pushery\WireKit\Support\AlpinePayload::string($ck) }}) === 'allow'" x-cloak aria-hidden="true" class="h-4 w-4 text-[color:var(--color-wk-success)]" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5l3.5 3.5 6.5-7"/></svg>
                                            <svg x-show="tristateValue({{ \Pushery\WireKit\Support\AlpinePayload::string($rk) }}, {{ \Pushery\WireKit\Support\AlpinePayload::string($ck) }}) === 'deny'" x-cloak aria-hidden="true" class="h-4 w-4 text-[color:var(--color-wk-danger)]" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                                            <svg x-show="tristateValue({{ \Pushery\WireKit\Support\AlpinePayload::string($rk) }}, {{ \Pushery\WireKit\Support\AlpinePayload::string($ck) }}) === 'inherit'" x-cloak aria-hidden="true" class="h-4 w-4 text-[color:var(--color-wk-text-subtle)]" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M4 8h8"/></svg>
                                        </button>
                                        @break

                                    @case('toggle')
                                        <button
                                            type="button"
                                            data-r="{{ $ri }}" data-c="{{ $ci }}"
                                            tabindex="{{ $tabindex }}"
                                            role="switch"
                                            :aria-checked="toggleOn({{ \Pushery\WireKit\Support\AlpinePayload::string($rk) }}, {{ \Pushery\WireKit\Support\AlpinePayload::string($ck) }}) ? 'true' : 'false'"
                                            {{-- See the tristate cell above: navigation is unconditional, and a
                                                 non-editable switch says so instead of accepting an Enter that
                                                 changes nothing. --}}
                                            @keydown="moveFocus($event, {{ $ri }}, {{ $ci }})"
                                            @unless($isEditable) aria-disabled="true" @endunless
                                            @if($isEditable)
                                                @click="toggleCell({{ \Pushery\WireKit\Support\AlpinePayload::string($rk) }}, {{ \Pushery\WireKit\Support\AlpinePayload::string($ck) }})"
                                                @keydown.enter.prevent="toggleCell({{ \Pushery\WireKit\Support\AlpinePayload::string($rk) }}, {{ \Pushery\WireKit\Support\AlpinePayload::string($ck) }})"
                                                @keydown.space.prevent="toggleCell({{ \Pushery\WireKit\Support\AlpinePayload::string($rk) }}, {{ \Pushery\WireKit\Support\AlpinePayload::string($ck) }})"
                                            @endif
                                            :aria-label="{{ \Pushery\WireKit\Support\AlpinePayload::from($row['label'].', '.$col['label'].': ') }} + (toggleOn({{ \Pushery\WireKit\Support\AlpinePayload::string($rk) }}, {{ \Pushery\WireKit\Support\AlpinePayload::string($ck) }}) ? {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::On')) }} : {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Off')) }})"
                                            class="{{ $cellButton }}"
                                        >
                                            <span x-show="toggleOn({{ \Pushery\WireKit\Support\AlpinePayload::string($rk) }}, {{ \Pushery\WireKit\Support\AlpinePayload::string($ck) }})" x-cloak class="h-2.5 w-2.5 rounded-full bg-[var(--color-wk-success)]"></span>
                                            <span x-show="!toggleOn({{ \Pushery\WireKit\Support\AlpinePayload::string($rk) }}, {{ \Pushery\WireKit\Support\AlpinePayload::string($ck) }})" x-cloak class="h-2.5 w-2.5 rounded-full border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]"></span>
                                        </button>
                                        @break

                                    @case('heat')
                                        @php $ratio = $heatRatio($val); @endphp
                                        <div
                                            class="flex items-center justify-center rounded-[var(--radius-wk-sm)] px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)]"
                                            style="background: color-mix(in oklch, {{ $heatFrom }}, {{ $heatTo }} {{ round($ratio * 100) }}%);"
                                        >
                                            {{-- The tile fill is a true cold→hot ramp: it interpolates heatFrom
                                                 (amber, low) → heatTo (red, high) by the cell's normalized value, so
                                                 the grid reads as actual heat rather than an achromatic fade. The value
                                                 rides in a contrast-guaranteed chip (--color-wk-text on
                                                 --color-wk-bg-elevated is the canonical ~18:1 body pairing in BOTH
                                                 themes), decoupling label legibility from the saturated tile — so the
                                                 number stays AA-legible at every point on the ramp and the grid works
                                                 for colorblind readers (the value is always printed). --}}
                                            <span class="inline-flex items-center justify-center rounded-[var(--radius-wk-sm)] bg-[var(--color-wk-bg-elevated)] px-[var(--padding-wk-x-xs)] py-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text)] tabular-nums">{{ $val !== null ? $val.$heatUnit : '—' }}</span>
                                        </div>
                                        @break

                                    @default
                                        {{-- status --}}
                                        @if($val !== null && $val !== '')
                                            <x-wirekit::badge :intent="$statusIntent($val)" size="sm">{{ $val }}</x-wirekit::badge>
                                        @else
                                            <span class="text-[color:var(--color-wk-text-subtle)]">—</span>
                                        @endif
                                @endswitch
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- Legend — names the cell encoding so it's never color-only. --}}
    @if($legend)
        <div class="mt-2 flex flex-wrap items-center gap-[var(--space-wk-md)] text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">
            @switch($cellType)
                @case('tristate')
                    <span class="inline-flex items-center gap-1"><svg aria-hidden="true" class="h-3.5 w-3.5 text-[color:var(--color-wk-success)]" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5l3.5 3.5 6.5-7"/></svg> {{ __('wirekit::Allowed') }}</span>
                    <span class="inline-flex items-center gap-1"><svg aria-hidden="true" class="h-3.5 w-3.5 text-[color:var(--color-wk-danger)]" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M4 4l8 8M12 4l-8 8"/></svg> {{ __('wirekit::Denied') }}</span>
                    <span class="inline-flex items-center gap-1"><svg aria-hidden="true" class="h-3.5 w-3.5 text-[color:var(--color-wk-text-subtle)]" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M4 8h8"/></svg> {{ __('wirekit::Inherited') }}</span>
                    @break
                @case('heat')
                    <span>{{ $heatMin }}{{ $heatUnit }}</span>
                    <span class="inline-block h-2.5 w-24 rounded-[var(--radius-wk-full)]" style="background: linear-gradient(to right, {{ $heatFrom }}, {{ $heatTo }});" aria-hidden="true"></span>
                    <span>{{ $heatMax }}{{ $heatUnit }}</span>
                    @break
                @case('toggle')
                    <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-[var(--color-wk-success)]"></span> {{ __('wirekit::On') }}</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]"></span> {{ __('wirekit::Off') }}</span>
                    @break
            @endswitch
            @if($isEditable && $cellType === 'tristate')
                {{-- One node, one string: the count and its noun are ONE sentence, and a
                     sentence assembled around a `<span>` in the template fixes English word
                     order for every language. `changedLabel` picks the translated form in the
                     browser, where the count lives. --}}
                {{-- A live region, because this number is the one piece of state the matrix holds that a
                     reader cannot reconstruct from anywhere else on the page. It changed on screen
                     and was announced to nobody. `polite`, not `assertive`: it follows an edit the
                     reader just made, so it belongs after whatever the edit itself said. --}}
                <span x-show="changedCount > 0" x-cloak role="status" aria-live="polite" x-text="changedLabel" class="text-[color:var(--color-wk-warning-text)]"></span>
            @endif
        </div>
    @endif
</div>
