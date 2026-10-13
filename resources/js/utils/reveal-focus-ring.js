/**
 * Room for a focus ring at the edge of the scroller that holds the focused element.
 *
 * A scroller of the kit carries a block scroll padding as deep as a focus ring, so a browser that
 * scrolls a focused element into view leaves its ring room at the edge. Blink and WebKit on macOS
 * read that padding when they do; WebKit on Linux leaves an element that already shows where it
 * is, with its ring cut at the edge. After the browser has had its say, the scroller moves by what
 * the ring still lacks: nothing when the browser made room, nothing for an element the browser
 * left out of view (bringing that in is the browser's call, and `focus({ preventScroll: true })`
 * asks it not to), and nothing for an element that is taller than the room.
 */

/**
 * Move `scroller` along the block axis so the ring of `target` has the room its padding asks for.
 *
 * @param {HTMLElement} scroller
 * @param {Element} target  the focused element, inside `scroller`
 * @returns {number} how far the scroller moved, negative for up
 */
export function revealFocusRing(scroller, target) {
    if (!scroller || !target || target === scroller || !target.isConnected || !scroller.contains(target)) {
        return 0;
    }

    const style = getComputedStyle(scroller);
    const before = parseFloat(style.scrollPaddingTop) || 0;
    const after = parseFloat(style.scrollPaddingBottom) || 0;

    if (before <= 0 && after <= 0) {
        return 0;
    }

    const top = scroller.getBoundingClientRect().top + scroller.clientTop;
    const bottom = top + scroller.clientHeight;
    const rect = target.getBoundingClientRect();

    // Only an element that already shows: a ring is all that is missing.
    if (rect.top < top - 0.5 || rect.bottom > bottom + 0.5 || rect.height + before + after > scroller.clientHeight) {
        return 0;
    }

    // Whole pixels, rounded up: a browser snaps a scroll offset to the pixel grid, and half a pixel
    // rounded the other way is half a pixel of ring cut again.
    const lackingAbove = Math.ceil(top + before - rect.top);

    if (lackingAbove > 0) {
        scroller.scrollTop -= lackingAbove;

        return -lackingAbove;
    }

    const lackingBelow = Math.ceil(rect.bottom - (bottom - after));

    if (lackingBelow > 0) {
        scroller.scrollTop += lackingBelow;

        return lackingBelow;
    }

    return 0;
}

/**
 * Give every element that takes the focus inside `scroller` room for its ring.
 *
 * Two frames after `focusin`, because WebKit scrolls a focused element into view after the next
 * layout rather than in the call, and only what the browser left is this one's to fix.
 *
 * @param {HTMLElement} scroller
 * @returns {() => void} removes the listener and drops a pending frame
 */
export function keepFocusRingsInView(scroller) {
    let frame = null;

    const onFocusIn = (event) => {
        const target = event.target;

        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => {
            frame = requestAnimationFrame(() => {
                frame = null;

                if (document.activeElement === target) {
                    revealFocusRing(scroller, target);
                }
            });
        });
    };

    scroller.addEventListener('focusin', onFocusIn);

    return () => {
        scroller.removeEventListener('focusin', onFocusIn);
        cancelAnimationFrame(frame);
        frame = null;
    };
}
