<?php

declare(strict_types=1);

namespace Pushery\WireKit\Values;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

/**
 * A range of moments: a range whose ends carry a time of day, to the minute.
 *
 * Everything a range does, with the time kept: `start()` and `end()` are moments, the value a
 * component writes is `YYYY-MM-DDTHH:MM/YYYY-MM-DDTHH:MM`, and the period ends at the end's
 * minute. A loop over it still runs a day at a time, from the start's time of day.
 *
 * A time is a wall-clock time in the application's time zone. A date object is converted to that
 * zone, a time written with an offset or a `Z` as well, a time written without one is read as it
 * stands, and seconds are dropped. A day alone starts at midnight.
 *
 * @phpstan-consistent-constructor
 */
class DateTimeRange extends DateRange
{
    protected const string FORMAT = 'Y-m-d\TH:i';

    /** The period ends at the end's minute itself: the time of day is part of the range. */
    protected static function through(CarbonImmutable $end): CarbonImmutable
    {
        return $end;
    }

    /** An end read as the minute it names in the application's time zone, or null for none. */
    protected static function read(DateTimeInterface|string|null $end): ?CarbonImmutable
    {
        $zone = date_default_timezone_get();

        if ($end instanceof DateTimeInterface) {
            return CarbonImmutable::instance($end)->setTimezone($zone)->startOfMinute();
        }

        $text = trim((string) $end);

        if (in_array(strtolower($text), ['now', 'today'], true)) {
            return strtolower($text) === 'now' ? CarbonImmutable::now($zone)->startOfMinute() : CarbonImmutable::today($zone);
        }

        // A day, with a time after a `T` or a space, seconds and fractions, and an offset or a `Z`.
        if (preg_match('/^(\d{4}-\d{2}-\d{2})(?:[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?)?(?:Z|[+-]\d{2}:?\d{2})?$/i', $text, $parts) !== 1) {
            return null;
        }

        try {
            $moment = CarbonImmutable::parse($text, $zone);
        } catch (Throwable) {
            return null;
        }

        // A day that does not exist, or an hour past the day, rolls over to another day when
        // parsed; neither names the moment it was written as.
        if ($moment->format('Y-m-d') !== $parts[1]) {
            return null;
        }

        return $moment->setTimezone($zone)->startOfMinute();
    }
}
