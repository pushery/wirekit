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
 * The hint brings the first one into view and puts the focus on it. A row under a strip or a bar
 * that covers the top of what the reader sees is out of sight too: the rows in view begin below
 * the scroller's scroll padding at the top, the page's for a list the page scrolls, or below an
 * offset the application sets, and the hint above rests below the same inset.
 *
 * Mutations are read with a MutationObserver rather than a Livewire hook, so the list works with
 * any renderer, and the observer callback runs after the whole morph, before the next paint.
 */
import { prefersReducedMotion, scrollBehavior } from '../utils/motion.js';
import { scrollRootOf } from '../utils/scroll-root.js';
import { SETTLE_AFTER_MS } from '../utils/scroll-into-view.js';

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
 * How far to scroll so a rectangle stands in the band: its top at the band's top when it lies above
 * the band or is taller than it, its bottom at the band's bottom when it lies below. Positive
 * scrolls down, 0 leaves a rectangle that stands in the band where it is.
 *
 * @param {{ top: number, bottom: number }} rect
 * @param {{ top: number, bottom: number }} band
 * @returns {number}
 */
export function intoBand(rect, band) {
    if (rect.top < band.top || rect.bottom - rect.top > band.bottom - band.top) {
        return rect.top - band.top;
    }

    return rect.bottom > band.bottom ? rect.bottom - band.bottom : 0;
}

/**
 * A length the page's stylesheet computed, in pixels. A registered length computes to pixels; any
 * other form, a value the stylesheet left unresolved or an empty one, counts as none.
 *
 * @param {string} value
 * @returns {number}
 */
function pixels(value) {
    const trimmed = String(value ?? '').trim();
    const number = trimmed.endsWith('px') ? parseFloat(trimmed) : NaN;

    return Number.isFinite(number) && number > 0 ? number : 0;
}

/**
 * A computed `scroll-padding` value in pixels. A length computes to pixels, and a percentage is of
 * the height of the container it pads; `auto`, a value the engine left as an expression and an
 * empty one count as none.
 *
 * @param {string} value
 * @param {number} height - the height a percentage is of
 * @returns {number}
 */
export function scrollPaddingLength(value, height) {
    const trimmed = String(value ?? '').trim();
    const number = parseFloat(trimmed);

    if (! Number.isFinite(number) || number <= 0) {
        return 0;
    }

    if (trimmed.endsWith('%')) {
        return (number / 100) * height;
    }

    return trimmed.endsWith('px') ? number : 0;
}

/**
 * The scroll padding of a scroll container at the top and the bottom, in pixels, for the page that
 * of `<html>`: the band a strip or a pinned bar of the kit reports there, which a scroll into view
 * keeps clear. A percentage is of the container's height, the viewport's for the page.
 *
 * @param {Element|null} scroller - the element that scrolls, or null for the page
 * @returns {{ top: number, bottom: number }}
 */
