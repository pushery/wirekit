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

    // `YYYY-MM-DD/YYYY-MM-DD` is the range spelling, the same one `date-picker`
    // already reads and writes. Sharing it is the point: a value copied from one
    // to the other keeps meaning, and a form receives the same two fields either
    // way. A single date in range mode is a started range with no end yet, which
    // is exactly the state a first click produces.
    const [rawStart, rawEnd] = String(config.value || '').split('/');
    const startValue = rawStart || null;
    const endValue = rawEnd || null;

    const initial = startValue ? new Date(startValue + 'T00:00:00') : null;

    return {
        viewYear: initial?.getFullYear() || today.getFullYear(),
        viewMonth: initial?.getMonth() || today.getMonth(),
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
        focusedDay: initial?.getDate() || today.getDate(),
        _name: config.name || 'date',
        // Multi-month display: render N consecutive months side by side (1 = the
        // classic single grid). Clamped 1..4. focusOffset tracks which displayed
        // month currently holds keyboard focus (always 0 for single-month).
        months: Math.min(4, Math.max(1, parseInt(config.months, 10) || 1)),
        focusOffset: 0,

        // First day of the week: 0 (Sun) .. 1 (Mon, default) — matches the house
        // convention + <x-wirekit::event-calendar>.
        weekStartsOn: Number.isInteger(config.weekStartsOn) ? config.weekStartsOn : 1,

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
                    label: base.toLocaleDateString('en-US', { month: 'long', year: 'numeric' }),
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
                result.push({
                    date: this._formatDate(new Date(year, month - 1, prevMonthLast - i)),
                    dayOfMonth: prevMonthLast - i,
                    isCurrentMonth: false,
                    isToday: false,
                    isSelected: false,
                    ...this._rangeFlags(this._formatDate(new Date(year, month - 1, prevMonthLast - i))),
                });
            }

            // Current month days
            for (let d = 1; d <= daysInMonth; d++) {
                const dateStr = this._formatDate(new Date(year, month, d));
                result.push({
                    date: dateStr,
                    dayOfMonth: d,
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
                    result.push({
                        date: this._formatDate(new Date(year, month + 1, d)),
                        dayOfMonth: d,
                        isCurrentMonth: false,
                        isToday: false,
                        isSelected: false,
                        ...this._rangeFlags(this._formatDate(new Date(year, month + 1, d))),
                    });
                }
            }

            return result;
        },

        /**
         * Get month/year display label.
         */
        get monthLabel() {
            const d = new Date(this.viewYear, this.viewMonth, 1);
            return d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
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
            const end = this.selectedEnd || (this.hoverDate && this.hoverDate > this.selected ? this.hoverDate : null);

            const isInRange = !! end && dateStr > this.selected && dateStr < end;

            // The day under the pointer, standing in for an end nobody has chosen
            // yet. It needs its own name because the two things that PAINT a day
            // both miss it: the endpoints are filled by `isSelected`, which is
            // false while `selectedEnd` is null, and the middle is shaded by
            // `isInRange`, which is exclusive precisely because the endpoints
            // normally fill themselves.
            //
            // So the provisional end fell between them and stayed blank — the
            // days on either side of the pointer shaded, and the one under it
            // did not. Reported from the docs page, and the page's own prose is
            // what makes it a defect rather than a preference: it promises the
            // shading answers "what am I about to choose", and the day being
            // chosen was the one day it did not answer for.
            //
            // It is shaded rather than filled: a dark fill would claim a choice
            // that has not been made, and the difference between "selected" and
            // "about to be selected" is the whole point of a preview.
            const isProvisionalEnd = ! this.selectedEnd && !! end && dateStr === end;

            return {
                isRangeStart: dateStr === this.selected && !! end,
                isRangeEnd: !! end && dateStr === end,
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
    };
}
