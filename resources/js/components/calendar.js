/**
 * WireKit Calendar Alpine Component.
 *
 * Standalone month grid with day cells for date selection.
 * Supports single date selection, keyboard navigation, and month/year changes.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/examples/datepicker-dialog/
 */
export default function wirekitCalendar(config = {}) {
    const today = new Date();

    // The APPLICATION's locale, not the browser's — the component receives it
    // from Blade, the way countdown does. `undefined` is the deliberate
    // fallback and not a placeholder: it is what `Intl` reads as "use the
    // browser's own preference", so a calendar mounted by hand without the
    // config still names its months in a language the reader chose, rather than
    // in the one this file happens to be written in.
    const locale = config.locale || undefined;

    // Built once per instance rather than per read. Every string below depends
    // only on the locale and on the first weekday, and neither changes for the
    // life of the component — while `days` is re-read on every keystroke that
    // moves focus, so a formatter constructed inside it would be constructed
    // forty-two times for a grid that cannot have changed.
    const monthYearFormat = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' });
    const fullDateFormat = new Intl.DateTimeFormat(locale, {
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

    // Resolved once here because two things need it: the grid's leading pad and
    // the column headers, and reading it from the reactive property would tie
    // the headers to a value the reader can never change.
    const weekStartsOn = Number.isInteger(config.weekStartsOn) ? config.weekStartsOn : 1;

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

    // `YYYY-MM-DD/YYYY-MM-DD` is the range spelling, the same one `date-picker`
    // already reads and writes. Sharing it is the point: a value copied from one
    // to the other keeps meaning, and a form receives the same two fields either
    // way. A single date in range mode is a started range with no end yet, which
    // is exactly the state a first click produces.
    const [rawStart, rawEnd] = String(config.value || '').split('/');
    const startValue = rawStart || null;
    const endValue = rawEnd || null;

    const initial = startValue ? new Date(startValue + 'T00:00:00') : null;

    // Every read of `initial` below branches on `initial` itself, never on the
    // number a getter hands back. `getMonth()` is zero-based, so a January value
    // yields 0 — and `0 || today.getMonth()` fell through to today, opening the
    // grid on the current month while the form carried January. A seasonal
    // defect: wrong for one month a year and correct the other eleven, which is
    // why it survived. The question being asked is "was a value supplied?", so
    // the test is on the value's presence and not on the digit it produced.
    return {
        viewYear: initial ? initial.getFullYear() : today.getFullYear(),
        viewMonth: initial ? initial.getMonth() : today.getMonth(),
        selected: startValue,

        // ── Range mode ────────────────────────────────────────────────────────
        // `selected` is the start in both modes, so every existing read of it —
        // the optimistic layer's bind, the hidden input, the keyboard model —
        // keeps working untouched. Only `selectedEnd` is new.
        range: !! config.range,
        selectedEnd: endValue,
        // The day under the pointer while a range is half-made. It drives the
        // provisional shading and nothing else: a range that shows no in-between
        // until the second click makes the reader guess what they are choosing.
        hoverDate: null,
        focusedDay: initial ? initial.getDate() : today.getDate(),
        _name: config.name || 'date',
        // Multi-month display: render N consecutive months side by side (1 = the
        // classic single grid). Clamped 1..4. focusOffset tracks which displayed
        // month currently holds keyboard focus (always 0 for single-month).
        months: Math.min(4, Math.max(1, parseInt(config.months, 10) || 1)),
        focusOffset: 0,

        // First day of the week: 0 (Sun) .. 1 (Mon, default) — matches the house
        // convention + <x-wirekit::event-calendar>.
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
         * The template used to do this inline, with
         * `Array.from({ length: Math.ceil(days.length / 7) }, (_, i) => …)`.
         * Under Alpine's CSP build that fails twice over: the arrow function is
         * outside the grammar its parser accepts, and `Array` / `Math` are
         * globals its evaluator cannot resolve. The grid simply did not render.
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

            // Pad with previous month days
            const prevMonthLast = new Date(year, month, 0).getDate();
            for (let i = startPad - 1; i >= 0; i--) {
                const padDate = new Date(year, month - 1, prevMonthLast - i);
                result.push({
                    date: this._formatDate(padDate),
                    dayOfMonth: prevMonthLast - i,
                    label: this._dayLabel(padDate, false),
                    isCurrentMonth: false,
                    isToday: false,
                    isSelected: false,
                    ...this._rangeFlags(this._formatDate(padDate)),
                });
            }

            // Current month days
            for (let d = 1; d <= daysInMonth; d++) {
                const dayDate = new Date(year, month, d);
                const dateStr = this._formatDate(dayDate);
                result.push({
                    date: dateStr,
                    dayOfMonth: d,
                    label: this._dayLabel(dayDate, dateStr === todayStr),
                    isCurrentMonth: true,
                    isToday: dateStr === todayStr,
                    isSelected: dateStr === this.selected || (this.range && dateStr === this.selectedEnd),
                    ...this._rangeFlags(dateStr),
                });
            }

            // Pad to complete last week
            const remaining = 7 - (result.length % 7);
            if (remaining < 7) {
                for (let d = 1; d <= remaining; d++) {
                    const padDate = new Date(year, month + 1, d);
                    result.push({
                        date: this._formatDate(padDate),
                        dayOfMonth: d,
                        label: this._dayLabel(padDate, false),
                        isCurrentMonth: false,
                        isToday: false,
                        isSelected: false,
                        ...this._rangeFlags(this._formatDate(padDate)),
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
         * Composed here rather than in the template because the base is the
         * component's `name`, which is developer-supplied: interpolated into an
         * Alpine expression it would be a quote away from ending the string it
         * sits in, the same hazard the value literal at the top of the template
         * is built to avoid.
         */
        monthLabelId(offset) {
            return this._name + '-calendar-label-' + (offset || 0);
        },

        /**
         * Navigate to previous month.
         */
        prevMonth() {
            if (this.viewMonth === 0) {
                this.viewMonth = 11;
                this.viewYear--;
            } else {
                this.viewMonth--;
            }
            this.focusedDay = 1;
        },

        /**
         * Navigate to next month.
         */
        nextMonth() {
            if (this.viewMonth === 11) {
                this.viewMonth = 0;
                this.viewYear++;
            } else {
                this.viewMonth++;
            }
            this.focusedDay = 1;
        },

        /**
         * Select a date.
         */
        selectDate(dateStr) {
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
            this._notify();
        },

        /** Shading state for one day. Cheap enough to run per cell, per render. */
        _rangeFlags(dateStr) {
            if (! this.range || ! this.selected) {
                return { isRangeStart: false, isRangeEnd: false, isInRange: false };
            }

            // While only the start is set, the day under the pointer stands in for
            // the end. That makes the shading answer the question the reader is
            // actually asking — "what am I about to choose" — instead of showing
            // nothing until the choice is already made.
            // IN EITHER DIRECTION. This used to require `hoverDate > selected`, so
            // hovering backwards produced no provisional end at all and the preview
            // simply did not exist: the reader dragged toward an earlier day, saw
            // nothing shade, clicked anyway, and only then discovered the range had
            // been right all along.
            //
            // The inconsistency was internal, which is what makes it a defect rather
            // than a missing feature. `selectDate()` below goes out of its way to
            // accept a backwards choice — "a second click before the start simply
            // becomes the start, and the range stays ordered" — so the component
            // supports the gesture and refused to preview it.
            const provisional = ! this.selectedEnd && this.hoverDate ? this.hoverDate : null;
            const end = this.selectedEnd || provisional;

            // Ordered the same way selectDate() orders a second click, so the shading
            // between the two days no longer depends on which one came first.
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
            // So the provisional end fell between them and stayed blank — the
            // days on either side of the pointer shaded, and the one under it
            // did not. The documented behavior is
            // what makes it a defect rather than a preference: it promises the
            // shading answers "what am I about to choose", and the day being
            // chosen was the one day it did not answer for.
            //
            // It is shaded rather than filled: a dark fill would claim a choice
            // that has not been made, and the difference between "selected" and
            // "about to be selected" is the whole point of a preview.
            const isProvisionalEnd = provisional !== null && dateStr === provisional;

            return {
                // The caps are the ORDERED ends, not "the one clicked first". While the
                // pointer sits before the start, the day under it is the visual start —
                // and keying these off `selected` gave the left cap's rounding to the
                // day on the right.
                isRangeStart: end !== null && dateStr === lo,
                isRangeEnd: end !== null && dateStr === hi,
                isInRange,
                isProvisionalEnd,
                // The attribute VALUE, computed here rather than in the template.
                // Alpine's CSP build holds one member access per directive — a
                // ternary with `&&` and `!` in it does not parse there, and the
                // failure is a directive that silently does nothing under a policy
                // the library promises to support.
                rangeMarker: (isInRange || isProvisionalEnd) && dateStr !== this.selected && dateStr !== this.selectedEnd ? '' : null,
            };
        },

        /** Provisional end while the range is half-made. */
        hoverDay(dateStr) {
            if (this.range && this.selected && ! this.selectedEnd) {
                this.hoverDate = dateStr;
            }
        },

        /**
         * The two ends as form values.
         *
         * Getters rather than `selected ?? ''` in the template: Alpine's CSP build
         * does not parse `??`, and a directive it cannot parse does nothing at all
         * under a policy this library promises to support. The combined field below
         * was already a getter for the same reason — these two were written as
         * expressions and the audit caught both.
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
         * show the old date while the form still submitted the new one.
         *
         * It reads `selected` rather than taking an argument for the same
         * reason: after a rollback there is no argument, only the value the
         * layer restored.
         */
        _notify() {
            // setAttribute as well as the reactive :value binding — the
            // attribute is what a form serializes, and assigning it fires
            // nothing, so the event goes out by hand for wire:model.
            this.$refs.hiddenInput?.setAttribute('value', this.rangeValue);
            this.$refs.hiddenInput?.dispatchEvent(new Event('input', { bubbles: true }));

            // Two named fields as well, matching `date-picker`: a server that
            // reads `name[start]` / `name[end]` from one gets them from the other.
            // The combined `name` field stays, so an existing single-date handler
            // is not broken by switching a calendar to range mode.
            if (this.range) {
                for (const [ref, value] of [['hiddenStart', this.selected], ['hiddenEnd', this.selectedEnd]]) {
                    this.$refs[ref]?.setAttribute('value', value ?? '');
                    this.$refs[ref]?.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }
        },

        /**
         * Handle keyboard navigation within the calendar grid.
         */
        handleKeydown(event) {
            // The focused grid = base view shifted by focusOffset. For a single
            // month focusOffset is always 0, so fYear/fMonth === view*, lastOffset
            // is 0, and every cross-grid branch below is skipped — byte-identical
            // to the classic single-month behavior.
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
                    } else {
                        this.prevMonth();
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
                        this.nextMonth();
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
                        this.prevMonth();
                    }
                    this._focusDay();
                    break;

                case 'Enter':
                case ' ':
                    event.preventDefault();
                    this.selectDate(this._formatDate(new Date(fYear, fMonth, this.focusedDay)));
                    break;

                case 'PageDown':
                    event.preventDefault();
                    this.nextMonth();
                    this._focusDay();
                    break;

                case 'PageUp':
                    event.preventDefault();
                    this.prevMonth();
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

        _focusDay() {
            // Moving focus IS hovering, for someone who is not using a pointer.
            //
            // The provisional end — the shading and the `aria-selected` that say
            // "release here and this is your range" — was written for `hoverDay()`
            // and reachable only from `mouseenter`. So a keyboard user picking a
            // range got the first date, then no feedback at all until they committed
            // the second one: the preview existed and they could not see it, and
            // there is no second way to find out what a range will be before making
            // it.
            //
            // Set here rather than in each arrow branch because every one of them
            // ends in this call — six branches, and a seventh added later would have
            // to remember. `hoverDay()` keeps its own guard, so this is a no-op
            // unless a range is genuinely half-made.
            //
            // This does mean an arrow key now moves `aria-selected` onto cells nobody
            // has chosen, and that objection was raised and is worth answering rather
            // than leaving for someone to rediscover. It is deliberate: while a range
            // is half-made the provisional span IS the pending selection — it is
            // exactly what the pointer user is looking at — and the grid pattern's
            // `aria-selected` is the closest true statement available. The suggested
            // alternative, announcing it through the live region instead, would fire
            // on every arrow key; a state on the focused cell is read when the cell is
            // read, which is quieter, not louder. And it only happens between the two
            // ends, which is the one moment the information is what the reader wants.
            //
            // The guard is what keeps this narrow: an ordinary calendar, or a range
            // with both ends set, moves no selection anywhere.
            this.hoverDay(this._formatDate(
                new Date(this.viewYear, this.viewMonth + this.focusOffset, this.focusedDay)
            ));

            this.$nextTick(() => {
                // Scope to the focused grid in multi-month mode (day numbers repeat
                // across grids); the single-month selector is unchanged.
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
         * The visible text of a day button is the day number alone, and that is
         * the whole of what a screen reader announced: "15, button", with the
         * month, the year and the fact that it is today carried only by the
         * heading above the grid and by a ring drawn around the cell. Neither
         * survives the reader moving through the cells, which is the way the
         * grid is meant to be read. The WAI-ARIA date-picker example this
         * component follows names every cell with its full date, so this does
         * too — and appends the translated word for today, the one part of a
         * date no formatter can supply.
         */
        _dayLabel(date, isToday) {
            const label = fullDateFormat.format(date);

            return isToday && todayWord ? label + ', ' + todayWord : label;
        },
    };
}