function scrollPaddingOf(scroller) {
    const style = getComputedStyle(scroller ?? document.documentElement);
    const height = scroller ? scroller.clientHeight : window.innerHeight;

    const padding = { top: scrollPaddingLength(style.scrollPaddingTop, height), bottom: scrollPaddingLength(style.scrollPaddingBottom, height) };

    return padding;
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
        // The scroll padding at the top of the list's scroller, as the stylesheet reads it.
        scrollInset: null,
        // The first row in view, and its distance from the top edge of its scroller.
        _anchor: null,
        _observer: null,
        _onScroll: null,
        _frame: null,
        // The hint's settling scroll, pending until the glide has had its time.
        _settle: null,
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

            clearTimeout(this._settle);
        },

        get hasAbove() {
            return this.above > 0;
        },

        get hasBelow() {
            return this.below > 0;
        },

        /**
         * The scroll padding at the top of the list's scroller as `--wk-live-list-scroll-inset`,
         * which the stylesheet rests the hint above below. Bound rather than written once, so an
         * update that patches the list's own `style` keeps it.
         */
        get insetStyle() {
            return { '--wk-live-list-scroll-inset': this.scrollInset };
        },

        /** The rows the list holds, in document order. */
        _rows() {
            return [...this.$refs.rows.querySelectorAll('[data-wk-live-row]')];
        },

        /** The element that scrolls the list, or null for the window. */
        _scroller() {
            return scrollRootOf(this.$el);
        },

        /**
         * How far a strip or a bar covers the top of what the reader sees of the list's scroller,
         * in pixels: the inset the hint above rests below. It is the scroller's scroll padding at
         * the top, the page's for a list the page scrolls, where a strip and every pinned bar of
         * the kit report their band; a region of its own reports what pins itself inside it, and
         * a strip of the page does not cover it. An offset the application sets with
         * `--wk-live-list-top` wins, as the registered length the stylesheet computes from it
         * (`--wk-live-list-inset`).
         */
        _inset() {
            const padding = scrollPaddingOf(this._scroller()).top;
            const style = getComputedStyle(this.$el);

            // The stylesheet reads the same padding for the hint. Kept in step here, where every
            // band is read, so a bar that grows or wraps moves the hint with it.
            this._syncInset(padding);

            return style.getPropertyValue('--wk-live-list-top').trim() !== '' ? pixels(style.getPropertyValue('--wk-live-list-inset')) : padding;
        },

        /** Hand a scroll padding to the stylesheet, when it changed. */
        _syncInset(padding) {
            const value = `${padding}px`;

            if (this.scrollInset !== value) {
                this.scrollInset = value;
            }
        },

        /** The part of the page the reader sees of the list's scroller, in viewport coordinates. */
        _band() {
            const scroller = this._scroller();

            if (scroller) {
                const rect = scroller.getBoundingClientRect();

                return { top: Math.max(rect.top + this._inset(), 0), bottom: Math.min(rect.bottom, window.innerHeight) };
            }

            // Where the page scrolls the list, the strip or the bar covers the top of the viewport,
            // and a row beneath it is as much out of sight as one above it.
            return { top: Math.min(this._inset(), window.innerHeight), bottom: window.innerHeight };
        },

        /**
         * Where the list measures how far a row moved: the top edge of its scroller, the
         * viewport's for a list the page scrolls. Not the band, whose top follows a bar above it:
         * a bar that grows or a strip that comes moves the band and no row, so a distance taken from
         * the band would read as the reader's row moving and scroll the list by the bar's change.
         */
        _origin() {
            const scroller = this._scroller();

            return scroller ? scroller.getBoundingClientRect().top : 0;
        },

        /** Remember the first row in view, where the reader is. */
        _capture() {
            const rows = this._rows();
            const at = firstInView(rows.map((row) => row.getBoundingClientRect()), this._band());

            this._anchor = at === -1 ? null : { row: rows[at], top: rows[at].getBoundingClientRect().top - this._origin() };
        },

        /** After a render: keep the reader's place when it was live, then count what is new. */
        _rendered() {
            const live = this.$el.getAttribute('data-wk-live') === 'true';
            const anchor = this._anchor;

            if (live && anchor && anchor.row.isConnected) {
                const moved = anchor.row.getBoundingClientRect().top - this._origin() - anchor.top;

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

            // The focus comes first, without a scroll of its own, and the scroll after it. In the
            // other order WebKit stopped the smooth scroll the moment the row took the focus, and
            // the row stayed out of sight with the focus on it, as bringIntoView() found for a
            // region. The row's own link or button takes it where the row has one.
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

            const reducedMotion = prefersReducedMotion();

            this._scrollTo(target, scrollBehavior(! reducedMotion));

            // A glide that something else stops leaves the row where it was, so a row still wholly
            // out of sight once the glide has had its time comes in without the animation. A jump
            // has arrived when it returns.
            clearTimeout(this._settle);
            this._settle = reducedMotion ? null : setTimeout(() => {
                this._settle = null;

                const rect = target.getBoundingClientRect();
                const now = this._band();

                if (target.isConnected && (rect.bottom <= now.top || rect.top >= now.bottom)) {
                    this._scrollTo(target, 'instant');
                }
            }, SETTLE_AFTER_MS);

            this._count(false);
        },

        /** Scroll the row into the part of its scroller the reader sees. */
        _scrollTo(target, behavior) {
            if (this._scroller()) {
                target.scrollIntoView({ block: 'nearest', behavior });

                return;
            }

            // The page: into the band, below the strip or the bar. A scroll into view stops at the
            // page's scroll padding, which knows a strip and the kit's pinned bars but not a bar of
            // the application's own, and a row left under that bar counted as brought into view
            // while nothing of it could be seen. The padding is kept clear as well, as the scroll
            // into view did.
            const band = this._band();
            const padding = scrollPaddingOf(null);
            const areaTop = Math.max(band.top, padding.top);
            const area = { top: areaTop, bottom: Math.max(areaTop, band.bottom - padding.bottom) };

            window.scrollBy({ top: intoBand(target.getBoundingClientRect(), area), behavior });
        },
    };
}
