@props([
    'data' => [],
    'trend' => null,
    'inline' => false,
    'height' => null,
    'scope' => null,
    // Per-instance chart-library override pass-through. See the `library`
    // prop on src/Components/Chart.php for full semantics. Without explicit
    // declaration here the prop would NOT reach the inner chart — sparkline
    // is an anonymous Blade wrapper, so attribute-bag flow is opt-in per prop.
    'library' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Sparkline — small, axis-less line/area chart for inline KPI strips and
    // dashboard cells. Delegates to <x-wirekit-chart type="sparkline"> so both
    // adapters render correctly:
    //   - ApexCharts: native sparkline mode (chart.sparkline.enabled = true)
    //   - Chart.js: falls back to a plain line; no axis chrome to begin with
    //
    // Two modes — inline (renders at surrounding text height for KPI ribbons)
    // and block (default, renders at $height — defaults to 2.5rem for compact
    // dashboard cells).

    // Trend resolution — auto compares first vs last data point and tints
    // green (up), red (down), or neutral (flat). Manual override via prop;
    // invalid prop values fall through to auto-detection.
    $resolveTrend = static function ($trendProp, array $data): string {
        // Explicit override — only when the prop is one of the canonical values.
        if (in_array($trendProp, ['up', 'down', 'neutral'], true)) {
            return $trendProp;
        }
        // Anything else (null, 'auto', invalid string) → auto-detect.
        if (count($data) < 2) {
            return 'neutral';
        }
        $first = (float) $data[0];
        $last = (float) $data[count($data) - 1];
        if ($last > $first) {
            return 'up';
        }
        if ($last < $first) {
            return 'down';
        }

        return 'neutral';
    };

    $resolvedTrend = $resolveTrend($trend, $data);

    // Trend-aware colour token. WireKit theming consumes these via the
    // chart adapter's normal palette; we pre-resolve at the Blade level so
    // both Chart.js and ApexCharts get a consistent series colour.
    $trendColor = match ($resolvedTrend) {
        'up' => 'var(--color-wk-success)',
        'down' => 'var(--color-wk-danger)',
        default => 'var(--color-wk-text-muted)',
    };

    $resolvedHeight = $height ?? ($inline ? '1.25em' : '2.5rem');

    // Pass-through to <x-wirekit-chart>. Sparkline type is in both adapters'
    // supportedTypes(); the underlying line chart is rendered with no axes,
    // no grid, no legend.
    $sparkDatasets = [
        [
            'data' => array_map(static fn ($v) => (float) $v, $data),
            'color' => $trendColor,
        ],
    ];

    // Adapter-specific options. ApexCharts has a built-in sparkline mode that
    // strips axes + grid; Chart.js gets the same effect via explicit option
    // overrides. Both arrive via the merged options bag.
    //
    // Marker / point sizing — sparklines read as a thin trend trace; the dots
    // at every data point dominate the visual when the chart is only
    // 1.25em × 4rem (inline mode) or 2.5rem tall (block mode). Both adapters
    // get marker size 0 (line-only) so the rendered output is a clean line.
    // Hover-state markers are kept tiny so developers who add a tooltip slot
    // still see a focus indicator at the active point.
    $sparkOptions = [
        // ApexCharts native sparkline mode + marker stripping.
        // `chart.sparkline.enabled: true` is the documented switch but it
        // doesn't always strip every chrome layer when WireKit's themer
        // injects axis-label styling later — explicit `show: false` on
        // grid + legend + axes is belt-and-suspenders so the rendered
        // SVG is genuinely just the line in every code path.
        'chart' => ['sparkline' => ['enabled' => true]],
        'grid' => ['show' => false, 'padding' => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0]],
        'legend' => ['show' => false],
        'xaxis' => [
            'labels' => ['show' => false],
            'axisBorder' => ['show' => false],
            'axisTicks' => ['show' => false],
        ],
        'yaxis' => [
            'labels' => ['show' => false],
            'axisBorder' => ['show' => false],
            'axisTicks' => ['show' => false],
        ],
        'tooltip' => ['enabled' => false],
        'dataLabels' => ['enabled' => false],
        'markers' => [
            'size' => 0,
            'hover' => ['size' => 2],
        ],
        'stroke' => [
            'curve' => 'smooth',
            'width' => 1.5,
        ],
        // Chart.js axis-stripping + point-radius stripping
        'plugins' => [
            'legend' => ['display' => false],
            'tooltip' => ['enabled' => false],
        ],
        'scales' => [
            'x' => ['display' => false],
            'y' => ['display' => false],
        ],
        'elements' => [
            'point' => [
                'radius' => 0,
                'hoverRadius' => 2,
            ],
            'line' => [
                'borderWidth' => 1.5,
                'tension' => 0.35,
            ],
        ],
    ];

    $rootClass = WireKit::resolveClasses(
        'sparkline',
        'base',
        $inline ? 'wk-sparkline inline-block align-middle' : 'wk-sparkline block',
        $scope,
    );

    // Inline-mode `display` MUST be set via inline style, NOT just the Tailwind
    // class. The Tailwind `inline-block` utility only applies when Tailwind has
    // generated a corresponding rule for it (i.e. the developer's compiled CSS
    // is in scope). docs.wirekit.app sandbox previews render WITHOUT developer Tailwind,
    // so an inline-sparkline that relies on `inline-block` alone falls back to
    // `<div>`'s default `display: block` — taking a full line and breaking the
    // surrounding paragraph's flow. /components/sparkline#inline-mode surfaced
    // this: each sparkline sat on its own line instead of nestling between
    // words in the running prose.
    //
    // The block-mode counterpart doesn't need the same treatment — `<div>` is
    // already `display: block` by default, so omitting the inline-style for
    // that branch is intentional. Same reasoning as the chart-wrapper width
    // fix in commit 669d978: utility classes for decoration, inline style for
    // load-bearing layout primitives.
    // Block-mode wrapper carries `min-width: 0; overflow: hidden` so the
    // sparkline never imposes the ApexCharts canvas's fixed intrinsic width
    // (~300px) as the min-content floor of a surrounding CSS-grid track. Without
    // it, a `repeat(2, 1fr)` KPI grid floors each column at the chart's 300px
    // intrinsic width and clips the right-hand cells off-screen on narrow
    // viewports (the inner <x-wirekit-chart> wrapper already does this, but the
    // sparkline's own outer wrapper sat between the grid track and that
    // protection and re-leaked the intrinsic width). Inline mode keeps its fixed
    // 4rem width — it lives in running prose, never in a grid track.
    $displayStyle = $inline
        ? 'display: inline-block; vertical-align: middle; '
        : 'min-width: 0; overflow: hidden; ';
@endphp

{{--
    Sparkline outer wrapper must be `<span>` (phrasing content) in inline mode
    so HTML5's parser doesn't auto-close a surrounding `<p>` on first descendant
    div. Block mode stays `<div>` for the default dashboard-cell use case.
--}}
@php $sparklineTag = $inline ? 'span' : 'div'; @endphp
<{{ $sparklineTag }}
    {{ $attributes->class([$rootClass]) }}
    style="{{ $displayStyle }}height: {{ $resolvedHeight }}; {{ $inline ? 'width: 4rem;' : 'width: 100%;' }}"
    data-trend="{{ $resolvedTrend }}"
>
    <x-wirekit-chart
        type="sparkline"
        :labels="array_fill(0, count($data), '')"
        :datasets="$sparkDatasets"
        :options="$sparkOptions"
        :height="$resolvedHeight"
        :inline="$inline"
        :library="$library"
    />
</{{ $sparklineTag }}>
