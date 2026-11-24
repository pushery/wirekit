<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Carbon\CarbonImmutable;

/**
 * The ranges a preset names: "today", "last 7 days", "this quarter" and the rest, computed from a
 * day, the first day of the week and, for `allTime`, the first day there is.
 *
 * The one place the server computes them. `Values\DateRange` builds its static constructors on
 * it, and resources/js/utils/date-presets.js computes the same ranges in the browser for a
 * calendar's list of presets; tests/fixtures/date-presets.json holds both to the same answers.
 *
 * Every range counts both of its ends:
 *
 *   - `last7Days`, `last14Days`, `last30Days` end today and start 6, 13 or 29 days before it;
 *     `next7Days` and the others start today.
 *   - `last3Months` and `last6Months` end today and start the day after the same date three or
 *     six months before, at the end of a shorter month when that date does not exist;
 *     `next3Months` and `next6Months` start today and end the day before the same date ahead.
 *   - a week starts on the day the caller names, a quarter is a calendar quarter, and
 *     `yearToDate` runs from 1 January to today.
 *   - `allTime` runs from the first day there is to today, and is no range without one, or with a
 *     first day after today.
 */
final class DatePresets
{
    /**
     * Every preset, in the order a list offers them when it offers all.
     *
     * @var list<string>
     */
    public const array KEYS = [
        'today', 'yesterday', 'thisWeek', 'lastWeek',
        'last7Days', 'last14Days', 'last30Days',
        'thisMonth', 'lastMonth', 'last3Months', 'last6Months',
        'thisQuarter', 'lastQuarter', 'thisYear', 'lastYear', 'yearToDate',
        'tomorrow', 'nextWeek', 'next7Days', 'next14Days', 'next30Days',
        'nextMonth', 'next3Months', 'next6Months', 'nextQuarter', 'nextYear',
        'allTime',
    ];

    /**
     * The list a component offers for `presets` without a list of its own.
     *
     * @var list<string>
     */
    public const array DEFAULTS = ['today', 'yesterday', 'thisWeek', 'last7Days', 'thisMonth', 'yearToDate', 'allTime'];

    /** The marker a list may end with, for a range picked in the calendar that no preset names. */
    public const string CUSTOM = 'custom';

    /**
     * The presets a component offers: its own list, written as a list or as keys separated by
     * spaces, or the default list for `true` and for a list it does not give. A key that names no
     * preset is left out, and so is a second copy of one.
     *
     * @param  array<array-key, mixed>|string|bool|null  $presets
     * @return list<string>
     */
    public static function listed(array|string|bool|null $presets): array
    {
        if ($presets === null || $presets === false) {
            return [];
        }

        if ($presets === true || (is_string($presets) && trim($presets) === '')) {
            $keys = self::DEFAULTS;
        } elseif (is_string($presets)) {
            $keys = preg_split('/[\s,]+/', trim($presets)) ?: [];
        } else {
            $keys = $presets;
        }

        $listed = [];

        foreach ($keys as $key) {
            if (is_string($key) && (in_array($key, self::KEYS, true) || $key === self::CUSTOM) && ! in_array($key, $listed, true)) {
                $listed[] = $key;
            }
        }

        return $listed;
    }

