{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'label' => null,
    'scope' => null,
])

{{-- Read the parent data-list's `layout`, because the `summary` layout is the
     one arrangement whose grid tracks live on the CONTAINER while the elements
     that occupy them (<dt> and <dd>) are emitted HERE. @aware is Laravel's
     canonical parent→child prop bridge.

     The fallback is this component's own default and mirrors the same config
     key the container reads: @aware only ever sees what was passed to the
     parent as an explicit attribute, never the parent's @props default — so a
     plain <x-wirekit::data-list> with no attributes lands here as `horizontal`
     by way of this line, not by way of the container's. --}}
@aware([
    'layout' => config('wirekit.components.data-list.layout', 'horizontal'),
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('data-list.item', $attributes->getAttributes());

    // `@aware` reads a value from the parent component, but — unlike `@props` —
    // it does NOT remove that key from the attribute bag. So when the key is
    // also written as an attribute on the tag, it survives into
    // `{{ $attributes }}` and renders as a stray HTML attribute on the element.
    $attributes = $attributes->except(['layout']);

    use Pushery\WireKit\WireKit;

    // Each item is a <dt>/<dd> pair displayed side-by-side.
    // Uses inline styles for layout to guarantee rendering in environments
    // where the developer's Tailwind JIT may not see vendor view classes
    // (preview iframes, SSR without a Tailwind build step, embeds).
    // `wk-data-list-item` carries the inter-row separator via a shipped
    // dist/wirekit.css rule (`.wk-data-list-item:not(:last-child)`) rather
    // than an inline border-bottom — so the LAST row omits the rule and no
    // longer doubles against the container's own bottom border. The rest of the layout stays inline for preview-iframe / SSR
    // robustness.
    $itemClasses = WireKit::resolveClasses('data-list', 'item', implode(' ', [
        'wk-data-list-item',
        'py-[var(--padding-wk-y-sm)]',
    ]), $scope);

    // In `summary` the grid tracks (`1fr max-content`) are declared on the <dl>,
    // so the <dt> and <dd> have to BE the grid items. This wrapper drops its own
    // box to let them through. That is also why the summary layout carries no row
    // separator and no item padding: both hang off this box, and a totals block
    // wants neither — its rhythm is the container's row gap, and its one rule
    // belongs above the grand total, not between every line.
    $isSummary = $layout === 'summary';

    // `detail` shares the grid mechanics and NOT the alignment. Both drop this box to
    // `display: contents` so the <dt>/<dd> become the container's own grid items — that is
    // what makes the value column ONE track measured across every row rather than a
    // per-row measurement.
    //
    // What does not carry over is the totals treatment. A summary value is an amount: flush
    // right, tabular figures, so the digits line up under one another. A detail value is
    // prose — "Credit card", "Standard shipping" — and right-aligning it would strand it
    // against the far edge with a gap in the middle of every row. The reporting application
    // described this layout as "literally the swapped column declaration", and the columns
    // are indeed swapped; the alignment has to swap with them.
    $isGridPair = $isSummary || $layout === 'detail';

    $wrapperStyle = $isGridPair
        ? 'display: contents;'
        : 'display: flex; align-items: flex-start; justify-content: space-between; gap: var(--gap-wk-lg, 1rem);';

    // The column is named explicitly rather than left to auto-placement. A row
    // whose <dt> is omitted (no `label`) would otherwise put its <dd> into the
    // FIRST track and shunt every following row one cell out of alignment —
    // silent, and visible only once a real list happens to contain one.
    $labelStyle = $isGridPair
        ? 'grid-column: 1; min-width: 0; overflow-wrap: anywhere;'
        : 'width: 33%; flex-shrink: 1; min-width: 0; overflow-wrap: anywhere;';

    // `font-variant-numeric` is set inline for the same reason the rest of the
    // layout is: the `tabular-nums` utility only exists if the developer's
    // Tailwind build happened to scan this vendor view. It affects digit glyphs
    // only, so a summary row carrying text rather than an amount is unchanged.
    $valueStyle = match (true) {
        $isSummary => 'grid-column: 2; min-width: 0; text-align: right; overflow-wrap: anywhere; '
            .'font-variant-numeric: tabular-nums;',
        $layout === 'detail' => 'grid-column: 2; min-width: 0; overflow-wrap: anywhere;',
        default => 'flex: 1; min-width: 0; text-align: right; overflow-wrap: anywhere;',
    };
@endphp

<div {{ $attributes->merge(['style' => $wrapperStyle])->class([$itemClasses]) }}>
    {{-- Label: the "key" in the key-value pair. min-width: 0 +
         overflow-wrap: anywhere let long single-word labels (e.g.
         "Berufsunfähigkeitsversicherung", "Mietpreisbremse" — German
         compound nouns are common in real-estate / insurance content)
         wrap inside their track on narrow viewports instead of
         bleeding past their cell and pushing the parent's scrollWidth
         past the viewport. --}}
    @if($label)
        <dt style="{{ $labelStyle }}" class="text-[length:var(--text-wk-sm)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text-muted)]">
            {{ $label }}
        </dt>
    @endif

    {{-- Value: the content slot. overflow-wrap: anywhere covers the
         symmetric case where the VALUE is a long single token (URL,
         compound German noun, file path). --}}
    <dd style="{{ $valueStyle }}" class="text-[color:var(--color-wk-text)]">
        {{ $slot }}
    </dd>
</div>
