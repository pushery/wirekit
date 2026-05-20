<?php

declare(strict_types=1);

namespace Pushery\WireKit\Charts;

use InvalidArgumentException;
use Pushery\WireKit\Contracts\ChartAdapter;

/**
 * Thrown when `<x-wirekit-chart type="...">` requests a chart type the
 * active adapter cannot render.
 *
 * The exception message includes the adapter name, the unsupported type,
 * the canonical supported list, and a switch-library hint so developers
 * can either pick a different type or change `config('wirekit.charts.library')`.
 */
final class TypeNotSupportedException extends InvalidArgumentException
{
    public static function for(ChartAdapter $adapter, string $requestedType): self
    {
        $supported = implode(', ', $adapter->supportedTypes());

        $hint = match ($adapter->name()) {
            'chartjs' => "To use this type, switch to ApexCharts: set 'charts.library' => 'apexcharts' in config/wirekit.php (license terms apply — see docs).",
            'apexcharts' => "To use this type, switch to Chart.js: set 'charts.library' => 'chartjs' in config/wirekit.php.",
            default => "Check your custom adapter's supportedTypes() implementation.",
        };

        return new self(sprintf(
            "WireKit: chart type '%s' is not supported by the active library '%s'. Supported: %s. %s",
            $requestedType,
            $adapter->name(),
            $supported,
            $hint,
        ));
    }
}
