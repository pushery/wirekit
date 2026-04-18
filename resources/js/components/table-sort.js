/**
 * WireKit Table Sort Alpine Component.
 *
 * Provides client-side sorting for static tables without Livewire.
 * Sorts rows by reading text content or data-wk-sort-value from cells.
 * Supports numeric and string sorting with locale-aware comparison.
 *
 * Usage: add alpine-sort to <x-wirekit::table> and column="name" to
 * sortable <x-wirekit::table.th> elements.
 */
export default function wirekitTableSort(config = {}) {
    return {
        sortColumn: null,
        sortDirection: null,
        _originalOrder: [],

        init() {
            // Snapshot original row order so we can restore it when sort is cleared
            this.$nextTick(() => {
                const tbody = this.$el.querySelector('tbody');
                if (tbody) {
                    this._originalOrder = [...tbody.querySelectorAll('tr')];
                }
            });
        },

        /**
         * Toggle sort on a column. Cycles: null → asc → desc → null.
         * @param {string} column - Column identifier matching data-wk-sort-column on th
         */
        sortBy(column) {
            if (this.sortColumn === column) {
                // Cycle direction: asc → desc → clear
                if (this.sortDirection === 'asc') {
                    this.sortDirection = 'desc';
                } else {
                    this.sortColumn = null;
                    this.sortDirection = null;
                }
            } else {
                this.sortColumn = column;
                this.sortDirection = 'asc';
            }

            this._reorderRows();
        },

        /**
         * Get current sort direction for a specific column.
         * Used by th elements to display the correct indicator arrow.
         * @param {string} column
         * @returns {string|null}
         */
        getSortDirection(column) {
            return this.sortColumn === column ? this.sortDirection : null;
        },

        /**
         * Reorder tbody rows based on current sort state.
         * Reads cell values from data-wk-sort-value attribute or textContent.
         */
        _reorderRows() {
            const tbody = this.$el.querySelector('tbody');
            if (!tbody) return;

            // No active sort — restore original DOM order
            if (!this.sortColumn || !this.sortDirection) {
                this._originalOrder.forEach((row) => tbody.appendChild(row));
                return;
            }

            // Find column index by matching data-wk-sort-column on th elements
            const ths = this.$el.querySelectorAll('thead th');
            let colIndex = -1;
            ths.forEach((th, i) => {
                if (th.dataset.wkSortColumn === this.sortColumn) {
                    colIndex = i;
                }
            });

            if (colIndex === -1) return;

            const rows = [...tbody.querySelectorAll('tr')];

            rows.sort((a, b) => {
                const cellA = a.cells[colIndex];
                const cellB = b.cells[colIndex];

                // Prefer explicit sort value, fall back to trimmed text content
                const valA = cellA?.dataset.wkSortValue ?? cellA?.textContent?.trim() ?? '';
                const valB = cellB?.dataset.wkSortValue ?? cellB?.textContent?.trim() ?? '';

                // Try numeric comparison when both values parse as numbers
                const numA = parseFloat(valA);
                const numB = parseFloat(valB);
                if (!isNaN(numA) && !isNaN(numB)) {
                    return this.sortDirection === 'asc' ? numA - numB : numB - numA;
                }

                // Locale-aware string comparison with natural number ordering
                const cmp = valA.localeCompare(valB, undefined, {
                    numeric: true,
                    sensitivity: 'base',
                });
                return this.sortDirection === 'asc' ? cmp : -cmp;
            });

            // Move sorted rows into the DOM (appendChild relocates existing nodes)
            rows.forEach((row) => tbody.appendChild(row));
        },
    };
}
