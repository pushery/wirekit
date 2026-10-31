import { onFormReset } from '../utils/form-reset.js';
import { watchModelEvents } from '../utils/model-events.js';
import { adoptPageLabel } from '../utils/page-label.js';
import { PHONE_MODAL_MEDIA, pickerDialogState } from '../utils/picker-dialog.js';
import { requiredCheckState } from '../utils/required-check.js';
import { momentInputState } from '../utils/moment-input.js';
import { followPageModel, hasModelBinding, observeServerValue, ownValues, WK_SERVER_VALUE_ATTRIBUTE } from '../utils/server-value.js';
import { timeInputState } from '../utils/time-input.js';
import { timeListState } from '../utils/time-list.js';
import { dayEdges, insideWindow, minutesOf, readTimeLimits, slotClosed, timeSlots, timeText } from '../utils/time-slots.js';

/**
 * WireKit Datetime Picker Alpine Component.
 *
 * A field that opens a dialog with a calendar and a time list, and holds a moment written
 * `YYYY-MM-DDTHH:MM`, a wall-clock time in the application's time zone.
 *
 * A value takes both halves. A day picked without a time is shown on the trigger and the focus
 * moves to the time list, while the field stays empty: half a moment cannot be written as one,
 * and a quiet midnight would be wrong. A time picked for another day, or before the day, goes
 * when the day picked closes it, and the day waits for a time. `default-time` supplies the time
 * with the day, on a day that leaves it open. Without confirmation a day changes a value at once
 * and the dialog stays open; a time completes it and closes the dialog. With confirmation both
 * wait for Select, and Cancel, Escape or a press outside leave the value as it was.
 *
 * The calendar inside the dialog is the `calendar` component: it shows the day it is handed on
 * `data-wk-server-value`, and its pick reaches this component as the `input` of its hidden field.
 * The time list is spread in from utils/time-list.js, and the dialog itself from
 * utils/picker-dialog.js.
 *
 * With `time="input"` the time is typed in segments below the calendar instead, from
 * utils/time-input.js, with the time list as suggestions below the field. The segments show a
 * time the list's value holds, and the list's value holds only a time typed whole and open on the
 * day; a typed time waits for Select, as a confirmation does.
 *
 * With `range` the value is two moments, `start/end`, in two fields of their own, `name[start]` and
 * `name[end]`, bound to `property.start` and `property.end` as on the date picker, and with
 * `presets` a third for the preset. The calendar picks the two days, and below it a row for each
 * end takes its time (resources/js/components/datetime-picker-time.js), which reads the day and the
 * time of its end through rangeDayOf() and rangeTimeOf() and hands back what it takes through
 * rangeTimeTaken(). A range always waits for Select, which stays closed while either end lacks its
 * day or its time, or the end does not come after the start; the sentence that says so is
 * rangeError. A preset picked takes the whole days, from the first time of the first day to the
 * last time of the last, unless `default-time` names the two, and where the limits close one of
 * those times on its day, the open time nearest to it.
 *
 * With `type="input"` the trigger is a field that takes the whole moment typed, its day, its month,
 * its year, its hour, its minute and its period in the locale's order (utils/moment-input.js), with
 * a button at its end that opens the dialog. A moment typed whole and open is taken at once, as a
 * native field takes it; a field emptied, left incomplete or holding a closed moment holds none.
 * The dialog and the field show each other's moment. With `range` as well, the start and the end
 * are typed in two fields, each a component of its own (datetime-picker-moment.js) that hands what
 * it makes through rangeMomentTyped() and shows what the picker counts in rangeFieldShows; a range
 * typed whole, in its order and within `min-range` and `max-range`, is taken at once, and the
 * dialog opens on what the two fields hold.
 *
 * Lifecycle resources held on `this`, released in destroy():
 *   - the dialog's trap, placement and page listeners, through pickerDialogDestroy().
 *   - _stopServerValue — the observer on the value the server renders on the root.
 *   - _modelEvents — `change` and `blur` on the hidden field (utils/model-events.js).
 *   - _stopFormReset — the listener that puts the starting value back on a form reset.
 *   - _stopPageLabel — the observers that keep a label of the page's own in the picker's name
 *     (utils/page-label.js).
 *   - the time list's typing timer, through timeListDestroy(), and the placement of the typed
 *     time's suggestions, through timeInputDestroy().
 *
 * Events, on the root, bubbling: `wirekit:datetime-picker-open`, `wirekit:datetime-picker-close`
 * and `wirekit:datetime-picker-clear`.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/examples/datepicker-dialog/
 */

