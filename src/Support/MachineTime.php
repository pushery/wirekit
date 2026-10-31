<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use DateTimeImmutable;
use DateTimeInterface;
use Stringable;

/**
 * The value of a `datetime` attribute, from what a developer hands a component for it.
 *
 * HTML reads `datetime` by a grammar of its own: a year, a month, a day, a day without its year,
 * a week, a time of day, a day with a time with or without its zone, a zone, or a duration. A
 * string written in that grammar is taken as it stands, so a day stays a day and a month stays a
 * month. Its year has four digits here: a longer run of digits is read as a Unix timestamp, as
 * Support\Moment reads one, rather than as a year the reader did not mean.
 *
 * A date object or a Unix timestamp is read as the instant it holds (Support\Moment) and written
 * with its offset. Cast to a string instead, a Carbon loses its zone, and PHP's own `DateTime`
 * and `DateTimeImmutable` have no string form at all. Any other string is read by Carbon, and one
 * Carbon cannot read is reported as an invalid prop of the component. There is no value then:
 * the element carries no machine-readable time rather than a wrong one.
 */
final class MachineTime
{
    /** A time of day: hours and minutes, optionally seconds and up to three digits of a fraction. */
    private const TIME = '(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d(?:\.\d{1,3})?)?';

    /** A zone: `Z`, or an offset of hours and minutes with or without the colon. */
    private const ZONE = '(?:Z|[+-](?:[01]\d|2[0-3]):?[0-5]\d)';

    /** A year of four digits that is not zero. */
    private const YEAR = '(?!0000)\d{4}';

    private const MONTH = '(?:0[1-9]|1[0-2])';

    private const DAY = '(?:0[1-9]|[12]\d|3[01])';

    /**
     * The value for the attribute, or null for none.
     *
     * Null, a boolean, an empty string and one of white space name no time. A component is named
     * so that a string Carbon cannot read is reported as its invalid `$prop` rather than ending
     * the page.
     */
    public static function attribute(
        DateTimeInterface|Stringable|string|int|float|bool|null $value,
        string $component,
        string $prop = 'datetime',
    ): ?string {
        if ($value instanceof Stringable && ! $value instanceof DateTimeInterface) {
            $value = (string) $value;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return null;
            }

            if (self::inGrammar($value)) {
                return $value;
            }

            // A week its year does not have is reported, as a day its month does not have is:
            // Carbon would read 2025-W53 as the Monday after the year's last week.
            if (preg_match('/\A\d{4}-W\d{2}\z/', $value) === 1) {
                StrictnessGate::reject($component, $prop, $value, 'a week its year has, such as "2026-W43"', '');

                return null;
            }
        }

        return Moment::of($value, component: $component, prop: $prop)?->toIso8601String();
    }

    /**
     * Whether a string is a value of the grammar HTML gives `datetime` on a `time` element.
     *
     * A day has to exist in its month, as HTML requires: 2026-02-30 is no day, and it is read by
     * Carbon, which reports it.
     */
    private static function inGrammar(string $value): bool
    {
        $date = self::YEAR.'-'.self::MONTH.'-'.self::DAY;
        $pattern = '/\A(?:'
            .self::YEAR.'(?:-'.self::MONTH.'(?:-'.self::DAY.')?)?'
            .'|(?:--)?'.self::MONTH.'-'.self::DAY
            .'|'.self::YEAR.'-W(?:0[1-9]|[1-4]\d|5[0-3])'
            .'|(?:'.$date.'[T ])?'.self::TIME
            .'|'.$date.'[T ]'.self::TIME.self::ZONE
            .'|'.self::ZONE
            .'|P(?=\d|T\d)(?:\d+D)?(?:T(?=\d)(?:\d+H)?(?:\d+M)?(?:\d+(?:\.\d{1,3})?S)?)?'
            .')\z/';

        if (preg_match($pattern, $value) !== 1) {
            return false;
        }

        if (preg_match('/\A(\d{4})-(\d{2})-(\d{2})(?!\d)/', $value, $day) === 1) {
            return checkdate((int) $day[2], (int) $day[3], (int) $day[1]);
        }

        // A day without its year exists if it exists in a leap year: 02-29 is a day, 02-30 none.
        if (preg_match('/\A(?:--)?(\d{2})-(\d{2})\z/', $value, $day) === 1) {
            return checkdate((int) $day[1], (int) $day[2], 2000);
        }

        // A year has a 53rd week when its 28 December falls in one.
        if (preg_match('/\A(\d{4})-W53\z/', $value, $week) === 1) {
            return (new DateTimeImmutable($week[1].'-12-28'))->format('W') === '53';
        }

        return true;
    }
}
