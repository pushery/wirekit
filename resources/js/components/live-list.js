/**
 * `<x-wirekit::live-list>`: a list that keeps the reader's place while entries arrive, and says
 * where new ones landed out of sight.
 *
 * A list a server re-renders while somebody reads it, an inbox that a broadcast refreshes, takes
 * new entries anywhere: one inserted above the rows in view pushes them down, and the reader loses
 * the line they were on. Scroll anchoring in the engine does not reach every case (a scroll region
 * of the page's own, an engine without it), so the list holds its place itself: it remembers the
 * first row in view while the reader scrolls, and after a render marked live it scrolls by exactly
 * the distance that row moved. A render that is not marked live, a filter or the next page, leaves
 * the scroll where it is.
 *
 * Rows carry `data-wk-live-row`. A row the application marks `data-wk-live-fresh` is new or moved;
 * one out of sight counts toward a hint above or below the rows in view, until it has been seen.
 * The hint brings the first one into view and puts the focus on it.
 *
 * Mutations are read with a MutationObserver rather than a Livewire hook, so the list works with
 * any renderer, and the observer callback runs after the whole morph, before the next paint.
 */
import { prefersReducedMotion, scrollBehavior } from '../utils/motion.js';
import { scrollRootOf } from '../utils/scroll-root.js';

/**
 * The first row that stands in the band between `top` and `bottom`, by its rectangle.
 *
 * @param {Array<{ top: number, bottom: number }>} rects - the rows' rectangles, in document order
 * @param {{ top: number, bottom: number }} band - the part of the scroller the reader sees
 * @returns {number} the index of the row, or -1
 */
export function firstInView(rects, band) {
    return rects.findIndex((rect) => rect.bottom > band.top && rect.top < band.bottom);
}

/**
 * How many rectangles lie wholly above and wholly below the band.
 *
 * @param {Array<{ top: number, bottom: number }>} rects
 * @param {{ top: number, bottom: number }} band
 * @returns {{ above: number, below: number }}
 */
export function outOfView(rects, band) {
    let above = 0;
    let below = 0;

    for (const rect of rects) {
        if (rect.bottom <= band.top) {
            above++;
        } else if (rect.top >= band.bottom) {
            below++;
        }
    }

    return { above, below };
}

/**
 * @param {Object} config
 * @param {string} [config.aboveSaid] - The sentence a screen reader hears when entries land above.
 * @param {string} [config.belowSaid] - The sentence a screen reader hears when entries land below.
 */
export default function wirekitLiveList(config = {}) {
    return {
        above: 0,
        below: 0,
        announcement: '',
        // The first row in view, and its distance from the top of what the reader sees.
        _anchor: null,
        _observer: null,
        _onScroll: null,
        _frame: null,
        // Fresh rows the reader has had in view: they count no longer.
        _seen: null,

        init() {
            this._seen = new WeakSet();
            this._onScroll = () => {
                if (this._frame !== null) {
                    return;
                }

                this._frame = requestAnimationFrame(() => {
                    this._frame = null;
                    this._capture();
                    this._count(false);
                });
            };

            // Capture catches the scroll of any region the list sits in, as well as the window's.
            window.addEventListener('scroll', this._onScroll, { passive: true, capture: true });
            window.addEventListener('resize', this._onScroll, { passive: true });

            this._observer = new MutationObserver(() => this._rendered());
            this._observer.observe(this.$refs.rows, { childList: true, subtree: true });

            this._capture();
            this._count(false);
        },

        destroy() {
            window.removeEventListener('scroll', this._onScroll, { capture: true });
            window.removeEventListener('resize', this._onScroll);
            this._observer?.disconnect();

            if (this._frame !== null) {
                cancelAnimationFrame(this._frame);
            }
        },

        get hasAbove() {
            return this.above > 0;
        },

        get hasBelow() {
            return this.below > 0;
        },

        /** The rows the list holds, in document order. */
        _rows() {
            return [...this.$refs.rows.querySelectorAll('[data-wk-live-row]')];
        },

        /** The element that scrolls the list, or null for the window. */
        _scroller() {
            return scrollRootOf(this.$el);
        },

        /** The part of the page the reader sees of the list's scroller, in viewport coordinates. */
        _band() {
            const scroller = this._scroller();

            if (scroller) {
                const rect = scroller.getBoundingClientRect();

                return { top: Math.max(rect.top, 0), bottom: Math.min(rect.bottom, window.innerHeight) };
            }

            return { top: 0, bottom: window.innerHeight };
        },

        /** Remember the first row in view, where the reader is. */
        _capture() {
            const rows = this._rows();
            const band = this._band();
            const at = firstInView(rows.map((row) => row.getBoundingClientRect()), band);

            this._anchor = at === -1 ? null : { row: rows[at], top: rows[at].getBoundingClientRect().top - band.top };
        },

        /** After a render: keep the reader's place when it was live, then count what is new. */
        _rendered() {
            const live = this.$el.getAttribute('data-wk-live') === 'true';
            const anchor = this._anchor;

            if (live && anchor && anchor.row.isConnected) {
                const moved = anchor.row.getBoundingClientRect().top - this._band().top - anchor.top;

                if (Math.abs(moved) >= 1) {
                    // A jump, never an animation, also under a page's smooth scrolling: the
                    // correction puts the reader's row back where it was, and a glide would show
                    // that row moving.
                    (this._scroller() ?? window).scrollBy({ top: moved, behavior: 'instant' });
                }
            }

            this._capture();
            this._count(live);
        },

        /**
         * Count the fresh rows out of sight that the reader has not seen yet, and announce a
         * direction that gained some after a live render.
         */
        _count(announce) {
            const band = this._band();
            const fresh = this._rows().filter((row) => row.hasAttribute('data-wk-live-fresh') && ! this._seen.has(row));
            const rects = fresh.map((row) => row.getBoundingClientRect());

            fresh.forEach((row, i) => {
                if (rects[i].bottom > band.top && rects[i].top < band.bottom) {
                    this._seen.add(row);
                }
            });

            const { above, below } = outOfView(rects, band);
            const gained = [above > this.above ? config.aboveSaid : null, below > this.below ? config.belowSaid : null].filter(Boolean);

            this.above = above;
            this.below = below;

            if (announce && gained.length > 0) {
                // Emptied first, so the same sentence twice is announced twice.
                this.announcement = '';
                queueMicrotask(() => {
                    this.announcement = gained.join(' ');
                });
            }
        },

        revealAbove() {
            this._reveal('above');
        },

        revealBelow() {
            this._reveal('below');
        },

        /** Bring the first unseen fresh row in that direction into view and give it the focus. */
        _reveal(direction) {
            const band = this._band();
            const target = this._rows().find((row) => {
                if (! row.hasAttribute('data-wk-live-fresh') || this._seen.has(row)) {
                    return false;
                }

                const rect = row.getBoundingClientRect();

                return direction === 'above' ? rect.bottom <= band.top : rect.top >= band.bottom;
            });

            if (! target) {
                return;
            }

            target.scrollIntoView({ block: 'nearest', behavior: scrollBehavior(! prefersReducedMotion()) });

            // The row's own link or button where it has one, the row itself otherwise.
            const control = target.querySelector('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])');

            if (control) {
                control.focus({ preventScroll: true });
            } else {
                if (! target.hasAttribute('tabindex')) {
                    target.setAttribute('tabindex', '-1');
                }

                target.focus({ preventScroll: true });
            }

            this._seen.add(target);
            this._count(false);
        },
    };
}
