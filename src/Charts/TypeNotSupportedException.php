<?php

declare(strict_types=1);

namespace Pushery\WireKit\Charts;

use InvalidArgumentException;
use Pushery\WireKit\Contracts\ChartAdapter;
use Pushery\WireKit\Support\SuggestSimilar;

/**
 * Thrown when `<x-wirekit-chart type="...">` requests a chart type the
 * active adapter cannot render.
 *
 * The exception message includes the adapter name, the unsupported type,
 * the canonical supported list, and the hint that helps: a switch to the
 * other built-in library where that library renders the type, and otherwise
 * the nearest type name the active adapter knows.
 */
final class TypeNotSupportedException extends InvalidArgumentException
{
    public static function for(ChartAdapter $adapter, string $requestedType): self
    {
        $supported = implode(', ', $adapter->supportedTypes());

        return new self(sprintf(
            "WireKit: chart type '%s' is not supported by the active library '%s'. Supported: %s. %s",
            $requestedType,
            $adapter->name(),
            $supported,
            self::hint($adapter, $requestedType),
        ));
    }

    /**
     * A switch is suggested only where it succeeds. The Chart.js types are a subset of the
     * ApexCharts types, so a type ApexCharts rejects is one neither library renders, usually a
     * misspelling, and a switch would only raise this exception a second time.
     */
    private static function hint(ChartAdapter $adapter, string $requestedType): string
    {
        $other = match ($adapter->name()) {
            'chartjs' => new ApexChartsAdapter,
            'apexcharts' => new ChartJsAdapter,
            default => null,
        };

        if ($other !== null && in_array($requestedType, $other->supportedTypes(), true)) {
            return $other->name() === 'apexcharts'
                ? "To use this type, switch to ApexCharts: set 'charts.library' => 'apexcharts' in config/wirekit.php (license terms apply — see docs)."
                : "To use this type, switch to Chart.js: set 'charts.library' => 'chartjs' in config/wirekit.php.";
        }

        $suggestion = SuggestSimilar::format(SuggestSimilar::byLevenshtein($requestedType, $adapter->supportedTypes()));

        if ($suggestion !== null) {
            return "Check the type name. {$suggestion}";
        }

        return $other === null
            ? "Check your custom adapter's supportedTypes() implementation."
            : 'Check the type name.';
    }
}
