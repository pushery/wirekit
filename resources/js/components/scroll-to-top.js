/**
 * Scroll-to-top — the button that appears once the reader is far enough down.
 *
 * The whole component was an inline `x-data` declaring three methods and holding
 * two arrow functions, which Alpine's CSP build parses none of. Under a strict
 * Content-Security-Policy the button never appeared at all, because `visible`
 * started false and nothing was listening to change it.
 *
 * Lifecycle resources held on `this`:
 *   - _onScroll (window scroll listener) — registered passive, removed in
 *     destroy(). A listener that outlives its component keeps a reference to the
 *     whole scope alive and writes into it on every scroll.
 *   - _ticking (rAF coalescing flag) — the frame itself is deliberately not
 *     canceled: its callback only assigns to `this`, which is inert once Alpine
 *     has released the component.
 *
 * @param {Object}  config
 * @param {boolean} [config.forceVisible]  skip the scroll logic entirely and
 *        stay visible — for a docs preview, where there is nothing to scroll
 * @param {number}  [config.threshold]  fraction of a viewport height to pass
 *        before the button appears
 */
export default function wirekitScrollToTop(config = {}) {
    return {
        visible: config.forceVisible === true,
        threshold: Number(config.threshold) || 0,

        _forceVisible: config.forceVisible === true,
        _onScroll: null,
        _ticking: false,

        init() {
            // A forced-visible button has nothing to react to, so it registers
            // no listener at all rather than one that can never change anything.
            if (this._forceVisible) {
                return;
            }

            this._onScroll = () => {
                if (this._ticking) {
                    return;
                }

                window.requestAnimationFrame(() => {
                    this.visible = window.scrollY > (window.innerHeight * this.threshold);
                    this._ticking = false;
                });
                this._ticking = true;
            };

            // Passive: this listener never calls preventDefault, and saying so
            // lets the browser scroll without waiting to find out.
            window.addEventListener('scroll', this._onScroll, { passive: true });
            this._onScroll();
        },

        destroy() {
            if (this._onScroll) {
                window.removeEventListener('scroll', this._onScroll);
                this._onScroll = null;
            }
        },

        scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
    };
}
