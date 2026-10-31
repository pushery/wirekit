import { minutesOf, timeText } from './time-slots.js';

/**
 * The segments of a time typed in place, the hour, the minute and the period of the day, as pure
 * functions: their order and the text between them in a locale, the range of each, what a typed
 * digit does, and the time they make together.
 *
 * Order and separators come from the locale through `Intl.DateTimeFormat#formatToParts`, so the
 * segments read `02:47 PM` in English, `14:47` in German and `午後02:47` in Japanese on a
 * 12-hour clock, whatever order the browser gives a native time field. A segment holds a number,
 * or null while it is empty. The period of the day holds 0 for the morning and 1 for the
 * afternoon, and only a 12-hour clock has one.
 */

const SEGMENTS = new Set(['hour', 'minute', 'dayPeriod']);

// The hours each clock of `Intl` counts: `h12` 1 to 12, `h11` 0 to 11, `h23` 0 to 23, `h24` 1 to 24.
const CLOCKS = { h11: [0, 11], h12: [1, 12], h23: [0, 23], h24: [1, 24] };

const isTwelveHour = (clock) => clock === 'h11' || clock === 'h12';

/**
 * The format of the times a picker shows, in its time list and its segments alike: the clock of
 * the locale, or a 12-hour or a 24-hour one.
 *
 * @param {?string} locale  BCP 47
 * @param {?string} timeFormat  `auto`, `12-hour` or `24-hour`
 * @returns {Intl.DateTimeFormat}
 */
export function timeFormatter(locale, timeFormat) {
    const hourCycle = { '12-hour': 'h12', '24-hour': 'h23' }[timeFormat];

    return new Intl.DateTimeFormat(locale || undefined, { hour: 'numeric', minute: '2-digit', ...(hourCycle ? { hourCycle } : {}) });
}

/**
 * The shape of a time in a locale: its clock, its segments in order with the text between them,
 * the names of the two periods of the day, and the ten digits it writes.
 *
 * @param {?string} locale
 * @param {?string} timeFormat
 * @param {{ am?: string, pm?: string }} [options]  the names of the two periods where the locale's
 *   format gives none, in the application's language
 * @returns {{ clock: string, parts: Array<{ type: string, text?: string }>, periods: string[], digits: string[] }}
 */
export function segmentShape(locale, timeFormat, options = {}) {
    const format = timeFormatter(locale, timeFormat);
    const resolved = format.resolvedOptions();
    const clock = CLOCKS[resolved.hourCycle] ? resolved.hourCycle : (resolved.hour12 ? 'h12' : 'h23');
    const at = (hour) => format.formatToParts(new Date(2000, 0, 1, hour, 47));
    const parts = [];

    for (const part of at(14)) {
        if (SEGMENTS.has(part.type)) {
            if (! parts.some((known) => known.type === part.type)) {
                parts.push({ type: part.type });
            }
        } else if (parts.at(-1)?.type === 'literal') {
            parts.at(-1).text += part.value;
        } else {
            parts.push({ type: 'literal', text: part.value });
        }
    }

    // Only the text between two segments stands in the field.
    while (parts[0]?.type === 'literal') {
        parts.shift();
    }

    while (parts.at(-1)?.type === 'literal') {
        parts.pop();
    }

    const period = (hour) => at(hour).find((part) => part.type === 'dayPeriod')?.value ?? '';
    const twelve = isTwelveHour(clock);

    if (twelve && ! parts.some((part) => part.type === 'dayPeriod')) {
        parts.push({ type: 'literal', text: ' ' }, { type: 'dayPeriod' });
    }

    const numbers = new Intl.NumberFormat(locale || undefined, { numberingSystem: resolved.numberingSystem, useGrouping: false });

    return {
        clock,
        parts,
        periods: twelve ? [period(1) || options.am || 'AM', period(13) || options.pm || 'PM'] : [],
        digits: Array.from({ length: 10 }, (_, digit) => numbers.format(digit)),
    };
}

/**
 * The values a segment takes on a clock.
 *
 * @param {string} type  `hour`, `minute` or `dayPeriod`
 * @param {string} clock
 * @returns {{ min: number, max: number }}
 */
export function segmentRange(type, clock) {
    if (type === 'minute') {
        return { min: 0, max: 59 };
    }

    if (type === 'dayPeriod') {
        return { min: 0, max: 1 };
    }

    const [min, max] = CLOCKS[clock] ?? CLOCKS.h23;

    return { min, max };
}

/**
 * A time, `HH:MM`, as the segments of a clock show it, or every segment empty.
 *
 * @param {?string} time
 * @param {string} clock
 * @returns {{ hour: ?number, minute: ?number, dayPeriod: ?number }}
 */
