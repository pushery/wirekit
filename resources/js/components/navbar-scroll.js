/**
 * Navbar scroll row — keeps the current entry of a sideways-scrolling navbar in view.
 *
 * `navbar mobile="scroll"` leaves its entries as one row on a narrow screen and lets the row
 * scroll, so the entry for the page you are on can sit far off to the right. This brings it in
 * when the row first lays out, whenever the row changes size, and whenever `aria-current` moves.
 *
 * On the row itself, through `scrollLeft`, never `scrollIntoView()`: that scrolls every
 * scrolling ancestor too, the page included, so a page reloaded half-way down would jump to its
 * top to show a link in the header.
 *
 * A single measurement at start is not enough, and that was measured rather than assumed: in
 * WebKit the row ends narrower than it first measures, once the fonts and the actions beside it
 * settle, and an entry centered for the first width sits off the edge of the final one. A
 * ResizeObserver fires for the width the row actually ends at. It watches the entries as well:
 * a web font that lands after the first pass widens them while the row keeps its own width, and
 * the current entry moves with them.
 *
 * The row draws no scrollbar, so it also says where entries lie beyond an edge: `data-fade`
 * names those edges (`start`, `end` or `both`) and `.wk-scroll-fade` fades them. Without it, a
 * word cut off at the edge was the only hint, and it read like a rendering fault. The attribute
 * follows every scroll, whether by touch, wheel, Tab or `reveal()`, and is absent when the row
 * fits.
 *
 * Cleanup: two observers and one scroll listener, each held under a `_` field, released in
 * `destroy()`, and null-checked in the callbacks that can still arrive after it.
 */
export default function wirekitNavbarScroll() {
    return {
        _resize: null,
        _mutations: null,
        _scroll: null,

        init() {
            const update = () => {
                this.reveal();
                this.fade();
            };

            // Both guarded because a plain unit harness has neither; the component then does
            // the single pass below and nothing else.
            if (typeof ResizeObserver === 'function') {
                this._resize = new ResizeObserver(update);
                this._resize.observe(this.$el);
                Array.from(this.$el?.children ?? []).forEach((entry) => this._resize.observe(entry));
            }

            // A Livewire render or a client-side route change can move `aria-current` without
            // the row changing size, and then only this notices.
            if (typeof MutationObserver === 'function') {
                this._mutations = new MutationObserver(update);
                this._mutations.observe(this.$el, { subtree: true, attributes: true, attributeFilter: ['aria-current'] });
            }

            // Which edges have entries behind them changes with every scroll. A queued event can
            // arrive after `destroy()`, hence the check before the work.
            this._scroll = () => {
                if (! this._scroll) {
                    return;
                }

                this.fade();
            };
            this.$el?.addEventListener?.('scroll', this._scroll, { passive: true });

            update();
        },

        destroy() {
            this._resize?.disconnect();
            this._mutations?.disconnect();

            if (this._scroll) {
                this.$el?.removeEventListener?.('scroll', this._scroll);
            }

            this._resize = null;
            this._mutations = null;
            this._scroll = null;
        },

        /**
         * Center the current entry when any part of it is outside the row. An entry already in
         * full view is left where it is, so a resize never moves a row the reader has scrolled
         * to where they can already see what they need.
         */
        reveal() {
            const row = this.$el;
            const current = row?.querySelector?.('[aria-current="page"]');

            if (! current || row.scrollWidth <= row.clientWidth) {
                return;
            }

            const box = row.getBoundingClientRect();
            const entry = current.getBoundingClientRect();

            if (entry.left >= box.left && entry.right <= box.right) {
                return;
            }

            // Physical coordinates on both sides, so the same arithmetic holds in a
            // right-to-left document, where `scrollLeft` runs from 0 into the negatives.
            row.scrollLeft += (entry.left + entry.width / 2) - (box.left + box.width / 2);
        },

        /**
         * Name the edges with entries behind them in `data-fade`, the attribute `.wk-scroll-fade`
         * reads. `start` and `end` are logical, and so is the offset here: its magnitude is the
         * distance from the start edge in either direction, because a right-to-left row scrolls
         * from 0 into the negatives. A pixel of slack on each side absorbs the fractional
         * `scrollLeft` a zoomed page reports at the very end of the row.
         */
        fade() {
            const row = this.$el;

            if (! row) {
                return;
            }

            const hidden = row.scrollWidth - row.clientWidth;
            const offset = Math.abs(row.scrollLeft);
            const start = hidden > 1 && offset > 1;
            const end = hidden > 1 && offset < hidden - 1;
            const edges = start && end ? 'both' : (start ? 'start' : (end ? 'end' : null));

            if (edges === null) {
                row.removeAttribute?.('data-fade');
            } else if (row.getAttribute?.('data-fade') !== edges) {
                row.setAttribute?.('data-fade', edges);
            }
        },
    };
}
