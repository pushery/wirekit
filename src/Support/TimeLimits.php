<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

/**
 * The times a time list offers, read from a picker's `interval`, `minTime`, `maxTime`,
 * `unavailable`, `min` and `max`.
 *
 * A time is a wall-clock time in the application's time zone, written `HH:MM`, and a moment
 * `YYYY-MM-DDTHH:MM`: a date object, or a moment written with its offset, is converted to that
 * zone, and seconds are dropped. The
 * calendar beside the list reads the same `min`, `max` and `unavailable` as days
 * (Support\CalendarLimits); this reads the times in them. A value that names no time limits
 * nothing.
 *
 * The limits steer what a reader can pick and nothing more. The server checks the value it
 * receives itself.
 */
final class TimeLimits
{
    /** A time of day, with seconds and their fraction as a database writes it: the minute it names. */
    private const TIME = '/^(\d{1,2}):(\d{2})(?::\d{2}(?:\.\d+)?)?$/';

    private const DAY = '/^\d{4}-\d{2}-\d{2}$/';

    private const MOMENT = '/^(\d{4}-\d{2}-\d{2})[T ](\d{1,2}:\d{2})(?::\d{2}(?:\.\d+)?)?$/i';

    /** A moment with its offset or a `Z`, as `toIso8601String()` and JSON write one. */
    private const INSTANT = '/^(\d{4}-\d{2}-\d{2})[T ](\d{1,2}:\d{2})(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})$/i';

    private const DATED_SPAN = '/^(\d{4}-\d{2}-\d{2})[T ](\d{1,2}:\d{2})-(\d{1,2}:\d{2})$/i';

    private const SPAN = '/^(\d{1,2}:\d{2})-(\d{1,2}:\d{2})$/';

    /**
     * The limits as the time list's script reads them.
     *
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $interval
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $minTime
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $maxTime
     * @param  array<array-key, DateTimeInterface|string|int|float|bool|null>|object|string|int|float|bool|null  $unavailable
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $min
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $max
     * @return array{interval: int, minTime: ?string, maxTime: ?string, unavailable: list<string>, min: ?string, max: ?string}
     */
    public static function from(
        array|object|string|int|float|bool|null $interval,
        array|object|string|int|float|bool|null $minTime,
        array|object|string|int|float|bool|null $maxTime,
        array|object|string|int|float|bool|null $unavailable,
        array|object|string|int|float|bool|null $min,
        array|object|string|int|float|bool|null $max,
    ): array {
        $minutes = NumericProp::intOrNull($interval);
        $earliest = self::moment($min, false);
        $latest = self::moment($max, true);

        // Moments the wrong way round would close every day, and read as written they say "between
        // these two", as two days do in CalendarLimits. They change places before they are read,
        // so a day without a time still opens at midnight and closes at its last minute.
        if ($earliest !== null && $latest !== null && $earliest > $latest) {
            $earliest = self::moment($max, false);
            $latest = self::moment($min, true);
        }

        return [
            'interval' => $minutes !== null && $minutes >= 1 && $minutes <= 720 ? $minutes : 30,
            'minTime' => self::time($minTime),
            'maxTime' => self::time($maxTime),
            'unavailable' => self::unavailableTimes($unavailable),
            'min' => $earliest,
            'max' => $latest,
        ];
    }

    /**
     * A time of day, `HH:MM`: written as one, with or without its seconds, or the time of a moment
     * or a date object. Null for anything else, a moment on a day that does not exist included:
     * the zero date with a time of zeros names no date, and no time either.
     *
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $value
     */
    public static function time(array|object|string|int|float|bool|null $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone(date_default_timezone_get())->format('H:i');
        }

        if (! is_string($value)) {
            return null;
        }

        $text = trim($value);

        if (preg_match(self::MOMENT, $text, $moment) === 1) {
            if (! self::realDay($moment[1])) {
                return null;
            }

            $text = $moment[2];
        }

        if (preg_match(self::TIME, $text, $parts) !== 1 || (int) $parts[1] > 23 || (int) $parts[2] > 59) {
            return null;
        }

