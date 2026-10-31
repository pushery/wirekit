<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use DateTimeInterface;
use DateTimeZone;
use Exception;

/**
 * A date a component was handed, read as the instant it names.
 *
 * A date object is taken as the instant it holds, whatever its class. Written out as a string it
 * loses its zone: a `CarbonImmutable` casts to `Y-m-d H:i:s` without an offset, which the
 * application then reads in its own zone, so a deadline handed over in Berlin moved by the
 * offset. Only the mutable `Carbon` escaped that, and an application that has called
 * `Date::use(CarbonImmutable::class)` gets immutable dates from every model.
 *
 * The value handed in is never changed. A copy is read, so writing it in another zone does not
 * move the caller's own object either.
 */
final class Moment
{
    /**
     * The instant `$value` names, or null for no date: null, a boolean, an empty string or one of
     * white space, and the zero date.
     *
     * A boolean is a condition at the call site, and read as a number it would be the Unix epoch,
     * 1 January 1970. The zero date, `0000-00-00` with or without a time of zeros, is how older
     * MySQL schemas write that a row has no date; Carbon reads it as 30 November of the year -1.
     *
     * A number is a Unix timestamp. A string is read by Carbon, in `$readIn` when it names no zone
     * of its own, and in the application's zone when `$readIn` is null. A timestamp names no zone
     * either, so it is placed in the same one: the instant handed over as a number, as text or as
     * a model's date is then written at one wall-clock time.
     *
     * A string Carbon cannot read, such as "TBD" or "Oktober 2026", one that names a day that does
     * not exist, such as 2026-02-30, and one that names a zone and nothing else, such as "CET" or
     * the military letter "P", is reported as an invalid `$prop` of `$component` when a component
     * is named, and is then no instant, as a missing date is: one date that is no date must not
     * end the page it stands on. Without a component the exception reaches the caller.
     */
    public static function of(
        DateTimeInterface|string|int|float|bool|null $value,
        ?string $readIn = null,
        ?string $component = null,
        string $prop = 'date',
    ): ?CarbonImmutable {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || is_bool($value) || (is_string($value) && self::namesNoDate($value))) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_int($value) || is_float($value) || is_numeric($value)) {
            // A number no integer holds, NAN, INF or one beyond the integer range, names no second,
            // and casting it would raise the warning PHP 8.5 gives for that cast, which ends the
            // page. It is reported as a string Carbon cannot read is.
            $seconds = NumericProp::intOrNull($value);

            if ($seconds === null) {
                if ($component === null) {
                    throw new InvalidFormatException(NumericProp::text($value).' is no Unix timestamp.');
                }

                StrictnessGate::reject($component, $prop, NumericProp::text($value), 'a date such as "2026-10-16 14:30"', '');

                return null;
            }

            // Carbon places a timestamp in UTC unless a zone is named; `parse()` reads text in
            // the default zone, which Laravel sets to the application's.
            return CarbonImmutable::createFromTimestamp($seconds, $readIn ?? date_default_timezone_get());
        }

        // Carbon reads a day past the end of its month as a day of the next one, 2026-02-30 as
        // 2 March, so a text that names a day that does not exist is read as one Carbon cannot
        // read, as Support\CalendarLimits::day() reads it.
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?!\d)/', $value, $day) === 1 && ! checkdate((int) $day[2], (int) $day[3], (int) $day[1])) {
            if ($component === null) {
                throw new InvalidFormatException("'{$value}' names a day that does not exist.");
            }

            StrictnessGate::reject($component, $prop, $value, 'a date such as "2026-10-16 14:30"', '');

            return null;
        }

        // A text that names a zone and nothing else takes the day and the time of now in the
        // application's zone, with the named zone's label put on them unconverted: "P", a military
        // zone, reads as an instant three hours from now. It names no moment, so it is read as one
        // Carbon cannot read.
        if (self::namesOnlyAZone($value)) {
            if ($component === null) {
                throw new InvalidFormatException("'{$value}' names a zone and no moment.");
            }

            StrictnessGate::reject($component, $prop, $value, 'a date such as "2026-10-16 14:30"', '');

            return null;
        }

        if ($component === null) {
            return CarbonImmutable::parse($value, $readIn);
        }

        try {
            return CarbonImmutable::parse($value, $readIn);
        } catch (InvalidFormatException) {
            StrictnessGate::reject($component, $prop, $value, 'a date such as "2026-10-16 14:30"', '');

            return null;
        }
    }

    /**
     * The name of the zone `$timezone` names, or `$fallback` for null, an empty string and a name
     * PHP does not know, which is reported the way any invalid prop of `$component` is. A zone
     * object, PHP's own `DateTimeZone` or a `CarbonTimeZone`, names its zone.
     *
     * For a zone a date is READ in: Carbon refuses an unknown one with an exception, before
     * `in()` could report it.
     */
    public static function zoneOr(DateTimeZone|string|null $timezone, string $fallback, string $component): string
    {
        if ($timezone instanceof DateTimeZone) {
            return $timezone->getName();
        }

        if ($timezone === null || $timezone === '') {
            return $fallback;
        }

        try {
            return (new DateTimeZone($timezone))->getName();
        } catch (Exception) {
            StrictnessGate::reject($component, 'timezone', $timezone, 'a time zone name such as "Europe/Berlin"', $fallback);

            return $fallback;
        }
    }

    /**
     * `$moment` written in `$timezone`, a zone name such as `Europe/Berlin` or a zone object; in
     * its own zone when `$timezone` is null or empty.
     *
     * A name PHP does not know is reported the way any invalid prop of `$component` is, and the
     * moment keeps its own zone rather than failing the render.
     */
    public static function in(CarbonImmutable $moment, DateTimeZone|string|null $timezone, string $component): CarbonImmutable
    {
        if ($timezone instanceof DateTimeZone) {
            return $moment->setTimezone($timezone);
        }

        if ($timezone === null || $timezone === '') {
            return $moment;
        }

        try {
            $zone = new DateTimeZone($timezone);
        } catch (Exception) {
            StrictnessGate::reject($component, 'timezone', $timezone, 'a time zone name such as "Europe/Berlin"', $moment->getTimezone()->getName());

            return $moment;
        }

        return $moment->setTimezone($zone);
    }

    /**
     * Whether a text names no date: it is empty, white space, or the zero date, `0000-00-00`
     * alone or with a time of zeros. A component reads such a text as no date and reports none
     * of them, where it reports a text that names a date it cannot read.
     */
    public static function namesNoDate(string $text): bool
    {
        $text = trim($text);

        return $text === '' || preg_match('/^0000-00-00(?:[ T]00:00(?::00(?:\.0+)?)?)?$/', $text) === 1;
    }

    /**
     * Whether a text names a zone and nothing else: no year, month, day, hour or minute, and
     * nothing relative such as "tomorrow" or "+1 day". "now" names no zone and stays the moment
     * it is, and a day with its zone, "2026-10-25 CET", names a day.
     */
    private static function namesOnlyAZone(string $text): bool
    {
        $parsed = date_parse($text);

        return isset($parsed['zone_type'])
            && ! isset($parsed['relative'])
            && $parsed['year'] === false
            && $parsed['month'] === false
            && $parsed['day'] === false
            && $parsed['hour'] === false
            && $parsed['minute'] === false;
    }
}
