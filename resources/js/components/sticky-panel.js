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
 *   - _resizeObserver (ResizeObserver on the scroller) — disconnected + nulled in
 *     destroy(); its callback null-guards both observers. It exists for the reveal of a
 *     hidden panel, see _recheckOnReveal below.
 */
export default function wirekitStickyPanelShadows() {
    return {
        topShadow: false,
        bottomShadow: false,

        /*
         * The inline-axis pair, for a bar that scrolls sideways — a tab strip, a chip row.
         *
         * The observer never cared which axis it watched; it only asks whether a sentinel is
         * in view. Only the names and the CSS were pinned to one direction, which left a
         * horizontal strip with no way to say "there is more this way" using design-system
         * parts. `fade` does not fill that gap: it is a static mask and dims the edge even at
         * the end of the strip, where nothing follows and the last item just looks disabled.
         *
         * `start` / `end` rather than left / right, so a right-to-left interface gets the cue
         * on the side its content actually continues toward.
         */
        startShadow: false,
        endShadow: false,

        _observer: null,
        _resizeObserver: null,

        /*
         * Set while the scroller has no box. An IntersectionObserver reports only CHANGES of
         * a sentinel's visibility, and a sentinel that is out of view both inside a hidden
         * panel and after the reveal (the end of a table that really overflows) never gets a
         * second report: its shadow would stay as the hidden state left it. So the reveal is
         * watched for separately, and the sentinels are observed afresh, which delivers a new
         * first report of their actual state.
         */
        _recheckOnReveal: false,

        init() {
            const scroller = this.$refs.scroller;

            if (!scroller || typeof IntersectionObserver === 'undefined') {
                return;
            }

            // Both pairs are optional and independent: a panel may scroll one way, the other,
            // or — a two-dimensional scroller — both at once.
            const sentinels = {
                top: this.$refs.topSentinel,
                bottom: this.$refs.bottomSentinel,
                start: this.$refs.startSentinel,
                end: this.$refs.endSentinel,
            };

            const present = Object.entries(sentinels).filter(([, el]) => !! el);

            if (present.length === 0) {
                return;
            }

            this._observer = new IntersectionObserver((entries) => {
                // Null-guard against post-destroy fire — browser-queued callbacks
                // can execute after Alpine teardown set this._observer to null.
                if (!this._observer) return;
                for (const entry of entries) {
                    // A scroller with no box says nothing about overflow. Inside a hidden
                    // panel (a tab not yet selected, anything under `display: none`) every
                    // sentinel reports "out of view", and taking that at its word switched
                    // BOTH shadows on: they stood there on the reveal until the next
                    // observation faded them out, a flash on each side of a table that fits.
                    // Such an entry now means "no shadow", and the observation that follows
                    // the reveal sets the real state from nothing.
                    const rootHasBox = !! entry.rootBounds
                        && entry.rootBounds.width > 0
                        && entry.rootBounds.height > 0;

                    if (! rootHasBox) {
                        this._recheckOnReveal = true;
                    }

                    for (const [edge, el] of present) {
                        if (entry.target === el) {
                            this[edge + 'Shadow'] = rootHasBox && ! entry.isIntersecting;
                        }
                    }
                }
            }, { root: scroller });

            for (const [, el] of present) {
                this._observer.observe(el);
            }

            if (typeof ResizeObserver !== 'undefined') {
                this._resizeObserver = new ResizeObserver((entries) => {
                    // Null-guard against post-destroy fire, for both observers this reaches.
                    if (!this._resizeObserver || !this._observer) return;

                    const box = entries[0]?.contentRect;

                    if (! box || box.width === 0 || box.height === 0) {
                        this._recheckOnReveal = true;

                        return;
                    }

                    if (! this._recheckOnReveal) return;

                    this._recheckOnReveal = false;

                    for (const [, el] of present) {
                        this._observer.unobserve(el);
                        this._observer.observe(el);
                    }
                });
                this._resizeObserver.observe(scroller);
            }
        },

        destroy() {
            if (this._observer) {
                this._observer.disconnect();
                this._observer = null;
            }

            if (this._resizeObserver) {
                this._resizeObserver.disconnect();
                this._resizeObserver = null;
            }
        },
    };
}
