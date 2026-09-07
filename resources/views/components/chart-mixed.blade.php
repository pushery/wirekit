{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'labels' => [],
    'datasets' => [],
    'options' => [],
    'height' => '380px',
    // Pass-through props that flow into the underlying class-based
    // chart delegate. Without these declared here, attribute-bag
    // would NOT carry them onto the inner chart — the chart-mixed wrapper
    // is anonymous Blade, so attributes only flow where explicitly
    // bound. Each prop mirrors the Chart class constructor (src/Components/Chart.php).
    'wireStream' => null,
    'wireStreamMode' => 'strict',
    'wireStreamCap' => 100,
    'annotations' => [],
    'inline' => false,
    'replayable' => false,
    'library' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('chart-mixed', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $inline = BooleanProp::from($inline, false);
    $replayable = BooleanProp::from($replayable, false);

    // Chart-mixed — multi-type / multi-axis dashboard chart. Each dataset
    // carries its OWN `type` (line/bar/area) plus an optional `yAxisID`
    // pointing at a per-axis configuration in $options.
    //
    // Both adapters consume the same per-dataset `type` field:
    //   - Chart.js: native — every dataset can override the chart-level type.
    //   - ApexCharts: maps to series[].type via the adapter normalizer.
    //
    // Multi-axis: when datasets carry yAxisID values like 'y1' / 'y2',
    // the developer-supplied $options must include matching scale entries
    // (Chart.js shape) or yaxis entries (ApexCharts shape). The component
    // does NOT auto-create them — multi-axis configuration is library-
    // specific and benefits from explicit developer control.
    //
    // Delegates to <x-wirekit-chart type="mixed"> which both adapters'
    // supportedTypes() include. Per-dataset type fields pass through the
    // normalizer untouched (existing array_diff_key passthrough behavior
    // in both ChartJsAdapter::normalizeData and ApexChartsAdapter::normalizeData).

    // Validate every dataset has a sensible type entry — empty type falls
    // through to the chart-level default (line for ApexCharts, bar for Chart.js
    // via mapType('mixed')). Throws on entirely-malformed input.
    foreach ($datasets as $i => $dataset) {
        if (! is_array($dataset)) {
            throw new InvalidArgumentException(
                "WireKit chart-mixed: dataset at index {$i} must be an array; got "
                .gettype($dataset)
            );
        }
        if (! isset($dataset['data']) || ! is_array($dataset['data'])) {
            throw new InvalidArgumentException(
                "WireKit chart-mixed: dataset at index {$i} must include a 'data' array."
            );
        }
    }

    $rootClass = WireKit::resolveClasses(
        'chart-mixed',
        'base',
        'wk-chart-mixed',
        $scope,
    );

    // ⚠️ A caller's accessible name has to reach the INNER chart, because that is
    // the element carrying `role="img"`. On this wrapper it sits on a role-less
    // `<div>` — ARIA `generic`, where naming is PROHIBITED (axe
    // `aria-prohibited-attr`) — so assistive technology dropped it and the chart
    // announced its own generic fallback instead. A mixed chart paints to a canvas
    // no reader can inspect, so that one word was the whole of what it said, and
    // `aria-label`, the attribute a developer reaches for to fix exactly that, was
    // the attribute being discarded. Worse for the developer: chart.blade.php's
    // debug warning gates on `! $attributes->has('aria-label')`, which was ALWAYS
    // true here, so the package told them to pass a label they had just passed.
    //
    // Same defect and same remedy as `sparkline.blade.php`. Those two are the whole
    // class: they are the only views in the package that wrap the class-based chart
    // rather than being it, so they are the only ones that can swallow its name.
    //
    // Forwarded as an attribute bag rather than as named props: the chart is a
    // CLASS-based component, so an attribute it does not declare flows into its
    // bag, and a bound `:attributes` is the same path an echoed bag compiles to.
    // Empty values are filtered out so the chart's own `__('wirekit::Chart')`
    // fallback still applies when no name was given — the decision about what an
    // unlabeled chart should announce stays where it is made.
    $chartAriaAttributes = new \Illuminate\View\ComponentAttributeBag(
        array_filter(
            $attributes->only(['aria-label', 'aria-labelledby'])->getAttributes(),
            static fn ($value) => filled($value),
        ),
    );

    // Stripped from the wrapper so the name is not ALSO emitted where it is
    // prohibited; the wrapper keeps every other attribute the caller passed.
    $mixedAttributes = $attributes->except(['aria-label', 'aria-labelledby']);
@endphp

<div {{ $mixedAttributes->merge(['style' => 'width: 100%; min-width: 0; display: block;'])->class([$rootClass]) }} >
    {{--
        Explicit `width: 100%; min-width: 0; display: block;` so the
        wrapper reliably resolves its width inside ANY parent context.
        Without it, when the chart-mixed sits inside a flex / grid /
        iframe-srcdoc container that doesn't propagate intrinsic
        width down to its children, the inner chart's
        `class="relative w-full"` resolves against an ambiguous
        parent width — Chart.js's responsive resizer reads
        `clientWidth` at init time, gets a fraction of the available
        space, and locks the canvas at that narrow width even when
        the surrounding box later expands.
    --}}
    <x-wirekit-chart
        type="mixed"
        :labels="$labels"
        :datasets="$datasets"
        :options="$options"
        :height="$height"
        :wireStream="$wireStream"
        :wireStreamMode="$wireStreamMode"
        :wireStreamCap="$wireStreamCap"
        :annotations="$annotations"
        :inline="$inline"
        :replayable="$replayable"
        :library="$library"
        :attributes="$chartAriaAttributes"
    />
</div>
