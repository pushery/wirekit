import { timeText } from './time-slots.js';
import { segmentRange, segmentsOf, segmentText, timeOfSegments } from './time-segments.js';

/**
 * The segments of a moment typed in place, the day, the month, the year, the hour, the minute and
 * the period of the day, as pure functions: their order and the text between them in a locale,
 * the range of each, the moment they make together, and what a pasted text fills.
 *
 * Order and separators come from the locale through `Intl.DateTimeFormat#formatToParts`, so a
 * German form asks `27.10.2026, 14:00`, an American one `10/27/2026, 2:00 PM` and a Japanese one
 * `2026/10/27 14:00`, whatever order the browser gives a native field. A segment holds a number,
 * or null while it is empty. The time segments are those of utils/time-segments.js.
 */

const SEGMENTS = new Set(['day', 'month', 'year', 'hour', 'minute', 'dayPeriod']);

const DATE_SEGMENTS = ['day', 'month', 'year'];

// The hours each clock of `Intl` counts, as utils/time-segments.js reads them.
const CLOCKS = new Set(['h11', 'h12', 'h23', 'h24']);

const pad = (number, length = 2) => String(number).padStart(length, '0');

/** The days of a month: 31 while its month is not known, and 29 in February while its year is not. */
function daysIn(month, year) {
    if (month === null || month === undefined) {
        return 31;
    }

    // 2000 was a leap year.
    return new Date(year ?? 2000, month, 0).getDate();
}

/**
 * The shape of a moment in a locale: its clock, its segments in order with the text between them,
 * the names of the two periods of the day, and the ten digits it writes.
 *
 * @param {?string} locale  BCP 47
 * @param {?string} timeFormat  `auto`, `12-hour` or `24-hour`
 * @param {{ am?: string, pm?: string }} [options]  the names of the two periods where the locale's
 *   format gives none, in the application's language
 * @returns {{ clock: string, parts: Array<{ type: string, text?: string }>, periods: string[], digits: string[] }}
 */
export function momentShape(locale, timeFormat, options = {}) {
    const hourCycle = { '12-hour': 'h12', '24-hour': 'h23' }[timeFormat];
    const format = new Intl.DateTimeFormat(locale || undefined, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        ...(hourCycle ? { hourCycle } : {}),
    });
    const resolved = format.resolvedOptions();
    const clock = CLOCKS.has(resolved.hourCycle) ? resolved.hourCycle : (resolved.hour12 ? 'h12' : 'h23');
    const at = (hour) => format.formatToParts(new Date(2026, 9, 27, hour, 47));
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

    const twelve = clock === 'h11' || clock === 'h12';
    const period = (hour) => at(hour).find((part) => part.type === 'dayPeriod')?.value ?? '';

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
 * The values a segment takes. A day runs to the end of its month once the month is known, and a
 * year from 1 to 9999.
 *
 * @param {string} type
 * @param {{ month?: ?number, year?: ?number }} values  the segments as they stand
 * @param {string} clock
 * @returns {{ min: number, max: number }}
 */
export function momentRange(type, values, clock) {
    if (type === 'year') {
        return { min: 1, max: 9999 };
    }

    if (type === 'month') {
        return { min: 1, max: 12 };
    }

    if (type === 'day') {
        return { min: 1, max: daysIn(values?.month ?? null, values?.year ?? null) };
    }

    return segmentRange(type, clock);
}

/**
 * A digit typed into the year, after the digits typed into it since it took the focus. A year is
 * four digits: it is done, and the focus moves on, at the fourth, and a fifth starts it again.
 *
 * @param {string} typed
 * @param {number} digit
 * @returns {{ typed: string, value: ?number, done: boolean }}
 */
export function typeYearDigit(typed, digit) {
    let text = String(typed ?? '') + String(digit);

    if (text.length > 4) {
        text = String(digit);
    }

    return {
        typed: text,
        value: text.length === 4 && Number(text) >= 1 ? Number(text) : null,
        done: text.length === 4,
    };
}

/** Every segment empty. */
export function emptyMoment() {
    return { day: null, month: null, year: null, hour: null, minute: null, dayPeriod: null };
}

/**
 * A moment, `YYYY-MM-DDTHH:MM`, as the segments of a clock show it, or every segment empty.
 *
 * @param {?string} value
 * @param {string} clock
 * @returns {{ day: ?number, month: ?number, year: ?number, hour: ?number, minute: ?number, dayPeriod: ?number }}
 */
export function segmentsOfMoment(value, clock) {
    const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}:\d{2})$/.exec(String(value ?? '').trim());

    if (! match) {
        return emptyMoment();
    }

    return { day: Number(match[3]), month: Number(match[2]), year: Number(match[1]), ...segmentsOf(match[4], clock) };
}

