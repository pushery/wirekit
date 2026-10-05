/**
 * Scroll-to-top — the button that appears once the reader is far enough down.
 *
 * The whole component was an inline `x-data` declaring three methods and holding
 * two arrow functions, which Alpine's CSP build parses none of. Under a strict
 * Content-Security-Policy the button never appeared at all, because `visible`
 * started false and nothing was listening to change it.
 *
 * It follows the region the reader moves through, which is not always the window. In an
 * `<x-wirekit::app-shell viewport>` the window never scrolls: the shell is as tall as the window
 * and `<x-wirekit::main>` scrolls inside it. A button that read only `window.scrollY` would never
 * appear there, and its click would scroll a window with nowhere to go.
 *
 * Lifecycle resources held on `this`:
 *   - _onScroll (document scroll listener, capture phase) — registered passive,
 *     removed in destroy(). A listener that outlives its component keeps a
 *     reference to the whole scope alive and writes into it on every scroll.
 *   - _ticking (rAF coalescing flag) — the frame itself is deliberately not
 *     canceled: its callback only assigns to `this`, which is inert once Alpine
 *     has released the component.
 *
 * @param {Object}  config
 * @param {boolean} [config.forceVisible]  skip the scroll logic entirely and
 *        stay visible — for a docs preview, where there is nothing to scroll
 * @param {number}  [config.threshold]  fraction of the scrolling region's height
 *        (the window's, unless a region scrolls instead) to pass before the button
 *        appears
 */
import { controlIsDisabled } from '../utils/fieldset-disabled.js';
import { prefersReducedMotion, scrollBehavior } from '../utils/motion.js';
import { FOCUSABLE } from '../utils/first-control.js';
import { scrollRootOf } from '../utils/scroll-root.js';

/**
 * The region that scrolls the page for this button, or null when the window does.
 *
 * First the scroll container the button stands in, which is the main region when the button is
 * placed inside it. Then the application shell's main region, for a button placed beside the
 * shell, as a layout file places it. Both only while they actually scroll: `<x-wirekit::main>`
 * carries `overflow-y-auto` in the shell's document mode too, where it grows with its content and
 * the window scrolls instead.
 *
 * Read on every scroll rather than once, because a region starts to scroll only when its content
 * outgrows it, which can happen after the button has started.
 *
 * @param {Element|null} button
 * @returns {Element|null}
 */
function scrollRegionFor(button) {
    const own = scrollRootOf(button);

    if (own) {
        return own;
    }

    if (typeof document === 'undefined' || typeof getComputedStyle !== 'function') {
        return null;
    }

    return [...document.querySelectorAll('.wk-main')].find((main) => {
        const overflowY = getComputedStyle(main).overflowY;

        return (overflowY === 'auto' || overflowY === 'scroll') && main.scrollHeight > main.clientHeight;
    }) ?? null;
}

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
                    this.visible = this._scrolledPast();
                    this._ticking = false;
                });
                this._ticking = true;
            };

            // On the document, in the capture phase: a scroll event does not bubble, so a
            // listener on the window hears the page and never a region that scrolls inside it.
            // Passive: this listener never calls preventDefault, and saying so lets the browser
            // scroll without waiting to find out.
            document.addEventListener('scroll', this._onScroll, { capture: true, passive: true });
            this._onScroll();
        },

        destroy() {
            if (this._onScroll) {
                document.removeEventListener('scroll', this._onScroll, { capture: true });
                this._onScroll = null;
            }
        },

        _scrolledPast() {
            const region = scrollRegionFor(this.$root);

            return region
                ? region.scrollTop > (region.clientHeight * this.threshold)
                : window.scrollY > (window.innerHeight * this.threshold);
        },

        scrollToTop() {
            /*
             * `behavior` is an ARGUMENT, so the CSS reduced-motion rule cannot
             * reach it. That rule sets `scroll-behavior`, which an explicit
             * argument overrides — the browser does what the call asked for.
             * Every animated scroll in this bundle therefore has to consult the
             * preference itself, and this one was the last that did not.
             */
            (scrollRegionFor(this.$root) ?? window).scrollTo({ top: 0, behavior: scrollBehavior(!prefersReducedMotion()) });

            // The button promises the top of the page, and the focus stayed at the bottom, on a
            // button that hides as soon as the page is back up there: the next Tab continued from
            // where the reader had left. When the button held the focus, the first control of the
            // document takes it (a skip link, usually), without a scroll of its own so the smooth
            // one is not cut short.
            const button = this.$root;

            if (typeof document !== 'undefined' && button && document.activeElement === button) {
                const first = [...document.querySelectorAll(FOCUSABLE)].find((el) => el !== button
                    && ! controlIsDisabled(el)
                    && el.tabIndex >= 0
                    && el.getClientRects().length > 0
                    && ! el.closest('[inert]'));

                if (first) {
                    first.focus({ preventScroll: true });
                }
            }
        },
    };
}
