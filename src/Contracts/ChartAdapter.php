<?php

declare(strict_types=1);

namespace Pushery\WireKit\Contracts;

interface ChartAdapter
{
    /**
     * JavaScript assets that must be loaded in the <head>.
     * Returns URLs or asset() paths.
     *
     * @return array<string>
     */
    public function scripts(): array;

    /**
     * Normalize WireKit's simplified data format into the library-specific format.
     *
     * WireKit input:
     *   type: 'bar'
     *   labels: ['Jan', 'Feb', 'Mar']
     *   datasets: [
     *       ['label' => 'Revenue', 'data' => [12, 19, 3]],
     *       ['label' => 'Costs',   'data' => [7, 11, 5]],
     *   ]
     *
     * Output: The format the JS library expects (e.g. Chart.js config object).
     *
     * @return array<string, mixed>
     */
    public function normalizeData(string $type, array $labels, array $datasets): array;

    /**
     * Default options with WireKit theming (colors from CSS variables, dark mode etc.).
     * Merged with user options (user options win on conflicts).
     *
     * @return array<string, mixed>
     */
    public function defaultOptions(string $type): array;

    /**
     * Name of the Alpine.js component used for x-data.
     * Example: 'wirekitChartJs' -> <div x-data="wirekitChartJs({...})">
     */
    public function alpineComponent(): string;
}
