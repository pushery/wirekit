import { momentInputState } from '../utils/moment-input.js';
import { watchCurrent } from '../utils/watch-current.js';

/**
 * WireKit Datetime Picker, the field of one end of a range typed in place.
 *
 * A datetime picker with `type="input"` and `range` takes its start and its end in two fields,
 * each the moment typed in segments in the locale's order (utils/moment-input.js), which a
 * component can hold once. Each field is this component, nested in the picker. It reads the moment
 * the picker shows for its end through rangeMomentShown(), each time the picker counts a new
 * showing in rangeFieldShows: a pick in the dialog, a clear, a value the server renders and a form
 * reset. What the field's segments make goes back through rangeMomentTyped(), and the field holds
 * its segments while they are typed, a moment half typed among them.
 *
 * The sentence under the field is its own, why its moment cannot be taken, and under the end the
 * picker's about the range as well (rangeTypedError): an end that does not come after the start,
 * or a range shorter or longer than `min-range` and `max-range` allow.
 *
 * @param {Object} config
 * @param {string} [config.end]  `start` or `end`
 * @param {string} [config.locale]  the application's locale, as BCP 47
 * @param {string} [config.timeFormat]  `auto`, `12-hour` or `24-hour`
 * @param {Object} [config.time]  the limits of the times (utils/time-slots.js)
 * @param {Object} [config.days]  the days the calendar closes (utils/date-limits.js)
 */
export default function wirekitDatetimePickerMoment(config = {}) {
    const end = config.end === 'end' ? 'end' : 'start';

    return {
        ...momentInputState(config),

        init() {
            this._momentFollow();

            // A new showing of the picker reaches the field. It is watched through a getter of
            // this field, which reads the picker's count through the scope the field is nested in:
            // a key the field's own scope does not offer would be resolved against `window`.
            watchCurrent(this, 'followedShows', () => this._momentFollow());
        },

        /** How many times the picker has shown its value in the fields. */
        get followedShows() {
            return this.rangeFieldShows ?? 0;
        },

        /** The sentence under the field: why its moment cannot be taken, and under the end why the range cannot. */
        get momentShownError() {
            return this.momentInputError || (end === 'end' ? (this.rangeTypedError ?? '') : '');
        },

        /** Whether the field says its moment, or the range at its end, cannot be taken. */
        momentInputInvalid() {
            return this.momentShownError !== '';
        },

        /** The moment the picker shows for this end, into the segments. */
        _momentFollow() {
            this.momentInputShow(this.rangeMomentShown?.(end) ?? '');
        },

        /** What the segments make, a moment or null, handed to the picker (utils/moment-input.js calls it). */
        onMomentTyped(moment) {
            this.rangeMomentTyped?.(end, moment);
        },
    };
}
