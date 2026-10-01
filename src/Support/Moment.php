<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Carbon\CarbonImmutable;
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
     */
    public static function of(DateTimeInterface|string|int|float|null $value, ?string $readIn = null): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_int($value) || is_float($value) || is_numeric($value)) {
            return CarbonImmutable::createFromTimestamp((int) $value);
        }

        return CarbonImmutable::parse($value, $readIn);
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
