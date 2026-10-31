/**
 * WireKit Calendar Alpine Component.
 *
 * Standalone month grid with day cells for date selection.
 * Supports single date selection, keyboard navigation, and month/year changes.
 *
 * Lifecycle resources held on `this`, released in destroy():
 *   - _modelEvents — `change` and `blur` on the hidden field for `wire:model.change` and
 *     `wire:model.blur`, which listen on that field alone (utils/model-events.js): `change` with
 *     every date chosen, and `blur` when the reader leaves the grid.
 *   - _stopFormReset — the listener that puts the starting date back when the form is reset
 *     (utils/form-reset.js).
 *   - _stopServerValue — the observer on the value the server renders on the root
 *     (utils/server-value.js).
 *   - _stopLimits — the observers on the limits the server renders on the root
 *     (utils/date-limits.js).
 *   - _stopOpened — the observer on `data-wk-opened`, the openings of a dialog that holds the
 *     grid (utils/picker-dialog.js).
 *   - _stopMonths — the observer on `data-wk-months`, the months a dialog that holds the grid
 *     asks for: one in a modal on a phone.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/examples/datepicker-dialog/
 */
import { onFormReset } from '../utils/form-reset.js';
import { requiredCheckState } from '../utils/required-check.js';
import { watchModelEvents } from '../utils/model-events.js';
import { hasModelBinding, observeServerValue, ownValues, WK_SERVER_VALUE_ATTRIBUTE } from '../utils/server-value.js';
import { dayClosed, LIMIT_ATTRIBUTES, limitsOn, monthOutside, openDayNear, readLimits } from '../utils/date-limits.js';
import { CUSTOM_PRESET, presetOf, presetRange } from '../utils/date-presets.js';

/** The days a range covers, both ends counted, from two days `YYYY-MM-DD`. */
function spanOf(start, end) {
    const utc = (day) => {
        const [year, month, date] = day.split('-').map(Number);

        return Date.UTC(year, month - 1, date);
    };

    return Math.round((utc(end) - utc(start)) / 86400000) + 1;
}

/**
 * A value in the calendar's spelling, read into its two ends and the day the grid opens on.
 *
 * `YYYY-MM-DD/YYYY-MM-DD` is the range spelling, the same one `date-picker` already reads and
 * writes. Sharing it is the point: a value copied from one to the other keeps meaning, and a form
 * receives the same two fields either way. A single date in range mode is a started range with no
 * end yet, which is exactly the state a first click produces.
 *
 * A start value the factory cannot read, such as a date with a time of day the server did not
 * recognize, opens the grid on the current month as no value does. As an Invalid Date it would set
 * the month and the year to NaN, whose heading no formatter can write, and the grid would render
 * no day at all.
 *
 * @param {*} value
 * @returns {{ start: ?string, end: ?string, initial: ?Date }}
 */
function readValue(value) {
    const [rawStart, rawEnd] = String(value || '').split('/');
    const start = rawStart || null;
    const parsed = start ? new Date(start + 'T00:00:00') : null;

    return {
        start,
        end: rawEnd || null,
        initial: parsed && !Number.isNaN(parsed.getTime()) ? parsed : null,
    };
}

/**
 * The ISO 8601 week of a Thursday, `YYYY-MM-DD`. The Thursday of a week decides its year and its
 * number, so a row of the grid is numbered by the Thursday it holds, whichever day it starts on.
 *
 * @param {string} thursday
 * @returns {number}
 */
function isoWeekOfThursday(thursday) {
    const [year, month, day] = thursday.split('-').map(Number);
    const dayOfYear = Math.round((Date.UTC(year, month - 1, day) - Date.UTC(year, 0, 1)) / 86400000);

    return Math.floor(dayOfYear / 7) + 1;
}

