/**
 * The times a time list offers on one day, and the time a reader's typing points at.
 *
 * A time is a wall-clock time, `HH:MM` on a 24-hour clock, and the arithmetic runs in minutes
 * after midnight: a day on which the clocks change offers the same list as any other. The server
 * writes the limits already read (Support\TimeLimits), so this side only checks shapes: a value
 * that is no time limits nothing.
 *
 * The limits steer what a reader can pick and nothing more. The server checks the value it
 * receives itself.
 */

const TIME = /^([01]\d|2[0-3]):([0-5]\d)$/;
const DAY = /^\d{4}-\d{2}-\d{2}$/;
const LAST_MINUTE = 23 * 60 + 59;

const pad = (n) => String(n).padStart(2, '0');

/**
 * Minutes after midnight of an `HH:MM` text, or null for anything else.
 *
 * @param {*} text
 * @returns {?number}
 */
export function minutesOf(text) {
    const match = TIME.exec(String(text ?? '').trim());

    return match ? Number(match[1]) * 60 + Number(match[2]) : null;
}

/**
 * The `HH:MM` text of minutes after midnight.
 *
 * @param {number} minutes
 * @returns {string}
 */
export function timeText(minutes) {
    return pad(Math.floor(minutes / 60)) + ':' + pad(minutes % 60);
}

/**
 * One entry of `unavailable` as the time list reads it: a time or a span of times on every day
 * (`12:00`, `12:00-13:00`), or on one day (`2026-10-23T10:00`, `2026-10-23T15:00-16:00`). Both
 * ends of a span are closed. A span that ends before it starts runs over midnight, as a time
 * input reads a `max` before its `min`: on every day `22:00-06:00` closes the night, from 22:00
 * to the end of the day and from midnight to 06:00, and on one day it closes that day from 22:00
 * and the next until 06:00. Anything else, a whole day among them, is the calendar's and reads as
 * no entry here.
 *
 * @param {*} entry
 * @returns {Array<{ day: ?string, from: number, to: number }>}
 */
function readClosed(entry) {
    const text = String(entry ?? '').trim();
    const dated = /^(\d{4}-\d{2}-\d{2})T(.+)$/.exec(text);
    const day = dated ? dated[1] : null;
    const times = (dated ? dated[2] : text).split('-');

    if (times.length > 2) {
        return [];
    }

    const from = minutesOf(times[0]);
    const to = times.length === 2 ? minutesOf(times[1]) : from;

    if (from === null || to === null) {
        return [];
    }

    if (from <= to) {
        return [{ day, from, to }];
    }

    return [
        { day, from, to: LAST_MINUTE },
        { day: day === null ? null : nextDay(day), from: 0, to },
    ];
}

/**
 * The day after a `YYYY-MM-DD` day, over the end of a month and of a year.
 *
 * @param {string} day
 * @returns {string}
 */
function nextDay(day) {
    const [year, month, date] = day.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, date + 1)).toISOString().slice(0, 10);
}

/**
 * A bound with a time of day, `YYYY-MM-DDTHH:MM`, or null.
 *
 * @param {*} value
 * @returns {?{ day: string, minutes: number }}
 */
function readBound(value) {
    const [day, time] = String(value ?? '').split('T');
    const minutes = minutesOf(time);

    return DAY.test(day ?? '') && minutes !== null ? { day, minutes } : null;
}

/**
 * The limits as a time list reads them, from the object the server wrote, or none. The window
 * keeps the order it was written in: a `maxTime` before the `minTime` runs over midnight.
 *
 * @param {*} value  `{ interval, minTime, maxTime, unavailable, min, max }`
 * @returns {{ interval: number, minTime: number, maxTime: number, closed: Array<{ day: ?string, from: number, to: number }>, min: ?{ day: string, minutes: number }, max: ?{ day: string, minutes: number } }}
 */
export function readTimeLimits(value) {
    const limits = value && typeof value === 'object' ? value : {};
    const interval = Number.isInteger(limits.interval) && limits.interval >= 1 && limits.interval <= 720 ? limits.interval : 30;

    return {
        interval,
        minTime: minutesOf(limits.minTime) ?? 0,
        maxTime: minutesOf(limits.maxTime) ?? LAST_MINUTE,
        closed: (Array.isArray(limits.unavailable) ? limits.unavailable : []).flatMap(readClosed),
        min: readBound(limits.min),
        max: readBound(limits.max),
    };
}

/**
 * Whether a time lies inside the window of `minTime` and `maxTime`, both ends included. A window
 * whose end comes before its start runs over midnight, as a time input reads a `max` before its
 * `min`: `22:00` to `06:00` holds the night, from 22:00 to the end of the day and from midnight to
 * 06:00.
 *
 * @param {number} minutes
 * @param {ReturnType<typeof readTimeLimits>} limits
 * @returns {boolean}
 */
