import { position } from './floating.js';
import { insideWindow, minutesOf, readTimeLimits, slotClosed, timeText } from './time-slots.js';
import { digitOf, periodOf, segmentRange, segmentShape, segmentsOf, segmentText, stepSegment, timeFormatter, timeOfSegments, typeDigit } from './time-segments.js';

/**
 * The state and the keyboard of a time typed in segments, spread into the factory of a component
 * that shows one, beside the markup in `partials/time-input`.
 *
 * Each segment is a field of its own with the keyboard of a spin button. A digit sets it, and the
 * focus moves on once no further digit fits (utils/time-segments.js, typeDigit). The arrow keys
 * step it round its range without carrying into the next segment, Home and End go to the ends of
 * the range, `a` and `p` set the period of the day from any segment, and Backspace empties a
 * segment, or moves back from an empty one. Left and Right move between the segments.
 *
 * A time the segments make whole is checked against the limits of the list's day and becomes the
 * list's `timeListValue`; one outside the day's window or closed on the day leaves it empty, and
 * the field says why in words. The button at the end of the field opens the time list below it,
 * as suggestions with the same limits, and a time picked there sets the segments.
 *
 * Methods rather than getters, as in utils/time-list.js: a factory spreads this object into its
 * own. The host spreads the time list as well, calls timeInputCheck() when the list's day
 * changes, and hands a time picked among the suggestions to timeInputChosen() from its own
 * onTimeChosen(). A host whose field holds the typed time itself, with no dialog and no Select,
 * defines onTimeTyped(time), which every check calls with the time it found, or null.
 *
 * Lifecycle resources held on `this`, released through timeInputDestroy() in the host's destroy():
 *   - _timeInputStopList — the placement of the open suggestions.
 *
 * @param {Object} config
 * @param {number} [config.interval]
 * @param {?string} [config.minTime]  `HH:MM`
 * @param {?string} [config.maxTime]  `HH:MM`
 * @param {string[]} [config.unavailable]
 * @param {?string} [config.min]  `YYYY-MM-DDTHH:MM`
 * @param {?string} [config.max]  `YYYY-MM-DDTHH:MM`
 * @param {?string} [config.locale]  the application's locale, as BCP 47
 * @param {string} [config.timeFormat]  `auto`, `12-hour` or `24-hour`
 * @param {?string} [config.openTo]  `HH:MM`, where the suggestions stand without a time
 * @param {string} [config.hourLabel]  the names of the three segments
 * @param {string} [config.minuteLabel]
 * @param {string} [config.dayPeriodLabel]
 * @param {string} [config.unavailableMessage]  `:time` stands for the time typed
 * @param {string} [config.outsideMessage]  `:from` and `:to` stand for the start and the end of the window
 * @param {boolean} [config.readonly]  the segments can be read and moved between, not changed, and
 *   the suggestions do not open
 */
