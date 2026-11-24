<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Carbon\CarbonImmutable;

/**
 * What a countdown shows as the page loads: its units with their values, the text a screen reader
 * reads, and whether the deadline has passed or is near.
 *
 * The server works it out as the script works it out when it starts (`computed`, `segValue()`,
 * `srText` and `urgent` in resources/js/components/countdown.js), so the time left stands on the
 * page before the script runs, and on a page that runs none. The script writes over it when it
 * starts, on the reader's clock. Without a script it stays the time left when the page was made.
 */
final class CountdownStart
{
    /** The letter after a unit's value in the inline variant, as the script writes it. */
    public const SUFFIXES = ['years' => 'y', 'days' => 'd', 'hours' => 'h', 'minutes' => 'm', 'seconds' => 's'];

    /** A unit's length in seconds, as the script counts it: a year is 365 days. */
    private const SECONDS = ['years' => 31536000, 'days' => 86400, 'hours' => 3600, 'minutes' => 60, 'seconds' => 1];

    /**
     * @param  int  $targetMs  the deadline as a unix timestamp in milliseconds
     * @param  list<string>  $units  the units shown, largest first
     * @param  bool  $auto  whether leading units that stand at zero are left out
     * @param  int|null  $warnSeconds  how many seconds before the deadline count as near
     * @param  array<string, array<int|string, string>>  $phrases  each unit's forms, from PluralPhrases::from()
     * @param  string  $locale  the language tag the countdown writes in
     * @param  bool  $separators  whether the leading value is grouped as the locale groups a number
     * @param  string  $expiredText  what the countdown says once the deadline has passed
     * @param  int|null  $nowMs  the time it is now, in milliseconds; null for the clock's
     * @param  bool|null  $intlAvailable  Test seam, production callers omit it.
     * @return array{expired: bool, urgent: bool, segments: list<array{unit: string, text: string}>, spoken: string}
     */
    public static function at(
        int $targetMs,
        array $units,
        bool $auto,
        ?int $warnSeconds,
        array $phrases,
        string $locale,
        bool $separators,
        string $expiredText,
        ?int $nowMs = null,
        ?bool $intlAvailable = null,
    ): array {
        $remainingMs = $targetMs - ($nowMs ?? CarbonImmutable::now()->getTimestampMs());
        $expired = $remainingMs <= 0;
        $values = self::values(max(0, intdiv($remainingMs, 1000)), $units, $auto);

        $segments = [];

        foreach ($values as $index => $segment) {
            $segments[] = [
                'unit' => $segment['unit'],
                'text' => $index === 0
                    ? self::leading($segment['value'], $separators, $locale, $intlAvailable)
                    : str_pad((string) $segment['value'], 2, '0', STR_PAD_LEFT),
            ];
        }

        return [
            'expired' => $expired,
            'urgent' => $warnSeconds !== null && ! $expired && $remainingMs <= $warnSeconds * 1000,
            'segments' => $segments,
            'spoken' => $expired ? $expiredText : self::spoken($values, $phrases, $locale, $intlAvailable),
        ];
    }

    /**
     * The time left across the units: the first carries everything above it, the rest cascade, and
     * in auto mode the leading units that stand at zero are left out, the last unit always kept.
     *
     * @param  list<string>  $units
     * @return list<array{unit: string, value: int}>
     */
    private static function values(int $seconds, array $units, bool $auto): array
    {
        $values = [];

        foreach ($units as $unit) {
            $length = self::SECONDS[$unit] ?? null;

            if ($length === null) {
                continue;
            }

            $value = intdiv($seconds, $length);
            $seconds -= $value * $length;
            $values[] = ['unit' => $unit, 'value' => $value];
        }

        if (! $auto) {
            return $values;
        }

        $start = 0;

        while ($start < count($values) - 1 && $values[$start]['value'] === 0) {
            $start++;
        }

        return array_slice($values, $start);
    }

    /**
     * The leading value, grouped as the locale groups a number, or with the application's own
     * marks where it gave WireKit a number format. Without intl and without that format the
     * digits stay ungrouped, as LocalizedNumber::integer() leaves them.
     */
    private static function leading(int $value, bool $separators, string $locale, ?bool $intlAvailable): string
    {
        $intlAvailable ??= extension_loaded('intl');

        if (! $separators || (! LocalizedNumber::hasFormatter() && ! $intlAvailable)) {
            return (string) $value;
        }

        return LocalizedNumber::format((float) $value, precision: 0, locale: $locale, intlAvailable: $intlAvailable);
    }

    /**
     * The sentence a screen reader reads: each unit with a value, and the last one even at zero,
     * in the form its count takes, joined by commas.
     *
     * @param  list<array{unit: string, value: int}>  $values
     * @param  array<string, array<int|string, string>>  $phrases
     */
    private static function spoken(array $values, array $phrases, string $locale, ?bool $intlAvailable): string
    {
        $last = count($values) - 1;
        $parts = [];

        foreach ($values as $index => $segment) {
            if ($segment['value'] === 0 && $index !== $last) {
                continue;
            }

            $forms = $phrases[$segment['unit']] ?? null;

            $parts[] = $forms === null
                ? $segment['value'].' '.$segment['unit']
                : PluralPhrases::choose($forms, $segment['value'], $locale, $intlAvailable);
        }

        return implode(', ', $parts);
    }
}
