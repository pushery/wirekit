/**
 * `x-wk-scroll-into-view`: a region that appears or changes after an action brings itself into view
 * and takes the focus, so the reader sees what their press did.
 *
 * The use it is built for: a list the reader has scrolled down, a "Create" or an "Edit" button in
 * it, and a form or a one-time secret that opens at the top of the page. Without it nothing seems
 * to happen, and the button reads as broken.
 *
 * THE SERVER DECIDES, through `data-wk-scroll-key`: a value that is new with every result to show,
 * an id or a time. The region comes into view when it appears carrying a key and whenever the key
 * changes; without a key it stays where it is, so a page that loads with the region on it does not
 * jump. Why an attribute plus an observer is the reasoning `x-wk-flash` gives: a Livewire render
 * writes the attribute onto the element that is already there, and Alpine does not initialize an
 * element again.
 *
 * A region already in full view is not scrolled. The focus moves to it either way, so the keyboard
 * goes on where the eyes do; a region that cannot take the focus gets `tabindex="-1"` for that.
 * `x-wk-scroll-into-view.nofocus` leaves the focus where it is.
 *
 * REDUCED MOTION jumps instead of gliding: scrollBehavior() in motion.js.
 *
 * Lifecycle: one MutationObserver per element, disconnected in the directive's cleanup.
 */

import { prefersReducedMotion, scrollBehavior } from './motion.js';

export const WK_SCROLL_KEY_ATTRIBUTE = 'data-wk-scroll-key';

/** Whether the whole element is inside the viewport. */
function inFullView(el) {
    const rect = el.getBoundingClientRect();
    const height = window.innerHeight || document.documentElement.clientHeight;
    const width = window.innerWidth || document.documentElement.clientWidth;

    return rect.top >= 0 && rect.left >= 0 && rect.bottom <= height && rect.right <= width;
}

/**
 * Bring the element into view and, unless told not to, give it the focus. Exported for the unit
 * test.
 *
 * @param {HTMLElement} el
 * @param {{focus?: boolean}} [options]
 */
export function bringIntoView(el, { focus = true } = {}) {
    if (! el || ! el.isConnected) {
        return;
    }

    if (! inFullView(el) && typeof el.scrollIntoView === 'function') {
        el.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: scrollBehavior(! prefersReducedMotion()) });
    }

    if (! focus || typeof el.focus !== 'function') {
        return;
    }

    // A `div` or a `section` cannot take the focus without a tabindex. -1 makes it focusable for
    // this call without adding a stop to the Tab order.
    if (el.tabIndex < 0 && ! el.hasAttribute('tabindex')) {
        el.setAttribute('tabindex', '-1');
    }

    el.focus({ preventScroll: true });
}

/** After the browser has laid the element out, so its box is the one the reader will see. */
function afterLayout(callback) {
    if (typeof requestAnimationFrame === 'function') {
        requestAnimationFrame(() => callback());
    } else {
        setTimeout(callback, 0);
    }
}

/**
 * Register the `x-wk-scroll-into-view` directive. The first scroll happens when Alpine initializes
 * the element, which is also when a region the server just added appears; every later one comes
 * from the observer.
 */
export function registerScrollIntoViewDirective(Alpine) {
    Alpine.directive('wk-scroll-into-view', (el, { modifiers }, { cleanup }) => {
        const options = { focus: ! modifiers.includes('nofocus') };
        let lastKey = el.getAttribute(WK_SCROLL_KEY_ATTRIBUTE);

        if (lastKey !== null && lastKey !== '') {
            afterLayout(() => bringIntoView(el, options));
        }

        if (typeof MutationObserver === 'undefined') {
            return;
        }

        const observer = new MutationObserver(() => {
            const key = el.getAttribute(WK_SCROLL_KEY_ATTRIBUTE);

            // A new key is a new result. The same key written again by a render changes nothing.
            if (key !== null && key !== '' && key !== lastKey) {
                lastKey = key;
                afterLayout(() => bringIntoView(el, options));
            }
        });

        observer.observe(el, { attributes: true, attributeFilter: [WK_SCROLL_KEY_ATTRIBUTE] });

        cleanup(() => observer.disconnect());
    });
}
