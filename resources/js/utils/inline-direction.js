/**
 * The direction a horizontal arrow key moves in, read from the page's writing direction.
 *
 * On a page that lays out right to left, `dir="rtl"` on any ancestor, the logical order of a row
 * runs from the right: its first item stands rightmost and the next one to its left. A key named
 * after a side moves toward that side, so there Right Arrow goes to the previous item and Left
 * Arrow to the next, as a native range input mirrors on a right-to-left page.
 */

/**
 * Whether an element lays out right to left, by its computed direction, which a `dir` on any
 * ancestor sets. Without a window to ask, as in a unit harness, left to right.
 *
 * @param {?Element} el
 * @returns {boolean}
 */
export function isRightToLeft(el) {
    if (! el || typeof window === 'undefined' || typeof window.getComputedStyle !== 'function') {
        return false;
    }

    try {
        return window.getComputedStyle(el).direction === 'rtl';
    } catch {
        return false;
    }
}

/**
 * The step a horizontal arrow key takes along a row in reading order: 1 toward its end, -1 toward
 * its start, 0 for any other key.
 *
 * @param {{ key?: string }} event
 * @param {?Element} el  an element of the row, whose direction decides
 * @returns {number}
 */
export function inlineStep(event, el) {
    if (event?.key === 'ArrowRight') {
        return isRightToLeft(el) ? -1 : 1;
    }

    if (event?.key === 'ArrowLeft') {
        return isRightToLeft(el) ? 1 : -1;
    }

    return 0;
}

/**
 * The key as a row written left to right reads it: on a right-to-left page Right Arrow and Left
 * Arrow swap, and every other key stays as it is. For a handler that names the two keys.
 *
 * @param {{ key?: string }} event
 * @param {?Element} el  an element of the row, whose direction decides
 * @returns {string|undefined}
 */
export function inlineKey(event, el) {
    const step = inlineStep(event, el);

    if (step === 0) {
        return event?.key;
    }

    return step > 0 ? 'ArrowRight' : 'ArrowLeft';
}

/** Register `$wkInlineStep(event)` on an Alpine instance: the step of a side key on the element it is called from. */
export function registerInlineStepMagic(Alpine) {
    Alpine.magic('wkInlineStep', (el) => (event) => inlineStep(event, el));
}