    /**
     * What a list calls each preset, and `custom`, in the application's language.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'today' => __('wirekit::Today'),
            'yesterday' => __('wirekit::Yesterday'),
            'thisWeek' => __('wirekit::This week'),
            'lastWeek' => __('wirekit::Last week'),
            'last7Days' => __('wirekit::Last 7 days'),
            'last14Days' => __('wirekit::Last 14 days'),
            'last30Days' => __('wirekit::Last 30 days'),
            'thisMonth' => __('wirekit::This month'),
            'lastMonth' => __('wirekit::Last month'),
            'last3Months' => __('wirekit::Last 3 months'),
            'last6Months' => __('wirekit::Last 6 months'),
            'thisQuarter' => __('wirekit::This quarter'),
            'lastQuarter' => __('wirekit::Last quarter'),
            'thisYear' => __('wirekit::This year'),
            'lastYear' => __('wirekit::Last year'),
            'yearToDate' => __('wirekit::Year to date'),
            'tomorrow' => __('wirekit::Tomorrow'),
            'nextWeek' => __('wirekit::Next week'),
            'next7Days' => __('wirekit::Next 7 days'),
            'next14Days' => __('wirekit::Next 14 days'),
            'next30Days' => __('wirekit::Next 30 days'),
            'nextMonth' => __('wirekit::Next month'),
            'next3Months' => __('wirekit::Next 3 months'),
            'next6Months' => __('wirekit::Next 6 months'),
            'nextQuarter' => __('wirekit::Next quarter'),
            'nextYear' => __('wirekit::Next year'),
            'allTime' => __('wirekit::All time'),
            self::CUSTOM => __('wirekit::Custom'),
        ];
    }

    /**
     * The first and the last day of a preset, or null for a key that names none or an `allTime`
     * without a first day before today.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public static function range(string $key, CarbonImmutable $today, int $weekStartsOn, ?CarbonImmutable $min = null): ?array
    {
        $today = $today->startOfDay();
        $week = $today->subDays(($today->dayOfWeek - $weekStartsOn + 7) % 7);

        return match ($key) {
            'today' => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            'tomorrow' => [$today->addDay(), $today->addDay()],
            'thisWeek' => [$week, $week->addDays(6)],
            'lastWeek' => [$week->subDays(7), $week->subDay()],
            'nextWeek' => [$week->addDays(7), $week->addDays(13)],
            'last7Days' => [$today->subDays(6), $today],
            'last14Days' => [$today->subDays(13), $today],
            'last30Days' => [$today->subDays(29), $today],
            'next7Days' => [$today, $today->addDays(6)],
            'next14Days' => [$today, $today->addDays(13)],
            'next30Days' => [$today, $today->addDays(29)],
            'thisMonth' => [$today->startOfMonth(), $today->endOfMonth()->startOfDay()],
            'lastMonth' => [$today->startOfMonth()->subMonth(), $today->startOfMonth()->subDay()],
            'nextMonth' => [$today->startOfMonth()->addMonth(), $today->startOfMonth()->addMonths(2)->subDay()],
            'last3Months' => [$today->subMonthsNoOverflow(3)->addDay(), $today],
            'last6Months' => [$today->subMonthsNoOverflow(6)->addDay(), $today],
            'next3Months' => [$today, $today->addMonthsNoOverflow(3)->subDay()],
            'next6Months' => [$today, $today->addMonthsNoOverflow(6)->subDay()],
            'thisQuarter' => [$today->firstOfQuarter(), $today->lastOfQuarter()],
            'lastQuarter' => [$today->firstOfQuarter()->subMonths(3), $today->firstOfQuarter()->subDay()],
            'nextQuarter' => [$today->firstOfQuarter()->addMonths(3), $today->firstOfQuarter()->addMonths(6)->subDay()],
            'thisYear' => [$today->startOfYear(), $today->endOfYear()->startOfDay()],
            'lastYear' => [$today->startOfYear()->subYear(), $today->startOfYear()->subDay()],
            'nextYear' => [$today->startOfYear()->addYear(), $today->startOfYear()->addYears(2)->subDay()],
            'yearToDate' => [$today->startOfYear(), $today],
            'allTime' => $min !== null && $min->startOfDay()->lessThanOrEqualTo($today) ? [$min->startOfDay(), $today] : null,
            default => null,
        };
    }

    /**
     * The presets a calendar lists as its script starts, for the copy the page loads with: each
     * key with its label, whether it can be picked and whether it is the value's, as
     * `presetOptions()` in resources/js/components/calendar.js gives them after `_matchPreset()`.
     *
     * A preset is closed where the calendar is disabled, and where its range falls outside the
     * limits, starts or ends on an unavailable day, or spans fewer or more days than a range may;
     * `custom` is never closed by its range. The chosen one is the preset the server named, else
     * the first whose range is the value's two ends as written, else `custom` where the list has
     * it. `$today` is the day the view writes on the root for the script.
     *
     * @internal Called by the calendar's view; held to tests/fixtures/calendar-preset-start-cases.json.
     *
     * @param  list<string>  $keys
     * @param  array<string, string>  $labels
     * @param  array{min: ?string, max: ?string, unavailable: list<string>, minRange: ?int, maxRange: ?int}  $limits  as CalendarLimits::from() reads them
     * @return list<array{key: string, label: string, disabled: bool, selected: bool}>
     */
    public static function start(array $keys, array $labels, string $value, string $named, CarbonImmutable $today, int $weekStartsOn, array $limits, bool $disabled): array
    {
        $min = self::dayOf($limits['min']);
        $chosen = self::chosen($keys, $value, $named, $today, $weekStartsOn, $min);
        $options = [];

        foreach ($keys as $key) {
            $options[] = [
                'key' => $key,
                'label' => $labels[$key] ?? $key,
                'disabled' => $disabled || ($key !== self::CUSTOM && self::openRange($key, $today, $weekStartsOn, $min, $limits) === null),
                'selected' => $chosen === $key,
            ];
        }

        return $options;
    }

