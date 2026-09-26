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
 * ResizeObserver fires for the width the row actually ends at.
 */
export default function wirekitNavbarScroll() {
    return {
        _resize: null,
        _mutations: null,

        init() {
            const reveal = () => this.reveal();

            // Both guarded because a plain unit harness has neither; the component then does
            // the single pass below and nothing else.
            if (typeof ResizeObserver === 'function') {
                this._resize = new ResizeObserver(reveal);
                this._resize.observe(this.$el);
            }

            // A Livewire render or a client-side route change can move `aria-current` without
            // the row changing size, and then only this notices.
            if (typeof MutationObserver === 'function') {
                this._mutations = new MutationObserver(reveal);
                this._mutations.observe(this.$el, { subtree: true, attributes: true, attributeFilter: ['aria-current'] });
            }

            reveal();
        },

        destroy() {
            this._resize?.disconnect();
            this._mutations?.disconnect();
            this._resize = null;
            this._mutations = null;
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
    };
}
