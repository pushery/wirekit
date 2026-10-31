import { dayClosed, readLimits } from './date-limits.js';
import { emptyMoment, momentOfSegments, momentRange, momentSegmentText, momentShape, readPasted, segmentsOfMoment, typeYearDigit } from './moment-segments.js';
import { digitOf, periodOf, stepSegment, timeFormatter, typeDigit } from './time-segments.js';
import { insideWindow, minutesOf, readTimeLimits, slotClosed } from './time-slots.js';

const DATE_SEGMENTS = ['year', 'month', 'day'];

/**
 * The parts of a moment as runs, the date and the time, in the order the locale writes them.
 *
 * A field too narrow for the whole moment breaks between two runs and never inside one, so an
 * hour is never parted from its minute or its period of the day. The text between two segments
 * goes with the segment before it, which keeps the comma of "10/27/2026, 10:30 AM" at the end of
 * the date; text before the first segment goes with the first. Each run has a key of its own,
 * since a locale that came back to the date after the time would give two runs of one kind.
 *
 * @param {Array<{type: string, segment: boolean}>} parts  as `momentInputParts()` returns them
 * @returns {Array<{key: string, kind: string, parts: Array<Object>}>}
 */
export function momentPartGroups(parts) {
    const runs = [];
    let leading = [];

    for (const part of parts) {
        if (! part.segment) {
            (runs.length > 0 ? runs[runs.length - 1].parts : leading).push(part);

            continue;
        }

        const kind = DATE_SEGMENTS.includes(part.type) ? 'date' : 'time';
        const last = runs[runs.length - 1];

        if (last && last.kind === kind) {
            last.parts.push(part);
        } else {
            runs.push({ key: 'run-' + runs.length, kind, parts: [...leading, part] });
            leading = [];
        }
    }

    return runs;
}

/**
 * The state and the keyboard of a moment typed in segments, the day, the month, the year, the
 * hour, the minute and the period of the day, spread into the factory of a picker whose trigger is
 * a field: the datetime picker with `type="input"`, beside the markup of its trigger.
 *
 * Each segment is a field of its own with the keyboard of a spin button, as in a typed time
 * (utils/time-input.js): a digit sets it and the focus moves on once no further digit fits, a year
 * at its fourth; the arrow keys step it round its range, Home and End go to its ends, `a` and `p`
 * set the period of the day, Backspace empties it or moves back from an empty one, and Left and
 * Right move between the segments. Alt with Down Arrow opens the dialog, as on a native field.
 * A pasted moment fills every segment it names (utils/moment-segments.js, readPasted).
 *
 * A moment the segments make whole is checked against the days and the times the picker closes;
 * one that does not exist or is closed is not taken, and the field says why in words. Every check
 * hands the moment it found, or null, to the host's onMomentTyped(moment).
 *
 * Methods rather than getters, as in utils/time-input.js: a factory spreads this object into its
 * own.
 *
 * @param {Object} config
 * @param {?string} [config.locale]  as BCP 47
 * @param {string} [config.timeFormat]  `auto`, `12-hour` or `24-hour`
 * @param {Object} [config.time]  the limits of the times (utils/time-slots.js)
 * @param {Object} [config.days]  the days closed: `min`, `max` and `unavailable` (utils/date-limits.js)
 * @param {string} [config.dayLabel]  the names of the six segments
 * @param {string} [config.monthLabel]
 * @param {string} [config.yearLabel]
 * @param {string} [config.hourLabel]
 * @param {string} [config.minuteLabel]
 * @param {string} [config.dayPeriodLabel]
 * @param {string} [config.invalidMessage]  for a day its month does not have
 * @param {string} [config.dayUnavailableMessage]  `:date` stands for the day typed
 * @param {string} [config.unavailableMessage]  `:time` stands for the time typed
 * @param {string} [config.outsideMessage]  `:from` and `:to` stand for the start and the end of the window
 * @param {boolean} [config.readonly]  the segments can be read and moved between, not changed
 */