/**
 * The moment the segments make, and what stands in its way: `invalid` for a day its month does not
 * have, or a day, a month or a year outside its range, as soon as the day, the month and the year
 * are set, `incomplete` while a segment the moment needs is empty, else `complete` with the moment,
 * its day and its time. Typing keeps each segment in its range; a pasted text fills them as its
 * numbers stand, so a month of 13 from a date written in another order is held here.
 *
 * @param {{ day: ?number, month: ?number, year: ?number, hour: ?number, minute: ?number, dayPeriod: ?number }} values
 * @param {string} clock
 * @returns {{ state: string, moment: ?string, day: ?string, time: ?string }}
 */
export function momentOfSegments(values, clock) {
    const { day, month, year } = values;
    const dated = day !== null && month !== null && year !== null;

    if (dated && (month < 1 || month > 12 || year < 1 || year > 9999 || day < 1 || day > daysIn(month, year))) {
        return { state: 'invalid', moment: null, day: null, time: null };
    }

    const time = timeOfSegments({ hour: values.hour, minute: values.minute, dayPeriod: values.dayPeriod }, clock);

    if (! dated || time === null) {
        return { state: 'incomplete', moment: null, day: null, time: null };
    }

    const text = pad(year, 4) + '-' + pad(month) + '-' + pad(day);

    return { state: 'complete', moment: text + 'T' + time, day: text, time };
}

/**
 * What a segment shows: a year in four digits of the locale's own, any other number in two, or
 * the name of a period.
 *
 * @param {string} type
 * @param {?number} value
 * @param {{ periods: string[], digits: string[] }} shape
 * @returns {string}
 */
export function momentSegmentText(type, value, shape) {
    if (type === 'year' && value !== null && value !== undefined) {
        return pad(value, 4).split('').map((digit) => shape.digits[Number(digit)] ?? digit).join('');
    }

    return segmentText(type, value, shape);
}

/** The period of the day a pasted text names, 0 or 1, by its name or as `am` and `pm`, or null. */
function periodIn(text, periods) {
    const lower = text.toLocaleLowerCase();

    for (const [index, name] of periods.map((word) => String(word).toLocaleLowerCase()).entries()) {
        if (name !== '' && lower.includes(name)) {
            return index;
        }
    }

    if (/(^|[^a-z])p\.?\s?m\.?($|[^a-z])/.test(lower)) {
        return 1;
    }

    return /(^|[^a-z])a\.?\s?m\.?($|[^a-z])/.test(lower) ? 0 : null;
}

/**
 * The segments a pasted text fills: a moment as a machine writes it (`2026-10-27T10:30`, with
 * seconds and an offset, which a field of wall-clock time drops), or as a locale writes it, its
 * numbers in the order of the field (`27.10.2026 10:30`, `10/27/2026, 2:30 PM`). A day without a
 * time fills the date alone. Null for a text that names no day.
 *
 * @param {string} text
 * @param {{ clock: string, parts: Array<{ type: string }>, periods: string[], digits: string[] }} shape
 * @returns {?{ day: ?number, month: ?number, year: ?number, hour: ?number, minute: ?number, dayPeriod: ?number }}
 */
export function readPasted(text, shape) {
    // The locale's own digits, and full-width ones, read as the digits they are.
    const ascii = Array.from(String(text ?? '').normalize('NFKC'))
        .map((char) => {
            const index = shape.digits.indexOf(char);

            return index === -1 ? char : String(index);
        })
        .join('')
        .trim();
    const values = emptyMoment();
    const takeTime = (hours, minutes) => {
        if (hours <= 23 && minutes <= 59) {
            Object.assign(values, segmentsOf(timeText(hours * 60 + minutes), shape.clock));
        }
    };

    const iso = /^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{1,2}):(\d{2}))?/.exec(ascii);

    if (iso) {
        values.year = Number(iso[1]);
        values.month = Number(iso[2]);
        values.day = Number(iso[3]);

        if (iso[4] !== undefined) {
            takeTime(Number(iso[4]), Number(iso[5]));
        }

        return values;
    }

    const numbers = ascii.match(/\d+/g) ?? [];

    if (numbers.length < 3) {
        return null;
    }

    const order = shape.parts.filter((part) => DATE_SEGMENTS.includes(part.type)).map((part) => part.type);

    order.forEach((type, index) => {
        values[type] = Number(numbers[index]);
    });

    if (numbers.length >= 5) {
        const period = periodIn(ascii, shape.periods);
        let hours = Number(numbers[3]);

        if (period !== null && hours >= 1 && hours <= 12) {
            hours = (hours % 12) + (period === 1 ? 12 : 0);
        }

        takeTime(hours, Number(numbers[4]));
    }

    return values;
}
