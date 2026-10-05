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
 * goes on where the eyes do; a region that cannot take the focus gets `tabindex="-1"` for that, and
 * keeps it through Livewire's next render for as long as it holds the focus: the server's markup
 * does not carry the attribute, and a render that took it away would take the focus with it.
 * `x-wk-scroll-into-view.nofocus` leaves the focus where it is.
 *
 * ONE RESPONSE, ONE TARGET: when one render brings several regions, the first in the document comes
 * into view and takes the focus, and the others stay where they are. Each region asks on its own,
 * and the last to ask would otherwise win, which is the lowest on the page.
 *
 * REDUCED MOTION jumps instead of gliding: scrollBehavior() in motion.js.
 *
 * Lifecycle: one MutationObserver per element and the settling scroll's timer, both released in the
 * directive's cleanup; one `morph.updating` hook per page, installed with the first tabindex this
 * module sets.
 */

import { prefersReducedMotion, scrollBehavior } from './motion.js';

export const WK_SCROLL_KEY_ATTRIBUTE = 'data-wk-scroll-key';

/** How long a smooth scroll gets before a region still out of view is brought in without one. */
export const SETTLE_AFTER_MS = 800;

/** Node.DOCUMENT_POSITION_PRECEDING, spelled out so the module reads the same outside a browser. */
const PRECEDING = 2;

/** Whether the whole element is inside the viewport. */
function inFullView(el) {
    const rect = el.getBoundingClientRect();
    const height = window.innerHeight || document.documentElement.clientHeight;
    const width = window.innerWidth || document.documentElement.clientWidth;

    return rect.top >= 0 && rect.left >= 0 && rect.bottom <= height && rect.right <= width;
}

/** Whether no part of the element is inside the viewport. */
function outOfView(el) {
    const rect = el.getBoundingClientRect();
    const height = window.innerHeight || document.documentElement.clientHeight;
    const width = window.innerWidth || document.documentElement.clientWidth;

    return rect.bottom <= 0 || rect.top >= height || rect.right <= 0 || rect.left >= width;
}

// The regions this module gave a tabindex, and the hook that keeps it through a render while the
// region holds the focus. A WeakSet, so a region that leaves the page is not held here.
const ownTabindex = new WeakSet();
let morphHookInstalled = false;

/**
 * Keep the tabindex this module set on a focused region when Livewire renders the component again.
 *
 * The morph makes the element match the server's markup, which never carried the attribute: it
 * removed it from a region that still held the focus, and the browser then moved the focus to the
 * page. Writing it onto the incoming element keeps it; once the focus has left, the next render
 * takes it away as before.
 */
function keepOwnTabindexThroughRenders() {
    if (morphHookInstalled || typeof window === 'undefined' || typeof window.Livewire?.hook !== 'function') {
        return;
    }

    morphHookInstalled = true;

    window.Livewire.hook('morph.updating', ({ el, toEl }) => {
        if (! ownTabindex.has(el) || ! toEl || typeof toEl.hasAttribute !== 'function') {
            return;
        }

        if (document.activeElement === el && ! toEl.hasAttribute('tabindex')) {
            toEl.setAttribute('tabindex', '-1');
        }
    });
}

// A region's pending settling scroll, so a newer request or the directive's cleanup can clear it.
const settleTimers = new WeakMap();

/** Clear the settling scroll a region still has pending. Exported for the directive's cleanup. */
export function cancelSettle(el) {
    const timer = settleTimers.get(el);

    if (timer !== undefined) {
        clearTimeout(timer);
        settleTimers.delete(el);
    }
}

/**
 * Bring the element into view and, unless told not to, give it the focus. Exported for the unit
 * test.
 *
 * The focus comes first, without a scroll of its own, and the scroll after it. In the other order
 * WebKit stopped the smooth scroll the moment the region took the focus: a region far above an
 * application's scrolling main area stayed there with the focus on it. A smooth scroll that
 * something else stops leaves the region where it was as well, so one that is still entirely out
 * of view after SETTLE_AFTER_MS is brought in without the animation.
 *
 * @param {HTMLElement} el
 * @param {{focus?: boolean}} [options]
 */
export function bringIntoView(el, { focus = true } = {}) {
    if (! el || ! el.isConnected) {
        return;
    }

    if (focus && typeof el.focus === 'function') {
        // A `div` or a `section` cannot take the focus without a tabindex. -1 makes it focusable
        // for this call without adding a stop to the Tab order.
        if (el.tabIndex < 0 && ! el.hasAttribute('tabindex')) {
            el.setAttribute('tabindex', '-1');
            ownTabindex.add(el);
            keepOwnTabindexThroughRenders();
        }

        el.focus({ preventScroll: true });
    }

    if (inFullView(el) || typeof el.scrollIntoView !== 'function') {
        return;
    }

    const reducedMotion = prefersReducedMotion();

    el.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: scrollBehavior(! reducedMotion) });

    // A jump has arrived when it returns; a glide has not.
    if (reducedMotion) {
        return;
    }

    cancelSettle(el);
    settleTimers.set(el, setTimeout(() => {
        settleTimers.delete(el);

        if (el.isConnected && outOfView(el)) {
            el.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'instant' });
        }
    }, SETTLE_AFTER_MS));
}

/** After the browser has laid the element out, so its box is the one the reader will see. */
function afterLayout(callback) {
    if (typeof requestAnimationFrame === 'function') {
        requestAnimationFrame(() => callback());
    } else {
        setTimeout(callback, 0);
    }
}

// The regions that asked to come into view since the last frame, in the order they asked.
let requests = [];

/**
 * Ask to bring a region into view once the browser has laid it out. Every region that asks before
 * that frame is one answer of the server, and the first of them in the document is the one brought
 * into view.
 */
function requestBringIntoView(el, options) {
    const existing = requests.find((request) => request.el === el);

    if (existing) {
        existing.options = options;

        return;
    }

    requests.push({ el, options });

    if (requests.length > 1) {
        return;
    }

    afterLayout(() => {
        const batch = requests.filter(({ el: region }) => region.isConnected);
        requests = [];

        if (batch.length === 0) {
            return;
        }

        // `compareDocumentPosition` answers PRECEDING when its argument comes before the element.
        const first = batch.reduce((chosen, request) => (
            typeof chosen.el.compareDocumentPosition === 'function'
                && (chosen.el.compareDocumentPosition(request.el) & PRECEDING)
                ? request
                : chosen
        ));

        bringIntoView(first.el, first.options);
    });
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
            requestBringIntoView(el, options);
        }

        cleanup(() => cancelSettle(el));

        if (typeof MutationObserver === 'undefined') {
            return;
        }

        const observer = new MutationObserver(() => {
            const key = el.getAttribute(WK_SCROLL_KEY_ATTRIBUTE);

            // A new key is a new result. The same key written again by a render changes nothing.
            if (key !== null && key !== '' && key !== lastKey) {
                lastKey = key;
                requestBringIntoView(el, options);
            }
        });

        observer.observe(el, { attributes: true, attributeFilter: [WK_SCROLL_KEY_ATTRIBUTE] });

        cleanup(() => observer.disconnect());
    });
}
