import { onFormReset } from '../utils/form-reset.js';
import { watchModelEvents } from '../utils/model-events.js';
import { adoptPageLabel } from '../utils/page-label.js';
import { PHONE_MODAL_MEDIA, pickerDialogState } from '../utils/picker-dialog.js';
import { requiredCheckState } from '../utils/required-check.js';
import { followPageModel, hasModelBinding, observeServerValue, ownValues, WK_SERVER_VALUE_ATTRIBUTE } from '../utils/server-value.js';
import { timeInputState } from '../utils/time-input.js';
import { timeListState } from '../utils/time-list.js';

/**
 * WireKit Time Picker Alpine Component, for the time picker with `popover`.
 *
 * Without `popover` the time picker is a native time field and runs no script. With it, the time
 * is picked in one of two ways, and this component holds the value, a wall-clock time written
 * `HH:MM` in a hidden field with the native field's name:
 *
 *   - from a list: a trigger shaped like the field opens a dialog with the time list
 *     (utils/time-list.js, the dialog from utils/picker-dialog.js); a time picked is taken and
 *     closes it.
 *   - typed, with `time="input"`: the field holds the hour, the minute and the period of the day
 *     as segments (utils/time-input.js), and its button opens the time list as suggestions. A
 *     time typed whole and inside the limits is the value at once; while the segments make none,
 *     the field is empty, as a native time field is while a time is half typed.
 *
 * Lifecycle resources held on `this`, released in destroy():
 *   - the dialog's trap, placement and page listeners, through pickerDialogDestroy().
 *   - _stopServerValue — the observer on the value the server renders on the root.
 *   - _modelEvents — `change` and `blur` on the hidden field (utils/model-events.js).
 *   - _stopFormReset — the listener that puts the starting value back on a form reset.
 *   - _stopPageLabel — the observers that keep a label of the page's own in the picker's name
 *     (utils/page-label.js).
 *   - the time list's typing timer, through timeListDestroy(), and the placement of the
 *     suggestions, through timeInputDestroy().
 *
 * Events, on the root, bubbling: `wirekit:time-picker-open`, `wirekit:time-picker-close` and
 * `wirekit:time-picker-clear`.
 */

const TIME = /^([01]\d|2[0-3]):[0-5]\d$/;

/**
 * @param {Object} config
 * @param {string} [config.locale]  the application's locale, as BCP 47
 * @param {string} [config.panelId]  the dialog's id
 * @param {string} [config.timeFormat]  `auto`, `12-hour` or `24-hour`
 * @param {Object} [config.time]  the time list's limits (utils/time-slots.js)
 * @param {string} [config.unavailableLabel]
 * @param {?Object} [config.timeInput]  the words of the typed time with `time="input"`, else null
 */
