/**
 * The days a calendar offers: its bounds, the days it closes, and the span a range may cover.
 *
 * Days are compared as `YYYY-MM-DD` text, which orders the way the calendar does. The server writes
 * the limits already read into days (Support\CalendarLimits), so this side only checks shapes: a
 * value that is no day or no whole number of days limits nothing.
 *
 * The limits steer what a reader can pick and nothing more. The server checks the value it
 * receives itself.
 */

const DAY = /^\d{4}-\d{2}-\d{2}$/;

/**
 * The limits as the calendar reads them, from the object the server wrote, or none.
 *
 * @param {*} value
 * @returns {{ min: ?string, max: ?string, unavailable: Set<string>, minRange: ?number, maxRange: ?number }}
 */
export function readLimits(value) {
    const limits = value && typeof value === 'object' ? value : {};
    const day = (candidate) => (typeof candidate === 'string' && DAY.test(candidate) ? candidate : null);
    const span = (candidate) => (Number.isInteger(candidate) && candidate >= 1 ? candidate : null);

    return {
        min: day(limits.min),
        max: day(limits.max),
        unavailable: new Set(Array.isArray(limits.unavailable) ? limits.unavailable.filter((candidate) => day(candidate) !== null) : []),
        minRange: span(limits.minRange),
        maxRange: span(limits.maxRange),
    };
}

/** The year, the zero-based month and the day of a `YYYY-MM-DD` text. */
function partsOf(day) {
    const [year, month, date] = day.split('-').map(Number);

    return [year, month - 1, date];
}

/**
 * How many days a span from one day to another covers, both ends counted: 1 for a single day. The
 * order of the two does not matter. Counted in UTC, so a change of daylight saving time between
 * them adds or takes no hour that could round to a day.
 *
 * @param {string} a
 * @param {string} b
 * @returns {number}
 */
export function spanDays(a, b) {
    const [from, to] = a <= b ? [a, b] : [b, a];

    return Math.round((Date.UTC(...partsOf(to)) - Date.UTC(...partsOf(from))) / 86400000) + 1;
}

/**
 * Whether a day is closed to a pick: before `min`, after `max`, or unavailable. While a range has
 * its start and waits for the other end, also a day that would make it shorter than `minRange` or
 * longer than `maxRange`. A day before the start ends the range on the start, as a second pick
 * before the start does in the calendar, so the span counts the same in both directions.
 *
 * @param {string} day
 * @param {ReturnType<typeof readLimits>} limits
 * @param {?string} [start] the start of a range waiting for its other end
 * @returns {boolean}
 */
export function dayClosed(day, limits, start = null) {
    if ((limits.min !== null && day < limits.min) || (limits.max !== null && day > limits.max) || limits.unavailable.has(day)) {
        return true;
    }

    if (start === null || (limits.minRange === null && limits.maxRange === null)) {
        return false;
    }

    const span = spanDays(start, day);

    return (limits.minRange !== null && span < limits.minRange) || (limits.maxRange !== null && span > limits.maxRange);
}

/**
 * Whether a whole month lies before `min` or after `max`, which takes the way into it away.
 *
 * @param {number} year
 * @param {number} month zero-based, and normalized as `Date` normalizes it: -1 is December before
 * @param {ReturnType<typeof readLimits>} limits
 * @returns {boolean}
 */
export function monthOutside(year, month, limits) {
    const first = textOf(new Date(year, month, 1));
    const last = textOf(new Date(year, month + 1, 0));

    return (limits.max !== null && first > limits.max) || (limits.min !== null && last < limits.min);
}

/**
 * The open day nearest to a day, where a calendar without a value opens: the day itself when the
 * limits leave it open, `min` or `max` when it lies outside them, and past a day marked
 * unavailable the first open day after it, or the last one before it when none follows. Looked
 * for within a year each way; null when no day there is open.
 *
 * @param {string} day  `YYYY-MM-DD`
 * @param {ReturnType<typeof readLimits>} limits
 * @returns {?string}
 */
export function openDayNear(day, limits) {
    let from = day;

    if (limits.min !== null && from < limits.min) {
        from = limits.min;
    } else if (limits.max !== null && from > limits.max) {
        from = limits.max;
    }

    const [year, month, date] = partsOf(from);

    for (const direction of [1, -1]) {
        for (let offset = 0; offset <= 366; offset++) {
            const candidate = textOf(new Date(year, month, date + direction * offset));

            if ((direction > 0 && limits.max !== null && candidate > limits.max) || (direction < 0 && limits.min !== null && candidate < limits.min)) {
                break;
            }

            if (! dayClosed(candidate, limits)) {
                return candidate;
            }
        }
    }

    return null;
}

/** A `Date` as `YYYY-MM-DD`, in local time as the grid draws it. */
function textOf(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

/** The attributes the server writes the limits on, each always present and empty when unset. */
export const LIMIT_ATTRIBUTES = ['data-wk-min', 'data-wk-max', 'data-wk-unavailable', 'data-wk-min-range', 'data-wk-max-range'];

/**
 * The limits written on an element: the two bounds and the two spans as text, the unavailable
 * days separated by commas. An element without them limits nothing.
 *
 * @param {?Element} el
 * @returns {ReturnType<typeof readLimits>}
 */
export function limitsOn(el) {
    const read = (name) => (typeof el?.getAttribute === 'function' ? el.getAttribute(name) : null);
    const number = (text) => (text === null || text === '' ? null : Number(text));
    const days = read('data-wk-unavailable');

    return readLimits({
        min: read('data-wk-min'),
        max: read('data-wk-max'),
        unavailable: days ? days.split(',') : [],
        minRange: number(read('data-wk-min-range')),
        maxRange: number(read('data-wk-max-range')),
    });
}
