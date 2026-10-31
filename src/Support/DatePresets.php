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
}