        return sprintf('%02d:%02d', (int) $parts[1], (int) $parts[2]);
    }

    /**
     * A moment, `YYYY-MM-DDTHH:MM`, or null. A day written without a time starts at midnight, or,
     * as the end of a window, ends at the last minute: `max="2026-10-05"` leaves the whole day
     * open. `today` is the day of the application's clock, `now` its minute.
     *
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $value
     */
    public static function moment(array|object|string|int|float|bool|null $value, bool $end): ?string
    {
        $zone = date_default_timezone_get();

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone($zone)->format('Y-m-d\TH:i');
        }

        if (! is_string($value)) {
            return null;
        }

        $text = trim($value);
        $word = strtolower($text);

        if ($word === 'now') {
            return CarbonImmutable::now($zone)->format('Y-m-d\TH:i');
        }

        $day = $word === 'today' ? CarbonImmutable::today($zone)->format('Y-m-d') : $text;

        if (preg_match(self::DAY, $day) === 1) {
            return self::realDay($day) ? $day.'T'.($end ? '23:59' : '00:00') : null;
        }

        // A moment with its offset names an instant, which is read in the application's zone, as
        // a date object is.
        if (preg_match(self::INSTANT, $text, $instant) === 1) {
            if (! self::realDay($instant[1]) || self::time($instant[2]) === null) {
                return null;
            }

            try {
                return CarbonImmutable::parse($text)->setTimezone($zone)->format('Y-m-d\TH:i');
            } catch (Throwable) {
                return null;
            }
        }

        if (preg_match(self::MOMENT, $text, $moment) !== 1 || ! self::realDay($moment[1])) {
            return null;
        }

        $time = self::time($moment[2]);

        return $time === null ? null : $moment[1].'T'.$time;
    }

    /**
     * The time of day a limit of `$component` names, as time() reads it, with a text that names
     * none reported as an invalid `$prop` is (Support\StrictnessGate). A text that names no date
     * at all, empty, white space or the zero date, is no limit and is not reported.
     *
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $value
     */
    public static function reportedTime(array|object|string|int|float|bool|null $value, string $component, string $prop): ?string
    {
        $time = self::time($value);

        if ($time === null && is_string($value) && ! Moment::namesNoDate($value)) {
            StrictnessGate::reject($component, $prop, trim($value), 'a time such as "09:30"', '');
        }

        return $time;
    }

    /**
     * The moment a limit of `$component` names, as moment() reads it, with a text that names none
     * reported as an invalid `$prop` is (Support\StrictnessGate). A text that names no date at
     * all, empty, white space or the zero date, is no limit and is not reported.
     *
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $value
     */
    public static function reportedMoment(array|object|string|int|float|bool|null $value, bool $end, string $component, string $prop): ?string
    {
        $moment = self::moment($value, $end);

        if ($moment === null && is_string($value) && ! Moment::namesNoDate($value)) {
            StrictnessGate::reject($component, $prop, trim($value), 'a moment such as "2026-10-05 09:30", a day, "now" or a date object', '');
        }

        return $moment;
    }

    /**
     * The times `unavailable` closes, as the time list reads them: a time or a span on every day
     * (`12:00`, `12:00-13:00`), a moment or a span on one day (`2026-10-23T10:00`,
     * `2026-10-23T15:00-16:00`), and a date object as the moment it names. A list, or entries
     * separated by commas. A whole day is the calendar's and is passed over here.
     *
     * @param  array<array-key, DateTimeInterface|string|int|float|bool|null>|object|string|int|float|bool|null  $value
     * @return list<string>
     */
    public static function unavailableTimes(array|object|string|int|float|bool|null $value): array
    {
        $entries = match (true) {
            is_array($value) => $value,
            is_string($value) => explode(',', $value),
            default => [],
        };

        $times = [];

        foreach ($entries as $entry) {
            $time = self::closedTime($entry);

            if ($time !== null) {
                $times[$time] = true;
            }
        }

        return array_keys($times);
    }

    /**
     * One entry of `unavailable` as a time, or null.
     *
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $entry
     */
    private static function closedTime(array|object|string|int|float|bool|null $entry): ?string
    {
        if ($entry instanceof DateTimeInterface) {
            return self::moment($entry, false);
        }

        if (! is_string($entry)) {
            return null;
        }

        $text = trim($entry);

        if (preg_match(self::DATED_SPAN, $text, $span) === 1) {
            $from = self::time($span[2]);
            $to = self::time($span[3]);

            return self::realDay($span[1]) && $from !== null && $to !== null ? $span[1].'T'.$from.'-'.$to : null;
        }

        if (preg_match(self::SPAN, $text, $span) === 1) {
            $from = self::time($span[1]);
            $to = self::time($span[2]);

            return $from !== null && $to !== null ? $from.'-'.$to : null;
        }

        if (preg_match(self::MOMENT, $text) === 1) {
            return self::moment($text, false);
        }

        return self::time($text);
    }

    /** Whether a `YYYY-MM-DD` text names a day that exists. */
    private static function realDay(string $day): bool
    {
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $day);
        } catch (Throwable) {
            return false;
        }

        return $date instanceof CarbonImmutable && $date->format('Y-m-d') === $day;
    }
}
