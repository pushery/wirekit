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
    const initial = config.value ? new Date(config.value + 'T00:00:00') : null;

    return {
        viewYear: initial?.getFullYear() || today.getFullYear(),
        viewMonth: initial?.getMonth() || today.getMonth(),
        selected: config.value || null,
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
                    isSelected: dateStr === this.selected,
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
            this.selected = dateStr;
            // Dispatch input event for wire:model
            this.$refs.hiddenInput?.setAttribute('value', dateStr);
            this.$refs.hiddenInput?.dispatchEvent(new Event('input', { bubbles: true }));
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
