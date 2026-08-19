/**
 * Scroll fade, measured — the edge mask that only appears where there is more.
 *
 * ## What the static mask gets wrong
 *
 * `fade="both"` is pure CSS: a `mask-image` on the scroll container, applied
 * unconditionally. That is the right default — no JavaScript, nothing to tear
 * down — but it cannot know two things the reader can see. Measured in a real
 * browser on a shipped preview:
 *
 *     scrolled to the very top      the TOP edge is faded, with nothing above it
 *     scrolled to the very bottom   the BOTTOM edge is faded, with nothing below
 *     content that does not overflow  BOTH edges faded, and the area cannot scroll
 *
 * The third is the one worth a plugin. A scroll area whose content fits has no
 * "more" to hint at, and the mask still dissolves its first and last line —
 * removing ink from text the reader is trying to read, to signal something that
 * is not true.
 *
 * `fade="auto"` opts into measuring instead. The container gets `data-fade`
 * only where an edge really continues: `end` at the top of a scrollable area,
 * `start` at the bottom, `both` in between, and nothing at all when the content
 * fits. The CSS is unchanged — this plugin only decides which of its existing
 * rules applies.
 *
 * ## Without JavaScript
 *
 * Nothing renders `data-fade`, so no mask is painted and the area scrolls
 * exactly as it would have. That is the deliberate direction: the failure of an
 * enhancement should be a missing hint, never text dissolved for no reason.
 *
 * ## Lifecycle resources held on `this`
 *
 *   - _onScroll (element scroll listener) — passive, removed in destroy().
 *   - _resizeObserver (ResizeObserver, via safeObserver) — the container's own
 *     box changing. Stopped in destroy().
 *   - _mutationObserver (MutationObserver, via safeObserver) — content added or
 *     removed, which changes scroll size WITHOUT changing the box, so no
 *     ResizeObserver on the container can see it. Stopped in destroy().
 *   - _ticking (rAF coalescing flag) — the frame is deliberately not canceled:
 *     its callback reads the DOM and writes one attribute, both inert once the
 *     element is detached.
 *
 * `safeObserver` carries the null-guard, so neither callback can fire into a
 * torn-down component.
 *
 * The mutation watch is `childList` + `subtree` and NOT `characterData`: a
 * transcript appending a message is the case that matters and it is a child
 * insertion, while watching every character of a long document would cost far
 * more than the hint is worth. Text edited in place is picked up by the next
 * scroll or resize.
 */
import { safeObserver } from '../utils/safe-observer.js';

/**
 * Slack in pixels before an edge counts as reached.
 *
 * Sub-pixel layout and fractional zoom leave `scrollTop` a hair short of the
 * end on a container that is visibly at the bottom, and a zero-tolerance
 * comparison would leave the start edge faded there forever.
 */
const EDGE_TOLERANCE = 1;

export default function wirekitScrollFade() {
    return {
        _onScroll: null,
        _resizeObserver: null,
        _mutationObserver: null,
        _ticking: false,

        init() {
            this._onScroll = () => this._schedule();

            // Passive: this listener never calls preventDefault, and saying so
            // lets the browser scroll without waiting to find out.
            this.$root.addEventListener('scroll', this._onScroll, { passive: true });

            // Guarded rather than assumed. Both observers are baseline in every
            // supported browser, but this factory is also constructed in a bare
            // Node harness, and a hard reference would make it throw at init
            // there instead of measuring what it is asked to measure.
            if (typeof ResizeObserver === 'function') {
                this._resizeObserver = safeObserver(ResizeObserver, () => this._schedule());
                this._resizeObserver.observe(this.$root);
            }

            if (typeof MutationObserver === 'function') {
                this._mutationObserver = safeObserver(MutationObserver, () => this._schedule());
                this._mutationObserver.observe(this.$root, { childList: true, subtree: true });
            }

            // Measure once now, synchronously: the first paint should already
            // carry the right answer rather than the wrong one for a frame.
            this.measure();
        },

        destroy() {
            if (this._onScroll) {
                this.$root.removeEventListener('scroll', this._onScroll);
                this._onScroll = null;
            }

            this._resizeObserver?.stop();
            this._resizeObserver = null;

            this._mutationObserver?.stop();
            this._mutationObserver = null;
        },

        /**
         * Coalesce every source of change into at most one measurement a frame.
         *
         * Scroll, resize and mutation can all fire in the same tick — a Livewire
         * morph that appends and scrolls does exactly that — and each one asks
         * the same question of the same element.
         */
        _schedule() {
            if (this._ticking) {
                return;
            }

            this._ticking = true;

            const run = () => {
                this._ticking = false;
                this.measure();
            };

            if (typeof requestAnimationFrame === 'function') {
                requestAnimationFrame(run);
            } else {
                run();
            }
        },

        /**
         * Read the container and write the one attribute the CSS reads.
         *
         * Public rather than private on purpose: it is the whole behavior, and a
         * test that can call it directly can assert the mapping without racing a
         * frame.
         */
        measure() {
            const el = this.$root;

            if (! el) {
                return;
            }

            const horizontal = el.getAttribute('data-fade-axis') === 'x';

            const overflow = horizontal
                ? el.scrollWidth - el.clientWidth
                : el.scrollHeight - el.clientHeight;

            /*
             * `Math.abs` is for right-to-left. A horizontal container in RTL
             * reports 0 at its start — the RIGHT edge — and counts DOWN into
             * negative numbers as the reader moves left. The distance traveled
             * from the start is the magnitude either way, and the stylesheet
             * already maps start and end onto the correct physical side under
             * `[dir="rtl"]`, so this stays a question about distance only.
             */
            const traveled = Math.abs(horizontal ? el.scrollLeft : el.scrollTop);

            const atStart = traveled <= EDGE_TOLERANCE;
            const atEnd = traveled >= overflow - EDGE_TOLERANCE;

            /*
             * Both edges reached at once is the case the plugin exists for:
             * there is nothing to scroll — the content fits, or the overflow is
             * under two pixels — so there is no "more" to hint at, and a mask
             * would take ink off text that is entirely visible.
             *
             * It is ONE branch rather than an early `overflow <= tolerance`
             * return, and that is a correction rather than a preference: the
             * early return was written first, and deleting it changed no
             * answer any test could see, because this comparison already
             * covered every case it did. A branch nothing can distinguish is a
             * branch nothing is testing.
             *
             * The other two are inverted on purpose: the edge that gets faded
             * is the one the content CONTINUES past. At the start of the area
             * everything is ahead, so the END edge fades; at the end it is the
             * other way around.
             */
            if (atStart && atEnd) {
                this._apply(null);
            } else if (atStart) {
                this._apply('end');
            } else if (atEnd) {
                this._apply('start');
            } else {
                this._apply('both');
            }
        },

        /**
         * Write `data-fade`, and only when it actually changes.
         *
         * The mutation observer does not watch attributes, so this cannot loop —
         * but an unconditional write still invalidates style on every frame of a
         * scroll, and the comparison costs nothing.
         */
        _apply(value) {
            const el = this.$root;
            const current = el.getAttribute('data-fade');

            if (current === value || (current === null && value === null)) {
                return;
            }

            if (value === null) {
                el.removeAttribute('data-fade');
            } else {
                el.setAttribute('data-fade', value);
            }
        },
    };
}
