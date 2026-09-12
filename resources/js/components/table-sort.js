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

export default function wirekitTableSort(config = {}) {
    return {
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
                // below, which restores exactly the previous behavior rather than sorting
                // by something invented here.
            }

            this.__separators = { group, decimal };

            return this.__separators;
        },

        /**
         * A cell's value as a number, or NaN when it is not one.
         *
         * `Number()` alone reads only the machine spelling, so a column formatted for the
         * page — "1.234,56" in German, "1 234,56" in French — came back NaN and fell through
         * to the text comparison below. That comparison splits on digit runs, so "1.234,56"
         * sorted before "999,00" on its leading `1`: the column looked sorted and was not.
         *
         * The locale path is deliberately strict. It accepts only a CANONICALLY grouped
         * number — groups of exactly three digits — so a German page containing the machine
         * value "1.5" is NOT read as 15: it fails the pattern and falls through to the text
         * comparison, which is where an ambiguous value belongs. `2025-01-15` fails for the
         * same reason and keeps sorting chronologically, which is the behavior the comment
         * below this one was written to protect.
         */
        _toNumber(raw) {
            if (raw === '') {
                return NaN;
            }

            // The machine spelling first — it is what `data-wk-sort-value` carries, and it
            // must keep winning regardless of the page's locale.
            const plain = Number(raw);
            if (!isNaN(plain)) {
                return plain;
            }

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
            const grouped = new RegExp(`^[+-]?\\d{1,3}(?:${g}\\d{3})+(?:${d}\\d+)?$`);
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
            // sort <button>, so at call time Alpine binds $el to that button —
            // which has no <tbody> (and no <thead th> descendants), so
            // $el.querySelector would return null and this method would
            // early-return: the aria-sort indicator (a reactive binding) flipped
            // but the rows never moved. $root is the x-data <table>, which owns
            // both. The handler sat on the <th> itself when this was first hit;
            // moving it into the button for keyboard operability changed which
            // wrong element $el points at, not that it is the wrong element.
            const tbody = this.$root.querySelector('tbody');
            if (!tbody) return;

            // No active sort — restore original DOM order
            if (!this.sortColumn || !this.sortDirection) {
                /*
                 * Only rows the table STILL has, and every row it has now.
                 *
                 * `_originalOrder` is a snapshot of node references taken once at init, and
                 * `appendChild` on a detached node RE-ATTACHES it. So after a Livewire
                 * re-render — a filter applied, a page turned, a row deleted — clearing the
                 * sort put the old nodes back into the table: rows the server had removed
                 * reappeared, with stale content, and nothing reported it. The table simply
                 * showed data that was no longer there.
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
             * being compared. Reading it inside the comparator meant a 500-row table did
             * roughly 4,500 text extractions instead of 500, and `_toNumber` compiles two
             * regular expressions per call — so the same table also built 9,000 RegExp
             * objects to answer a question about 500 cells.
             *
             * Nothing about the ORDER changes: same value per row, same numeric rule,
             * same locale comparison.
             */
            const keyed = rows.map((row) => {
                const cell = row.cells[colIndex];

                // Prefer explicit sort value, fall back to trimmed text content
                const val = cell?.dataset.wkSortValue ?? cell?.textContent?.trim() ?? '';

                // Numeric comparison ONLY when each value is FULLY numeric.
                // parseFloat() reads a leading number out of a non-numeric
                // string — "2025-01-15" → 2025 — which collapsed every ISO date
                // in a column to its year, so they compared equal and the
                // column never sorted. Number() returns NaN unless the whole
                // string parses, so dates / "12px" / "$5" / "3 items" correctly
                // fall through to the locale comparison below (which orders ISO
                // dates chronologically via numeric:true). The `=== ''` guard
                // stops Number('') === 0 from treating empty cells as numeric.
                return { row, val, num: this._toNumber(val) };
            });

            /*
             * One collator for the whole sort.
             *
             * `String.prototype.localeCompare(other, locale, options)` has to build an
             * `Intl.Collator` to honor those options, and it builds a new one on every
             * call — the single most expensive thing a comparator can do. Hoisting it is
             * the documented reason `Intl.Collator` exists as a separate object.
             *
             * `undefined` for the locale keeps the previous behavior exactly: the runtime's
             * default, which is what localeCompare was being given.
             */
            const collator = new Intl.Collator(undefined, {
                numeric: true,
                sensitivity: 'base',
            });

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
