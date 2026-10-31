import { onFormReset } from '../utils/form-reset.js';
import { watchModelEvents } from '../utils/model-events.js';
import { adoptPageLabel } from '../utils/page-label.js';
import { PHONE_MODAL_MEDIA, pickerDialogState } from '../utils/picker-dialog.js';
import { requiredCheckState } from '../utils/required-check.js';
import { followPageModel, hasModelBinding, observeServerValue, ownValues, WK_SERVER_VALUE_ATTRIBUTE } from '../utils/server-value.js';

/**
 * WireKit Date Picker Alpine Component, for the date picker with `popover`.
 *
 * Without `popover` the date picker is a native date field and runs no script. With it, its
 * trigger opens a dialog with the `calendar` component, and this component holds the value: a
 * day written `YYYY-MM-DD`, or with `range` two days. Each end of a range is a field of its own,
 * `name[start]` and `name[end]`, bound to `property.start` and `property.end`, as the native
 * range sends them, so switching between the two forms changes nothing on the server.
 *
 * A day picked is taken at once and closes the dialog. In a range the first day waits for the
 * second, and the second takes both and closes it; a dialog closed between the two leaves the
 * range as it was. The calendar shows the value it is handed on `data-wk-server-value`, and its
 * pick reaches this component as the `input` of its hidden field. The dialog itself comes from
 * utils/picker-dialog.js.
 *
 * With `presets` a range has a third field, `name[preset]` bound to `property.preset`, which takes
 * the preset the calendar's own field names for the same pick, empty for a range picked day by
 * day. It is written after the two ends, as a new end drops the preset of a Values\DateRange.
 *
 * Lifecycle resources held on `this`, released in destroy():
 *   - the dialog's trap, placement and page listeners, through pickerDialogDestroy().
 *   - _stopServerValue — the observer on the value the server renders on the root.
 *   - _modelEvents — `change` and `blur` on the hidden fields (utils/model-events.js).
 *   - _stopFormReset — the listener that puts the starting value back on a form reset.
 *   - _stopPageLabel — the observers that keep a label of the page's own in the picker's name
 *     (utils/page-label.js).
 *
 * Events, on the root, bubbling: `wirekit:date-picker-open`, `wirekit:date-picker-close` and
 * `wirekit:date-picker-clear`.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/examples/datepicker-dialog/
 */

const DAY = /^\d{4}-\d{2}-\d{2}$/;

/** The days of a value: one, or the two ends of a range written `start/end`, each null when absent. */
function readDays(value) {
    const [start = '', end = ''] = String(value ?? '').trim().split('/');

    return { start: DAY.test(start) ? start : null, end: DAY.test(end) ? end : null };
}

/** A day as a date at midnight, for formatting. */
function dateOf(day) {
    const [year, month, date] = day.split('-').map(Number);

    return new Date(year, month - 1, date);
}

/**
 * @param {Object} config
 * @param {string} [config.locale]  the application's locale, as BCP 47
 * @param {string} [config.panelId]  the dialog's id
 * @param {boolean} [config.range]  two days rather than one
 */
