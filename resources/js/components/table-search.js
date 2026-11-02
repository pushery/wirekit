/**
 * `<x-wirekit::table.search>`: a field that shortens the rows of a table in the browser.
 *
 * The table is the server's markup, named by its id, and the field never rebuilds it: a row the
 * query does not match gets the `hidden` attribute, and loses it again once it matches. A row is
 * found by the text of its cells, folded the way every search in the kit folds (`munchen` finds
 * `München`). A cell with `data-wk-search-skip` is left out of that text, and a row with
 * `data-wk-search` is found by that attribute instead.
 *
 * Only the rows of the table itself count, never those of a table in one of its cells
 * (`tBodies` and `rows` hold this table's own), and a row the page hid itself is left alone: it
 * is neither shown by an empty query nor counted as a match. The details row of `table.row`
 * (`data-wk-table-details`) belongs to the record above it: the record is found by its text as
 * well, and the two show and hide together.
 *
 * The rows can change under the field. A client-side sort moves them, and a Livewire update
 * sorts, filters or rewrites them, taking away the `hidden` its morph does not know. An observer
 * on the table applies the query again after each change, before the next paint, so a row the
 * reader filtered out never shows. It writes only where a row's state differs, so the round its
 * own writes start ends with the next call.
 *
 * Lifecycle resources: the MutationObserver on the table and the timer of the announcement, kept
 * in the closure with the table and the caches, never in the component's reactive data, and both
 * released in destroy().
 *
 * @param {Object} config
 * @param {string} config.table     the id of the `<table>` the field filters, or of an element
 *                                  around it
 * @param {Object} [config.phrases] sample count -> sentence (`PluralPhrases`), the number of
 *                                  matching rows
 * @param {string} [config.empty]   the sentence for a query that matches no row
 * @param {string} [config.locale]  the application's locale, which chooses the plural form and
 *                                  groups the count
 * @param {number} [config.delay]   milliseconds after the last keystroke before the count is
 *                                  announced
 */
import { foldForSearch } from '../utils/search-fold.js';
import { pluralizeGrouped } from '../utils/plural.js';
import { timerDelay } from '../utils/timer-delay.js';

/** The attributes a row's text depends on besides its content, and the one this field writes. */
const WATCHED = ['hidden', 'data-wk-search', 'data-wk-search-skip'];

export default function wirekitTableSearch(config = {}) {
    // The table and what is known about its rows, outside the component's data: Alpine makes its
    // data reactive, and no binding reads any of this.
    let table = null;
    let observer = null;
    let timer = null;
    // A row's folded text, until its table changes.
    let folded = new WeakMap();
    // The rows this field hid, so an empty query gives back only those.
    const ours = new WeakSet();

    /** The text a row is found by. */
    function textOf(row) {
        const own = row.getAttribute('data-wk-search');

        if (own !== null) {
            return own;
        }

        let text = '';

        for (const cell of row.cells ?? []) {
            if (! cell.hasAttribute('data-wk-search-skip')) {
                text += ` ${cell.textContent ?? ''}`;
            }
        }

        return text;
    }

    /** Hide a row the page shows, and remember that this field hid it. */
    function conceal(row) {
        if (! row.hidden) {
            row.hidden = true;
            ours.add(row);
        }
    }

    /** Show a row again, only where this field hid it. */
    function reveal(row) {
        if (ours.has(row)) {
            ours.delete(row);

            if (row.hidden) {
                row.hidden = false;
            }
        }
    }

    function foldedTextOf(row) {
        let text = folded.get(row);

        if (text === undefined) {
            text = foldForSearch(textOf(row));
            folded.set(row, text);
        }

        return text;
    }

    return {
        query: '',
        // How many rows match, and how many rows the query is asked of.
        matches: 0,
        total: 0,
        // What the status region says. Set a moment after the last keystroke, so a reader who
        // types a word hears its count once rather than one count per letter.
        announcement: '',

        /** A query that matches no row: the field says so beside itself. */
        get isEmpty() {
            return this.query.trim() !== '' && this.matches === 0;
        },

        init() {
            this.$nextTick(() => this.apply());
        },

        destroy() {
            observer?.disconnect();
            observer = null;
            clearTimeout(timer);
            timer = null;
            table = null;
        },

        /**
         * The reader changed the query: apply it, tell the page, and announce the count once the
         * typing pauses.
         */
        search() {
            this.apply();
            this.$dispatch('wirekit:table-search', { query: this.query, matches: this.matches, total: this.total });

            clearTimeout(timer);
            timer = setTimeout(() => {
                timer = null;
                this.announcement = this._sentence();
            }, timerDelay(config.delay, 500));
        },

        /** Show the records the query matches and hide the others, and count both. */
        apply() {
            const target = this._table();
            let matches = 0;
            let total = 0;

            if (target) {
                const needle = foldForSearch(this.query.trim());

                for (const body of target.tBodies ?? []) {
                    const rows = [...(body.rows ?? [])];

                    for (let at = 0; at < rows.length; at++) {
                        // A record and the details rows that follow it.
                        const record = [rows[at]];

                        while (at + 1 < rows.length && rows[at + 1].hasAttribute?.('data-wk-table-details')) {
                            record.push(rows[++at]);
                        }

                        // Hidden by the page, not by this field: none of its business.
                        if (record[0].hidden && ! ours.has(record[0])) {
                            continue;
                        }

                        total++;

                        const show = needle === '' || record.some((row) => foldedTextOf(row).includes(needle));

                        if (show) {
                            matches++;
                        }

                        for (const row of record) {
                            if (show) {
                                reveal(row);
                            } else {
                                conceal(row);
                            }
                        }
                    }
                }
            }

            this.matches = matches;
            this.total = total;
        },

        /** The sentence for the current count: nothing without a query. */
        _sentence() {
            if (this.query.trim() === '') {
                return '';
            }

            return this.matches === 0 ? (config.empty ?? '') : pluralizeGrouped(config.phrases ?? {}, this.matches, config.locale || 'en');
        },

        /**
         * The table the field filters, found again when the one it had left the page (a Livewire
         * update that replaced it, a navigation), and observed from then on.
         */
        _table() {
            if (table && table.isConnected) {
                return table;
            }

            observer?.disconnect();
            observer = null;
            folded = new WeakMap();

            const named = config.table && typeof document !== 'undefined' ? document.getElementById(config.table) : null;
            table = named && named.tagName === 'TABLE' ? named : (named?.querySelector?.('table') ?? null);

            if (table && typeof MutationObserver === 'function') {
                observer = new MutationObserver((records) => {
                    // A change the browser queued before destroy() can arrive after it.
                    if (! observer) {
                        return;
                    }

                    // Only this field's own writes leave the text of every row as it was.
                    if (records.some((record) => record.type !== 'attributes' || record.attributeName !== 'hidden')) {
                        folded = new WeakMap();
                    }

                    this.apply();
                });
                observer.observe(table, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: WATCHED });
            }

            return table;
        },
    };
}
