import { timeInputState } from '../utils/time-input.js';
import { timeListState } from '../utils/time-list.js';
import { watchCurrent } from '../utils/watch-current.js';

/**
 * WireKit Datetime Picker, the time of one end of a range.
 *
 * A datetime picker with `range` takes the time of each end in a row of its own below the
 * calendar, one for the start and one for the end. Each row is this component, nested in the
 * picker, with a time list and a typed time of its own spread in (utils/time-list.js,
 * utils/time-input.js), which a component can hold once. The row reads the day of its end and the
 * time the picker holds for it from the picker, through rangeDayOf() and rangeTimeOf(), and hands
 * every time it takes back through rangeTimeTaken(); it holds no value of its own.
 *
 * Without `time="input"` the row is a field that opens the list of times below it
 * (partials/time-select); with it, the time is typed in segments with the list as suggestions
 * (partials/time-input). Both check a time against the limits of the end's day, and a time that a
 * new day closes is handed back as none, with the sentence that says why.
 *
 * Lifecycle resources held on `this`, released in destroy():
 *   - the time list's typing timer, through timeListDestroy(), and the placement of the open list,
 *     through timeInputDestroy().
 *
 * @param {Object} config
 * @param {string} [config.end]  `start` or `end`
 * @param {string} [config.locale]  the application's locale, as BCP 47
 * @param {string} [config.timeFormat]  `auto`, `12-hour` or `24-hour`
 * @param {Object} [config.time]  the time list's limits (utils/time-slots.js)
 * @param {string} [config.unavailableLabel]
 * @param {Object} [config.timeInput]  the words of a typed time (utils/time-input.js)
 * @param {?string} [config.openTo]  `HH:MM`, where the list stands without a time
 * @param {string} [config.placeholder]  what the field of a time picked from the list shows without one
 */
export default function wirekitDatetimePickerTime(config = {}) {
    const end = config.end === 'end' ? 'end' : 'start';

    return {
        ...timeListState({ ...(config.time ?? {}), locale: config.locale, timeFormat: config.timeFormat, unavailableLabel: config.unavailableLabel }),
        ...timeInputState({ ...(config.time ?? {}), ...(config.timeInput ?? {}), locale: config.locale, timeFormat: config.timeFormat, openTo: config.openTo }),

        init() {
            this._timeFollow(this.followedDay, this.followedTime);

            // The day of this end and the time the picker holds for it follow the picker, and the
            // list closes with its dialog. Each is watched through a getter of this row, which
            // reads the picker's state through the scope the row is nested in: a key the row's own
            // scope does not offer would be resolved against `window` where the picker is absent.
            watchCurrent(this, 'followedDay', () => this._timeFollow(this.followedDay, this.followedTime));
            watchCurrent(this, 'followedTime', () => this._timeFollow(this.followedDay, this.followedTime));
            watchCurrent(this, 'dialogOpen', (open) => {
                if (! open) {
                    this.closeTimeInputList(false);
                }
            });
        },

        /** The day of this end in the picker's dialog, or null. */
        get followedDay() {
            return this.rangeDayOf?.(end) ?? null;
        },

        /** The time the picker holds for this end, or null. */
        get followedTime() {
            return this.rangeTimeOf?.(end) ?? null;
        },

        /** Whether the picker's dialog is open. */
        get dialogOpen() {
            return this.isOpen === true;
        },

        destroy() {
            this.timeListDestroy();
            this.timeInputDestroy();
        },

        /**
         * The day and the time the picker holds for this end. A time the picker sets is shown as
         * it is; a new day checks the time shown against its own limits, and the check hands the
         * picker what it found.
         */
        _timeFollow(day, time) {
            const dayChanged = day !== this.timeListDay;

            this.timeListDay = day;

            if (time !== this.timeListValue) {
                this.timeInputShow(time);
                this.timeListValue = time;
            }

            if (dayChanged && ! this.timeInputBlank()) {
                this.timeInputCheck();
            }
        },

        /** A time picked in the list (utils/time-list.js calls it). */
        onTimeChosen(time) {
            this.timeInputChosen(time);
        },

        /** Every time checked, a whole one or none (utils/time-input.js calls it). */
        onTimeTyped(time) {
            this.rangeTimeTaken?.(end, time);
        },

        /** What the field of a time picked from the list shows: the time, or its placeholder. */
        timeSelectText() {
            return this.timeListValue ? this.timeLabel(this.timeListValue) : (config.placeholder || 'Time');
        },

        /** Whether that field shows its placeholder. */
        timeSelectEmpty() {
            return ! this.timeListValue;
        },
    };
}