    /**
     * The preset a calendar starts on, `_matchPreset()`: the named one, else the first whose range
     * is the value's two ends, else `custom` where the list has it; null without both ends.
     *
     * @param  list<string>  $keys
     */
    private static function chosen(array $keys, string $value, string $named, CarbonImmutable $today, int $weekStartsOn, ?CarbonImmutable $min): ?string
    {
        if ($keys === []) {
            return null;
        }

        if ($named !== '' && $named !== self::CUSTOM && in_array($named, $keys, true)) {
            return $named;
        }

        $ends = explode('/', $value);
        $start = $ends[0];
        $end = $ends[1] ?? '';

        if ($start === '' || $end === '') {
            return null;
        }

        foreach ($keys as $key) {
            $range = self::range($key, $today, $weekStartsOn, $min);

            if ($range !== null && $range[0]->format('Y-m-d') === $start && $range[1]->format('Y-m-d') === $end) {
                return $key;
            }
        }

        return in_array(self::CUSTOM, $keys, true) ? self::CUSTOM : null;
    }

    /**
     * The range of a preset inside a calendar's limits, `_presetRange()`, as its two days; null for
     * one outside them.
     *
     * @param  array{min: ?string, max: ?string, unavailable: list<string>, minRange: ?int, maxRange: ?int}  $limits
     * @return array{0: string, 1: string}|null
     */
    private static function openRange(string $key, CarbonImmutable $today, int $weekStartsOn, ?CarbonImmutable $min, array $limits): ?array
    {
        $range = self::range($key, $today, $weekStartsOn, $min);

        if ($range === null) {
            return null;
        }

        $start = $range[0]->format('Y-m-d');
        $end = $range[1]->format('Y-m-d');
        // Both days counted, as `spanOf()` counts them, from the dates alone.
        $last = (int) gmmktime(0, 0, 0, (int) $range[1]->format('n'), (int) $range[1]->format('j'), (int) $range[1]->format('Y'));
        $first = (int) gmmktime(0, 0, 0, (int) $range[0]->format('n'), (int) $range[0]->format('j'), (int) $range[0]->format('Y'));
        $days = (int) round(($last - $first) / 86400) + 1;

        $outside = (($limits['min'] ?? '') !== '' && strcmp($start, (string) $limits['min']) < 0)
            || (($limits['max'] ?? '') !== '' && strcmp($end, (string) $limits['max']) > 0)
            || ($limits['minRange'] !== null && $days < $limits['minRange'])
            || ($limits['maxRange'] !== null && $days > $limits['maxRange'])
            || in_array($start, $limits['unavailable'], true)
            || in_array($end, $limits['unavailable'], true);

        return $outside ? null : [$start, $end];
    }

    /** A day written `YYYY-MM-DD`, or null for anything else. */
    private static function dayOf(?string $text): ?CarbonImmutable
    {
        if ($text === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) !== 1) {
            return null;
        }

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $text);

        return $day instanceof CarbonImmutable && $day->format('Y-m-d') === $text ? $day : null;
    }
}
