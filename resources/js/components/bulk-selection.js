/**
 * WireKit Bulk Selection Alpine Component.
 *
 * A list the server renders shows one page of its matches, and "everything" has two meanings
 * there: the rows on this page, or every match on every page. Around a table of the
 * application's own, this keeps the two apart:
 *
 * - the header checkbox (`bulk-selection.toggle`) reads the page only: checked when every row of
 *   the page is, mixed when some are;
 * - once the page is selected and there are more matches, the notice above the rows names both
 *   numbers and offers every match, and afterwards the way back to the page;
 * - the state "every match" is the component's `allMatches`, bound with `x-modelable`, so a
 *   `wire:model` or an `x-model` on the block reads and writes it, and each change is also
 *   dispatched as `wirekit:bulk-selection-change`;
 * - while every match is selected, the rows of a page the reader turns to are selected as they
 *   render, and a render with another filter clears the state and says so.
 *
 * The rows are the checkboxes inside the block, whatever binds them, so a click on one is the
 * reader's own and each box the block changes is clicked, as `x-wk-range-select` does: a
 * `wire:model` array hears it the way it hears a reader. A switch or a box inside
 * `[data-wk-range-skip]` is no row, as it is no part of a range (utils/range-select.js).
 */
import { pluralize } from '../utils/plural.js';
import { allMatchesNotice, matchCount } from '../utils/all-matches.js';
import { watchModelEvents } from '../utils/model-events.js';
import { isRowBox } from '../utils/range-select.js';
import { watchCurrent } from '../utils/watch-current.js';

/**
 * @param {Object} config - From the Blade component
 * @param {Object} [config.pagePhrases] - "The :count rows on this page are selected", by sample count
 * @param {Object} [config.offerPhrases] - "Select all :count matches on all pages", by sample count
 * @param {Object} [config.allPhrases] - "All :count matches on all pages are selected", by sample count
 * @param {string} [config.onlyText] - "Select only this page"
 * @param {string} [config.filterClearedText] - what a filter that clears every match says
 * @param {string} [config.locale='en'] - The application's locale, for the plural forms
 * @param {{group: string, decimal: string}|null} [config.separators] - The application's number marks
 */
