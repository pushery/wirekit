{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
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
    // The tooltip's value format. Not declared, they stayed in the bag and reached the wrapper as
    // attributes of a `div`, while the chart's tooltip printed every value raw.
    'valueDecimals' => null,
    'valuePrefix' => null,
    'valueSuffix' => null,
    // Whether the values group their thousands; null leaves the chart's default, which groups.
    'valueGrouping' => null,
    // Further values the tooltip names for each point, the receipts beside a day's revenue.
    'tooltipExtras' => [],
    // The colors the series take, `semantic` or `categorical`; null leaves it to the chart's
    // config default, as on the chart itself.
    'palette' => null,
    // The data as a table under the chart, `disclosure` or `screen-reader`, as on the chart itself.
    'table' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('chart-mixed', $attributes->getAttributes());

    // The decimals reach the Chart class's constructor as a whole number or nothing: a NAN handed
    // on as it came is coerced to a string there, which PHP 8.5 warns about. Chart keeps the
    // number within 0 to 100.
    $valueDecimals = \Pushery\WireKit\Support\NumericProp::intOrNull($valueDecimals, 'chart-mixed', 'valueDecimals');

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $inline = BooleanProp::from($inline, false, 'chart-mixed', 'inline');
    $replayable = BooleanProp::from($replayable, false, 'chart-mixed', 'replayable');

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
    // via mapType('mixed')). Throws on entirely-malformed input. The datasets, and the data of
    // each, usually arrive as Collections; read as arrays first, a Collection is no malformed input.
    $datasets = \Pushery\WireKit\Components\Chart::datasetsFrom($datasets);
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

    // A caller's accessible name has to reach the inner chart, because that is
    // the element carrying `role="img"`. On this wrapper it would sit on a role-less
    // `<div>` — ARIA `generic`, where naming is prohibited (axe
    // `aria-prohibited-attr`) — so assistive technology would drop it and the chart
    // would announce its own generic fallback instead; a chart painted to a canvas
    // has nothing else to say. And chart.blade.php's debug warning gates on
    // `! $attributes->has('aria-label')`, so a label left on the wrapper would also
    // be reported as missing.
    //
    // Same remedy as `sparkline.blade.php`. Those two are the only views in the
    // package that wrap the class-based chart rather than being it, so they are the
    // only ones that could swallow its name.
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

    // An inline display wins over every class of the caller's, `hidden` included: where the
    // caller's class reaches for one, it is left out here and comes back as its utility.
    [$mixedStyle, $mixedStyleClasses] = \Pushery\WireKit\Support\UtilityClasses::yieldStyleToCaller($attributes, 'width: 100%; min-width: 0; display: block;');

    // One value for the style, so a `;` in it carries no declaration of its own (Support\CssValue).
    $height = \Pushery\WireKit\Support\CssValue::prop($height, 'chart-mixed', 'height', '380px');
@endphp

<div {{ $mixedAttributes->merge(['style' => $mixedStyle])->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$rootClass, $mixedStyleClasses => $mixedStyleClasses !== ''])) }} >
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
        :valueDecimals="$valueDecimals"
        :valuePrefix="$valuePrefix"
        :valueSuffix="$valueSuffix"
        :valueGrouping="$valueGrouping"
        :tooltipExtras="$tooltipExtras"
        :palette="$palette"
        :table="$table"
        :attributes="$chartAriaAttributes"
    />
</div>
