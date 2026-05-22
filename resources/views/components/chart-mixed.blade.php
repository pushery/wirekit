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
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

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
    // normalizer untouched (existing array_diff_key passthrough behaviour
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
@endphp

<div {{ $attributes->class([$rootClass]) }} style="width: 100%; min-width: 0; display: block;">
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
    />
</div>