export default function wirekitBulkSelection(config = {}) {
    const phrases = {
        page: config.pagePhrases && typeof config.pagePhrases === 'object' ? config.pagePhrases : {},
        offer: config.offerPhrases && typeof config.offerPhrases === 'object' ? config.offerPhrases : {},
        all: config.allPhrases && typeof config.allPhrases === 'object' ? config.allPhrases : {},
        only: typeof config.onlyText === 'string' ? config.onlyText : '',
    };
    const filterCleared = typeof config.filterClearedText === 'string' ? config.filterClearedText : '';
    const locale = typeof config.locale === 'string' && config.locale !== '' ? config.locale : 'en';
    const marks = config.separators && typeof config.separators === 'object' ? config.separators : null;

    return {
        // Whether every match on every page is selected. `x-modelable` makes it the value a
        // `wire:model` or an `x-model` on the block binds.
        allMatches: false,
        // The page as its boxes show it: how many rows can be chosen, and how many are.
        pageCount: 0,
        pageChecked: 0,
        // The number of matches on all pages, from `data-wk-bulk-total`; null without one.
        total: null,
        // What the status region says. Empty until the first change, so the region is in the
        // page before its first sentence, which a screen reader needs to speak it.
        announcement: '',

        // The filter the rows answered at the last render, from `data-wk-bulk-filter`.
        _filter: '',
        // Whether every match was selected at the last render, so a filter that changed in the
        // same response as a binding that already cleared the state is still said.
        _wasAllMatches: false,
        // Whether the offer of every match has been said for the page as it stands.
        _offered: false,
        // The clicks this block sends itself, which its change listener does not take as a
        // reader's.
        _clicking: false,
        _onChange: null,
        _observer: null,
        _unhook: null,
        // The `change` and `blur` a binding on the root hears, released in destroy().
        _modelEvents: null,

        init() {
            this._readAttributes(true);
            this._count(true);

            // `x-modelable` puts the binding on the root, a <div>, which no choice sends a `change`
            // and nothing blurs, so `wire:model.change` or `.blur` would never hear every match.
            // Both carry the value as their detail, which `x-model` reads from a CustomEvent.
            const root = this.$root;
            this._modelEvents = watchModelEvents(root, () => root, { detail: () => this.allMatches });

            this._onChange = (event) => this._changed(event);
            this.$root.addEventListener('change', this._onChange);

            // Every match written by the binding, a toolbar's "select all" or a state the page
            // loads with: the rows of the page follow, as on a page the reader turns to. A write
            // of false leaves them, as the notice's way back to the page does. The notice's own
            // offer sets true over a selected page, where nothing is left to click.
            if (typeof this.$watch === 'function') {
                watchCurrent(this, 'allMatches', (value) => {
                    if (value === true) {
                        this._clickWhere((box) => ! box.checked);
                    }
                });
            }

            if (this.allMatches === true) {
                this._clickWhere((box) => ! box.checked);
            }

            // A render: other rows, another total, another filter.
            if (typeof MutationObserver !== 'undefined') {
                this._observer = new MutationObserver(() => this._rendered());
                this._observer.observe(this.$root, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['data-wk-bulk-total', 'data-wk-bulk-filter'],
                });
            }

            // Livewire writes a bound box's `checked` after a request without an event and
            // without a mutation, so the page is counted again once each request has applied.
            const livewire = typeof window !== 'undefined' ? window.Livewire : undefined;

            this._unhook = livewire && typeof livewire.hook === 'function'
                ? livewire.hook('commit', ({ succeed }) => succeed(() => queueMicrotask(() => this._count())))
                : null;
        },

        destroy() {
            this.$root?.removeEventListener?.('change', this._onChange);
            this._onChange = null;
            this._observer?.disconnect();
            this._observer = null;

            if (typeof this._unhook === 'function') {
                this._unhook();
            }

            this._unhook = null;
            this._modelEvents?.dispose();
            this._modelEvents = null;
        },

        /** The row boxes: every checkbox in the block that is a row's choice (_isRow()). */
        _boxes() {
            const root = this.$root;

            if (typeof root?.querySelectorAll !== 'function') {
                return [];
            }

            return [...root.querySelectorAll('input[type="checkbox"]')].filter((box) => this._isRow(box));
        },

        /**
         * Whether a checkbox in the block chooses a row: not the header's own, not a switch and not
         * a box inside `[data-wk-range-skip]` (isRowBox()). A switch turned on or off for every row
         * of the page would change each record, and counted, it would stand the header checkbox
         * mixed over a page with no row chosen.
         *
         * @param {HTMLInputElement} box
         */
        _isRow(box) {
            return ! box.hasAttribute?.('data-wk-bulk-toggle') && isRowBox(box);
        },

        /** The row boxes a reader can change. A disabled row is no part of "the whole page". */
        _choosable() {
            return this._boxes().filter((box) => ! box.disabled);
        },

        /**
         * Count the page again, and say the offer of every match once, when the page has become
         * selected. A quiet count takes the offer as known without saying it: a page that loads
         * or renders selected is not news.
         *
         * @param {boolean} [quiet]
         */
        _count(quiet = false) {
            const boxes = this._choosable();

            this.pageCount = boxes.length;
            this.pageChecked = boxes.filter((box) => box.checked).length;

            if (! this.pageAll) {
                this._offered = false;

                return;
            }

            const notice = this.notice;

            if (this._offered || ! notice || notice.offers !== 'all') {
                return;
            }

            this._offered = true;

            if (! quiet) {
                this._say(notice.spoken);
            }
        },

        get pageAll() {
            return this.pageCount > 0 && this.pageChecked === this.pageCount;
        },

        get pageSome() {
            return this.pageChecked > 0 && ! this.pageAll;
        },

        // No row the reader can choose: an empty page, or every row disabled. The header checkbox
        // is disabled then. A press would change no row, and a binding writes only when what it
        // reads changes, so the box would stay checked over nothing.
        get pageEmpty() {
            return this.pageCount === 0;
        },

        /**
         * Read the total and the filter the server rendered.
         *
         * @param {boolean} [first]
         * @returns {boolean} whether the filter is another than at the last render
         */
        _readAttributes(first = false) {
            const root = this.$root;
            const filter = root?.getAttribute?.('data-wk-bulk-filter') ?? '';

            this.total = matchCount(root?.getAttribute?.('data-wk-bulk-total'));

            if (first) {
                this._filter = filter;
                this._wasAllMatches = this.allMatches;

                return false;
            }

            const changed = filter !== this._filter;
            this._filter = filter;

            return changed;
        },

        /** After a render of the rows, the total or the filter. */
        _rendered() {
            const filterChanged = this._readAttributes();
            const was = this._wasAllMatches || this.allMatches;

            this._count(true);

            // Every match of another filter is not what the reader selected.
            if (filterChanged && was) {
                this._set(false);
                this._wasAllMatches = false;
                this._say(filterCleared);

                return;
            }

            // Every match stays selected on the page the reader turns to.
            if (this.allMatches) {
                this._clickWhere((box) => ! box.checked);
            }

            this._wasAllMatches = this.allMatches;
        },

        /** A change of a row box: the reader's click, or one this block sent. */
        _changed(event) {
            const box = event?.target;

            // The block's own clicks are counted once, after the last of them, and a box that is no
            // row (a switch turned off among them) leaves every match chosen.
            if (! box || box.type !== 'checkbox' || ! this._isRow(box) || this._clicking) {
                return;
            }

            this._count();

            // A row the reader clears is no longer one of every match.
            if (this.allMatches && ! box.checked) {
                this._set(false);
            }
        },

        /**
         * The header checkbox: select the page's rows that are not, or clear the page when all
         * of them are. Rows on other pages keep their state.
         */
        toggle() {
            if (this.pageAll) {
                this._clickWhere((box) => box.checked);
                this._set(false);

                return;
            }

            this._clickWhere((box) => ! box.checked);
        },

        /** The notice's action: every match, or back to the rows of this page. */
        noticeAction() {
            const notice = this.notice;

            if (! notice) {
                return;
            }

            if (notice.offers === 'all') {
                this._set(true);
                this._say(this.notice?.spoken ?? '');

                return;
            }

            this._set(false);
            // The page stays selected, and the offer that comes back is the one just left.
            this._offered = true;
            this._say(pluralize(phrases.page, this.pageCount, locale));
        },

        /**
         * Click the row boxes that match, in a task of their own, so a `wire:model` array hears
         * each one as it hears a reader's click: a click sent while another is still being
         * dispatched reaches a bound array out of order (see utils/range-select.js).
         *
         * @param {(box: HTMLInputElement) => boolean} predicate
         */
        _clickWhere(predicate) {
            if (this._choosable().filter(predicate).length === 0) {
                return;
            }

            setTimeout(() => {
                this._clicking = true;

                try {
                    for (const box of this._choosable()) {
                        if (predicate(box)) {
                            box.click();
                        }
                    }
                } finally {
                    this._clicking = false;
                }

                this._count();
            }, 0);
        },

        _set(value) {
            if (this.allMatches === value) {
                return;
            }

            this.allMatches = value;
            // A choice, like a radio button's: committed at once.
            this._modelEvents?.commit();
            this.$dispatch('wirekit:bulk-selection-change', { allMatches: value });
        },

        /** Say a sentence, the same one twice as well: cleared first, set a tick later. */
        _say(text) {
            if (typeof text !== 'string' || text === '') {
                return;
            }

            this.announcement = '';

            if (typeof this.$nextTick === 'function') {
                this.$nextTick(() => {
                    this.announcement = text;
                });
            } else {
                this.announcement = text;
            }
        },

        /** What the notice says and offers now, or null. */
        get notice() {
            return allMatchesNotice({
                pageAll: this.pageAll,
                allMatches: this.allMatches,
                pageCount: this.pageCount,
                total: this.total,
            }, phrases, locale, marks);
        },

        // The bindings the template reads. Getters rather than inline expressions, so each stays
        // a member read inside the grammar of Alpine's CSP build.
        get noticeShows() {
            return this.notice !== null;
        },

        get noticeText() {
            return this.notice?.text ?? '';
        },

        get noticeActionText() {
            return this.notice?.action ?? '';
        },
    };
}