export default function wirekitCalendar(config = {}) {
    // The reader's day, until init() reads the application's where the server wrote it.
    let today = new Date();

    // The APPLICATION's locale, not the browser's — the component receives it
    // from Blade, the way countdown does. `undefined` is the deliberate
    // fallback and not a placeholder: it is what `Intl` reads as "use the
    // browser's own preference", so a calendar mounted by hand without the
    // config still names its months in a language the reader chose, rather than
    // in the one this file happens to be written in.
    const locale = config.locale || undefined;

    // The names a screen reader hears for the days and the today button, written in the page's
    // language. A name carries one language, and the words added to it ("today", "unavailable")
    // are the application's, so a calendar shown in another language than the page writes those
    // dates in the page's; what it shows in its own says so with `lang`, set by the view.
    const labelLocale = config.labelLocale || locale;

    // Built once per instance rather than per read. Every string below depends
    // only on the locale and on the first weekday, and neither changes for the
    // life of the component — while `days` is re-read on every keystroke that
    // moves focus, so a formatter constructed inside it would be constructed
    // forty-two times for a grid that cannot have changed.
    const monthYearFormat = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' });
    const fullDateFormat = new Intl.DateTimeFormat(labelLocale, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
    const weekdayShortFormat = new Intl.DateTimeFormat(locale, { weekday: 'short' });
    const weekdayLongFormat = new Intl.DateTimeFormat(locale, { weekday: 'long' });
    const monthNameFormat = new Intl.DateTimeFormat(locale, { month: 'long' });

    // The word appended to today's cell label, translated on the PHP side —
    // "today" is the one part of a date a formatter cannot produce.
    const todayWord = config.todayLabel || '';

    // The word appended to the label of a day that cannot be picked, translated the same way.
    const unavailableWord = config.unavailableLabel || '';

    // The name of the today button, `:date` filled with today's month and day.
    const todayButtonTemplate = config.todayButtonLabel || '';
    const monthDayFormat = new Intl.DateTimeFormat(labelLocale, { month: 'long', day: 'numeric' });

    // The day `openTo` names, where the grid opens without a value. Without it the grid opens on
    // today, and init() moves it to the open day nearest to today once the limits are read.
    const openTo = readValue(config.openTo).initial;
    const opensOn = openTo ?? today;

    // The presets a range calendar offers, with what the list calls each, already filtered by the
    // server (Support\DatePresets).
    const presets = config.presets && typeof config.presets === 'object' ? config.presets : {};
    const presetKeys = Array.isArray(presets.keys) ? presets.keys.filter((key) => typeof key === 'string') : [];
    const presetLabels = presets.labels && typeof presets.labels === 'object' ? presets.labels : {};

    // Resolved once here because two things need it: the grid's leading pad and
    // the column headers, and reading it from the reactive property would tie
    // the headers to a value the reader can never change.
    // Any whole number is a day of the week, brought into 0..6 as the template brings it.
    const weekStartsOn = Number.isInteger(config.weekStartsOn) ? ((config.weekStartsOn % 7) + 7) % 7 : 1;

    // 1 January 2023 was a Sunday, which is what makes it usable as an index
    // into the week: adding the weekday number lands on that weekday, whatever
    // the locale calls it. A local (not UTC) date on purpose — `Intl` formats in
    // the local zone, and a UTC midnight is the previous day west of Greenwich.
    const weekAnchor = new Date(2023, 0, 1);

    /** Column headers for one week, rotated to start on the configured first weekday. */
    function weekdayHeadersFrom(weekStartsOn) {
        const headers = [];

        for (let i = 0; i < 7; i++) {
            const day = new Date(2023, 0, weekAnchor.getDate() + ((weekStartsOn + i) % 7));
            headers.push({ short: weekdayShortFormat.format(day), long: weekdayLongFormat.format(day) });
        }

        return headers;
    }

    /** The twelve month names, for the selectable header's `<select>`. */
    function monthNamesIn() {
        const names = [];

        for (let m = 0; m < 12; m++) {
            names.push(monthNameFormat.format(new Date(2023, m, 1)));
        }

        return names;
    }

    // The value the config hands over. Without one, init() reads the value the server renders on
    // the root instead: in `x-data` it made every value a different expression, which a Livewire
    // morph answers by starting the calendar again.
    const { start: startValue, end: endValue, initial } = readValue(config.value);

    // The values this calendar hands to its model, so the answer to one is not taken for a date
    // the server set (utils/server-value.js).
    const own = ownValues();

    // Every read of `initial` below branches on `initial` itself, never on the
    // number a getter hands back. `getMonth()` is zero-based, so a January value
    // yields 0, and `0 || today.getMonth()` would fall through to today: the grid
    // would open on the current month while the form carried January, wrong for
    // one month a year and right for the other eleven. The question being asked
    // is "was a value supplied?", so the test is on the value's presence and not
    // on the digit it produced.
    return {
        _modelEvents: null,
        // Puts the starting date back when the form is reset (utils/form-reset.js). Released in
        // destroy().
        _stopFormReset: null,
        _stopServerValue: null,
        // The value a form reset returns to: the one the page started with, or the one the server
        // rendered last, as a native field returns to the value the server rendered.
        _resetValue: config.value == null ? '' : String(config.value),
        // The days a reader can pick (utils/date-limits.js): from the config, or from the
        // attributes the server writes on the root, followed when a render changes them.
        _limits: readLimits(config.limits),
        _stopLimits: null,
        // Shows the value again each time a dialog that holds the grid opens. Released in destroy().
        _stopOpened: null,
        // Follows the months a dialog that holds the grid asks for. Released in destroy().
        _stopMonths: null,
        // `requiredMessage` and `onRequiredInvalid()`: a required calendar stops an empty submit
        // (utils/required-check.js).
        ...requiredCheckState(),

        init() {
            const root = this.$root;

            // With presets the server writes the application's day (`data-wk-today`), and the
            // presets count from it. The grid, the today button and the opening month take the
            // same day, so a calendar has one today. Without it they keep the reader's day, which
            // a page served from a cache keeps right.
            const writtenToday = typeof root?.getAttribute === 'function' ? root.getAttribute('data-wk-today') : null;

            if (/^\d{4}-\d{2}-\d{2}$/.test(writtenToday ?? '')) {
                today = readValue(writtenToday).initial ?? today;
            }

            // `$root` is checked rather than assumed, as a unit harness hands a bare stub.
            if (config.value === undefined && typeof root?.getAttribute === 'function') {
                const seed = root.getAttribute(WK_SERVER_VALUE_ATTRIBUTE);

                if (seed !== null) {
                    this._resetValue = seed;
                    this._show(readValue(seed));
                }
            }

            this._matchPreset(this._namedPreset());

            this._modelEvents = watchModelEvents(root, () => this.$refs?.hiddenInput);
            this._stopFormReset = onFormReset(root, () => this.$refs?.hiddenInput, () => this._restore());

            // The limits live in attributes rather than in `x-data`, so that a render which changes
            // them, `min="today"` on the next day or a day the server has just booked, reaches the
            // grid without starting the calendar again.
            if (config.limits === undefined) {
                this._limits = limitsOn(root);

                const stops = LIMIT_ATTRIBUTES.map((name) => observeServerValue(root, () => {
                    this._limits = limitsOn(root);
                }, name));

                this._stopLimits = () => stops.forEach((stop) => stop());
            }

            // With the limits known, a grid without a value moves off a today they close.
            if (this.selected === null) {
                this._view(this._opening());
            }

            // A date the server sets reaches the grid, which opens on its month. One the reader
            // already sees needs nothing, and neither does the answer to a pick of theirs: with
            // `.live` the reader can be a pick or a month further when it arrives, and Livewire
            // leaves its property alone then too.
            this._stopServerValue = observeServerValue(root, (value) => {
                this._resetValue = value;

                const answered = hasModelBinding(this.$refs?.hiddenInput) && own.answers(value);

                if (answered || value === this.rangeValue) {
                    return;
                }

                this._show(readValue(value));
            });

            // A dialog that holds the grid counts its openings on `data-wk-opened`
            // (utils/picker-dialog.js), and each opening shows the value on the root again: a
            // month the reader browsed to and left is not where the next one starts, and the
            // focus starts on the chosen day.
            this._stopOpened = observeServerValue(root, () => {
                this._show(readValue(root.getAttribute(WK_SERVER_VALUE_ATTRIBUTE)));
            }, 'data-wk-opened');

            // A dialog that holds the grid can ask for fewer months on `data-wk-months`, one in a
            // modal on a phone, where two do not fit side by side; without the attribute the grid
            // keeps the months it was rendered with.
            this._stopMonths = observeServerValue(root, () => {
                const asked = parseInt(root.getAttribute('data-wk-months'), 10);

                if (Number.isInteger(asked)) {
                    this.months = Math.min(4, Math.max(1, asked));
                    this.focusOffset = Math.min(this.focusOffset, this.months - 1);
                }
            }, 'data-wk-months');
        },

        destroy() {
            this._modelEvents?.dispose();
            this._modelEvents = null;
            this._stopFormReset?.();
            this._stopFormReset = null;
            this._stopServerValue?.();
            this._stopServerValue = null;
            this._stopLimits?.();
            this._stopLimits = null;
            this._stopOpened?.();
            this._stopOpened = null;
            this._stopMonths?.();
            this._stopMonths = null;
        },

        /**
         * Show a value: its ends selected and the grid on the month of its start, or on the day
         * `_opening()` names without one.
         *
         * @param {{ start: ?string, end: ?string, initial: ?Date }} read  what readValue() made of it
         */
        _show(read) {
            this.selected = read.start;
            this.selectedEnd = read.end;
            this.hoverDate = null;
            this._view(read.initial ?? this._opening());
            this._matchPreset(this._namedPreset());
        },

        /** The grid on the month of a day, with the keyboard on that day. */
        _view(day) {
            this.viewYear = day.getFullYear();
            this.viewMonth = day.getMonth();
            this.focusedDay = day.getDate();
            this.focusOffset = 0;
        },

        /**
         * The day the grid opens on without a value: `openTo`, or today, or, when the limits close
         * today, the open day nearest to it (utils/date-limits.js). A reader who opens the grid
         * starts on a day that can be picked, never on a month with nothing to pick.
         *
         * @returns {Date}
         */
        _opening() {
            if (openTo !== null) {
                return openTo;
            }

            const near = openDayNear(this._formatDate(today), this._limits);

            return near === null ? today : readValue(near).initial;
        },

        /**
         * Back to the starting date, or range, after a form reset, with the grid on its month so
         * the date it returns to is in view. The hidden fields take it as _notify() writes them,
         * without the events: a reset changes a native field without one.
         */
        _restore() {
            this._show(readValue(this._resetValue));

            this.$refs?.hiddenInput?.setAttribute('value', this.rangeValue);

            if (this.range) {
                this.$refs?.hiddenStart?.setAttribute('value', this.selected ?? '');
                this.$refs?.hiddenEnd?.setAttribute('value', this.selectedEnd ?? '');
                this.$refs?.hiddenPreset?.setAttribute('value', this.presetValue);
            }

            this.requiredMessage = '';
        },

        /** What the required check reads: empty exactly while no date is chosen. */
        get requiredValue() {
            return this.rangeValue;
        },

        /** The day the keyboard stands on, which is where an empty required calendar sends the focus. */
        _focusRequiredControl() {
            this._focusDay();
        },

        viewYear: (initial ?? opensOn).getFullYear(),
        viewMonth: (initial ?? opensOn).getMonth(),
        selected: startValue,

        // ── Range mode ────────────────────────────────────────────────────────
        // `selected` is the start in both modes, so every read of it — the
        // optimistic layer's bind, the hidden input, the keyboard model — means the
        // same in both. Range mode adds only `selectedEnd`.
        range: !! config.range,
        selectedEnd: endValue,
        // `disabled` locks every control and the template takes the fields out of the form;
        // `readonly` leaves the months and days to be read and moved between, and the value is
        // still sent. Either way nothing the reader does picks a day or a preset. Disabled wins
        // where both are set, as it does on a native field.
        disabled: config.disabled === true,
        readonly: config.readonly === true && config.disabled !== true,
        // The day under the pointer while a range is half-made. It drives the
        // provisional shading and nothing else: a range that shows no in-between
        // until the second click makes the reader guess what they are choosing.
        hoverDate: null,
        // The preset the range is, a key of the list or `custom`, or null without a whole range;
        // and the option of the list the keyboard stands on.
        preset: null,
        presetActive: -1,
        focusedDay: (initial ?? opensOn).getDate(),
        // Six rows in every month, so that a panel keeps its height from one month to the next.
        fixedWeeks: config.fixedWeeks === true,
        // The base of the ids this calendar names its grids by. The template hands it over unique
        // on the page; a config without it derives the base from the name.
        _idBase: config.idBase || ((config.name || 'date') + '-calendar'),
        // Multi-month display: render N consecutive months side by side (1 = the
        // classic single grid). Clamped 1..4. focusOffset tracks which displayed
        // month currently holds keyboard focus (always 0 for single-month).
        months: Math.min(4, Math.max(1, parseInt(config.months, 10) || 1)),
        focusOffset: 0,

        // First day of the week: 0 (Sun) .. 6 (Sat), Monday by default, which matches the house
        // convention and <x-wirekit::event-calendar>.
        weekStartsOn,

        // Column headers, `{ short, long }` per column. The template prints the
        // short form and hands the long one to the `<th abbr>` the WAI-ARIA
        // date-picker example uses, so a reader navigating the grid by column
        // hears "Sunday" rather than the two letters that fit in the cell.
        weekdayHeaders: weekdayHeadersFrom(weekStartsOn),

        // Month names for the selectable header. Built here rather than written
        // out in the template because the template can only spell them in one
        // language, and the label beside the `<select>` is already spelling them
        // in the reader's.
        monthNames: monthNamesIn(),

        /** Whether nothing the reader does may change the value: disabled or read-only. */
        get locked() {
            return this.disabled || this.readonly;
        },

        /**
         * Get days array for current view month.
         * Returns objects: { date, dayOfMonth, isCurrentMonth, isToday, isSelected }
         */
        get days() {
            return this._daysFor(this.viewYear, this.viewMonth);
        },

        // N consecutive months from the base view, for the multi-month layout.
        get monthsView() {
            const out = [];
            for (let i = 0; i < this.months; i++) {
                const base = new Date(this.viewYear, this.viewMonth + i, 1);
                out.push({
                    offset: i,
                    year: base.getFullYear(),
                    month: base.getMonth(),
                    label: monthYearFormat.format(base),
                    days: this._daysFor(base.getFullYear(), base.getMonth()),
                });
            }

            return out;
        },

        /**
         * Chunk a month's flat day list into weeks of seven.
         *
         * Done inline in the template, with
         * `Array.from({ length: Math.ceil(days.length / 7) }, (_, i) => …)`,
         * this would fail twice over under Alpine's CSP build: the arrow function
         * is outside the grammar its parser accepts, and `Array` / `Math` are
         * globals its evaluator cannot resolve. The grid would simply not render.
         *
         * `_daysFor` already pads to whole weeks, so the last chunk is never
         * short — but the loop does not assume that, because a caller passing an
         * unpadded list should get a short final week rather than undefined
         * entries.
         */
        weeksOf(days) {
            const weeks = [];

            for (let i = 0; i < days.length; i += 7) {
                weeks.push(days.slice(i, i + 7));
            }

            return weeks;
        },

        /**
         * The number of a week of the grid: the ISO 8601 week of the Thursday it holds. A row of
         * seven days holds exactly one Thursday, whichever day the week starts on.
         *
         * @param {Array<{ date: string }>} week
         * @returns {number|string} the number, or '' for a row that is shorter than a week
         */
        weekNumber(week) {
            const thursday = week.find((day) => new Date(day.date + 'T00:00:00').getDay() === 4);

            return thursday ? isoWeekOfThursday(thursday.date) : '';
        },

        // Year options (±10 around the view year) for the selectable header.
        get yearRange() {
            const years = [];
            for (let y = this.viewYear - 10; y <= this.viewYear + 10; y++) {
                years.push(y);
            }

            return years;
        },

        // Day matrix for a given month (offset-independent), extracted so the
        // multi-month view can build each grid from one routine.
        _daysFor(year, month) {
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            // Leading pad offset by weekStartsOn so the grid begins on the configured
            // first weekday (Mon by default). Mirrors <x-wirekit::event-calendar>.
            const startPad = (firstDay.getDay() - this.weekStartsOn + 7) % 7;
            const daysInMonth = lastDay.getDate();

            const todayStr = this._formatDate(today);
            const result = [];
            // While a range waits for its other end, the spans its limits allow close days too.
            const start = this._waitingStart();

            // Pad with previous month days. A pad is a muted, inert day of the next or the previous
            // month and carries no range state: it could show only part of a range, never its ends,
            // which `isSelected` fills and a pad never has, and with several months the grid of its
            // own month shows that range a second time.
            const prevMonthLast = new Date(year, month, 0).getDate();
            for (let i = startPad - 1; i >= 0; i--) {
                const padDate = new Date(year, month - 1, prevMonthLast - i);
                result.push({
                    date: this._formatDate(padDate),
                    dayOfMonth: prevMonthLast - i,
                    label: this._dayLabel(padDate, false, false),
                    isCurrentMonth: false,
                    isToday: false,
                    isSelected: false,
                    isClosed: false,
                    ariaDisabled: null,
                    ...this._noRangeFlags(),
                });
            }

            // Current month days
            for (let d = 1; d <= daysInMonth; d++) {
                const dayDate = new Date(year, month, d);
                const dateStr = this._formatDate(dayDate);
                const closed = dayClosed(dateStr, this._limits, start);
                result.push({
                    date: dateStr,
                    dayOfMonth: d,
                    label: this._dayLabel(dayDate, dateStr === todayStr, closed),
                    isCurrentMonth: true,
                    isToday: dateStr === todayStr,
                    isSelected: dateStr === this.selected || (this.range && dateStr === this.selectedEnd),
                    isClosed: closed,
                    // The attribute VALUE, as `rangeMarker` is: `'true'` sets `aria-disabled` and
                    // `null` removes it. A closed day stays focusable, so it is no `disabled`.
                    ariaDisabled: closed ? 'true' : null,
                    ...this._rangeFlags(dateStr),
                });
            }

            // Pad to complete the last week, or to six of them with `fixedWeeks`.
            const remaining = this.fixedWeeks ? 42 - result.length : (7 - (result.length % 7)) % 7;
            if (remaining > 0) {
                for (let d = 1; d <= remaining; d++) {
                    const padDate = new Date(year, month + 1, d);
                    result.push({
                        date: this._formatDate(padDate),
                        dayOfMonth: d,
                        label: this._dayLabel(padDate, false, false),
                        isCurrentMonth: false,
                        isToday: false,
                        isSelected: false,
                        isClosed: false,
                        ariaDisabled: null,
                        ...this._noRangeFlags(),
                    });
                }
            }

            return result;
        },

        /**
         * Get month/year display label.
         */
        get monthLabel() {
            return monthYearFormat.format(new Date(this.viewYear, this.viewMonth, 1));
        },

        /**
         * The id of the heading that names one grid.
         *
         * Composed here rather than in the template because the base comes from
         * the component's `name`, which is developer-supplied: interpolated into an
         * Alpine expression it would be a quote away from ending the string it
         * sits in, the same hazard the value literal at the top of the template
         * is built to avoid.
         */
        monthLabelId(offset) {
            return this._idBase + '-label-' + (offset || 0);
        },

        /** Whether the month before the view holds a day inside `min`. */
        get canGoPrev() {
            return ! monthOutside(this.viewYear, this.viewMonth - 1, this._limits);
        },

        /** Whether the month after the last one shown holds a day inside `max`. */
        get canGoNext() {
            return ! monthOutside(this.viewYear, this.viewMonth + this.months, this._limits);
        },

        /** `aria-disabled` for the button to the month before: `'true'`, or `null` to remove it. */
        get prevDisabled() {
            return this.canGoPrev ? null : 'true';
        },

        /** `aria-disabled` for the button to the month after. */
        get nextDisabled() {
            return this.canGoNext ? null : 'true';
        },

        /**
         * `aria-disabled` for the today button: `'true'` while today's month lies wholly outside
         * `min` and `max`, which closes the way into it as it closes the arrows' way.
         */
        get todayDisabled() {
            return monthOutside(today.getFullYear(), today.getMonth(), this._limits) ? 'true' : null;
        },

        /** Today's day of the month, the number on the face of the today button. */
        get todayDayOfMonth() {
            return today.getDate();
        },

        /** The name of the today button: "Today, October 22" in the application's language. */
        get todayButtonLabel() {
            const date = monthDayFormat.format(today);

            return todayButtonTemplate ? todayButtonTemplate.replace(':date', date) : date;
        },

        /** Today as `YYYY-MM-DD`, the day the today button picks. */
        get todayDate() {
            return this._formatDate(today);
        },

        /**
         * The today button: from another month it goes back to today's, and with today's month
         * in view it answers that today is to be picked. The template picks it, as it picks a day,
         * so the pick goes through the optimistic layer where there is one. A closed today is only
         * gone to (utils/date-limits.js), and a month that lies wholly outside the limits not at
         * all.
         *
         * @returns {boolean} true when today is to be picked now
         */
        goToday() {
            if (this.disabled || this.todayDisabled !== null) {
                return false;
            }

            const shownFrom = new Date(this.viewYear, this.viewMonth, 1);
            const shownTo = new Date(this.viewYear, this.viewMonth + this.months, 0);
            const todaysMonth = new Date(today.getFullYear(), today.getMonth(), 1);

            if (todaysMonth < shownFrom || todaysMonth > shownTo) {
                this.viewYear = today.getFullYear();
                this.viewMonth = today.getMonth();
                this.focusOffset = 0;
                this.focusedDay = today.getDate();

                return false;
            }

            this.focusOffset = (today.getFullYear() - this.viewYear) * 12 + today.getMonth() - this.viewMonth;
            this.focusedDay = today.getDate();

            // Read-only, the button only goes to today; it picks nothing.
            return ! this.readonly && ! dayClosed(this.todayDate, this._limits, this._waitingStart());
        },

        /**
         * Navigate to previous month. A month that lies wholly before `min` is no way to go: the
         * view stays, and so does the day the keyboard stands on.
         *
         * @returns {boolean} whether the view moved
         */
        prevMonth() {
            if (! this.canGoPrev) {
                return false;
            }

            if (this.viewMonth === 0) {
                this.viewMonth = 11;
                this.viewYear--;
            } else {
                this.viewMonth--;
            }
            this.focusedDay = 1;

            return true;
        },

        /**
         * Navigate to next month, unless the month that would come into view lies wholly after
         * `max`.
         *
         * @returns {boolean} whether the view moved
         */
        nextMonth() {
            if (! this.canGoNext) {
                return false;
            }

            if (this.viewMonth === 11) {
                this.viewMonth = 0;
                this.viewYear++;
            } else {
                this.viewMonth++;
            }
            this.focusedDay = 1;

            return true;
        },

        /** Move the view by whole months, forward or back, and stop at a month the limits close. */
        _moveMonths(count) {
            for (let step = 0; step < Math.abs(count); step++) {
                if (! (count > 0 ? this.nextMonth() : this.prevMonth())) {
                    return;
                }
            }
        },

        /**
         * Page the keyboard's day by whole months, as Page Up and Page Down do: the view moves as
         * `_moveMonths()` moves it, and the day keeps its number, or becomes the last day of a
         * shorter month. `nextMonth()` and `prevMonth()` alone land on the 1st, which suits the
         * arrow buttons, whose focus stays on the button. Where a bound holds the view where it
         * was, the day stays as well.
         *
         * @param {number} count
         */
        _pageMonths(count) {
            const day = this.focusedDay;

            this._moveMonths(count);

            const lastDay = new Date(this.viewYear, this.viewMonth + this.focusOffset + 1, 0).getDate();
            this.focusedDay = Math.min(day, lastDay);
        },

        /**
         * Select a date.
         */
        selectDate(dateStr) {
            // A closed day is not picked, whichever way the pick arrives: the click is guarded in the
            // template, and Enter and Space come here. Nor is any day of a locked calendar.
            if (this.locked || dayClosed(dateStr, this._limits, this._waitingStart())) {
                return;
            }

            if (! this.range) {
                this.selected = dateStr;
                this._notify();

                return;
            }

            // Two clicks, and the SECOND one decides which end it is. Forcing the
            // reader to click the earlier day first is the kind of rule that only
            // reads as a rule after they have broken it — so a second click before
            // the start simply becomes the start, and the range stays ordered.
            if (! this.selected || this.selectedEnd) {
                this.selected = dateStr;
                this.selectedEnd = null;
            } else if (dateStr < this.selected) {
                this.selectedEnd = this.selected;
                this.selected = dateStr;
            } else {
                this.selectedEnd = dateStr;
            }

            this.hoverDate = null;
            // A range picked in the grid is the preset it equals, or a custom one.
            this._matchPreset();
            this._notify();
        },

        // ── Presets ───────────────────────────────────────────────────────

        /** Today for the presets: the application's day, which the server writes on the root, else the reader's. */
        _presetToday() {
            const written = typeof this.$root?.getAttribute === 'function' ? this.$root.getAttribute('data-wk-today') : null;

            return /^\d{4}-\d{2}-\d{2}$/.test(written ?? '') ? written : this._formatDate(new Date());
        },

        /** The preset the server named for the value, or null. */
        _namedPreset() {
            const named = typeof this.$root?.getAttribute === 'function' ? this.$root.getAttribute('data-wk-preset') : null;

            return named || null;
        },

        /**
         * The range a preset names today, inside the calendar's limits, or null. A preset that
         * starts or ends on an unavailable day is closed as a click on that day picks nothing; one
         * that only covers such a day stays open, as a range picked in the grid may.
         */
        _presetRange(key) {
            const range = presetRange(key, this._presetToday(), this.weekStartsOn, this._limits.min);

            if (range === null) {
                return null;
            }

            const [start, end] = range;
            const limits = this._limits;
            const span = spanOf(start, end);
            const outside = (limits.min && start < limits.min) || (limits.max && end > limits.max)
                || (limits.minRange !== null && span < limits.minRange) || (limits.maxRange !== null && span > limits.maxRange)
                || limits.unavailable.has(start) || limits.unavailable.has(end);

            return outside ? null : range;
        },

        /**
         * The label of the list's option at `index`, read from the presets alone.
         *
         * The list writes its text through this rather than through the option it iterates: the
         * options are new objects whenever the tab stop moves, as it does on the focus a press
         * gives an option, and a text bound to the option is written again then. WebKit sends no
         * click when the text under the pointer is replaced between the press and the release.
         */
        presetLabelAt(index) {
            const key = presetKeys[index];

            return key === undefined ? '' : (presetLabels[key] ?? key);
        },

        /**
         * The options of the list: each preset with its label, whether it can be picked, whether
         * it is the range's, and whether it holds the tab stop.
         */
        presetOptions() {
            const options = presetKeys.map((key) => ({
                key,
                label: presetLabels[key] ?? key,
                disabled: this.disabled || (key !== CUSTOM_PRESET && this._presetRange(key) === null),
                selected: this.preset === key,
            }));
            const chosen = options.findIndex((option) => option.selected);
            const active = this.presetActive >= 0 && this.presetActive < options.length ? this.presetActive : -1;
            // A disabled calendar keeps no tab stop in its list, as its grid keeps none.
            const stop = this.disabled ? -1 : (chosen !== -1 ? chosen : (active !== -1 ? active : options.findIndex((option) => ! option.disabled)));

            return options.map((option, index) => ({ ...option, tabStop: index === stop }));
        },

        /**
         * Pick a preset: its range, with the grid on the month of its start. `custom` takes no range
         * of its own and hands the focus to the grid, where the reader picks one.
         */
        choosePreset(key) {
            if (this.locked) {
                return;
            }

            if (key === CUSTOM_PRESET && presetKeys.includes(CUSTOM_PRESET)) {
                this.preset = CUSTOM_PRESET;
                this.$nextTick?.(() => this._focusDay());

                return;
            }

            const range = presetKeys.includes(key) ? this._presetRange(key) : null;

            if (range === null) {
                return;
            }

            const [year, month, date] = range[0].split('-').map(Number);

            this.selected = range[0];
            this.selectedEnd = range[1];
            this.hoverDate = null;
            this.preset = key;
            this.viewYear = year;
            this.viewMonth = month - 1;
            this.focusedDay = date;
            this.focusOffset = 0;
            this._notify();
        },

        /** The preset the range is: the one the server named, the one its days equal, `custom`, or none. */
        _matchPreset(named = null) {
            if (presetKeys.length === 0) {
                return;
            }

            if (named !== null && named !== CUSTOM_PRESET && presetKeys.includes(named)) {
                this.preset = named;

                return;
            }

            if (! this.selected || ! this.selectedEnd) {
                this.preset = null;

                return;
            }

            this.preset = presetOf(this.selected, this.selectedEnd, presetKeys, this._presetToday(), this.weekStartsOn, this._limits.min)
                ?? (presetKeys.includes(CUSTOM_PRESET) ? CUSTOM_PRESET : null);
        },

        /** The preset a form receives: its key, and nothing for a custom range or none. */
        get presetValue() {
            return this.preset && this.preset !== CUSTOM_PRESET ? this.preset : '';
        },

        /** The keyboard of the list. */
        presetKeydown(event) {
            const options = this.presetOptions();
            const open = options.map((option, index) => (option.disabled ? -1 : index)).filter((index) => index !== -1);
            const at = this.presetActive >= 0 ? this.presetActive : options.findIndex((option) => option.tabStop);
            const moves = {
                ArrowDown: () => open.find((index) => index > at) ?? at,
                ArrowUp: () => [...open].reverse().find((index) => index < at) ?? at,
                Home: () => open[0] ?? at,
                End: () => open.at(-1) ?? at,
            };

            if (moves[event.key]) {
                event.preventDefault();
                const next = moves[event.key]();

                this.presetActive = next;
                this.$nextTick?.(() => this.$refs?.presetList?.querySelectorAll('[role="option"]')[next]?.focus());

                return;
            }

            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                const option = options[at];

                if (option && ! option.disabled) {
                    this.choosePreset(option.key);
                }
            }
        },

        /**
         * The range state of a day outside any range: every key `_rangeFlags()` returns, each off.
         * Every key, because the view binds `day.rangeMarker` to `data-wk-in-range` and `day.band`
         * to `data-wk-band`, and Alpine renders a field the object does not have as an empty
         * attribute, which the stylesheet reads as a day in a range.
         */
        _noRangeFlags() {
            return { isRangeStart: false, isRangeEnd: false, isInRange: false, isProvisionalEnd: false, rangeMarker: null, band: null };
        },

        /** Shading state for one day. Cheap enough to run per cell, per render. */
        _rangeFlags(dateStr) {
            if (! this.range || ! this.selected) {
                return this._noRangeFlags();
            }

            // While only the start is set, the day under the pointer stands in for
            // the end. That makes the shading answer the question the reader is
            // actually asking — "what am I about to choose" — instead of showing
            // nothing until the choice is already made.
            // IN EITHER DIRECTION. Requiring `hoverDate > selected` would give
            // hovering backwards no provisional end at all: the reader drags toward
            // an earlier day, sees nothing shade, clicks anyway, and only then
            // discovers the range was right all along.
            //
            // `selectDate()` below goes out of its way to accept a backwards choice —
            // "a second click before the start simply becomes the start, and the
            // range stays ordered" — so the preview supports the same gesture.
            const provisional = ! this.selectedEnd && this.hoverDate ? this.hoverDate : null;
            const end = this.selectedEnd || provisional;

            // Ordered the same way selectDate() orders a second click, so the shading
            // between the two days does not depend on which one came first.
            const lo = end !== null && end < this.selected ? end : this.selected;
            const hi = end !== null && end < this.selected ? this.selected : end;

            const isInRange = end !== null && dateStr > lo && dateStr < hi;

            // The day under the pointer, standing in for an end nobody has chosen
            // yet. It needs its own name because the two things that PAINT a day
            // both miss it: the endpoints are filled by `isSelected`, which is
            // false while `selectedEnd` is null, and the middle is shaded by
            // `isInRange`, which is exclusive precisely because the endpoints
            // normally fill themselves.
            //
            // Without it the provisional end would fall between them and stay
            // blank, the days on either side of the pointer shaded and the one
            // under it not, while the documented behavior promises that the
            // shading answers "what am I about to choose".
            //
            // It is shaded rather than filled: a dark fill would claim a choice
            // that has not been made, and the difference between "selected" and
            // "about to be selected" is the whole point of a preview.
            const isProvisionalEnd = provisional !== null && dateStr === provisional;

            // The caps are the ORDERED ends, not "the one clicked first". While the
            // pointer sits before the start, the day under it is the visual start,
            // and keying these off `selected` would give the left cap's half of the
            // band to the day on the right.
            const isRangeStart = end !== null && dateStr === lo;
            const isRangeEnd = end !== null && dateStr === hi;

            // The part of the band this day's cell draws: from its middle onward at the start,
            // up to its middle at the end, across the whole cell between them. A range of one
            // day, or the pointer resting on the start, has no band to draw.
            let band = null;
            if (end !== null && lo !== hi) {
                band = isRangeStart ? 'start' : (isRangeEnd ? 'end' : (isInRange ? 'middle' : null));
            }

            return {
                isRangeStart,
                isRangeEnd,
                isInRange,
                isProvisionalEnd,
                // The attribute VALUE, computed with the rest of this cell's range
                // state: `''` sets `data-wk-in-range` and `null` removes it, which
                // is what the stylesheet selects on.
                rangeMarker: (isInRange || isProvisionalEnd) && dateStr !== this.selected && dateStr !== this.selectedEnd ? '' : null,
                // Likewise the VALUE of the cell's `data-wk-band`, or null to remove it.
                band,
            };
        },

        /** Provisional end while the range is half-made. */
        hoverDay(dateStr) {
            // A locked calendar previews no range: nothing the reader does could make it.
            if (! this.locked && this.range && this.selected && ! this.selectedEnd) {
                // The preview shows a span the reader can make, and a closed day ends none.
                this.hoverDate = dayClosed(dateStr, this._limits, this.selected) ? null : dateStr;
            }
        },

        /** The start of a range that waits for its other end, or null. */
        _waitingStart() {
            return this.range && this.selected && ! this.selectedEnd ? this.selected : null;
        },

        /**
         * The two ends as form values.
         *
         * Getters rather than `selected ?? ''` in the template: Alpine's CSP build
         * does not parse `??`, and a directive it cannot parse does nothing at all
         * under a policy this library promises to support. The combined field below
         * is a getter for the same reason.
         */
        get rangeStartValue() {
            return this.selected || '';
        },

        get rangeEndValue() {
            return this.selectedEnd || '';
        },

        /** The value a form or a server sees. */
        get rangeValue() {
            if (! this.range) {
                return this.selected ?? '';
            }

            return this.selected ? (this.selectedEnd ? this.selected + '/' + this.selectedEnd : this.selected) : '';
        },

        /**
         * Tell the form what `selected` now holds.
         *
         * Split out of selectDate() because the optimistic layer writes
         * `selected` itself — on the flip AND on the rollback — and has to run
         * the same sync afterwards. Without it a rolled-back calendar would
         * show the old date while the form still submits the new one.
         *
         * It reads `selected` rather than taking an argument for the same
         * reason: after a rollback there is no argument, only the value the
         * layer restored.
         */
        _notify() {
            // The preset first, so a picker that hears the combined field below reads the preset
            // of the same pick from its field.
            this.$refs.hiddenPreset?.setAttribute('value', this.presetValue);

            if (this.$refs.hiddenPreset) {
                this.$refs.hiddenPreset.value = this.presetValue;
            }

            // setAttribute as well as the reactive :value binding — the
            // attribute is what a form serializes, and assigning it fires
            // nothing, so the event goes out by hand for wire:model.
            this.$refs.hiddenInput?.setAttribute('value', this.rangeValue);
            own.note(this.rangeValue);
            this.$refs.hiddenInput?.dispatchEvent(new Event('input', { bubbles: true }));

            // Two named fields as well, matching `date-picker`: a server that
            // reads `name[start]` / `name[end]` from one gets them from the other.
            // The combined `name` field is written as well, so a single-date handler
            // keeps working when a calendar switches to range mode.
            if (this.range) {
                for (const [ref, value] of [['hiddenStart', this.selected], ['hiddenEnd', this.selectedEnd]]) {
                    this.$refs[ref]?.setAttribute('value', value ?? '');
                    this.$refs[ref]?.dispatchEvent(new Event('input', { bubbles: true }));
                }

                this.$refs.hiddenPreset?.dispatchEvent(new Event('input', { bubbles: true }));
            }

            // A chosen date is committed at once, as a native date field commits a pick.
            this._modelEvents?.commit();
        },

        /**
         * Handle keyboard navigation within the calendar grid.
         */
        handleKeydown(event) {
            // The focused grid = base view shifted by focusOffset. For a single
            // month focusOffset is always 0, so fYear/fMonth === view*, lastOffset
            // is 0, and every cross-grid branch below is skipped.
            //
            // At a month boundary ArrowUp and ArrowDown move to the same weekday one
            // week away, as the documented model promises, not to the 1st that
            // `prevMonth()` / `nextMonth()` land on. Both else-branches compute the
            // day the way the cross-grid branches beside them do, so the single-month
            // and multi-month modes answer identically.
            const fBase = new Date(this.viewYear, this.viewMonth + this.focusOffset, 1);
            const fYear = fBase.getFullYear();
            const fMonth = fBase.getMonth();
            const daysInMonth = new Date(fYear, fMonth + 1, 0).getDate();
            const lastOffset = this.months - 1;

            switch (event.key) {
                case 'ArrowRight':
                    event.preventDefault();
                    if (this.focusedDay < daysInMonth) {
                        this.focusedDay++;
                    } else if (this.focusOffset < lastOffset) {
                        this.focusOffset++;
                        this.focusedDay = 1;
                    } else {
                        this.nextMonth();
                    }
                    this._focusDay();
                    break;

                case 'ArrowLeft':
                    event.preventDefault();
                    if (this.focusedDay > 1) {
                        this.focusedDay--;
                    } else if (this.focusOffset > 0) {
                        this.focusOffset--;
                        this.focusedDay = new Date(this.viewYear, this.viewMonth + this.focusOffset + 1, 0).getDate();
                    } else if (this.prevMonth()) {
                        this.focusedDay = new Date(this.viewYear, this.viewMonth + 1, 0).getDate();
                    }
                    this._focusDay();
                    break;

                case 'ArrowDown':
                    event.preventDefault();
                    if (this.focusedDay + 7 <= daysInMonth) {
                        this.focusedDay += 7;
                    } else if (this.focusOffset < lastOffset) {
                        // Same weekday, one week down — Date normalization rolls the
                        // overflow into the next displayed month exactly.
                        this.focusedDay = new Date(fYear, fMonth, this.focusedDay + 7).getDate();
                        this.focusOffset++;
                    } else {
                        // Off the last displayed grid, so the VIEW has to move —
                        // but the day the reader lands on is still the same weekday
                        // one week down, which is what the keyboard table promises.
                        // Read it BEFORE the view moves: `nextMonth()` writes
                        // `focusedDay = 1`, so a bare call to it would throw the
                        // reader to the 1st of the next month. ArrowRight is the
                        // branch where day 1 IS the answer, which is why the default
                        // is right there and wrong here; ArrowLeft overrides it in
                        // exactly this shape.
                        const sameWeekdayNextWeek = new Date(fYear, fMonth, this.focusedDay + 7).getDate();

                        // At `max` the view stays, and so does the day.
                        if (this.nextMonth()) {
                            this.focusedDay = sameWeekdayNextWeek;
                        }
                    }
                    this._focusDay();
                    break;

                case 'ArrowUp':
                    event.preventDefault();
                    if (this.focusedDay - 7 >= 1) {
                        this.focusedDay -= 7;
                    } else if (this.focusOffset > 0) {
                        this.focusedDay = new Date(fYear, fMonth, this.focusedDay - 7).getDate();
                        this.focusOffset--;
                    } else {
                        // The mirror of ArrowDown above: `prevMonth()` also lands on
                        // the 1st, which is not a day any reader arrowing UPWARD asked
                        // for.
                        const sameWeekdayPrevWeek = new Date(fYear, fMonth, this.focusedDay - 7).getDate();

                        // At `min` the view stays, and so does the day.
                        if (this.prevMonth()) {
                            this.focusedDay = sameWeekdayPrevWeek;
                        }
                    }
                    this._focusDay();
                    break;

                case 'Enter':
                case ' ':
                    event.preventDefault();
                    this.selectDate(this._formatDate(new Date(fYear, fMonth, this.focusedDay)));
                    break;

                // A month, with Shift a year, twelve months as far as `min` and `max` allow, onto
                // the day with the same number, as the date picker dialog of the WAI-ARIA practices
                // moves (`_pageMonths()`).
                case 'PageDown':
                case 'PageUp':
                    event.preventDefault();
                    this._pageMonths((event.key === 'PageDown' ? 1 : -1) * (event.shiftKey ? 12 : 1));
                    this._focusDay();
                    break;

                case 'Home':
                    event.preventDefault();
                    this.focusedDay = 1;
                    this._focusDay();
                    break;

                case 'End':
                    event.preventDefault();
                    this.focusedDay = daysInMonth;
                    this._focusDay();
                    break;
            }
        },

        /**
         * Keep the keyboard's day on the day that took the focus.
         *
         * A click focuses the day it lands on, and the arrow keys and the grid's one tab stop
         * have to go on from that day rather than from the one the keyboard stood on before.
         * Bound to the day's focus event, as the menubar binds its triggers, rather than to
         * its click: the optimistic layer's click never reaches `selectDate()`. A focus the
         * arrow keys set changes nothing here, `_focusDay()` has put the day there already.
         */
        followFocus(day, offset) {
            if (! day?.isCurrentMonth) {
                return;
            }

            this.focusedDay = day.dayOfMonth;
            this.focusOffset = offset;
        },

        _focusDay() {
            // Moving focus IS hovering, for someone who is not using a pointer.
            //
            // The provisional end — the shading and the `aria-selected` that say
            // "release here and this is your range" — is set by `hoverDay()`. Reached
            // only from `mouseenter`, it would leave a keyboard user picking a range
            // with the first date and then no feedback at all until they committed
            // the second one, and there is no other way to find out what a range
            // will be before making it.
            //
            // Set here rather than in each arrow branch because every one of them
            // ends in this call, and a branch added later would otherwise have to
            // remember. `hoverDay()` keeps its own guard, so this is a no-op unless a
            // range is genuinely half-made.
            //
            // An arrow key therefore moves `aria-selected` onto cells nobody has
            // chosen, and deliberately: while a range is half-made the provisional
            // span IS the pending selection — it is exactly what the pointer user is
            // looking at — and the grid pattern's `aria-selected` is the closest true
            // statement available. Announcing it through the live region instead
            // would fire on every arrow key; a state on the focused cell is read when
            // the cell is read, which is quieter, not louder. And it only happens
            // between the two ends, which is the one moment the information is what
            // the reader wants.
            //
            // The guard is what keeps this narrow: an ordinary calendar, or a range
            // with both ends set, moves no selection anywhere.
            this.hoverDay(this._formatDate(
                new Date(this.viewYear, this.viewMonth + this.focusOffset, this.focusedDay)
            ));

            this.$nextTick(() => {
                // Scope to the focused grid in multi-month mode (day numbers repeat
                // across grids); a single month has only the one grid.
                const sel = this.months > 1
                    ? `[data-wk-month="${this.focusOffset}"] [data-wk-day="${this.focusedDay}"]`
                    : `[data-wk-day="${this.focusedDay}"]`;
                this.$el.querySelector(sel)?.focus();
            });
        },

        _formatDate(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        },

        /**
         * The accessible name of one day cell.
         *
         * The visible text of a day button is the day number alone, and on its
         * own that is the whole of what a screen reader would announce: "15,
         * button", with the month, the year and the fact that it is today
         * carried only by the heading above the grid and by a ring drawn around
         * the cell. Neither survives the reader moving through the cells, which
         * is the way the grid is meant to be read. The WAI-ARIA date-picker example this
         * component follows names every cell with its full date, so this does
         * too — and appends the translated word for today, the one part of a
         * date no formatter can supply.
         */
        _dayLabel(date, isToday, closed) {
            const label = fullDateFormat.format(date);
            const today = isToday && todayWord ? label + ', ' + todayWord : label;

            // A closed day stays in the grid for the keyboard, so it says it cannot be picked.
            return closed && unavailableWord ? today + ', ' + unavailableWord : today;
        },
    };
}
