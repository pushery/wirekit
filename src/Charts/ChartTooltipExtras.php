<?php

declare(strict_types=1);

namespace Pushery\WireKit\Charts;

use Illuminate\Contracts\Support\Arrayable;
use Pushery\WireKit\Support\LocalizedNumber;
use Stringable;

/**
 * Further values a chart's tooltip names for each point, without a series of their own.
 *
 * A dashboard draws revenue per day as bars and the average receipt as a line, and wants the number
 * of receipts of the day in the tooltip rather than drawn: a series for it would take an axis and
 * an entry in the legend. Each extra gives a label and one value per point, in the order of the
 * chart's labels, and the tooltip names the value of the point it shows under the series' values.
 * It belongs to the point, not to a series, so it stays when a series is switched off.
 *
 * The values are written here, in the application's locale, with the extra's own `decimals`,
 * `prefix` and `suffix`: a count of receipts takes no currency sign from the chart's
 * `valuePrefix`. The tooltip writes them as text.
 */
final class ChartTooltipExtras
{
    /**
     * The extras as the tooltip reads them, each value written; null where a point has none. One
     * extra may be given without the list around it, and an extra without a label or a list of
     * values is left out.
     *
     * @param  array<array-key, mixed>|Arrayable<array-key, mixed>  $extras
     * @return list<array{label: string, values: list<string|null>}>
     */
    public static function from(array|Arrayable $extras): array
    {
        $extras = $extras instanceof Arrayable ? $extras->toArray() : $extras;

        if (array_key_exists('label', $extras) || array_key_exists('data', $extras)) {
            $extras = [$extras];
        }

        $written = [];

        foreach ($extras as $extra) {
            $extra = $extra instanceof Arrayable ? $extra->toArray() : $extra;

            if (! is_array($extra)) {
                continue;
            }

            $label = self::text($extra['label'] ?? null);
            $data = $extra['data'] ?? null;
            $data = $data instanceof Arrayable ? $data->toArray() : $data;

            if ($label === null || ! is_array($data)) {
                continue;
            }

            $decimals = is_numeric($extra['decimals'] ?? null) ? min(100, max(0, (int) $extra['decimals'])) : null;
            $prefix = is_string($extra['prefix'] ?? null) ? $extra['prefix'] : '';
            $suffix = is_string($extra['suffix'] ?? null) ? $extra['suffix'] : '';

            $written[] = [
                'label' => $label,
                // `mixed`: each value is whatever the developer's data holds at that point.
                'values' => array_map(
                    static fn (mixed $value): ?string => self::value($value, $decimals, $prefix, $suffix),
                    array_values($data),
                ),
            ];
        }

        return $written;
    }

    /** A value as the tooltip shows it, or null where the point has none; `mixed` as the data holds it. */
    private static function value(mixed $value, ?int $decimals, string $prefix, string $suffix): ?string
    {
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            $number = $decimals === null
                ? LocalizedNumber::format((float) $value, maxPrecision: 3)
                : LocalizedNumber::format((float) $value, precision: $decimals);

            return $prefix.$number.$suffix;
        }

        $text = self::text($value);

        return $text === null ? null : $prefix.$text.$suffix;
    }

    /** A label or a value as text, or null when it has none to give; `mixed` as the data holds it. */
    private static function text(mixed $value): ?string
    {
        if (is_string($value) || is_int($value) || is_float($value) || $value instanceof Stringable) {
            $text = trim((string) $value);

            return $text === '' ? null : $text;
        }

        return null;
    }
}
