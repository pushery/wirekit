<?php

declare(strict_types=1);

namespace Pushery\WireKit\Charts;

use InvalidArgumentException;
use Pushery\WireKit\Contracts\ChartAdapter;

/**
 * Manages chart adapter resolution and lifecycle.
 * Registered as singleton — one instance per request.
 */
final class ChartManager
{
    /**
     * Built-in adapter mapping.
     *
     * @var array<string, class-string<ChartAdapter>>
     */
    private const ADAPTERS = [
        'chartjs' => ChartJsAdapter::class,
        'apexcharts' => ApexChartsAdapter::class,
    ];

    private ?ChartAdapter $adapter = null;

    /**
     * Check if charts are enabled.
     * No side effects — reads only config.
     */
    public function enabled(): bool
    {
        return config('wirekit.charts.library') !== null;
    }

    /**
     * Get the active chart adapter.
     * Returns null if charts are disabled ('charts.library' => null).
     */
    public function adapter(): ?ChartAdapter
    {
        if ($this->adapter !== null) {
            return $this->adapter;
        }

        $library = config('wirekit.charts.library');

        if ($library === null) {
            return null;
        }

        $this->adapter = $this->resolve($library);

        return $this->adapter;
    }

    /**
     * Get the adapter for a SPECIFIC library, bypassing the global
     * `wirekit.charts.library` config. Used by callers that need to
     * pin a chart to a specific library regardless of the app's
     * default (e.g. an ApexCharts demo page in a docs app whose
     * default is Chart.js). Does not mutate the singleton's cached
     * `$adapter` — each per-instance lookup is independent so two
     * charts on the same page can use different libraries without
     * fighting for the cache slot.
     *
     * Throws InvalidArgumentException for unknown library names
     * (matches the existing adapter() error shape).
     */
    public function adapterFor(string $library): ChartAdapter
    {
        return $this->resolve($library);
    }

    /**
     * Resolve a library name to a fresh adapter instance.
     * Accepts built-in keys (chartjs / apexcharts) AND custom FQCN.
     */
    private function resolve(string $library): ChartAdapter
    {
        if (isset(self::ADAPTERS[$library])) {
            return new (self::ADAPTERS[$library]);
        }

        if (class_exists($library)) {
            $instance = new $library;

            if (! $instance instanceof ChartAdapter) {
                throw new InvalidArgumentException(
                    "WireKit: Chart adapter '{$library}' must implement "
                    .ChartAdapter::class
                );
            }

            return $instance;
        }

        throw new InvalidArgumentException(
            "WireKit: Unknown chart library '{$library}'. "
            .'Available: '.implode(', ', array_keys(self::ADAPTERS))
            .' or a fully qualified class name implementing '.ChartAdapter::class
        );
    }

    /**
     * Get list of available built-in adapter keys.
     *
     * @return array<string>
     */
    public static function availableAdapters(): array
    {
        return array_keys(self::ADAPTERS);
    }
}
