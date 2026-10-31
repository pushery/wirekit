<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * The days a calendar offers, read from its `min`, `max`, `unavailable`, `minRange` and `maxRange`.
 *
 * Every limit is written as the day it names in the application's time zone, because the grid
 * compares days: a date object in another zone, or a moment with a time of day, bounds the day it
 * falls on there. `today` and `now` read the application's clock. A value that names no day
 * bounds nothing, so a mistyped limit leaves the calendar open rather than locked.
 *
 * The limits steer what a reader can pick and nothing more. The server checks the value it
 * receives itself.
 */
final class CalendarLimits
{
    private const DAY = '/^(\d{4})-(\d{2})-(\d{2})$/';

    /**
     * The limits as the calendar's script reads them. The range limits count both ends and apply
     * to a range only.
     *
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $min
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $max
     * @param  array<array-key, DateTimeInterface|string|int|float|bool|null>|object|string|int|float|bool|null  $unavailable
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $minRange
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $maxRange
     * @return array{min: ?string, max: ?string, unavailable: list<string>, minRange: ?int, maxRange: ?int}
     */
    public static function from(
        array|object|string|int|float|bool|null $min,
        array|object|string|int|float|bool|null $max,
        array|object|string|int|float|bool|null $unavailable,
        array|object|string|int|float|bool|null $minRange,
        array|object|string|int|float|bool|null $maxRange,
        bool $range,
    ): array {
        $minDay = self::day($min);
        $maxDay = self::day($max);

        // Bounds the wrong way round would close every day. Read as written they say "between these
        // two days", so they are put in order.
        if ($minDay !== null && $maxDay !== null && $minDay > $maxDay) {
            [$minDay, $maxDay] = [$maxDay, $minDay];
        }

        $minSpan = $range ? self::span($minRange) : null;
        $maxSpan = $range ? self::span($maxRange) : null;

        // The same for the two spans: a shortest span longer than the longest leaves no end to pick.
        if ($minSpan !== null && $maxSpan !== null && $minSpan > $maxSpan) {
            [$minSpan, $maxSpan] = [$maxSpan, $minSpan];
        }

        return [
            'min' => $minDay,
            'max' => $maxDay,
            'unavailable' => self::unavailableDays($unavailable),
            'minRange' => $minSpan,
            'maxRange' => $maxSpan,
        ];
    }

    /**
     * The day a limit names, `Y-m-d`, or null: a date object, a day, a moment with a time of day,
     * `today` or `now`.
     *
     * A moment is read as the time list reads one (Support\TimeLimits::moment()): with its seconds
     * and their fraction, and with an offset or a `Z`, which names an instant and falls on its day
     * in the application's zone, as a date object does. A calendar and the time list beside it
     * then bound the same days.
     *
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $value
     */
    public static function day(array|object|string|int|float|bool|null $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone(date_default_timezone_get())->format('Y-m-d');
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (in_array(strtolower($value), ['today', 'now'], true)) {
            return CarbonImmutable::now(date_default_timezone_get())->format('Y-m-d');
        }

        if (preg_match(self::DAY, $value, $parts) === 1) {
            return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $value : null;
        }

        $moment = TimeLimits::moment($value, false);

        return $moment === null ? null : substr($moment, 0, 10);
    }

    /**
     * The day a limit of `$component` names, as day() reads it, with a text that names none
     * reported as an invalid `$prop` is (Support\StrictnessGate): a limit is the developer's, and
     * one written wrong would leave every day open without a word. A text that names no date at
     * all, empty, white space or the zero date, is no limit and is not reported (Support\Moment).
     *
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $value
     */
    public static function reportedDay(array|object|string|int|float|bool|null $value, string $component, string $prop): ?string
    {
        $day = self::day($value);

        if ($day === null && is_string($value) && ! Moment::namesNoDate($value)) {
            StrictnessGate::reject($component, $prop, trim($value), 'a day such as "2026-10-05", a date and time, "today" or a date object', '');
        }

        return $day;
    }

    /**
     * The entries of `unavailable` that `$component` can read, with each one no reader of the kit
     * reads reported as an invalid `unavailable` is (Support\StrictnessGate): a closed day or time
     * written wrong would stay open for booking without a word, as a wrong `min` would leave every
     * day open (reportedDay()).
     *
     * An entry is read when it is a date object, a day that exists, or a time, a span or a moment
     * the time list reads (Support\TimeLimits::unavailableTimes()). One that names no date at all,
     * empty, white space, the zero date, `null` or `false`, is left out without a report. The list
     * that comes back holds each entry read, a text trimmed, so a picker hands its calendar only
     * what it read, and an entry is reported once, by the component the developer wrote.
     *
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $unavailable
     * @return list<mixed>
     */
    public static function reportedUnavailable(array|object|string|int|float|bool|null $unavailable, string $component): array
    {
        $entries = match (true) {
            is_string($unavailable) => explode(',', $unavailable),
            is_iterable($unavailable) => iterator_to_array($unavailable, false),
            $unavailable === null || $unavailable === false => [],
            default => [$unavailable],
        };

        $read = [];

        foreach ($entries as $entry) {
            if ($entry instanceof DateTimeInterface) {
                $read[] = $entry;

                continue;
            }

            if ($entry === null || $entry === false || (is_string($entry) && Moment::namesNoDate($entry))) {
                continue;
            }

            if (is_string($entry) && ((preg_match(self::DAY, trim($entry)) === 1 && self::day($entry) !== null) || TimeLimits::unavailableTimes([$entry]) !== [])) {
                $read[] = trim($entry);

                continue;
            }

            StrictnessGate::reject(
                $component,
                'unavailable',
                is_scalar($entry) ? trim((string) $entry) : get_debug_type($entry),
                'a day such as "2026-10-24", a time or a span such as "12:00-13:00", a moment such as "2026-10-24T10:00" or a date object',
                '',
            );
        }

        return $read;
    }

    /**
     * The days `unavailable` closes, in order: a list, or text separated by commas, of days and
     * date objects. A moment with a time of day closes that time, which is the time list's to
     * read, and no day of the calendar.
     *
     * @param  array<array-key, DateTimeInterface|string|int|float|bool|null>|object|string|int|float|bool|null  $unavailable
     * @return list<string>
     */
    public static function unavailableDays(array|object|string|int|float|bool|null $unavailable): array
    {
        if (is_string($unavailable)) {
            $entries = explode(',', $unavailable);
        } elseif (is_iterable($unavailable)) {
            $entries = [];

            foreach ($unavailable as $entry) {
                $entries[] = $entry;
            }
        } else {
            $entries = [];
        }

        $days = [];

        foreach ($entries as $entry) {
            if ($entry instanceof DateTimeInterface) {
                $days[] = (string) self::day($entry);

                continue;
            }

            if (is_string($entry) && preg_match(self::DAY, trim($entry)) === 1) {
                $day = self::day($entry);

                if ($day !== null) {
                    $days[] = $day;
                }
            }
        }

        $days = array_values(array_unique($days));
        sort($days);

        return $days;
    }

    /**
     * A number of days, at least one, or null.
     *
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $value
     */
    private static function span(array|object|string|int|float|bool|null $value): ?int
    {
        $days = NumericProp::intOrNull(is_array($value) || is_object($value) ? null : $value);

        return $days !== null && $days >= 1 ? $days : null;
    }
}