export default function wirekitTimePicker(config = {}) {
    const typed = config.timeInput !== null && typeof config.timeInput === 'object';
    const own = ownValues();

    return {
        ...requiredCheckState(),
        ...timeListState({ ...(config.time ?? {}), locale: config.locale, timeFormat: config.timeFormat, unavailableLabel: config.unavailableLabel }),
        ...timeInputState({ ...(config.time ?? {}), ...(config.timeInput ?? {}), locale: config.locale, timeFormat: config.timeFormat }),
        ...pickerDialogState({
            panelId: config.panelId ?? null,
            openEvent: 'wirekit:time-picker-open',
            closeEvent: 'wirekit:time-picker-close',
            missingTriggerWarning: '[wirekit] time-picker: the trigger slot has no button or link, so a keyboard cannot open the picker. Put a <button> in the slot.',
            // The time the list's keyboard stands on: the value's, or the first open one.
            initialFocus: (panel) => panel.querySelector('[role="option"][tabindex="0"]'),
            // On a phone the dialog is a modal over the page, as the datetime picker's is.
            modalMedia: PHONE_MODAL_MEDIA,
        }),

        // The committed time, `HH:MM`, or '' for none. Read from the root in init(): in `x-data`
        // it made every value a different expression.
        value: '',
        _resetValue: '',
        _stopServerValue: null,
        _modelEvents: null,
        _stopFormReset: null,
        _stopPageLabel: null,

        init() {
            const root = this.$root;

            // `$root` is checked rather than assumed, as a unit harness hands a bare stub.
            const seed = typeof root?.getAttribute === 'function' ? root.getAttribute(WK_SERVER_VALUE_ATTRIBUTE) : null;
            this._take(seed ?? '');
            this._resetValue = this.value;

            this._modelEvents = watchModelEvents(root, () => this.$refs?.input);
            this._stopFormReset = onFormReset(root, () => this.$refs?.input, () => this._restore());

            // A time the server sets reaches the field. The answer to a pick of the reader's own
            // changes nothing, as on the datetime picker.
            this._stopServerValue = observeServerValue(root, (next) => {
                this._resetValue = next;

                if ((hasModelBinding(this.$refs?.input) && own.answers(next)) || next === this.value) {
                    return;
                }

                this._take(next);
            });

            // A time an Alpine page's model writes into the field reaches the list and the segments,
            // as one the server renders does (utils/server-value.js).
            this.$nextTick?.(() => followPageModel(this, this.$refs?.input, (value) => {
                if (String(value ?? '') !== this.value) {
                    this._take(value);
                }
            }));

            // A typed time opens no dialog.
            if (! typed) {
                this.pickerDialogInit();
            }

            // A label of the page's own, pointed at the picker, names it where the server could only
            // make a name from `name` (utils/page-label.js). It looks after this tick, once the
            // teleported dialog is in the page, and not at all when the picker is gone by then.
            this._stopPageLabel = () => {};
            this.$nextTick?.(() => {
                if (this._stopPageLabel !== null) {
                    this._stopPageLabel = adoptPageLabel(root, () => this.$refs?.panel);
                }
            });
        },

        destroy() {
            this.pickerDialogDestroy();
            this._stopPageLabel?.();
            this._stopPageLabel = null;
            this._stopServerValue?.();
            this._stopServerValue = null;
            this._modelEvents?.dispose();
            this._modelEvents = null;
            this._stopFormReset?.();
            this._stopFormReset = null;
            this.timeListDestroy();
            this.timeInputDestroy();
        },

        /** A time as the field holds it, into the list and the segments. */
        _take(value) {
            const time = TIME.test(String(value ?? '').trim()) ? String(value).trim() : null;

            this.value = time ?? '';
            this.timeListValue = time;
            // Where the keyboard stood in the list belongs to the dialog that is gone.
            this.timeListActive = -1;

            if (typed) {
                this.timeInputShow(time);
            }
        },

        /** Back to the starting time after a form reset, without the events a pick fires. */
        _restore() {
            this._take(this._resetValue);
            this.$refs?.input?.setAttribute('value', this.value);
            this.requiredMessage = '';
        },

        /** What the required check reads: empty exactly while no time is chosen. */
        get requiredValue() {
            return this.value;
        },

        /** Where an empty required picker sends the focus: the trigger, or the first segment. */
        _focusRequiredControl() {
            if (typed) {
                this.timeInputFocusFirst();

                return;
            }

            this._trigger()?.focus();
        },

        // ── The trigger ──────────────────────────────────────────────────

        /** The time the trigger shows, or '' without a value. */
        get shownValue() {
            return this.value ? this.timeLabel(this.value) : '';
        },

        /** Whether the trigger shows its placeholder. */
        get showsPlaceholder() {
            return this.shownValue === '';
        },

        // ── The dialog (utils/picker-dialog.js) ──────────────────────────

        /** The list starts from the value, whatever the keyboard did in it the last time. */
        _dialogOpening() {
            this._take(this.value);
        },

        /** The list stands at the value's time, or at the time now without one. */
        _dialogOpened() {
            const now = new Date();

            this.timeListScrollTo(String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0'));
        },

        // ── Picking ──────────────────────────────────────────────────────

        /** A time picked in the list: taken, and the dialog closes; typed, it fills the segments. */
        onTimeChosen(time) {
            if (typed) {
                this.timeInputChosen(time);

                return;
            }

            this._commit(time);
            this.closePanel();
        },

        /** A typed time checked (utils/time-input.js): a whole one is the value, none empties it. */
        onTimeTyped(time) {
            if (! typed || (time ?? '') === this.value) {
                return;
            }

            this._commit(time ?? '');
        },

        /** Empty the field. */
        clear() {
            if (this.value === '') {
                return;
            }

            this._commit('');
            this._take('');
            this.$root?.dispatchEvent(new CustomEvent('wirekit:time-picker-clear', { bubbles: true }));
            this._trigger()?.focus();
        },

        /** A time into the field, with the events a native field fires for a pick. */
        _commit(value) {
            this.value = value;
            own.note(value);

            const input = this.$refs?.input;
            input?.setAttribute('value', value);

            if (input) {
                input.value = value;
            }

            input?.dispatchEvent(new Event('input', { bubbles: true }));
            this._modelEvents?.commit();
            this.requiredMessage = '';
        },
    };
}