export function segmentsOf(time, clock) {
    const minutes = minutesOf(time);

    if (minutes === null) {
        return { hour: null, minute: null, dayPeriod: null };
    }

    const hours = Math.floor(minutes / 60);
    const hour = { h11: hours % 12, h12: hours % 12 || 12, h24: hours || 24 }[clock] ?? hours;

    return { hour, minute: minutes % 60, dayPeriod: isTwelveHour(clock) ? Number(hours >= 12) : null };
}

/**
 * The time the segments make, `HH:MM`, or null while one they need is empty.
 *
 * @param {{ hour: ?number, minute: ?number, dayPeriod: ?number }} segments
 * @param {string} clock
 * @returns {?string}
 */
export function timeOfSegments(segments, clock) {
    const twelve = isTwelveHour(clock);
    const { hour, minute, dayPeriod } = segments;

    if (hour === null || minute === null || (twelve && dayPeriod === null)) {
        return null;
    }

    const hours = twelve ? (hour % 12) + (dayPeriod === 1 ? 12 : 0) : hour % 24;

    return timeText(hours * 60 + minute);
}

/**
 * A digit typed into a number segment, after the digits typed into it since it took the focus.
 * The two make one number while they fit the range; a digit that cannot follow starts the number
 * again. The segment is done, and the focus moves on, at two digits or once no further digit
 * fits: on a 12-hour clock `1` waits for `10` to `12`, and `3` is three o'clock at once. A number
 * outside the range, such as a lone `0` on a 12-hour clock, leaves the segment empty and waiting.
 *
 * @param {string} typed  the digits typed so far
 * @param {number} digit
 * @param {{ min: number, max: number }} range
 * @returns {{ typed: string, value: ?number, done: boolean }}
 */
export function typeDigit(typed, digit, range) {
    let text = String(typed ?? '') + String(digit);

    if (text.length > 2 || Number(text) > range.max || (text.length === 2 && Number(text) < range.min)) {
        text = String(digit);
    }

    const number = Number(text);

    return {
        typed: text,
        value: number >= range.min && number <= range.max ? number : null,
        done: text.length === 2 || number * 10 > range.max,
    };
}

/**
 * A segment's value a step up or down, round from one end of its range to the other. It carries
 * nothing into the next segment: 59 minutes and a step up are 00 in the same hour. An empty
 * segment starts at the end the step comes from.
 *
 * @param {?number} value
 * @param {{ min: number, max: number }} range
 * @param {1|-1} direction
 * @returns {number}
 */
export function stepSegment(value, range, direction) {
    if (value === null || value === undefined) {
        return direction > 0 ? range.min : range.max;
    }

    const span = range.max - range.min + 1;

    return range.min + ((((value - range.min + direction) % span) + span) % span);
}

/**
 * The value of a typed digit, Western or one of the locale's own, or null. A full-width digit,
 * as a Japanese input method writes one, reads as the digit it is.
 *
 * @param {string} key
 * @param {string[]} digits  the locale's ten digits
 * @returns {?number}
 */
export function digitOf(key, digits = []) {
    const text = String(key ?? '').normalize('NFKC');

    if (/^[0-9]$/.test(text)) {
        return Number(text);
    }

    const index = digits.indexOf(text);

    return index === -1 ? null : index;
}

/**
 * The period of the day a typed letter names: the first letter of its name where the two names
 * start differently, and `a` and `p` in every locale.
 *
 * @param {string} key
 * @param {string[]} periods  the names of the morning and the afternoon
 * @returns {?number}
 */
export function periodOf(key, periods = []) {
    const letter = String(key ?? '').normalize('NFKC').toLocaleLowerCase();
    const firsts = periods.map((name) => String(name).trim().charAt(0).toLocaleLowerCase());

    if (firsts.length === 2 && firsts[0] !== firsts[1] && firsts.includes(letter)) {
        return firsts.indexOf(letter);
    }

    return { a: 0, p: 1 }[letter] ?? null;
}

/**
 * What a segment shows: two digits in the locale's own, or the name of its period.
 *
 * @param {string} type
 * @param {?number} value
 * @param {{ periods: string[], digits: string[] }} shape
 * @returns {string}
 */
export function segmentText(type, value, shape) {
    if (value === null || value === undefined) {
        return '';
    }

    if (type === 'dayPeriod') {
        return shape.periods[value] ?? '';
    }

    return String(value).padStart(2, '0').split('').map((digit) => shape.digits[Number(digit)] ?? digit).join('');
}