export function momentInputState(config = {}) {
    const timeLimits = readTimeLimits(config.time ?? {});
    const dayLimits = readLimits(config.days ?? {});
    // The names of the two periods where the locale's format gives none: the two halves of the
    // segment's own name, "AM/PM" in the application's language.
    const [am, pm] = String(config.dayPeriodLabel || 'AM/PM').split('/').map((word) => word.trim());
    const shape = momentShape(config.locale, config.timeFormat, { am, pm });
    const timeFormat = timeFormatter(config.locale, config.timeFormat);
    const dayFormat = new Intl.DateTimeFormat(config.locale || undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    const names = {
        day: config.dayLabel || 'Day',
        month: config.monthLabel || 'Month',
        year: config.yearLabel || 'Year',
        hour: config.hourLabel || 'Hour',
        minute: config.minuteLabel || 'Minute',
        dayPeriod: config.dayPeriodLabel || 'AM/PM',
    };
    const invalidMessage = config.invalidMessage || 'This date does not exist.';
    const dayUnavailableMessage = config.dayUnavailableMessage || ':date is not available.';
    const unavailableMessage = config.unavailableMessage || ':time is not available.';
    const outsideMessage = config.outsideMessage || 'Pick a time from :from to :to.';
    const readonly = config.readonly === true;
    const timeLabel = (minutes) => timeFormat.format(new Date(2000, 0, 1, Math.floor(minutes / 60), minutes % 60));
    const dayLabel = (day) => {
        const [year, month, date] = day.split('-').map(Number);

        return dayFormat.format(new Date(year, month - 1, date));
    };

    // A segment is as wide as the widest text it can show, held by an invisible copy of that text
    // beneath it in the markup.
    const widest = shape.periods.reduce((wide, name) => (name.length > wide.length ? name : wide), '');
    const first = shape.parts.find((part) => part.type !== 'literal')?.type ?? null;
    const parts = shape.parts.map((part, index) => (part.type === 'literal'
        ? { key: 'literal-' + index, type: 'literal', segment: false, text: part.text, sizer: part.text }
        : {
            key: part.type,
            type: part.type,
            segment: true,
            // The label points at the first segment, so a press on it starts the typing there.
            first: part.type === first,
            name: names[part.type],
            min: momentRange(part.type, {}, shape.clock).min,
            sizer: part.type === 'dayPeriod' ? widest : shape.digits[8].repeat(part.type === 'year' ? 4 : 2),
            placeholder: part.type === 'year' ? '----' : '--',
            inputmode: part.type === 'dayPeriod' ? 'text' : 'numeric',
        }));
    const order = parts.filter((part) => part.segment).map((part) => part.type);
    const groups = momentPartGroups(parts);

    return {
        momentInputValues: emptyMoment(),
        momentInputError: '',
        // The digits typed into one segment since it took the focus, and which segment that is.
        _momentTyped: '',
        _momentTypedIn: null,

        /** The segments in the locale's order, with the text between them. */
        momentInputParts() {
            return parts;
        },

        /** The same parts as the runs a narrow field breaks between: the date and the time. */
        momentInputGroups() {
            return groups;
        },

        /** What a segment shows: its value, or the digits typed into it that make none yet. */
        momentInputText(type) {
            const value = this.momentInputValues[type];

            if (value === null && this._momentTypedIn === type && this._momentTyped !== '') {
                return this._momentTyped.split('').map((digit) => shape.digits[Number(digit)] ?? digit).join('');
            }

            return momentSegmentText(type, value, shape);
        },

        /** A segment's `aria-valuenow`, or null while it is empty. */
        momentInputValueNow(type) {
            return this.momentInputValues[type];
        },

        /** A segment's `aria-valuemax`: a day's runs to the end of the month typed. */
        momentInputMax(type) {
            return momentRange(type, this.momentInputValues, shape.clock).max;
        },

        /** A segment's `aria-valuetext`: its digits, or the name of the period. */
        momentInputValueText(type) {
            const text = momentSegmentText(type, this.momentInputValues[type], shape);

            return text === '' ? null : text;
        },

        /** Whether the field says its moment cannot be taken: the segments' `aria-invalid` and the frame's border. */
        momentInputInvalid() {
            return this.momentInputError !== '';
        },

        /** Whether every segment is empty. */
        momentInputBlank() {
            return order.every((type) => this.momentInputValues[type] === null);
        },

        /** Show a moment, `YYYY-MM-DDTHH:MM`, in the segments, or empty them, with no word under them. */
        momentInputShow(value) {
            this.momentInputValues = segmentsOfMoment(value, shape.clock);
            this._momentTyped = '';
            this.momentInputError = '';
        },

        /**
         * Check the moment the segments make. A day its month does not have, a closed day and a
         * closed time are said in words, and the host hears the moment, or null for none.
         */
        momentInputCheck() {
            const made = momentOfSegments(this.momentInputValues, shape.clock);
            let moment = null;

            if (made.state === 'invalid') {
                this.momentInputError = invalidMessage;
            } else if (made.state === 'incomplete') {
                this.momentInputError = '';
            } else if (dayClosed(made.day, dayLimits)) {
                this.momentInputError = dayUnavailableMessage.replace(':date', () => dayLabel(made.day));
            } else {
                const minutes = minutesOf(made.time);

                if (! insideWindow(minutes, timeLimits)) {
                    this.momentInputError = outsideMessage.replace(':from', () => timeLabel(timeLimits.minTime)).replace(':to', () => timeLabel(timeLimits.maxTime));
                } else if (slotClosed(made.day, minutes, timeLimits)) {
                    this.momentInputError = unavailableMessage.replace(':time', () => timeLabel(minutes));
                } else {
                    this.momentInputError = '';
                    moment = made.moment;
                }
            }

            this.onMomentTyped?.(moment);
        },

        /** A segment that takes the focus starts its typing afresh. */
        momentInputFocus(type) {
            this._momentTyped = '';
            this._momentTypedIn = type;
        },

        /** The keys that step a segment, the ones that move between them, and Alt with Down Arrow. */
        momentInputKeydown(event, type) {
            if (event.altKey && event.key === 'ArrowDown') {
                event.preventDefault();
                this.openPanel?.();

                return;
            }

            if (event.ctrlKey || event.metaKey || event.altKey) {
                return;
            }

            if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
                event.preventDefault();
                const next = this._momentNext(type, event.key === 'ArrowRight' ? 1 : -1);

                if (next) {
                    this._momentFocusSegment(next);
                }

                return;
            }

            if (readonly) {
                return;
            }

            const range = momentRange(type, this.momentInputValues, shape.clock);
            const value = this.momentInputValues[type];
            // An empty year steps to the current one, as a native field's does, never to the year 1.
            const thisYear = type === 'year' && value === null ? new Date().getFullYear() : null;
            const steps = {
                ArrowUp: () => thisYear ?? stepSegment(value, range, 1),
                ArrowDown: () => thisYear ?? stepSegment(value, range, -1),
                Home: () => range.min,
                End: () => range.max,
            };

            if (steps[event.key]) {
                event.preventDefault();
                this._momentTyped = '';
                this._momentSet(type, steps[event.key]());
                this.momentInputCheck();
            }
        },

        /**
         * What a segment is about to receive as text, a typed digit, a letter of the period or a
         * deletion, handled here rather than written: the segment shows its value, never the keys.
         * Text that spills past a segment goes on into the next.
         */
        momentInputBeforeInput(event, type) {
            // An input method's text cannot be stopped while it is composed; it is read on `input`.
            if (event.isComposing || event.inputType === 'insertCompositionText') {
                return;
            }

            event.preventDefault();

            if (readonly) {
                return;
            }

            if (String(event.inputType ?? '').startsWith('delete')) {
                this._momentDelete(type, event.inputType === 'deleteContentBackward');

                return;
            }

            const text = String(event.data ?? '');

            // A whole moment, as a paste or a drop brings one, fills every segment it names.
            if (Array.from(text).length > 2 && this._momentFill(text)) {
                return;
            }

            let at = type;

            for (const key of Array.from(text)) {
                at = this._momentType(at, key);
            }
        },

        /** A paste: a whole moment fills every segment it names, other text is typed. */
        momentInputPaste(event, type) {
            const text = event?.clipboardData?.getData?.('text') ?? '';

            event?.preventDefault?.();

            if (readonly) {
                return;
            }

            if (! this._momentFill(text)) {
                let at = type;

                for (const key of Array.from(text)) {
                    at = this._momentType(at, key);
                }
            }
        },

        /** What an input method left in a segment: its last character, typed as a key. */
        momentInputInput(event, type) {
            const field = event?.target;
            const last = Array.from(String(field?.value ?? '')).pop();

            if (last !== undefined && ! readonly) {
                this._momentType(type, last);
            }

            if (field) {
                field.value = this.momentInputText(type);
            }
        },

        /** Fill the segments a pasted text names; whether it named a day. */
        _momentFill(text) {
            const pasted = readPasted(text, shape);

            if (pasted === null) {
                return false;
            }

            const values = { ...this.momentInputValues };

            for (const type of order) {
                if (pasted[type] !== null) {
                    values[type] = pasted[type];
                }
            }

            this.momentInputValues = values;
            this._momentTyped = '';
            this.momentInputCheck();

            return true;
        },

        _momentSet(type, value) {
            this.momentInputValues = { ...this.momentInputValues, [type]: value };
        },

        /** One typed character, into a segment; the segment the next one goes to. */
        _momentType(type, key) {
            const period = shape.periods.length ? periodOf(key, shape.periods) : null;

            if (period !== null) {
                this._momentSet('dayPeriod', period);
                this._momentTyped = '';
                this.momentInputCheck();

                return type;
            }

            const digit = digitOf(key, shape.digits);

            if (digit === null || type === 'dayPeriod') {
                return type;
            }

            const before = this._momentTypedIn === type ? this._momentTyped : '';
            const typed = type === 'year'
                ? typeYearDigit(before, digit)
                // A day types against the longest month: the month may come after it.
                : typeDigit(before, digit, type === 'day' ? { min: 1, max: 31 } : momentRange(type, this.momentInputValues, shape.clock));

            this._momentTypedIn = type;
            this._momentTyped = typed.typed;
            this._momentSet(type, typed.value);
            this.momentInputCheck();

            const next = typed.done ? this._momentNext(type, 1) : null;

            if (next) {
                this._momentFocusSegment(next);

                return next;
            }

            return type;
        },

        /** Empty a segment, or move back from one already empty with Backspace. */
        _momentDelete(type, backward) {
            const typing = this._momentTypedIn === type && this._momentTyped !== '';

            if (this.momentInputValues[type] === null && ! typing) {
                const before = backward ? this._momentNext(type, -1) : null;

                if (before) {
                    this._momentFocusSegment(before);
                }

                return;
            }

            this._momentSet(type, null);
            this._momentTyped = '';
            this.momentInputCheck();
        },

        /** The segment before or after one, in the locale's order, or null at an end. */
        _momentNext(type, direction) {
            return order[order.indexOf(type) + direction] ?? null;
        },

        _momentFocusSegment(type) {
            this._momentTyped = '';
            this._momentTypedIn = type;
            this.$refs?.momentInputField?.querySelector('[data-wk-moment-segment="' + type + '"]')?.focus();
        },

        /** The first segment, where an empty required picker sends the focus. */
        momentInputFocusFirst() {
            this._momentFocusSegment(order[0]);
        },
    };
}
