/**
 * Close-on-scroll for a fixed overlay, measured against the thing the overlay belongs to.
 *
 * A fixed panel is positioned once, against its trigger, and several components close theirs on
 * any scroll outside the panel because a scroll strands it away from that trigger. That is only
 * true of a scroll that MOVES the trigger. Browsers deliver a scroll's event a frame late, so a
 * scroll that finished before the panel opened can still be announced after it: an action that
 * scrolls its own trigger into view and then opens — a right-click below the fold, a keyboard or
 * assistive-technology path — had its panel shut by its own scroll. Measured on the context menu
 * at 393px in Chromium: the page scrolled at ~35 ms, the menu opened at ~37 ms, and the event of
 * that same scroll arrived at ~70 ms with the page exactly where it had been at the open.
 *
 * So each of them takes a snapshot of its anchor when it opens, and a scroll closes the panel
 * only once the anchor has moved since.
 */

/**
 * Where an element stands in the viewport, or null when there is nothing to measure.
 *
 * @param {Element|null|undefined} el
 * @returns {{top: number, left: number}|null}
 */
export function anchorSnapshot(el) {
    if (! el || typeof el.getBoundingClientRect !== 'function') {
        return null;
    }

    const rect = el.getBoundingClientRect();

    return { top: rect.top, left: rect.left };
}

/**
 * Has the element moved since the snapshot?
 *
 * Half a pixel of tolerance, because a fractional layout reports sub-pixel noise with nothing
 * having scrolled. Without a snapshot or an element the answer is yes, which keeps
 * close-on-scroll for a panel opened by a path that took no snapshot.
 *
 * @param {{top: number, left: number}|null} snapshot
 * @param {Element|null|undefined} el
 * @returns {boolean}
 */
export function anchorMoved(snapshot, el) {
    if (! snapshot || ! el || typeof el.getBoundingClientRect !== 'function') {
        return true;
    }

    const rect = el.getBoundingClientRect();

    return Math.abs(rect.top - snapshot.top) > 0.5 || Math.abs(rect.left - snapshot.left) > 0.5;
}

export default anchorMoved;
