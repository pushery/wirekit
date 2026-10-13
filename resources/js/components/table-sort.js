/**
 * WireKit Table Sort Alpine Component.
 *
 * Provides client-side sorting for static tables without Livewire.
 * Sorts rows by reading text content or data-wk-sort-value from cells.
 * Supports numeric and string sorting with locale-aware comparison.
 *
 * Usage: add alpine-sort to <x-wirekit::table> and column="name" to
 * sortable <x-wirekit::table.th> elements.
 *
 * @param {Object} config
 * @param {string} [config.warning]  a debug-only composition warning to emit at
 *   init. It arrives here rather than in its own x-init because an element
 *   cannot carry two Alpine components, and because an inline console.warn
 *   breaks the whole scope under the CSP build — see utils/dev-warning.js.
 */
import { devWarn } from '../utils/dev-warning.js';
import { sortCollator } from '../utils/sort-collator.js';

export default function wirekitTableSort(config = {}) {
    return {
        // Handles set while the component runs, declared so that they are its own: Alpine stores a
        // property no scope declares on the outermost scope around the component.
        __separators: null,

        sortColumn: null,
        sortDirection: null,
        _originalOrder: [],

        // The application's locale, so a formatted column can be READ as a number rather
        // than only compared as text. Handed down from the Blade template — this file has
        // no access to App::getLocale(), and the browser's own locale is the reader's
        // machine, not the page.
        _locale: config.locale || 'en',

        /**
         * The group and decimal separators this locale writes numbers with, worked out once
         * from a sample rather than hardcoded. `Intl.NumberFormat` is the only thing that
         * knows that German groups with `.` and French with a narrow no-break space.
         */
        get _separators() {
            if (this.__separators) {
                return this.__separators;
            }

            let group = '';
            let decimal = '';

            try {
                for (const part of new Intl.NumberFormat(this._locale).formatToParts(12345.6)) {
                    if (part.type === 'group') group = part.value;
                    if (part.type === 'decimal') decimal = part.value;
                }
            } catch {
                // An unknown locale tag throws. Empty separators disable the locale path
                // below, so the sort falls back to the locale-free comparison rather than
                // sorting by something invented here.
            }

            this.__separators = { group, decimal };

            return this.__separators;
        },

        /**
         * A cell's value as a number, or NaN when it is not one.
         *
         * Cell text is read the way the page's reader reads it: as a number in the page's
         * locale first, and in the machine spelling only when the locale cannot have written
         * it. On a German page "12.345" is twelve thousand three hundred and forty-five. Read
         * the machine way first it would be just over twelve, and a column of whole numbers
         * would sort "2.000", "12.345", "999". `Number()` cannot read "1.234,56" or
         * "1 234,56" at all, and the text comparison below splits on digit runs, so without
         * the locale reading those would sort on their leading digits too.
         *
         * A `data-wk-sort-value` is the other way round. It carries the machine spelling, so
         * that is read first, whatever the page's locale.
         *
         * @param {string} raw
         * @param {boolean} [machineFirst=false]  true for a `data-wk-sort-value`
         */
        _toNumber(raw, machineFirst = false) {
            if (raw === '') {
                return NaN;
            }

            if (machineFirst) {
                const machine = Number(raw);

                return isNaN(machine) ? this._localeNumber(raw) : machine;
            }

            const local = this._localeNumber(raw);

            return isNaN(local) ? Number(raw) : local;
        },

        /**
         * `raw` read as a number written in the page's locale, or NaN when the locale cannot
         * have written it.
         *
         * Deliberately strict, so it claims only what the locale's own formatting produces:
         * canonical groups of exactly three digits after a first group that does not start
         * with a zero, or digits on both sides of the locale's decimal separator. "1.5" and
         * "0.125" on a German page are neither, so the machine spelling reads them as one and
         * a half and as an eighth. An ISO date such as `2025-01-15` is neither number, and the
         * text comparison sorts it chronologically.
         */
        _localeNumber(raw) {
            const { group, decimal } = this._separators;
            if (!group || !decimal || group === decimal) {
                return NaN;
            }

            const esc = (c) => c.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

            // French groups with a NARROW NO-BREAK SPACE (U+202F), not the space on a
            // keyboard. A page that writes its numbers by hand, or through a tool that
            // normalizes whitespace, produces U+0020 or U+00A0 instead — visually identical
            // and a different code point. All three are accepted when the locale's group
            // separator is any kind of space, because refusing two of them would make this
            // work in `Intl`-formatted output and nowhere else.
            const isSpace = /^[\s\u00a0\u202f]$/.test(group);
            const g = isSpace ? '[\\s\\u00a0\\u202f]' : esc(group);
            const d = esc(decimal);
            const grouped = new RegExp(`^[+-]?[1-9]\\d{0,2}(?:${g}\\d{3})+(?:${d}\\d+)?$`);
            const plainDecimal = new RegExp(`^[+-]?\\d+${d}\\d+$`);

            if (!grouped.test(raw) && !plainDecimal.test(raw)) {
                return NaN;
            }

            const stripped = isSpace
                ? raw.replace(/[\s\u00a0\u202f]/g, '')
                : raw.split(group).join('');

            return Number(stripped.replace(decimal, '.'));
        },

        init() {
            devWarn(config.warning);

            // Snapshot original row order so we can restore it when sort is cleared.
            // Use $root (the x-data <table>), NOT $el — see _reorderRows for why.
            this.$nextTick(() => {
                const tbody = this.$root.querySelector('tbody');
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
            // $root, NOT $el. sortBy() is reached from an @click on the header's
            // sort <button>, so at call time Alpine binds $el to that button,
            // which has no <tbody> and no <thead th> descendants: $el.querySelector
            // would return null and this method would return early, so the
            // aria-sort indicator (a reactive binding) would flip while the rows
            // stayed where they were. $root is the x-data <table>, which owns
            // both, wherever inside it the handler sits.
            const tbody = this.$root.querySelector('tbody');
            if (!tbody) return;

            // No active sort — restore original DOM order
            if (!this.sortColumn || !this.sortDirection) {
                /*
                 * Only rows the table STILL has, and every row it has now.
                 *
                 * `_originalOrder` is a snapshot of node references taken once at init, and
                 * `appendChild` on a detached node RE-ATTACHES it. After a Livewire
                 * re-render — a filter applied, a page turned, a row deleted — restoring the
                 * whole snapshot would put the old nodes back into the table: rows the server
                 * has removed would return with stale content, showing data that no longer
                 * exists, and nothing would report it.
                 *
                 * The snapshot is still worth keeping: it is the only record of the order
                 * the server sent, and re-deriving it from the current DOM after a sort
                 * would just record the sorted order. So it is INTERSECTED with what is
                 * live, and anything the current table holds that the snapshot does not —
                 * rows added by that same re-render — keeps its place at the end rather
                 * than being dropped.
                 */
                const live = new Set(tbody.querySelectorAll('tr'));
                const ordered = this._originalOrder.filter((row) => live.has(row));

                ordered.forEach((row) => {
                    live.delete(row);
                    tbody.appendChild(row);
                });

                // Whatever the snapshot never saw, in the order the table has it.
                live.forEach((row) => tbody.appendChild(row));

                return;
            }

            // Find column index by matching data-wk-sort-column on th elements
            // ($root, not $el — see the tbody lookup above).
            const ths = this.$root.querySelectorAll('thead th');
            let colIndex = -1;
            ths.forEach((th, i) => {
                if (th.dataset.wkSortColumn === this.sortColumn) {
                    colIndex = i;
                }
            });

            if (colIndex === -1) return;

            const rows = [...tbody.querySelectorAll('tr')];

            /*
             * Decorate, sort, undecorate.
             *
             * Everything the comparator needs is derived ONCE PER ROW here, because a
             * comparator runs O(n log n) times and none of this work depends on the pair
             * being compared. Read inside the comparator, a 500-row table would extract
             * text roughly 4,500 times instead of 500, and since `_toNumber` compiles its
             * regular expressions on every call, it would also build thousands of RegExp
             * objects to answer a question about 500 cells.
             */
            const keyed = rows.map((row) => {
                const cell = row.cells[colIndex];
                const sortValue = cell?.dataset.wkSortValue;

                // Prefer explicit sort value, fall back to trimmed text content
                const val = sortValue ?? cell?.textContent?.trim() ?? '';

                // Numeric comparison ONLY when each value is FULLY numeric.
                // parseFloat() reads a leading number out of a non-numeric
                // string — "2025-01-15" → 2025 — which would collapse every ISO
                // date in a column to its year, so they would compare equal and
                // the column would not sort. Number() returns NaN unless the whole
                // string parses, so dates / "12px" / "$5" / "3 items" correctly
                // fall through to the locale comparison below (which orders ISO
                // dates chronologically via numeric:true). The `=== ''` guard
                // stops Number('') === 0 from treating empty cells as numeric.
                return { row, val, num: this._toNumber(val, sortValue !== undefined) };
            });

            /*
             * One collator for the whole sort.
             *
             * `String.prototype.localeCompare(other, locale, options)` has to build an
             * `Intl.Collator` to honor those options, and it builds a new one on every
             * call — the single most expensive thing a comparator can do. Hoisting it is
             * the documented reason `Intl.Collator` exists as a separate object.
             *
             * In the application's locale rather than the runtime's default, which is the
             * reader's browser: where a letter such as "Ä" sorts depends on the language the
             * page is written in, and only the server knows that.
             */
            const collator = sortCollator(this._locale);

            const direction = this.sortDirection === 'asc' ? 1 : -1;

            keyed.sort((a, b) => {
                if (!isNaN(a.num) && !isNaN(b.num)) {
                    return (a.num - b.num) * direction;
                }

                // Locale-aware string comparison with natural number ordering
                return collator.compare(a.val, b.val) * direction;
            });

            // Move sorted rows into the DOM (appendChild relocates existing nodes)
            keyed.forEach(({ row }) => tbody.appendChild(row));
        },
    };
}