export function timeInputState(config = {}) {
    const limits = readTimeLimits(config);
    // The names of the two periods where the locale's format gives none: the two halves of the
    // segment's own name, "AM/PM" in the application's language.
    const [am, pm] = String(config.dayPeriodLabel || 'AM/PM').split('/').map((word) => word.trim());
    const shape = segmentShape(config.locale, config.timeFormat, { am, pm });
    const format = timeFormatter(config.locale, config.timeFormat);
    const names = {
        hour: config.hourLabel || 'Hour',
        minute: config.minuteLabel || 'Minute',
        dayPeriod: config.dayPeriodLabel || 'AM/PM',
    };
    const unavailableMessage = config.unavailableMessage || ':time is not available.';
    const outsideMessage = config.outsideMessage || 'Pick a time from :from to :to.';
    const readonly = config.readonly === true;
    const label = (minutes) => format.format(new Date(2000, 0, 1, Math.floor(minutes / 60), minutes % 60));

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
            ...segmentRange(part.type, shape.clock),
            sizer: part.type === 'dayPeriod' ? widest : shape.digits[8] + shape.digits[8],
            inputmode: part.type === 'dayPeriod' ? 'text' : 'numeric',
        }));
    const order = parts.filter((part) => part.segment).map((part) => part.type);

    return {
        timeInputHour: null,
        timeInputMinute: null,
        timeInputPeriod: null,
        timeInputError: '',
        timeInputListOpen: false,
        // The digits typed into one segment since it took the focus, and which segment that is.
        _timeInputTyped: '',
        _timeInputTypedIn: null,
        _timeInputStopList: null,

        /** The segments in the locale's order, with the text between them. */
        timeInputParts() {
            return parts;
        },

        _timeInputValue(type) {
            return { hour: this.timeInputHour, minute: this.timeInputMinute, dayPeriod: this.timeInputPeriod }[type] ?? null;
        },

        _timeInputSet(type, value) {
            if (type === 'hour') {
                this.timeInputHour = value;
            } else if (type === 'minute') {
                this.timeInputMinute = value;
            } else {
                this.timeInputPeriod = value;
            }
        },

        /** What a segment shows: its value, or the digits typed into it that make none yet. */
        timeInputText(type) {
            const value = this._timeInputValue(type);

            if (value === null && this._timeInputTypedIn === type && this._timeInputTyped !== '') {
                return this._timeInputTyped.split('').map((digit) => shape.digits[Number(digit)] ?? digit).join('');
            }

            return segmentText(type, value, shape);
        },

        /** A segment's `aria-valuenow`, or null while it is empty. */
        timeInputValueNow(type) {
            return this._timeInputValue(type);
        },

        /** A segment's `aria-valuetext`: two digits, or the name of the period. */
        timeInputValueText(type) {
            const text = segmentText(type, this._timeInputValue(type), shape);

            return text === '' ? null : text;
        },

        /** Whether every segment is empty. */
        timeInputBlank() {
            return order.every((type) => this._timeInputValue(type) === null);
        },

        /** Show a time, `HH:MM`, in the segments, or empty them, with no word under them. */
        timeInputShow(time) {
            const segments = segmentsOf(time, shape.clock);

            this.timeInputHour = segments.hour;
            this.timeInputMinute = segments.minute;
            this.timeInputPeriod = segments.dayPeriod;
            this._timeInputTyped = '';
            this.timeInputError = '';
        },

        /**
         * Check the time the segments make against the limits of the list's day. A time inside
         * them becomes the list's value; one outside leaves the list without a value, and the
         * field says why.
         */
        timeInputCheck() {
            const minutes = minutesOf(timeOfSegments({ hour: this.timeInputHour, minute: this.timeInputMinute, dayPeriod: this.timeInputPeriod }, shape.clock));

            if (minutes === null) {
                this.timeListValue = null;
                this.timeInputError = '';
            } else if (! insideWindow(minutes, limits)) {
                this.timeListValue = null;
                this.timeInputError = outsideMessage.replace(':from', () => label(limits.minTime)).replace(':to', () => label(limits.maxTime));
            } else if (slotClosed(this.timeListDay ?? null, minutes, limits)) {
                this.timeListValue = null;
                this.timeInputError = unavailableMessage.replace(':time', () => label(minutes));
            } else {
                this.timeListValue = timeText(minutes);
                this.timeInputError = '';
            }

            // A host whose field takes the typed time itself hears every time checked, a whole
            // one or none.
            this.onTimeTyped?.(this.timeListValue);
        },

        /** A segment that takes the focus starts its typing afresh. */
        timeInputFocus(type) {
            this._timeInputTyped = '';
            this._timeInputTypedIn = type;
        },

        /** The keys that step a segment, and the ones that move between them. */
        timeInputKeydown(event, type) {
            if (event.ctrlKey || event.metaKey || event.altKey) {
                return;
            }

            const range = segmentRange(type, shape.clock);
            const value = this._timeInputValue(type);
            const steps = {
                ArrowUp: () => stepSegment(value, range, 1),
                ArrowDown: () => stepSegment(value, range, -1),
                Home: () => range.min,
                End: () => range.max,
            };

            if (steps[event.key]) {
                event.preventDefault();

                if (readonly) {
                    return;
                }

                this._timeInputTyped = '';
                this._timeInputSet(type, steps[event.key]());
                this.timeInputCheck();

                return;
            }

            if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
                event.preventDefault();
                const next = this._timeInputNext(type, event.key === 'ArrowRight' ? 1 : -1);

                if (next) {
                    this._timeInputFocusSegment(next);
                }
            }
        },

        /**
         * What a segment is about to receive as text, a typed digit, a letter of the period or a
         * deletion, handled here rather than written: the segment shows its value, never the keys.
         * Text that spills past a segment goes on into the next, as a pasted `1430` does.
         */
        timeInputBeforeInput(event, type) {
            // An input method's text cannot be stopped while it is composed; it is read on `input`.
            if (event.isComposing || event.inputType === 'insertCompositionText') {
                return;
            }

            event.preventDefault();

            if (readonly) {
                return;
            }

            if (String(event.inputType ?? '').startsWith('delete')) {
                this._timeInputDelete(type, event.inputType === 'deleteContentBackward');

                return;
            }

            let at = type;

            for (const key of Array.from(String(event.data ?? ''))) {
                at = this._timeInputType(at, key);
            }
        },

        /** What an input method left in a segment: its last character, typed as a key. */
        timeInputInput(event, type) {
            const field = event?.target;
            const last = Array.from(String(field?.value ?? '')).pop();

            if (last !== undefined && ! readonly) {
                this._timeInputType(type, last);
            }

            if (field) {
                field.value = this.timeInputText(type);
            }
        },

        /** One typed character, into a segment; the segment the next one goes to. */
        _timeInputType(type, key) {
            const period = shape.periods.length ? periodOf(key, shape.periods) : null;

            if (period !== null) {
                this.timeInputPeriod = period;
                this._timeInputTyped = '';
                this.timeInputCheck();

                return type;
            }

            const digit = digitOf(key, shape.digits);

            if (digit === null || type === 'dayPeriod') {
                return type;
            }

            const typed = typeDigit(this._timeInputTypedIn === type ? this._timeInputTyped : '', digit, segmentRange(type, shape.clock));

            this._timeInputTypedIn = type;
            this._timeInputTyped = typed.typed;
            this._timeInputSet(type, typed.value);
            this.timeInputCheck();

            const next = typed.done ? this._timeInputNext(type, 1) : null;

            if (next) {
                this._timeInputFocusSegment(next);

                return next;
            }

            return type;
        },

        /** Empty a segment, or move back from one already empty with Backspace. */
        _timeInputDelete(type, backward) {
            const typing = this._timeInputTypedIn === type && this._timeInputTyped !== '';

            if (this._timeInputValue(type) === null && ! typing) {
                const before = backward ? this._timeInputNext(type, -1) : null;

                if (before) {
                    this._timeInputFocusSegment(before);
                }

                return;
            }

            this._timeInputSet(type, null);
            this._timeInputTyped = '';
            this.timeInputCheck();
        },

        /** The segment before or after one, in the locale's order, or null at an end. */
        _timeInputNext(type, direction) {
            return order[order.indexOf(type) + direction] ?? null;
        },

        _timeInputFocusSegment(type) {
            this._timeInputTyped = '';
            this._timeInputTypedIn = type;
            this.$refs?.timeInputField?.querySelector('[data-wk-time-segment="' + type + '"]')?.focus();
        },

        /** The first segment, where a day picked without a time sends the focus. */
        timeInputFocusFirst() {
            this._timeInputFocusSegment(order[0]);
        },

        // ── The suggestions ──────────────────────────────────────────────

        /** Open or close the suggestions, from the button at the end of the field. */
        async toggleTimeInputList() {
            if (this.timeInputListOpen) {
                this.closeTimeInputList();

                return;
            }

            // A pick from the suggestions would change a time that is only shown.
            if (readonly) {
                return;
            }

            this.timeInputListOpen = true;
            await this.$nextTick?.();

            const field = this.$refs?.timeInputField;
            const list = this.$refs?.timeInputList;

            if (field && list) {
                this._timeInputStopList?.();
                const { stop } = await position(field, list, {
                    placement: 'bottom-end',
                    offset: 4,
                    fitViewport: true,
                    // A Livewire update patches the dialog the list stands in against its
                    // template, whose `style` carries none of the placement: this puts it back.
                    repairErasure: true,
                });

                // Closed while the placement waited for a box: nothing is left to watch.
                if (! this.timeInputListOpen) {
                    stop?.();

                    return;
                }

                this._timeInputStopList = stop ?? null;
            }

            // The list scrolls to the time typed, a closed one too, else to the time it opens to,
            // and the keyboard starts on the open time it scrolls to.
            const now = new Date();
            const near = this.timeListValue
                ?? timeOfSegments({ hour: this.timeInputHour, minute: this.timeInputMinute, dayPeriod: this.timeInputPeriod }, shape.clock)
                ?? config.openTo
                ?? timeText(now.getHours() * 60 + now.getMinutes());

            this.timeListActive = this.timeListNear(near);
            this.timeListScrollTo(near);
            this.$nextTick?.(() => {
                this.$refs?.timeList?.querySelector('[role="option"][tabindex="0"]')?.focus({ preventScroll: true });
            });
        },

        /** Close the suggestions, and hand the focus back to their button when it was in them. */
        closeTimeInputList(returnFocus = true) {
            if (! this.timeInputListOpen) {
                return;
            }

            const list = this.$refs?.timeInputList;
            const hadFocus = Boolean(list && typeof document !== 'undefined' && list.contains(document.activeElement));

            this.timeInputListOpen = false;
            this._timeInputStopList?.();
            this._timeInputStopList = null;

            if (returnFocus && hadFocus) {
                this.$refs?.timeInputToggle?.focus({ preventScroll: true });
            }
        },

        /**
         * Escape closes open suggestions and is spent there, so the dialog around them stays
         * open (utils/focus-trap.js, escapeUnlessHandled); the next Escape closes the dialog.
         */
        timeInputEscape(event) {
            if (! this.timeInputListOpen) {
                return;
            }

            event.preventDefault();
            this.closeTimeInputList();
        },

        /** A focus that leaves the field and its suggestions closes them. */
        timeInputFocusOut(event) {
            const row = this.$refs?.timeInput;

            if (this.timeInputListOpen && row && ! row.contains(event?.relatedTarget ?? null)) {
                this.closeTimeInputList(false);
            }
        },

        /** A time picked among the suggestions: the segments show it, and the list closes. */
        timeInputChosen(time) {
            this.timeInputShow(time);
            this.timeInputCheck();
            this.closeTimeInputList();
        },

        /** Release what the field holds; the host calls it from its destroy(). */
        timeInputDestroy() {
            this._timeInputStopList?.();
            this._timeInputStopList = null;
        },
    };
}