export default function wirekitDatePicker(config = {}) {
    const range = config.range === true;
    const format = new Intl.DateTimeFormat(config.locale || undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    const own = ownValues();

    return {
        ...requiredCheckState(),
        ...pickerDialogState({
            panelId: config.panelId ?? null,
            openEvent: 'wirekit:date-picker-open',
            closeEvent: 'wirekit:date-picker-close',
            missingTriggerWarning: '[wirekit] date-picker: the trigger slot has no button or link, so a keyboard cannot open the picker. Put a <button> in the slot.',
            // The day the calendar's keyboard stands on, as the date-picker dialog of the WAI-ARIA
            // practices focuses it.
            initialFocus: (panel) => panel.querySelector('button[data-wk-day][tabindex="0"]'),
            // On a phone the dialog is a modal over the page, as the datetime picker's is.
            modalMedia: PHONE_MODAL_MEDIA,
        }),

        // The committed day, or the first day of a range; '' for none. Read from the root in
        // init(): in `x-data` it made every value a different expression.
        start: '',
        // The last day of a range; '' for none, and always '' for a single day.
        end: '',
        // The preset the range was chosen by, with `presets`; '' for one picked day by day.
        preset: '',
        _resetPreset: '',
        // The first day of a range picked in the calendar while it waits for the second; null
        // hands the calendar the committed value.
        calendarDraft: null,
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
            this._resetValue = this.serverValue();
            this.preset = this.$refs?.inputPreset?.getAttribute('value') ?? '';
            this._resetPreset = this.preset;

            this._modelEvents = watchModelEvents(root, () => this._fields());
            this._stopFormReset = onFormReset(root, () => this._fields()[0], () => this._restore());

            // A value the server sets reaches the trigger and the calendar. The answer to a pick of
            // the reader's own changes nothing, as on the datetime picker.
            this._stopServerValue = observeServerValue(root, (next) => {
                this._resetValue = next;

                if ((this._fields().some((field) => hasModelBinding(field)) && own.answers(next)) || next === this.serverValue()) {
                    return;
                }

                this._take(next);
            });

            // A day an Alpine page's model writes into the fields reaches the trigger and the
            // calendar, as one the server renders does (utils/server-value.js).
            this.$nextTick?.(() => {
                const [first, second, preset] = this._fields();
                const pull = () => {
                    const start = String(first?._x_model?.get?.() ?? '');
                    const end = range ? String(second?._x_model?.get?.() ?? '') : '';
                    const value = start && end ? start + '/' + end : start;

                    if (value !== this.serverValue()) {
                        this._take(value);
                    }
                };

                followPageModel(this, first, pull);

                if (range) {
                    followPageModel(this, second, pull);
                    followPageModel(this, preset, (value) => {
                        if (typeof value === 'string' && value !== this.preset) {
                            this.preset = value;
                        }
                    });
                }
            });

            this.pickerDialogInit();

            // A label of the page's own, pointed at the trigger, names it where the server could only
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
        },

        /** The hidden fields that carry the value: one, or the two ends of a range and its preset. */
        _fields() {
            return (range ? [this.$refs?.inputStart, this.$refs?.inputEnd, this.$refs?.inputPreset] : [this.$refs?.input]).filter(Boolean);
        },

        /** A value as the root carries it: a day, or `start/end`. */
        _take(value) {
            const { start, end } = readDays(value);

            this.start = start ?? '';
            this.end = range && start ? (end ?? '') : '';
        },

        /** The value as the root and the calendar write it: the day, `start/end`, or '' for none. */
        serverValue() {
            if (! range) {
                return this.start;
            }

            return this.start ? (this.end ? this.start + '/' + this.end : this.start) : '';
        },

        /** Back to the starting value after a form reset, without the events a pick fires. */
        _restore() {
            this._take(this._resetValue);
            this.preset = this._resetPreset;
            this._write();
            this.requiredMessage = '';
        },

        /** What the required check reads: empty while there is no day, or no whole range. */
        get requiredValue() {
            return range ? (this.start && this.end ? this.serverValue() : '') : this.start;
        },

        /** The trigger is where an empty required picker sends the focus. */
        _focusRequiredControl() {
            this._trigger()?.focus();
        },

        // ── The trigger ──────────────────────────────────────────────────

        /**
         * What the trigger shows: the day, or the range from its first to its last day, each
         * written whole and joined as the server writes them, so the text does not change when the
         * script starts.
         */
        get shownValue() {
            if (! this.start) {
                return '';
            }

            const first = format.format(dateOf(this.start));

            return range && this.end ? first + ' – ' + format.format(dateOf(this.end)) : first;
        },

        /** Whether the trigger shows its placeholder. */
        get showsPlaceholder() {
            return this.shownValue === '';
        },

        // ── The dialog (utils/picker-dialog.js) ──────────────────────────

        /** A range half picked in the calendar goes with the dialog: the calendar starts again from the value. */
        _dialogClosed() {
            this.calendarDraft = null;
        },

        /** The value the calendar shows: the committed one, or the first day of a range in progress. */
        calendarValue() {
            return this.calendarDraft ?? this.serverValue();
        },

        // ── Picking ──────────────────────────────────────────────────────

        /**
         * A day or a range picked in the calendar, heard as the `input` of its hidden field. Only
         * that field's: its two fields for the ends of a range fire `input` as well.
         */
        onCalendarInput(event) {
            const field = event?.target;

            if (! field || field.getAttribute?.('x-ref') !== 'hiddenInput') {
                return;
            }

            const { start, end } = readDays(field.value);

            if (! start) {
                return;
            }

            if (range && ! end) {
                // The first day of a range waits for the second.
                this.calendarDraft = start;

                return;
            }

            this.calendarDraft = null;
            // The preset of the same pick, which the calendar writes before it sends the range.
            const preset = range ? (field.closest?.('[x-data]')?.querySelector('[x-ref="hiddenPreset"]')?.value ?? '') : '';

            this._commit(start, range ? end : '', preset);
            this.closePanel();
        },

        /** Empty the field. */
        clear() {
            if (! this.start) {
                return;
            }

            this._commit('', '', '');
            this.$root?.dispatchEvent(new CustomEvent('wirekit:date-picker-clear', { bubbles: true }));
            this._trigger()?.focus();
        },

        /** A day or a range into the hidden fields, with the events a native field fires for a pick. */
        _commit(start, end, preset = '') {
            this.start = start;
            this.end = range ? end : '';
            this.preset = range ? preset : '';
            own.note(this.serverValue());
            this._write();

            for (const field of this._fields()) {
                field.dispatchEvent(new Event('input', { bubbles: true }));
            }

            this._modelEvents?.commit();
            this.requiredMessage = '';
        },

        /** The value into the hidden fields, as the attribute a form sends and the property `wire:model` reads. */
        _write() {
            const values = range ? [this.start, this.end, this.preset] : [this.start];

            this._fields().forEach((field, index) => {
                field.setAttribute('value', values[index] ?? '');
                field.value = values[index] ?? '';
            });
        },
    };
}