export function insideWindow(minutes, limits) {
    return limits.minTime <= limits.maxTime
        ? minutes >= limits.minTime && minutes <= limits.maxTime
        : minutes >= limits.minTime || minutes <= limits.maxTime;
}

/**
 * The first and the last time of a day the window leaves open, as a range of whole days takes
 * them: the window's own ends, or midnight and 23:59 for a window over midnight, which holds the
 * start and the end of every day.
 *
 * @param {ReturnType<typeof readTimeLimits>} limits
 * @returns {{ first: number, last: number }}
 */
export function dayEdges(limits) {
    return limits.minTime <= limits.maxTime
        ? { first: limits.minTime, last: limits.maxTime }
        : { first: 0, last: LAST_MINUTE };
}

/**
 * The times of one day, in the order of the clock: every multiple of the interval after midnight
 * inside the window, so a window over midnight lists its morning first. Each is closed where an
 * entry of `unavailable` covers it on every day or on this one, before `min` on the day of `min`,
 * and after `max` on the day of `max`. A day before `min` or after `max` closes all of them.
 *
 * @param {?string} day  `YYYY-MM-DD`, or null before a day is chosen
 * @param {ReturnType<typeof readTimeLimits>} limits
 * @returns {Array<{ value: string, minutes: number, closed: boolean }>}
 */
export function timeSlots(day, limits) {
    const slots = [];

    for (let minutes = 0; minutes <= LAST_MINUTE; minutes += limits.interval) {
        if (insideWindow(minutes, limits)) {
            slots.push({ value: timeText(minutes), minutes, closed: slotClosed(day, minutes, limits) });
        }
    }

    return slots;
}

/**
 * Whether one time of one day is closed.
 *
 * @param {?string} day
 * @param {number} minutes
 * @param {ReturnType<typeof readTimeLimits>} limits
 * @returns {boolean}
 */
export function slotClosed(day, minutes, limits) {
    if (limits.closed.some((entry) => (entry.day === null || entry.day === day) && minutes >= entry.from && minutes <= entry.to)) {
        return true;
    }

    if (day === null) {
        return false;
    }

    if (limits.min !== null && (day < limits.min.day || (day === limits.min.day && minutes < limits.min.minutes))) {
        return true;
    }

    return limits.max !== null && (day > limits.max.day || (day === limits.max.day && minutes > limits.max.minutes));
}

/**
 * The slot a reader's typing points at, as an index into the slots, or -1. `9` is the first
 * open time in the nine o'clock hour, `14` the first at two in the afternoon, and `9:3` or `93`
 * the first at nine whose minutes start with 3. Two digits that make no hour are an hour and the
 * start of its minutes.
 *
 * @param {string} typed
 * @param {Array<{ minutes: number, closed: boolean }>} slots
 * @returns {number}
 */
export function typedSlot(typed, slots) {
    const text = String(typed ?? '').trim();
    let hour;
    let minutes = '';

    if (text.includes(':')) {
        [hour, minutes] = text.split(':', 2);
    } else if (/^\d{1,4}$/.test(text)) {
        const twoDigitHour = text.length >= 2 && Number(text.slice(0, 2)) <= 23;
        hour = twoDigitHour ? text.slice(0, 2) : text.slice(0, 1);
        minutes = text.slice(hour.length);
    } else {
        return -1;
    }

    if (! /^\d{1,2}$/.test(hour) || ! /^\d{0,2}$/.test(minutes) || Number(hour) > 23) {
        return -1;
    }

    return slots.findIndex((slot) => ! slot.closed
        && Math.floor(slot.minutes / 60) === Number(hour)
        && pad(slot.minutes % 60).startsWith(minutes));
}

/**
 * The open slot an hour away from a slot, as PageUp and PageDown move: the first at or past the
 * hour in that direction, or the last open one before the end of the list. -1 when there is none.
 *
 * @param {Array<{ minutes: number, closed: boolean }>} slots
 * @param {number} from  index of the slot the keyboard stands on
 * @param {1|-1} direction
 * @returns {number}
 */
export function hourAway(slots, from, direction) {
    const target = (slots[from]?.minutes ?? 0) + 60 * direction;
    const open = slots.map((slot, index) => ({ slot, index })).filter(({ slot }) => ! slot.closed);

    if (direction > 0) {
        return (open.find(({ slot }) => slot.minutes >= target) ?? open.at(-1))?.index ?? -1;
    }

    return ([...open].reverse().find(({ slot }) => slot.minutes <= target) ?? open[0])?.index ?? -1;
}
