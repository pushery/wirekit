<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * How the ApexCharts adapter, `wirekit-apex.js`, reaches a page: `wirekit.scripts.apex`.
 *
 * `true` puts its tag on every page beside the bundle. `'on-demand'` puts it on none: a chart drawn
 * with ApexCharts names the adapter's address on its root, and the bundle's script holds such a
 * chart until the adapter has loaded, on the first page that draws one, after a `wire:navigate`
 * and in a lazy island alike. `false` loads it nowhere, for a page that supplies it itself.
 *
 * @internal Read by `@wirekitScripts` and the chart view. Not a developer-facing API.
 */
final class ApexAdapter
{
    public const EAGER = 'eager';

    public const ON_DEMAND = 'on-demand';

    public const OFF = 'off';

    /** The delivery the configuration asks for. */
    public static function delivery(): string
    {
        $value = config('wirekit.scripts.apex', false);

        if (is_string($value) && strtolower(trim($value)) === self::ON_DEMAND) {
            return self::ON_DEMAND;
        }

        return BooleanProp::from($value, false) ? self::EAGER : self::OFF;
    }

    /** The address a chart names while the adapter loads on demand, or null. */
    public static function source(): ?string
    {
        return self::delivery() === self::ON_DEMAND ? AssetTags::source('wirekit-apex.js') : null;
    }
}