const MOMENT = /^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})$/;

/** A value read into its day and its time, both null for anything that is no moment. */
function readMoment(value) {
    const match = MOMENT.exec(String(value ?? '').trim());

    return match ? { day: match[1], time: match[2] } : { day: null, time: null };
}

/** A range written `start/end` read into its two ends, each a day, a time and the moment they make. */
function readRange(value) {
    const [first = '', second = ''] = String(value ?? '').trim().split('/');
    const end = (text) => {
        const { day, time } = readMoment(text);

        return { day, time, moment: day && time ? day + 'T' + time : null };
    };
    const start = end(first);

    return { start, end: start.moment ? end(second) : end('') };
}

/** The days the calendar of a range writes, `start/end` or the start alone, each null when absent. */
function readDays(value) {
    const [start = '', end = ''] = String(value ?? '').trim().split('/');
    const day = /^\d{4}-\d{2}-\d{2}$/;

    return { start: day.test(start) ? start : null, end: day.test(start) && day.test(end) ? end : null };
}

/**
 * @param {Object} config
 * @param {string} [config.locale]  the application's locale, as BCP 47
 * @param {string} [config.panelId]  the dialog's id
 * @param {boolean} [config.withConfirmation]
 * @param {?string} [config.defaultTime]  `HH:MM`
 * @param {?string} [config.openTo]  `HH:MM`, where the time list stands without a value
 * @param {Object} [config.time]  the time list's limits (utils/time-slots.js)
 * @param {string} [config.unavailableLabel]
 * @param {string} [config.atLabel]  the word between the day and the time on the trigger
 * @param {?Object} [config.timeInput]  the words of the typed time with `time="input"`, else null
 * @param {boolean} [config.range]  two moments rather than one
 * @param {?string} [config.endAfterStart]  the sentence while the end of a range does not come after its start
 * @param {?Object} [config.momentInput]  with `type="input"`, the words and the closed days of the field, else null
 * @param {?Object} [config.rangeLimits]  with `type="input"` and `range`, the fewest and the most days a range covers, `min` and `max`, and what the end says when it covers fewer or more, `minMessage` and `maxMessage`
 */
