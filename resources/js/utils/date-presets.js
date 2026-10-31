/**
 * The ranges a preset names, "today", "last 7 days", "this quarter" and the rest, computed from a
 * day, the first day of the week and, for `allTime`, the first day there is: the same answers as
 * Support\DatePresets gives on the server, held together by tests/fixtures/date-presets.json.
 *
 * Every range counts both of its ends. `last7Days` ends today and starts six days before it, and
 * `next7Days` starts today. `last3Months` ends today and starts the day after the same date three
 * months before, at the end of a shorter month when that date does not exist; `next3Months` starts
 * today and ends the day before the same date ahead. A week starts on the day given, a quarter is a
 * calendar quarter, and `allTime` runs from the first day there is to today, and is no range
 * without one or with one after today.
 *
 * Days are written `YYYY-MM-DD` and computed as local dates, so a day on which the clocks change
 * counts as any other.
 */

/** Every preset, in the order a list offers them when it offers all. */
export const PRESET_KEYS = [
    'today', 'yesterday', 'thisWeek', 'lastWeek',
    'last7Days', 'last14Days', 'last30Days',
    'thisMonth', 'lastMonth', 'last3Months', 'last6Months',
    'thisQuarter', 'lastQuarter', 'thisYear', 'lastYear', 'yearToDate',
    'tomorrow', 'nextWeek', 'next7Days', 'next14Days', 'next30Days',
    'nextMonth', 'next3Months', 'next6Months', 'nextQuarter', 'nextYear',
    'allTime',
];

/** The marker a list may hold for a range picked in the calendar that no preset names. */
export const CUSTOM_PRESET = 'custom';

const pad = (number) => String(number).padStart(2, '0');

/** A date as the day it names, `YYYY-MM-DD`. */
const dayOf = (date) => date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());

/** A day `YYYY-MM-DD` as its year, its month from 0 and its date, or null. */
function readDay(text) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(text ?? ''));

    return match ? { year: Number(match[1]), month: Number(match[2]) - 1, date: Number(match[3]) } : null;
}

/** The same date some months away, or the last day of a shorter month when that date does not exist. */
function monthsAway(year, month, date, months) {
    const target = new Date(year, month + months, 1);
    const last = new Date(target.getFullYear(), target.getMonth() + 1, 0).getDate();

    return new Date(target.getFullYear(), target.getMonth(), Math.min(date, last));
}

/**
 * The first and the last day of a preset, or null for a key that names none, an `allTime`
 * without a first day on or before today, or a day that is no day.
 *
 * @param {string} key
 * @param {string} today  `YYYY-MM-DD`
 * @param {number} weekStartsOn  0 for Sunday to 6 for Saturday
 * @param {?string} [min]  `YYYY-MM-DD`, the first day there is
 * @returns {?[string, string]}
 */
export function presetRange(key, today, weekStartsOn, min = null) {
    const read = readDay(today);

    if (read === null) {
        return null;
    }

    const { year, month, date } = read;
    const away = (days) => dayOf(new Date(year, month, date + days));
    const weekStart = date - ((new Date(year, month, date).getDay() - weekStartsOn + 7) % 7);
    const inWeek = (days) => dayOf(new Date(year, month, weekStart + days));
    const quarter = Math.floor(month / 3) * 3;
    const monthDay = (monthOffset, day) => dayOf(new Date(year, month + monthOffset, day));
    const quarterDay = (monthOffset, day) => dayOf(new Date(year, quarter + monthOffset, day));
    const back = (months) => {
        const start = monthsAway(year, month, date, -months);

        return [dayOf(new Date(start.getFullYear(), start.getMonth(), start.getDate() + 1)), away(0)];
    };
    const ahead = (months) => {
        const end = monthsAway(year, month, date, months);

        return [away(0), dayOf(new Date(end.getFullYear(), end.getMonth(), end.getDate() - 1))];
    };

    switch (key) {
        case 'today': return [away(0), away(0)];
        case 'yesterday': return [away(-1), away(-1)];
        case 'tomorrow': return [away(1), away(1)];
        case 'thisWeek': return [inWeek(0), inWeek(6)];
        case 'lastWeek': return [inWeek(-7), inWeek(-1)];
        case 'nextWeek': return [inWeek(7), inWeek(13)];
        case 'last7Days': return [away(-6), away(0)];
        case 'last14Days': return [away(-13), away(0)];
        case 'last30Days': return [away(-29), away(0)];
        case 'next7Days': return [away(0), away(6)];
        case 'next14Days': return [away(0), away(13)];
        case 'next30Days': return [away(0), away(29)];
        case 'thisMonth': return [monthDay(0, 1), monthDay(1, 0)];
        case 'lastMonth': return [monthDay(-1, 1), monthDay(0, 0)];
        case 'nextMonth': return [monthDay(1, 1), monthDay(2, 0)];
        case 'last3Months': return back(3);
        case 'last6Months': return back(6);
        case 'next3Months': return ahead(3);
        case 'next6Months': return ahead(6);
        case 'thisQuarter': return [quarterDay(0, 1), quarterDay(3, 0)];
        case 'lastQuarter': return [quarterDay(-3, 1), quarterDay(0, 0)];
        case 'nextQuarter': return [quarterDay(3, 1), quarterDay(6, 0)];
        case 'thisYear': return [dayOf(new Date(year, 0, 1)), dayOf(new Date(year, 11, 31))];
        case 'lastYear': return [dayOf(new Date(year - 1, 0, 1)), dayOf(new Date(year - 1, 11, 31))];
        case 'nextYear': return [dayOf(new Date(year + 1, 0, 1)), dayOf(new Date(year + 1, 11, 31))];
        case 'yearToDate': return [dayOf(new Date(year, 0, 1)), away(0)];
        case 'allTime': {
            const first = readDay(min);
            const firstDay = first ? dayOf(new Date(first.year, first.month, first.date)) : null;

            return firstDay !== null && firstDay <= away(0) ? [firstDay, away(0)] : null;
        }
        default: return null;
    }
}

/**
 * The preset that names a range exactly, among the keys a list offers, or null.
 *
 * @param {?string} start  `YYYY-MM-DD`
 * @param {?string} end  `YYYY-MM-DD`
 * @param {string[]} keys
 * @param {string} today
 * @param {number} weekStartsOn
 * @param {?string} [min]
 * @returns {?string}
 */
export function presetOf(start, end, keys, today, weekStartsOn, min = null) {
    if (! start || ! end) {
        return null;
    }

    return keys.find((key) => {
        const range = presetRange(key, today, weekStartsOn, min);

        return range !== null && range[0] === start && range[1] === end;
    }) ?? null;
}
