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

        // Built-in adapter
        if (isset(self::ADAPTERS[$library])) {
            $this->adapter = new (self::ADAPTERS[$library]);

            return $this->adapter;
        }

        // Custom adapter (FQCN)
        if (class_exists($library)) {
            $instance = new $library;

            if (! $instance instanceof ChartAdapter) {
                throw new InvalidArgumentException(
                    "WireKit: Chart adapter '{$library}' must implement "
                    .ChartAdapter::class
                );
            }

            $this->adapter = $instance;

            return $this->adapter;
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