export default function wirekitDatetimePicker(config = {}) {
    const dateFormat = new Intl.DateTimeFormat(config.locale || undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    const range = config.range === true;
    const typed = config.timeInput !== null && typeof config.timeInput === 'object';
    const confirms = config.withConfirmation === true || typed || range;
    const isTime = (time) => /^\d{2}:\d{2}$/.test(time ?? '');
    const defaultTime = ! range && isTime(config.defaultTime) ? config.defaultTime : null;
    // With `range`, the default time of each end, `[start, end]`.
    const defaultTimes = range && Array.isArray(config.defaultTime) ? config.defaultTime.map((time) => (isTime(time) ? time : null)) : [null, null];
    // The limits of the times: the window of every day, whose open edges a preset's whole days
    // run from and to (dayEdges), and the times `unavailable`, `min` and `max` close.
    const dayWindow = readTimeLimits(config.time ?? {});
    const endAfterStart = config.endAfterStart || 'End must be after start.';
    // With `type="input"`, the trigger is a field that takes the moment typed, and with `range`
    // two fields, one for each end.
    const typesMoment = ! range && config.momentInput !== null && typeof config.momentInput === 'object';
    const typesRange = range && config.momentInput !== null && typeof config.momentInput === 'object';
    const rangeLimits = config.rangeLimits ?? {};
    const own = ownValues();

    return {
        ...requiredCheckState(),
        ...timeListState({ ...(config.time ?? {}), locale: config.locale, timeFormat: config.timeFormat, unavailableLabel: config.unavailableLabel }),
        ...timeInputState({ ...(config.time ?? {}), ...(config.timeInput ?? {}), locale: config.locale, timeFormat: config.timeFormat, openTo: config.openTo }),
        ...momentInputState({ ...(config.momentInput ?? {}), time: config.time ?? {}, locale: config.locale, timeFormat: config.timeFormat }),
        ...pickerDialogState({
            panelId: config.panelId ?? null,
            openEvent: 'wirekit:datetime-picker-open',
            closeEvent: 'wirekit:datetime-picker-close',
            missingTriggerWarning: '[wirekit] datetime-picker: the trigger slot has no button or link, so a keyboard cannot open the picker. Put a <button> in the slot.',
            // The day the calendar's keyboard stands on, as the date-picker dialog of the WAI-ARIA
            // practices focuses it.
            initialFocus: (panel) => panel.querySelector('button[data-wk-day][tabindex="0"]'),
            // On a phone the dialog is a modal over the page.
            modalMedia: PHONE_MODAL_MEDIA,
        }),

        // The word between the day and the time on the trigger, in the application's language.
        atWord: config.atLabel || 'at',
        // The committed moment, `YYYY-MM-DDTHH:MM`, or '' for none. Read from the root in init():
        // in `x-data` it made every value a different expression.
        value: '',
        // The day picked in the dialog and not yet part of a value. The time waits in
        // `timeListValue`, which the time list owns.
        draftDay: null,
        // With `range`, the two days picked in the dialog and the time of each end, which wait for
        // Select, and the preset the calendar names for the days. `preset` is the committed one,
        // '' for a range picked day by day.
        draftStart: null,
        draftEnd: null,
        rangeTimes: { start: null, end: null },
        draftPreset: '',
        preset: '',
        _resetPreset: '',
        _resetValue: '',
        // Set while a moment typed in the field is committed, so the field is not written over
        // with what it already shows.
        _fromField: false,
        // With `type="input"` and `range`: what each field makes, a moment or null, and how many
        // times the picker has shown its value in the two fields (datetime-picker-moment.js).
        typedEnds: { start: null, end: null },
        rangeFieldShows: 0,
        _stopServerValue: null,
        _modelEvents: null,
        _stopFormReset: null,
        _stopPageLabel: null,

        init() {
            const root = this.$root;

            // `$root` is checked rather than assumed, as a unit harness hands a bare stub.
            const seed = typeof root?.getAttribute === 'function' ? root.getAttribute(WK_SERVER_VALUE_ATTRIBUTE) : null;
            this.preset = range ? (this.$refs?.inputPreset?.getAttribute('value') ?? '') : '';
            this._resetPreset = this.preset;
            this._take(seed ?? '');
            this._resetValue = this.value;
            this._showInField();

            this._modelEvents = watchModelEvents(root, () => this._fields());
            this._stopFormReset = onFormReset(root, () => this._fields()[0], () => this._restore());

            // A moment the server sets reaches the field and the dialog. The answer to a pick of
            // the reader's own changes nothing: with `.live` the reader can be a pick further when
            // it arrives, and an open dialog stays open.
            this._stopServerValue = observeServerValue(root, (next) => {
                this._resetValue = next;

                if ((this._fields().some((field) => hasModelBinding(field)) && own.answers(next)) || next === this.value) {
                    return;
                }

                this._take(next);
                this._showInField();
            });

            // A moment an Alpine page's model writes into the fields reaches the trigger and the
            // dialog, as one the server renders does (utils/server-value.js).
            this.$nextTick?.(() => {
                const [first, second, preset] = this._fields();
                const pull = () => {
                    const start = String(first?._x_model?.get?.() ?? '');
                    const end = range ? String(second?._x_model?.get?.() ?? '') : '';
                    const value = start && end ? start + '/' + end : start;

                    if (value !== this.value) {
                        this._take(value);
                        this._showInField();
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

        /** The hidden fields that carry the value: one, or the two ends of a range and its preset. */
        _fields() {
            return (range ? [this.$refs?.inputStart, this.$refs?.inputEnd, this.$refs?.inputPreset] : [this.$refs?.input]).filter(Boolean);
        },

        /** A moment as the field holds it: the value, and the two halves the dialog shows. */
        _take(value) {
            if (range) {
                const { start, end } = readRange(value);

                this.value = start.moment ? start.moment + (end.moment ? '/' + end.moment : '') : '';
                this._draftRange(start, end);

                return;
            }

            const { day, time } = readMoment(value);

            this.value = day && time ? day + 'T' + time : '';
            this.draftDay = day;
            this.timeListDay = day;
            this.timeListValue = time;
            // Where the keyboard stood in the list belongs to the dialog that is gone.
            this.timeListActive = -1;

            if (typed) {
                this.timeInputShow(time);
            }
        },

        /** Back to the starting moment after a form reset, without the events a pick fires. */
        _restore() {
            if (range) {
                this.preset = this._resetPreset;
                this._take(this._resetValue);
                this._writeRange();
                this._showInField();
                this.requiredMessage = '';

                return;
            }

            this._take(this._resetValue);
            this._showInField();
            this.$refs?.input?.setAttribute('value', this.value);
            this.requiredMessage = '';
        },

        /** With `type="input"`, the dialog stands below the whole field, or the two of a range, not below the button. */
        _dialogAnchor() {
            if (typesRange) {
                return this.$refs?.momentRangeField ?? null;
            }

            return typesMoment ? (this.$refs?.momentInputField ?? null) : null;
        },

        /** With `type="input"`, the field shows the moment the picker holds, or the two fields the ends of its range. */
        _showInField() {
            if (typesMoment) {
                this.momentInputShow(this.value);
            }

            if (typesRange) {
                const { start, end } = readRange(this.value);

                this.typedEnds = { start: start.moment, end: end.moment };
                this.rangeFieldShows++;
            }
        },

        /** The first segment of the typed field, or of the start of a typed range, where the focus goes to type a new value. */
        _focusFirstSegment() {
            if (typesRange) {
                this.$refs?.momentRangeField?.querySelector('input[data-wk-moment-segment]')?.focus();

                return;
            }

            this.momentInputFocusFirst();
        },

        /**
         * A moment typed in the field (utils/moment-input.js): a whole one is taken at once, as a
         * native field takes it, and a field emptied, left incomplete or holding a closed moment
         * holds none.
         */
        onMomentTyped(moment) {
            const next = moment ?? '';

            if (! typesMoment || next === this.value) {
                return;
            }

            this._fromField = true;
            this._commit(next);
            this._fromField = false;
            this._take(next);
        },

        /** What the required check reads: empty exactly while no moment, or no whole range, is chosen. */
        get requiredValue() {
            return range ? (readRange(this.value).end.moment ? this.value : '') : this.value;
        },

        /** The trigger, or the field's first segment, is where an empty required picker sends the focus. */
        _focusRequiredControl() {
            if (typesMoment || typesRange) {
                this._focusFirstSegment();

                return;
            }

            this._trigger()?.focus();
        },

        // ── The trigger ──────────────────────────────────────────────────

        /** The day the trigger shows: the value's, or a day picked that waits for its time. */
        get shownDay() {
            const day = this.value ? readMoment(this.value).day : (this.confirming ? null : this.draftDay);

            return day ? this._dayText(day) : '';
        },

        /** The time the trigger shows, or '' without a value. */
        get shownTime() {
            return this.value ? this.timeLabel(readMoment(this.value).time) : '';
        },

        /**
         * A range as the trigger writes it, from the start to the end, each "Oct 27, 2026 at 9:00
         * AM", and the end's time alone on the start's day; the server joins the same parts.
         */
        get shownRange() {
            const { start, end } = readRange(this.value);

            if (! start.moment) {
                return '';
            }

            const at = (moment, withDay) => (withDay ? this._dayText(moment.day) + ' ' + this.atWord + ' ' : '') + this.timeLabel(moment.time);

            return at(start, true) + (end.moment ? ' – ' + at(end, end.day !== start.day) : '');
        },

        /** A day as the trigger writes it, "Oct 27, 2026". */
        _dayText(day) {
            const [year, month, date] = day.split('-').map(Number);

            return dateFormat.format(new Date(year, month - 1, date));
        },

        /** Whether the trigger shows its placeholder. */
        get showsPlaceholder() {
            return range ? this.shownRange === '' : this.shownDay === '';
        },

        // ── The dialog (utils/picker-dialog.js) ──────────────────────────

        /**
         * The dialog starts from the value, whatever was left in it the last time. With a typed
         * range it starts from what the two fields hold, a start typed without its end among it,
         * as the value holds only a whole range; an end before the start is left out.
         */
        _dialogOpening() {
            if (typesRange) {
                const { start, end } = this.typedEnds;
                const typed = readRange((start ?? '') + (start && end && end.slice(0, 10) >= start.slice(0, 10) ? '/' + end : ''));

                this._draftRange(typed.start, typed.end);

                return;
            }

            this._take(this.value);
        },

        /** The list of times stands at the value's time, or at `open-to` or the clock without one. */
        _dialogOpened() {
            if (! range) {
                this.timeListScrollTo(config.openTo ?? this._now());
            }
        },

        /** What waits for a confirmation, or for a time, goes with the dialog. */
        _dialogClosed() {
            this.closeTimeInputList(false);
            this._take(this.value);
        },

        /** The time now, `HH:MM`, where the time list stands without a value. */
        _now() {
            const now = new Date();

            return String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
        },

        // ── Picking ──────────────────────────────────────────────────────

        /**
         * A day picked in the calendar, heard as the `input` of its hidden field. Only that
         * field's: anything else typed into the dialog bubbles here too.
         */
        onCalendarInput(event) {
            if (range) {
                this._rangeCalendarInput(event?.target);

                return;
            }

            const field = event?.target;

            if (! field || field.getAttribute?.('x-ref') !== 'hiddenInput' || ! /^\d{4}-\d{2}-\d{2}$/.test(field.value)) {
                return;
            }

            this.draftDay = field.value;
            this.timeListDay = field.value;

            if (typed) {
                if (defaultTime && this.timeInputBlank()) {
                    this.timeInputShow(defaultTime);
                }

                // A time typed for another day can be closed on this one, or open again.
                this.timeInputCheck();

                // Without a time the day can take, the focus goes where the time is typed.
                if (! this.timeListValue) {
                    this.$nextTick?.(() => this.timeInputFocusFirst());
                }

                return;
            }

            // A time picked for another day, or before a day, can be closed on this one. It goes,
            // the day waits for a time as a day without one does, and the keyboard stands on the
            // open time nearest to it.
            if (this.timeListValue && ! this._timeOpenOn(this.draftDay, this.timeListValue)) {
                this.timeListActive = this.timeListNear(this.timeListValue);
                this.timeListValue = null;
            }

            if (! this.timeListValue && defaultTime && this._timeOpenOn(this.draftDay, defaultTime)) {
                this.timeListValue = defaultTime;
                this.timeListScrollTo(defaultTime);
            }

            if (this.timeListValue) {
                if (! this.confirming) {
                    this._commit(this.draftDay + 'T' + this.timeListValue);
                }

                return;
            }

            // A day without a time is half a value: the focus goes where the other half is, the
            // list of times, or in a modal the select that stands in for it.
            this.$nextTick?.(() => {
                if (this.dialogModal) {
                    this.$refs?.timeSelect?.focus();

                    return;
                }

                this.$refs?.timeList?.querySelector('[role="option"][tabindex="0"]')?.focus();
            });
        },

        /**
         * Whether a time, `HH:MM`, is open on a day: inside `min-time` and `max-time`, and closed
         * by none of `unavailable`, `min` and `max`.
         */
        _timeOpenOn(day, time) {
            const minutes = minutesOf(time);

            return minutes !== null && insideWindow(minutes, dayWindow) && ! slotClosed(day, minutes, dayWindow);
        },

        /**
         * The time a preset's whole days take on a day: the one given while the day leaves it
         * open, else the open time of the list nearest to it inside the day, the first after it
         * for a start (`direction` 1) and the last before it for an end (-1). With none, the one
         * given, and the row of that end says why it is not taken.
         */
        _openBound(day, time, direction) {
            if (! day || ! time || this._timeOpenOn(day, time)) {
                return time;
            }

            const minutes = minutesOf(time);
            const open = timeSlots(day, dayWindow).filter((slot) => ! slot.closed);
            const near = direction > 0
                ? open.find((slot) => slot.minutes >= minutes)
                : [...open].reverse().find((slot) => slot.minutes <= minutes);

            return near ? near.value : time;
        },

        /** A time picked in the list (utils/time-list.js calls it). */
        onTimeChosen(time) {
            if (typed) {
                this.timeInputChosen(time);

                return;
            }

            if (this.confirming) {
                return;
            }

            if (! this.draftDay) {
                // A time without a day waits for the day.
                this.$refs?.panel?.querySelector('button[data-wk-day][tabindex="0"]')?.focus();

                return;
            }

            this._commit(this.draftDay + 'T' + time);
            this.closePanel();
        },

        /**
         * Whether a pick waits for Select: with `with-confirmation`, a typed time and a range, and
         * on a phone, where the dialog is a modal and a day or a time picked by a thumb is easily
         * the wrong one.
         */
        get confirming() {
            return confirms || this.dialogModal === true;
        },

        /** A time picked in the select that stands in for the list of times in a modal. */
        chooseTimeFromSelect(event) {
            const time = event?.target?.value ?? '';

            this.chooseTimeSlot(this.timeListSlots().findIndex((slot) => slot.value === time));
        },

        /** Whether Select has a whole moment to take, or with `range` two in their order. */
        get canConfirm() {
            if (range) {
                return Boolean(this._rangeMoment('start') && this._rangeMoment('end')) && this.rangeError === '';
            }

            return Boolean(this.draftDay && this.timeListValue);
        },

        /** Select, with confirmation: take the picked moment and close. */
        confirmPick() {
            if (! this.canConfirm) {
                return;
            }

            if (range) {
                this._commitRange(this._rangeMoment('start') + '/' + this._rangeMoment('end'), this.draftPreset);
                this.closePanel();

                return;
            }

            this._commit(this.draftDay + 'T' + this.timeListValue);
            this.closePanel();
        },

        /** Cancel, with confirmation: close and leave the value as it was. */
        cancel() {
            this.closePanel();
        },

        /** Empty the field. */
        clear() {
            if (this.value === '') {
                return;
            }

            if (range) {
                this._commitRange('', '');
            } else {
                this._commit('');
            }

            this._take('');
            this.$root?.dispatchEvent(new CustomEvent('wirekit:datetime-picker-clear', { bubbles: true }));

            // The clear button leaves with the value, so the focus goes where a new moment starts:
            // the field's first segment, or the trigger.
            if (typesMoment || typesRange) {
                this._focusFirstSegment();

                return;
            }

            this._trigger()?.focus();
        },

        /** A moment into the field, with the events a native field fires for a pick. */
        _commit(value) {
            this.value = value;
            own.note(value);

            // A moment picked in the dialog fills the field.
            if (typesMoment && ! this._fromField) {
                this.momentInputShow(value);
            }

            const input = this.$refs?.input;
            input?.setAttribute('value', value);

            if (input) {
                input.value = value;
            }

            input?.dispatchEvent(new Event('input', { bubbles: true }));
            this._modelEvents?.commit();
            this.requiredMessage = '';
        },

        // ── A range (`range`) ────────────────────────────────────────────

        /** The day of an end of the range in the dialog, or null (datetime-picker-time.js reads it). */
        rangeDayOf(end) {
            return end === 'end' ? this.draftEnd : this.draftStart;
        },

        /** The time the dialog holds for an end of the range, or null (datetime-picker-time.js reads it). */
        rangeTimeOf(end) {
            return this.rangeTimes[end === 'end' ? 'end' : 'start'] ?? null;
        },

        /** A time a row took for an end, or none (datetime-picker-time.js hands it). */
        rangeTimeTaken(end, time) {
            const key = end === 'end' ? 'end' : 'start';

            if ((this.rangeTimes[key] ?? null) !== (time ?? null)) {
                this.rangeTimes = { ...this.rangeTimes, [key]: time ?? null };
            }
        },

        /** The two ends the dialog shows, each a day and a time, from a range readRange() read. */
        _draftRange(start, end) {
            this.draftStart = start.day;
            this.draftEnd = end.day;
            this.rangeTimes = { start: start.time, end: end.time };
            this.draftPreset = this.value ? this.preset : '';
        },

        /** The moment the picker shows in the field of an end of a typed range, or '' (datetime-picker-moment.js reads it). */
        rangeMomentShown(end) {
            return this.typedEnds[end === 'end' ? 'end' : 'start'] ?? '';
        },

        /**
         * What the field of an end of a typed range makes, a moment or null (datetime-picker-moment.js
         * hands it). A range typed whole, in its order and within its limits, is taken at once, as
         * a native field takes a value; a field emptied, half typed or holding a closed moment
         * leaves the picker without a range, and the field keeps what is typed in it.
         */
        rangeMomentTyped(end, moment) {
            this.typedEnds = { ...this.typedEnds, [end === 'end' ? 'end' : 'start']: moment ?? null };

            const { start, end: last } = this.typedEnds;
            const next = start && last && this.rangeTypedError === '' ? start + '/' + last : '';

            if (next === this.value) {
                return;
            }

            this._fromField = true;
            this._commitRange(next, '');
            this._fromField = false;
            this._take(this.value);
        },

        /**
         * With a typed range, what the end says about the range its two fields hold: that the end
         * does not come after the start, or that the range covers fewer or more days than
         * `min-range` and `max-range` allow, both ends counted; '' while it says nothing.
         */
        get rangeTypedError() {
            const { start, end } = this.typedEnds;

            if (! typesRange || ! start || ! end) {
                return '';
            }

            if (end <= start) {
                return endAfterStart;
            }

            const days = Math.round((Date.parse(end.slice(0, 10) + 'T00:00Z') - Date.parse(start.slice(0, 10) + 'T00:00Z')) / 86_400_000) + 1;

            if (Number.isInteger(rangeLimits.min) && days < rangeLimits.min) {
                return rangeLimits.minMessage || '';
            }

            if (Number.isInteger(rangeLimits.max) && days > rangeLimits.max) {
                return rangeLimits.maxMessage || '';
            }

            return '';
        },

        /** An end of the range in the dialog as a moment, or null while it lacks its day or its time. */
        _rangeMoment(end) {
            const day = this.rangeDayOf(end);
            const time = this.rangeTimeOf(end);

            return day && time ? day + 'T' + time : null;
        },

        /** The sentence under the times while the end does not come after the start, or ''. */
        get rangeError() {
            const start = this._rangeMoment('start');
            const end = this._rangeMoment('end');

            return start && end && end <= start ? endAfterStart : '';
        },

        /** The days the calendar in the dialog shows: `start/end`, the start alone, or ''. */
        get draftDays() {
            return this.draftStart ? this.draftStart + (this.draftEnd ? '/' + this.draftEnd : '') : '';
        },

        /**
         * The days the calendar picked, heard as the `input` of its hidden field. A first day waits
         * for the second; the two take the preset the calendar names for them, written before it
         * sends them, and the times a preset or `default-time` gives.
         */
        _rangeCalendarInput(field) {
            if (! field || field.getAttribute?.('x-ref') !== 'hiddenInput') {
                return;
            }

            const { start, end } = readDays(field.value);

            if (! start) {
                return;
            }

            this.draftStart = start;
            this.draftEnd = end;
            this.draftPreset = end ? (field.closest?.('[x-data]')?.querySelector('[x-ref="hiddenPreset"]')?.value ?? '') : '';

            if (! end) {
                return;
            }

            // A preset names whole days: from the first time of the first to the last time of the
            // last, as Values\DateTimeRange::fromPreset() reads one, unless `default-time` names
            // the two. Where `unavailable`, `min` or `max` close that time on its day, the preset
            // takes the open time nearest to it inside the day, so a preset the calendar offers
            // never opens on a time its row refuses. Days picked by hand keep the times already
            // chosen, or take `default-time`, and their rows say when a day closes one.
            const whole = this.draftPreset !== '' && this.draftPreset !== 'custom';

            this.rangeTimes = whole
                ? {
                    start: this._openBound(start, defaultTimes[0] ?? timeText(dayEdges(dayWindow).first), 1),
                    end: this._openBound(end, defaultTimes[1] ?? timeText(dayEdges(dayWindow).last), -1),
                }
                : { start: this.rangeTimes.start ?? defaultTimes[0], end: this.rangeTimes.end ?? defaultTimes[1] };
        },

        /** A range into the hidden fields, with the events a native field fires for a pick, start first. */
        _commitRange(value, preset = '') {
            const { start, end } = readRange(value);

            this.value = start.moment && end.moment ? start.moment + '/' + end.moment : '';
            this.preset = this.value ? preset : '';
            own.note(this.value);
            this._writeRange();

            // A range picked in the dialog, or emptied, fills the two typed fields.
            if (typesRange && ! this._fromField) {
                this._showInField();
            }

            for (const field of this._fields()) {
                field.dispatchEvent(new Event('input', { bubbles: true }));
            }

            this._modelEvents?.commit();
            this.requiredMessage = '';
        },

        /** The range into the hidden fields, as the attribute a form sends and the property `wire:model` reads. */
        _writeRange() {
            const { start, end } = readRange(this.value);
            const values = [start.moment ?? '', end.moment ?? '', this.preset];

            this._fields().forEach((field, index) => {
                field.setAttribute('value', values[index] ?? '');
                field.value = values[index] ?? '';
            });
        },
    };
}
