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
     * The instant `$value` names, or null for null and an empty string.
     *
     * A number is a Unix timestamp. A string is read by Carbon, in `$readIn` when it names no zone
     * of its own, and in the application's zone when `$readIn` is null.
     *
     * A string Carbon cannot read, such as "TBD" or "Oktober 2026", is reported as an invalid
     * `$prop` of `$component` when a component is named, and is then no instant, as a missing date
     * is: one date that is no date must not end the page it stands on. Without a component the
     * exception reaches the caller.
     */
    public static function of(
        DateTimeInterface|string|int|float|null $value,
        ?string $readIn = null,
        ?string $component = null,
        string $prop = 'date',
    ): ?CarbonImmutable {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_int($value) || is_float($value) || is_numeric($value)) {
            return CarbonImmutable::createFromTimestamp((int) $value);
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
     * PHP does not know, which is reported the way any invalid prop of `$component` is.
     *
     * For a zone a date is READ in: Carbon refuses an unknown one with an exception, before
     * `in()` could report it.
     */
    public static function zoneOr(?string $timezone, string $fallback, string $component): string
    {
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
     * `$moment` written in `$timezone`, a zone name such as `Europe/Berlin`; in its own zone when
     * `$timezone` is null or empty.
     *
     * A name PHP does not know is reported the way any invalid prop of `$component` is, and the
     * moment keeps its own zone rather than failing the render.
     */
    public static function in(CarbonImmutable $moment, ?string $timezone, string $component): CarbonImmutable
    {
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
}
