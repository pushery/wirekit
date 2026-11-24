<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Carbon\CarbonImmutable;

/**
 * The months a calendar draws as it starts: the month of its value, or of the day it opens on
 * without one, and the months after it, each as weeks of days with what the script marks on them.
 *
 * The script builds the same grid (`_daysFor()` in resources/js/components/calendar.js), and the
 * view writes this one for the page as it loads, before the script starts and on a page that runs
 * none. What only the reader's browser knows stays with the script: its own day, the day under
 * the pointer and the day the keyboard stands on. Without a value the grid opens on the
 * application's day, as the script does once the server writes it.
 */
final class CalendarStart
{
    /**
     * The months, from the one the grid opens on.
     *
     * @param  string  $value  the value as the view hands it to the script: a day, or two joined by `/` for a range
     * @param  array{min: ?string, max: ?string, unavailable: list<string>, minRange: ?int, maxRange: ?int}  $limits  as CalendarLimits::from() reads them
     * @return list<array{first: CarbonImmutable, weeks: list<list<array{date: string, day: int, inMonth: bool, selected: bool, closed: bool, inRange: bool, band: ?string}>>}>
     */
    public static function months(string $value, ?string $openTo, CarbonImmutable $today, array $limits, int $weekStartsOn, int $count, bool $fixedWeeks, bool $range): array
    {
        $parts = explode('/', $value, 3);
        $start = ($parts[0] ?? '') !== '' ? $parts[0] : null;
        $end = ($parts[1] ?? '') !== '' ? $parts[1] : null;

        $view = self::dayOf($start) ?? self::dayOf($openTo) ?? self::dayOf(self::openDayNear($today->format('Y-m-d'), $limits)) ?? $today;
        $waiting = $range && $start !== null && $end === null && self::dayOf($start) !== null ? $start : null;

        $months = [];
        for ($offset = 0; $offset < max(1, min(4, $count)); $offset++) {
            $first = $view->startOfMonth()->addMonthsNoOverflow($offset);
            $months[] = [
                'first' => $first,
                'weeks' => array_chunk(self::daysOf($first, $start, $end, $limits, $weekStartsOn, $fixedWeeks, $range, $waiting), 7),
            ];
        }

        return $months;
    }

    /**
     * The ISO 8601 week of a row, read from the Thursday it holds, or null for a row shorter than a week.
     *
     * @param  list<array{date: string}>  $week
     */
    public static function weekNumber(array $week): ?int
    {
        foreach ($week as $day) {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $day['date']);

            if ($date !== null && $date->dayOfWeek === 4) {
                return (int) $date->format('W');
            }
        }

        return null;
    }

    /**
     * The days of a month's grid: the days before it that fill its first week, its own, and the
     * days after it that fill its last week, or six weeks with `$fixedWeeks`.
     *
     * @param  array{min: ?string, max: ?string, unavailable: list<string>, minRange: ?int, maxRange: ?int}  $limits
     * @return list<array{date: string, day: int, inMonth: bool, selected: bool, closed: bool, inRange: bool, band: ?string}>
     */
    private static function daysOf(CarbonImmutable $first, ?string $start, ?string $end, array $limits, int $weekStartsOn, bool $fixedWeeks, bool $range, ?string $waiting): array
    {
        $pad = static fn (CarbonImmutable $date): array => ['date' => $date->format('Y-m-d'), 'day' => $date->day, 'inMonth' => false, 'selected' => false, 'closed' => false, 'inRange' => false, 'band' => null];

        $days = [];
        $lead = (($first->dayOfWeek - $weekStartsOn) % 7 + 7) % 7;
        for ($i = $lead; $i >= 1; $i--) {
            $days[] = $pad($first->subDays($i));
        }

        // The range as the script orders it, its ends whichever was picked first.
        $low = $high = null;
        if ($range && $start !== null && $end !== null) {
            [$low, $high] = $end < $start ? [$end, $start] : [$start, $end];
        }

        for ($date = $first; $date->month === $first->month; $date = $date->addDay()) {
            $text = $date->format('Y-m-d');
            $band = null;
            if ($low !== null && $high !== null && $low !== $high) {
                $band = match (true) {
                    $text === $low => 'start',
                    $text === $high => 'end',
                    $text > $low && $text < $high => 'middle',
                    default => null,
                };
            }

            $days[] = [
                'date' => $text,
                'day' => $date->day,
                'inMonth' => true,
                'selected' => $text === $start || ($range && $text === $end),
                'closed' => self::closed($text, $limits, $waiting),
                'inRange' => $low !== null && $high !== null && $text > $low && $text < $high,
                'band' => $band,
            ];
        }

        $after = $fixedWeeks ? 42 - count($days) : (7 - count($days) % 7) % 7;
        $next = $first->addMonthNoOverflow();
        for ($i = 0; $i < $after; $i++) {
            $days[] = $pad($next->addDays($i));
        }

        return $days;
    }

    /**
     * Whether a day is closed to a pick, as utils/date-limits.js `dayClosed()` reads it: before
     * `min`, after `max`, unavailable, or, while a range waits for its other end, out of the span
     * `minRange` and `maxRange` allow from its start.
     *
     * @param  array{min: ?string, max: ?string, unavailable: list<string>, minRange: ?int, maxRange: ?int}  $limits
     */
    private static function closed(string $day, array $limits, ?string $start): bool
    {
        if (($limits['min'] !== null && $day < $limits['min']) || ($limits['max'] !== null && $day > $limits['max']) || in_array($day, $limits['unavailable'], true)) {
            return true;
        }

        if ($start === null || ($limits['minRange'] === null && $limits['maxRange'] === null)) {
            return false;
        }

        $from = CarbonImmutable::createFromFormat('!Y-m-d', min($start, $day), 'UTC');
        $to = CarbonImmutable::createFromFormat('!Y-m-d', max($start, $day), 'UTC');
        if ($from === null || $to === null) {
            return false;
        }

        $span = (int) $from->diffInDays($to) + 1;

        return ($limits['minRange'] !== null && $span < $limits['minRange']) || ($limits['maxRange'] !== null && $span > $limits['maxRange']);
    }

    /**
     * The open day nearest to a day, as utils/date-limits.js `openDayNear()` finds it: the day,
     * else `min` or `max` where it lies outside them, else the first open day after it within a
     * year, else the last one before it.
     *
     * @param  array{min: ?string, max: ?string, unavailable: list<string>, minRange: ?int, maxRange: ?int}  $limits
     */
    private static function openDayNear(string $day, array $limits): ?string
    {
        $from = $day;
        if ($limits['min'] !== null && $from < $limits['min']) {
            $from = $limits['min'];
        } elseif ($limits['max'] !== null && $from > $limits['max']) {
            $from = $limits['max'];
        }

        $base = self::dayOf($from);
        if ($base === null) {
            return null;
        }

        foreach ([1, -1] as $direction) {
            for ($offset = 0; $offset <= 366; $offset++) {
                $candidate = $base->addDays($direction * $offset)->format('Y-m-d');

                if (($direction > 0 && $limits['max'] !== null && $candidate > $limits['max']) || ($direction < 0 && $limits['min'] !== null && $candidate < $limits['min'])) {
                    break;
                }

                if (! self::closed($candidate, $limits, null)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /** A `YYYY-MM-DD` text as the day it names, or null for anything else. */
    private static function dayOf(?string $text): ?CarbonImmutable
    {
        if ($text === null || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text, $m) !== 1 || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $text);
    }
}
