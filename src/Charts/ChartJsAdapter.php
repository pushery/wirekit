<?php

declare(strict_types=1);

namespace Pushery\WireKit\Charts;

use Pushery\WireKit\Contracts\ChartAdapter;

/**
 * Chart.js adapter — normalizes WireKit data into Chart.js config format.
 *
 * Chart.js is installed by the user via npm (not bundled by WireKit).
 */
final class ChartJsAdapter implements ChartAdapter
{
    public function name(): string
    {
        return 'chartjs';
    }

    /**
     * Chart.js is installed and imported by the user.
     * WireKit does not deliver JS dependencies.
     */
    public function scripts(): array
    {
        return [];
    }

    public function normalizeData(string $type, array $labels, array $datasets): array
    {
        // WireKit's simplified format -> Chart.js format. The mapType()
        // detour handles WireKit-canonical types that Chart.js doesn't have a
        // native equivalent for (e.g. 'sparkline' → 'line' rendered without
        // axis chrome via developer-supplied options).
        //
        // Per-dataset type normalization: chart-mixed lets each dataset carry
        // its own `type` field. ApexCharts accepts `column` (vertical bar)
        // and `area` (filled line) as native per-series types; Chart.js does
        // not — it needs `bar` for both vertical and horizontal bars, and
        // `line` plus `fill: true` for filled area. Map both shapes here so
        // a single chart-mixed config feeds both adapters cleanly.
        $isAreaTopLevel = $type === 'area';
        $normalizedDatasets = [];

        foreach ($datasets as $index => $dataset) {
            $passthrough = array_diff_key($dataset, array_flip(['label', 'data']));

            if (isset($passthrough['type'])) {
                $passthrough['type'] = match ($passthrough['type']) {
                    'column' => 'bar',
                    'area' => 'line',
                    default => $passthrough['type'],
                };

                // Per-dataset 'area' becomes 'line' + fill:true so the
                // visual stays an area even though the controller is line.
                if (($dataset['type'] ?? null) === 'area' && ! isset($passthrough['fill'])) {
                    $passthrough['fill'] = true;
                }
            }

            // Top-level 'area' chart type → fill every dataset (the chart-
            // level type itself is already mapped to 'line' by mapType).
            if ($isAreaTopLevel && ! isset($passthrough['fill'])) {
                $passthrough['fill'] = true;
            }

            $normalizedDatasets[] = array_merge(
                [
                    'label' => $dataset['label'] ?? "Dataset {$index}",
                    'data' => $dataset['data'] ?? [],
                ],
                $passthrough,
            );
        }

        return [
            'type' => $this->mapType($type),
            'data' => [
                'labels' => $labels,
                'datasets' => $normalizedDatasets,
            ],
        ];
    }

    /**
     * WireKit canonical type → Chart.js native type. Sparkline / annotated
     * fall back to line; mixed falls back to bar (the per-dataset `type`
     * field on individual datasets carries the actual rendering shape).
     */
    private function mapType(string $type): string
    {
        // Chart.js has no native 'area' controller — area charts are line
        // charts with dataset.fill = true. We map the type to 'line' here
        // and the Alpine factory's _applyThemeToDatasets sets fill:true on
        // every dataset whenever the chart-level type is 'area' (carried
        // through the rawConfig.data.areaIntent flag set in normalizeData).
        // 'column' maps to 'bar' too (Chart.js bars are vertical by default;
        // ApexCharts splits horizontal/vertical into bar/column).
        return match ($type) {
            'sparkline' => 'line',
            'annotated' => 'line',
            'mixed' => 'bar',
            'area' => 'line',
            'column' => 'bar',
            default => $type,
        };
    }

    public function defaultOptions(string $type): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 16,
                        // Font color and family are set client-side via CSS variables
                    ],
                ],
                // `position: 'nearest'` anchors the tooltip at the data
                // element nearest the cursor, with the default 2 px
                // `caretPadding` between cursor and panel — matches the
                // ApexCharts adapter's `followCursor: true` behaviour
                // so tooltips read uniformly across chart libraries.
                'tooltip' => [
                    'enabled' => true,
                    'mode' => 'index',
                    'intersect' => false,
                    'position' => 'nearest',
                    'caretPadding' => 4,
                ],
            ],
            'scales' => $this->defaultScales($type),
        ];
    }

    public function alpineComponent(): string
    {
        return 'wirekitChartJs';
    }

    public function rendersTo(): string
    {
        return 'canvas';
    }

    public function supportedTypes(): array
    {
        return [
            // Native Chart.js types
            'bar', 'line', 'area', 'pie', 'doughnut', 'radar', 'polarArea', 'scatter', 'bubble',
            // WireKit canonical types that map onto Chart.js natives via mapType()
            'sparkline', // → line (chrome stripped via developer options)
            'annotated', // → line (annotation layer adds via chartjs-plugin-annotation)
            'mixed',     // → bar  (per-dataset type field carries the actual render shape)
        ];
    }

    /**
     * Type-specific scale defaults.
     * Pie/doughnut/radar/polar have no cartesian axes.
     *
     * IMPORTANT: Do NOT include empty 'ticks' or 'grid' objects here.
     * Chart.js 4.x uses Proxy objects for option resolution. Empty
     * plain objects from PHP (@js()) break the Proxy chain, causing
     * "setContext is not a function" errors. Colors and fonts for
     * ticks/grid are set client-side via Chart.defaults in the Alpine
     * component (resources/js/components/chart.js).
     */
    private function defaultScales(string $type): array
    {
        if (in_array($type, ['pie', 'doughnut', 'radar', 'polarArea'], true)) {
            return [];
        }

        return [
            'x' => [
                'grid' => [
                    'display' => true,
                ],
            ],
            'y' => [
                'beginAtZero' => true,
                'grid' => [
                    'display' => true,
                ],
            ],
        ];
    }
}
