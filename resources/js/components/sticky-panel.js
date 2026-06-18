/**
 * WireKit Sticky Panel — overlay scroll-shadow driver.
 *
 * The panel body's top/bottom overflow shadows are OVERLAYS painted above the
 * scrolled content (`.wk-scroll-shadow-top/-bottom` in dist/wirekit.css), so a
 * hovered row or button near an edge can never cover the affordance. The
 * earlier approach drew the shadows in the scroll container's `background`,
 * which children paint over (a ghost button's hover surface swallowed the
 * shadow at both edges).
 *
 * Auto-hide at the scroll extremes is driven by an IntersectionObserver over
 * two 1px sentinels at the very start/end of the scroll content: a sentinel
 * leaving the scrollport means there is more content in that direction, so
 * that side's shadow shows. No scroll listener — the observer fires only on
 * boundary transitions.
 *
 * Graceful no-JS: the overlays carry x-cloak, so without Alpine they simply
 * never render (the panel still sticks and scrolls).
 *
 * Lifecycle resources held on `this`:
 *   - _observer (IntersectionObserver) — disconnected + nulled in destroy();
 *     the callback null-guards against post-destroy fires.
 */
export default function wirekitStickyPanelShadows() {
    return {
        topShadow: false,
        bottomShadow: false,
        _observer: null,

        init() {
            const scroller = this.$refs.scroller;
            const top = this.$refs.topSentinel;
            const bottom = this.$refs.bottomSentinel;
            if (!scroller || !top || !bottom || typeof IntersectionObserver === 'undefined') {
                return;
            }
            this._observer = new IntersectionObserver((entries) => {
                // Null-guard against post-destroy fire — browser-queued callbacks
                // can execute after Alpine teardown set this._observer to null.
                if (!this._observer) return;
                for (const entry of entries) {
                    if (entry.target === top) this.topShadow = !entry.isIntersecting;
                    if (entry.target === bottom) this.bottomShadow = !entry.isIntersecting;
                }
            }, { root: scroller });
            this._observer.observe(top);
            this._observer.observe(bottom);
        },

        destroy() {
            if (this._observer) {
                this._observer.disconnect();
                this._observer = null;
            }
        },
    };
}
