@props([
    'rows' => [],                   // [{key,label}] — the matrix rows (left axis)
    'columns' => [],                // [{key,label}] — the matrix columns (top axis)
    'cells' => [],                  // value map: ["rowKey:colKey" => value] OR [rowKey => [colKey => value]]
    'cellType' => config('wirekit.components.status-matrix.cell-type', 'status'), // tristate | toggle | status | heat
    'editable' => false,            // tristate / toggle become interactive
    'cornerLabel' => '',            // top-left header cell label (the row-axis name)
    'ariaLabel' => __('Status matrix'), // accessible name for the grid
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
    use Pushery\WireKit\WireKit;
    use Illuminate\Support\Str;

    $cellType = WireKit::validateProp('status-matrix', 'cellType', $cellType, ['tristate', 'toggle', 'status', 'heat']);
    $isEditable = filter_var($editable, FILTER_VALIDATE_BOOLEAN) && in_array($cellType, ['tristate', 'toggle'], true);

    $id = $attributes->get('id', 'status-matrix-'.Str::random(6));
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
    $cellButton = 'inline-flex items-center justify-center min-w-[var(--size-wk-sm)] h-[var(--size-wk-sm)] rounded-[var(--radius-wk-md)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] transition-colors '.($isEditable ? 'cursor-pointer hover:bg-[var(--color-wk-bg-muted)]' : 'cursor-default');
@endphp

<div
    {{ $attributes->except(['id', 'name', 'class'])->whereDoesntStartWith('wire:model') }}
    id="{{ $id }}"
    x-data="wirekitStatusMatrix({ cells: @js($flatCells), cellType: '{{ $cellType }}', editable: {{ $isEditable ? 'true' : 'false' }}, rowCount: {{ count($rowList) }}, colCount: {{ count($colList) }}, heatMin: {{ (float) $heatMin }}, heatMax: {{ (float) $heatMax }} })"
    {{ $attributes->only('class')->class([$base]) }}
>
    @if($isEditable)
        {{-- JSON bridge for wire:model / form submission of the edited cell map. --}}
        <input type="hidden" x-ref="model" @if($name) name="{{ $name }}" @endif {{ $attributes->whereStartsWith('wire:model') }} :value="JSON.stringify(cells)" />
    @endif

    {{-- Scroll region — keyboard-reachable per WCAG 2.1.1 (region + tabindex +
         label + focus ring). The inner table is the role=grid composite. --}}
    <div
        role="region"
        aria-label="{{ $ariaLabel }}"
        tabindex="0"
        class="w-full overflow-x-auto wk-scrollbar rounded-[var(--radius-wk-lg)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
    >
        <table role="grid" class="w-full border-collapse" aria-label="{{ $ariaLabel }}">
            <thead>
                <tr>
                    {{-- Top-left corner: the row-axis label — left-aligned to match
                         the row headers below it. --}}
                    <th scope="col" class="{{ $headCell }} text-left left-0 z-[calc(var(--z-wk-sticky)+1)]">{{ $cornerLabel }}</th>
                    @foreach($colList as $col)
                        <th scope="col" class="{{ $headCell }} text-center z-[var(--z-wk-sticky)]">{{ $col['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($rowList as $ri => $row)
                    <tr>
                        <th scope="row" class="{{ $rowHead }}">{{ $row['label'] }}</th>
                        @foreach($colList as $ci => $col)
                            @php
                                $rk = $row['key'];
                                $ck = $col['key'];
                                $val = $cellAt($rk, $ck);
                                // Single tab stop into the grid (roving entry); arrows navigate.
                                $tabindex = ($ri === 0 && $ci === 0) ? '0' : '-1';
                            @endphp
                            <td role="gridcell" class="{{ $cellBox }}">
                                @switch($cellType)
                                    @case('tristate')
                                        <button
                                            type="button"
                                            data-r="{{ $ri }}" data-c="{{ $ci }}"
                                            tabindex="{{ $tabindex }}"
                                            @if($isEditable)
                                                @click="activate('{{ $rk }}', '{{ $ck }}')"
                                                @keydown="moveFocus($event, {{ $ri }}, {{ $ci }})"
                                                @keydown.enter.prevent="activate('{{ $rk }}', '{{ $ck }}')"
                                                @keydown.space.prevent="activate('{{ $rk }}', '{{ $ck }}')"
                                            @endif
                                            :aria-label="'{{ $row['label'] }}, {{ $col['label'] }}: ' + tristateLabel('{{ $rk }}', '{{ $ck }}')"
                                            :class="isChanged('{{ $rk }}', '{{ $ck }}') ? 'ring-[length:var(--ring-wk-width)] ring-[var(--color-wk-warning)]' : ''"
                                            class="{{ $cellButton }}"
                                        >
                                            {{-- Shape differentiates the three states (colorblind-safe);
                                                 color is redundant reinforcement; the text state lives
                                                 in aria-label. --}}
                                            <svg x-show="tristateValue('{{ $rk }}', '{{ $ck }}') === 'allow'" x-cloak aria-hidden="true" class="h-4 w-4 text-[color:var(--color-wk-success)]" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5l3.5 3.5 6.5-7"/></svg>
                                            <svg x-show="tristateValue('{{ $rk }}', '{{ $ck }}') === 'deny'" x-cloak aria-hidden="true" class="h-4 w-4 text-[color:var(--color-wk-danger)]" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                                            <svg x-show="tristateValue('{{ $rk }}', '{{ $ck }}') === 'inherit'" x-cloak aria-hidden="true" class="h-4 w-4 text-[color:var(--color-wk-text-subtle)]" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M4 8h8"/></svg>
                                        </button>
                                        @break

                                    @case('toggle')
                                        <button
                                            type="button"
                                            data-r="{{ $ri }}" data-c="{{ $ci }}"
                                            tabindex="{{ $tabindex }}"
                                            role="switch"
                                            :aria-checked="toggleOn('{{ $rk }}', '{{ $ck }}') ? 'true' : 'false'"
                                            @if($isEditable)
                                                @click="toggleCell('{{ $rk }}', '{{ $ck }}')"
                                                @keydown="moveFocus($event, {{ $ri }}, {{ $ci }})"
                                                @keydown.enter.prevent="toggleCell('{{ $rk }}', '{{ $ck }}')"
                                                @keydown.space.prevent="toggleCell('{{ $rk }}', '{{ $ck }}')"
                                            @endif
                                            :aria-label="'{{ $row['label'] }}, {{ $col['label'] }}: ' + (toggleOn('{{ $rk }}', '{{ $ck }}') ? 'On' : 'Off')"
                                            class="{{ $cellButton }}"
                                        >
                                            <span x-show="toggleOn('{{ $rk }}', '{{ $ck }}')" x-cloak class="h-2.5 w-2.5 rounded-full bg-[var(--color-wk-success)]"></span>
                                            <span x-show="!toggleOn('{{ $rk }}', '{{ $ck }}')" x-cloak class="h-2.5 w-2.5 rounded-full border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]"></span>
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
    </div>

    {{-- Legend — names the cell encoding so it's never color-only. --}}
    @if($legend)
        <div class="mt-2 flex flex-wrap items-center gap-[var(--space-wk-md)] text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">
            @switch($cellType)
                @case('tristate')
                    <span class="inline-flex items-center gap-1"><svg aria-hidden="true" class="h-3.5 w-3.5 text-[color:var(--color-wk-success)]" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5l3.5 3.5 6.5-7"/></svg> Allowed</span>
                    <span class="inline-flex items-center gap-1"><svg aria-hidden="true" class="h-3.5 w-3.5 text-[color:var(--color-wk-danger)]" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M4 4l8 8M12 4l-8 8"/></svg> Denied</span>
                    <span class="inline-flex items-center gap-1"><svg aria-hidden="true" class="h-3.5 w-3.5 text-[color:var(--color-wk-text-subtle)]" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M4 8h8"/></svg> Inherited</span>
                    @break
                @case('heat')
                    <span>{{ $heatMin }}{{ $heatUnit }}</span>
                    <span class="inline-block h-2.5 w-24 rounded-[var(--radius-wk-full)]" style="background: linear-gradient(to right, {{ $heatFrom }}, {{ $heatTo }});" aria-hidden="true"></span>
                    <span>{{ $heatMax }}{{ $heatUnit }}</span>
                    @break
                @case('toggle')
                    <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-[var(--color-wk-success)]"></span> On</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]"></span> Off</span>
                    @break
            @endswitch
            @if($isEditable && $cellType === 'tristate')
                <span x-show="changedCount > 0" x-cloak class="text-[color:var(--color-wk-warning-text)]"><span x-text="changedCount"></span> unsaved change(s)</span>
            @endif
        </div>
    @endif
</div>
