import { hourAway, minutesOf, readTimeLimits, timeSlots, typedSlot } from './time-slots.js';
import { timeFormatter } from './time-segments.js';

/**
 * The state and the keyboard of a time list, spread into the factory of a component that shows
 * one, beside the markup in `partials/time-list`.
 *
 * The host tells the list the day it is for through `timeListDay`, and the chosen time through
 * `timeListValue`, and it hears a choice in `onTimeChosen(value)`, which it defines. Methods
 * rather than getters throughout: a factory spreads this object into its own, and a spread copies
 * a getter's value once instead of the getter. The list is
 * a `listbox`: one option takes the tab stop (the chosen time, else the one the keyboard stands
 * on, else the open time at or after a chosen time that falls between two of the list's, else the
 * first open one), the arrow keys move between open times, Home and End go to the
 * first and the last, PageUp and PageDown an hour, typing goes to the time typed, and Enter or
 * Space chooses. A closed time is passed over, named as unavailable, and never chosen.
 *
 * Lifecycle resources held on `this`, released through timeListDestroy() in the host's destroy():
 *   - _timeTypedTimer — the timeout that ends a typed run.
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
 * @param {string} [config.unavailableLabel]  the word appended to the name of a closed time
 */
export function timeListState(config = {}) {
    const limits = readTimeLimits(config);
    const format = timeFormatter(config.locale, config.timeFormat);
    const unavailableWord = config.unavailableLabel || '';

    // The slots of the day last asked for, so that a render reads them without building them
    // again; they change only with the day.
    let cache = { day: undefined, slots: [] };

    return {
        timeListDay: null,
        timeListValue: null,
        timeListActive: -1,
        _timeTyped: '',
        _timeTypedTimer: 0,

        /**
         * The times of the list's day, each with its label, its name, whether it is chosen and
         * whether it holds the tab stop.
         */
        timeListSlots() {
            const slots = this._timeListDaySlots();
            const stop = this._timeListStop(slots);

            return slots.map((slot, index) => ({
                ...slot,
                selected: slot.value === this.timeListValue,
                ariaDisabled: slot.closed ? 'true' : null,
                tabStop: index === stop,
            }));
        },

        /** The times of the list's day with their labels and names, built once for each day. */
        _timeListDaySlots() {
            if (cache.day !== this.timeListDay) {
                cache = {
                    day: this.timeListDay,
                    slots: timeSlots(this.timeListDay, limits).map((slot) => {
                        const label = format.format(new Date(2000, 0, 1, Math.floor(slot.minutes / 60), slot.minutes % 60));

                        return { ...slot, label, name: slot.closed && unavailableWord ? label + ', ' + unavailableWord : label };
                    }),
                };
            }

            return cache.slots;
        },

        /**
         * The label of the list's option at `index`, read from the times of the day alone.
         *
         * The list writes its text through this rather than through the slot it iterates: the
         * slots are new objects whenever the tab stop moves, as it does on the focus a press gives
         * an option, and a text bound to the slot is written again then. WebKit sends no click when
         * the text under the pointer is replaced between the press and the release.
         */
        timeListLabelAt(index) {
            return this._timeListDaySlots()[index]?.label ?? '';
        },

        /** A time as the list writes it, `9:30 AM` or `09:30` after the locale and the format. */
        timeLabel(value) {
            const minutes = minutesOf(value);

            return minutes === null ? '' : format.format(new Date(2000, 0, 1, Math.floor(minutes / 60), minutes % 60));
        },

        /**
         * The option that takes the tab stop: the chosen time, the active one, the open time the
         * list scrolls to for a chosen time between two of its times, or the first open one.
         */
        _timeListStop(slots) {
            const chosen = slots.findIndex((slot) => slot.value === this.timeListValue);

            if (chosen !== -1) {
                return chosen;
            }

            if (this.timeListActive >= 0 && this.timeListActive < slots.length) {
                return this.timeListActive;
            }

            const near = this.timeListNear(this.timeListValue);

            return near !== -1 ? near : slots.findIndex((slot) => ! slot.closed);
        },

        /**
         * The open time at or after a time, `HH:MM`, or the last open one before it; -1 for no
         * time or no open one. A time between two of the list's, as a typed one is, stands here.
         */
        timeListNear(time) {
            const minutes = minutesOf(time);

            if (minutes === null) {
                return -1;
            }

            const slots = this._timeListDaySlots();
            const after = slots.findIndex((slot) => ! slot.closed && slot.minutes >= minutes);

            if (after !== -1) {
                return after;
            }

            for (let index = slots.length - 1; index >= 0; index--) {
                if (! slots[index].closed) {
                    return index;
                }
            }

            return -1;
        },

        /** Choose the time at an index, unless it is closed. */
        chooseTimeSlot(index) {
            const slot = this.timeListSlots()[index];

            if (! slot || slot.closed) {
                return;
            }

            this.timeListValue = slot.value;
            this.timeListActive = index;
            this.onTimeChosen?.(slot.value);
        },

        /** Move the keyboard to an index and its option into view. */
        _timeListFocus(index) {
            if (index < 0) {
                return;
            }

            this.timeListActive = index;
            this.$nextTick?.(() => {
                const option = this.$refs?.timeList?.querySelectorAll('[role="option"]')[index];
                option?.focus({ preventScroll: true });
                option?.scrollIntoView?.({ block: 'nearest' });
            });
        },

        /** The keyboard of the list. */
        timeListKeydown(event) {
            const slots = this.timeListSlots();
            const open = slots.map((slot, index) => (slot.closed ? -1 : index)).filter((index) => index !== -1);
            const at = this.timeListActive >= 0 ? this.timeListActive : this._timeListStop(slots);
            const step = (direction) => {
                const next = direction > 0 ? open.find((index) => index > at) : [...open].reverse().find((index) => index < at);

                return next ?? at;
            };

            const moves = {
                ArrowDown: () => step(1),
                ArrowUp: () => step(-1),
                Home: () => open[0] ?? -1,
                End: () => open.at(-1) ?? -1,
                PageDown: () => hourAway(slots, at, 1),
                PageUp: () => hourAway(slots, at, -1),
            };

            if (moves[event.key]) {
                event.preventDefault();
                this._timeListFocus(moves[event.key]());

                return;
            }

            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                this.chooseTimeSlot(at);

                return;
            }

            // Typing goes to the time typed: digits and a colon, gathered while they come quickly.
            if (/^[\d:]$/.test(event.key) && ! event.ctrlKey && ! event.metaKey && ! event.altKey) {
                event.preventDefault();
                clearTimeout(this._timeTypedTimer);
                this._timeTyped += event.key;
                this._timeTypedTimer = setTimeout(() => { this._timeTyped = ''; }, 700);
                this._timeListFocus(typedSlot(this._timeTyped, slots));
            }
        },

        /**
         * Scroll the list so that the chosen time stands at the top with one row above it, or the
         * time given, `HH:MM`, without a choice. Instant: a list that slides into place moves
         * under the reader's eyes, with or without a preference for reduced motion.
         */
        timeListScrollTo(fallback = null) {
            this.$nextTick?.(() => {
                const list = this.$refs?.timeList;
                const slots = this.timeListSlots();
                const target = minutesOf(this.timeListValue ?? fallback);
                const from = target === null ? -1 : slots.findIndex((slot) => slot.minutes >= target);
                // A time after the last one in the list scrolls to the end of it.
                const index = target !== null && from === -1 ? slots.length - 1 : from;
                const option = index >= 0 ? list?.querySelectorAll('[role="option"]')[index] : null;

                if (list && option) {
                    list.scrollTop = Math.max(0, option.offsetTop - option.offsetHeight);
                }
            });
        },

        /** Release what the list holds; the host calls it from its destroy(). */
        timeListDestroy() {
            clearTimeout(this._timeTypedTimer);
            this._timeTypedTimer = 0;
            this._timeTyped = '';
        },
    };
}
