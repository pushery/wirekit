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

        /**
         * Get days array for current view month.
         * Returns objects: { date, dayOfMonth, isCurrentMonth, isToday, isSelected }
         */
        get days() {
            const year = this.viewYear;
            const month = this.viewMonth;
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const startPad = firstDay.getDay(); // 0=Sun
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
            const daysInMonth = new Date(this.viewYear, this.viewMonth + 1, 0).getDate();

            switch (event.key) {
                case 'ArrowRight':
                    event.preventDefault();
                    if (this.focusedDay < daysInMonth) {
                        this.focusedDay++;
                    } else {
                        this.nextMonth();
                    }
                    this._focusDay();
                    break;

                case 'ArrowLeft':
                    event.preventDefault();
                    if (this.focusedDay > 1) {
                        this.focusedDay--;
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
                    } else {
                        this.nextMonth();
                    }
                    this._focusDay();
                    break;

                case 'ArrowUp':
                    event.preventDefault();
                    if (this.focusedDay - 7 >= 1) {
                        this.focusedDay -= 7;
                    } else {
                        this.prevMonth();
                    }
                    this._focusDay();
                    break;

                case 'Enter':
                case ' ':
                    event.preventDefault();
                    this.selectDate(this._formatDate(new Date(this.viewYear, this.viewMonth, this.focusedDay)));
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
                const btn = this.$el.querySelector(`[data-wk-day="${this.focusedDay}"]`);
                btn?.focus();
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
