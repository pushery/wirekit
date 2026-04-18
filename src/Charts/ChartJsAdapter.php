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
        // WireKit's simplified format -> Chart.js format
        $normalizedDatasets = [];

        foreach ($datasets as $index => $dataset) {
            $normalizedDatasets[] = array_merge(
                [
                    'label' => $dataset['label'] ?? "Dataset {$index}",
                    'data' => $dataset['data'] ?? [],
                ],
                // Pass through all additional keys (backgroundColor, borderColor, etc.)
                array_diff_key($dataset, array_flip(['label', 'data'])),
            );
        }

        return [
            'type' => $type,
            'data' => [
                'labels' => $labels,
                'datasets' => $normalizedDatasets,
            ],
        ];
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
                'tooltip' => [
                    'enabled' => true,
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'scales' => $this->defaultScales($type),
        ];
    }

    public function alpineComponent(): string
    {
        return 'wirekitChartJs';
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
