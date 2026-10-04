/**
 * `x-wk-stuck`: marks a sticky element as `data-wk-stuck` while it rests at the line it sticks to.
 *
 * CSS says where a sticky element sticks. Whether it sticks right now no engine but Blink can say
 * in CSS (`scroll-state(stuck: top)`), so the marker comes from here, and a style hangs on it: the
 * page header fades its bottom border in while it rests, which sets the content scrolling under it
 * apart, and draws none in the flow of the page.
 *
 * The element rests when its top edge is at the line and the box it scrolls in has scrolled; a
 * header that stands at the very top of a page that has not moved has nothing beneath it yet. The
 * line is the element's own `top`, counted from the top of the box it scrolls in and inside that
 * box's padding, which is where a sticky element comes to rest. For the page it is the top of the
 * viewport.
 *
 * A Livewire render writes the attributes the server sent and so takes the marker away. An
 * observer puts it back while the element still rests.
 *
 * Lifecycle: a capture listener for `scroll` on the document, which hears every box that scrolls,
 * a listener for `resize` on the window and one MutationObserver, all released in the directive's
 * cleanup, together with a pending frame.
 */

export const WK_STUCK_ATTRIBUTE = 'data-wk-stuck';

/**
 * The box a sticky element sticks in: its nearest ancestor that is a scroll container on the
 * block axis, or null when that is the page.
 *
 * `hidden` counts, because CSS makes such a box a scroll container although no reader can scroll
 * it, and a sticky element inside one sticks to it rather than to the page. `clip` does not.
 *
 * @param {HTMLElement} el
 * @returns {HTMLElement|null}
 */
export function scrollParent(el) {
    for (let node = el.parentElement; node && node !== document.body && node !== document.documentElement; node = node.parentElement) {
        const overflow = getComputedStyle(node).overflowY;

        if (overflow === 'auto' || overflow === 'scroll' || overflow === 'hidden' || overflow === 'overlay') {
            return node;
        }
    }

    return null;
}

/**
 * Whether the element rests at its line now.
 *
 * @param {HTMLElement} el
 * @param {HTMLElement|null} container the box it scrolls in, null for the page
 * @returns {boolean}
 */
export function restsAtItsLine(el, container) {
    const top = parseFloat(getComputedStyle(el).top);
    const offset = Number.isFinite(top) ? top : 0;

    let line = offset;
    let scrolled;

    if (container) {
        const padding = parseFloat(getComputedStyle(container).paddingTop);

        line += container.getBoundingClientRect().top + (container.clientTop || 0) + (Number.isFinite(padding) ? padding : 0);
        scrolled = container.scrollTop > 0;
    } else {
        scrolled = (window.scrollY || document.documentElement.scrollTop || 0) > 0;
    }

    // Half a pixel either way: a line at a fractional offset is reported rounded by some engines.
    // Past it in either direction the element is still moving, into its place or out with the
    // end of its container.
    return scrolled && Math.abs(el.getBoundingClientRect().top - line) <= 0.5;
}

/**
 * Register the `x-wk-stuck` directive.
 *
 * @param {object} Alpine
 */
export function registerStuckDirective(Alpine) {
    Alpine.directive('wk-stuck', (el, directive, { cleanup }) => {
        let container = scrollParent(el);
        let rests = false;
        let frame = 0;

        // Writes the marker only when it differs, so the observer below sees no change of its own.
        const apply = () => {
            if (rests && ! el.hasAttribute(WK_STUCK_ATTRIBUTE)) {
                el.setAttribute(WK_STUCK_ATTRIBUTE, '');
            } else if (! rests && el.hasAttribute(WK_STUCK_ATTRIBUTE)) {
                el.removeAttribute(WK_STUCK_ATTRIBUTE);
            }
        };

        const measure = () => {
            frame = 0;

            if (! el.isConnected) {
                return;
            }

            rests = restsAtItsLine(el, container);
            apply();
        };

        // One measurement a frame, however many scroll events arrive in it.
        const schedule = () => {
            if (frame === 0 && typeof requestAnimationFrame === 'function') {
                frame = requestAnimationFrame(measure);
            }
        };

        const resized = () => {
            container = scrollParent(el);
            schedule();
        };

        document.addEventListener('scroll', schedule, { capture: true, passive: true });
        window.addEventListener('resize', resized, { passive: true });

        let observer = null;

        if (typeof MutationObserver !== 'undefined') {
            observer = new MutationObserver(apply);
            observer.observe(el, { attributes: true, attributeFilter: [WK_STUCK_ATTRIBUTE] });
        }

        schedule();

        cleanup(() => {
            document.removeEventListener('scroll', schedule, { capture: true });
            window.removeEventListener('resize', resized);

            if (observer) {
                observer.disconnect();
            }

            if (frame !== 0 && typeof cancelAnimationFrame === 'function') {
                cancelAnimationFrame(frame);
            }

            frame = 0;
        });
    });
}
