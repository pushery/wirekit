/**
 * Scroll a track sideways just far enough that one of its items stands in the visible part of it.
 *
 * The segmented control brings its chosen segment into its track this way, and a tab list its
 * selected tab into its bar.
 *
 * Only the track moves. `scrollIntoView()` would scroll every scrollable ancestor as well, the page
 * included, and a page that jumps on load to show a control in its header is the defect the
 * track's own scroll exists to prevent. The focus stays where it is.
 *
 * The arithmetic uses rectangles rather than `offsetLeft`, so it does not depend on the writing
 * direction: `scrollLeft` counts from the other edge in a right-to-left track, and a rectangle
 * delta does not care. The track's scroll padding is the margin an item keeps from the edge, as it
 * is when the browser scrolls an item into view itself, so a focus ring is not cut off there.
 *
 * An item already in full view leaves the track where it is, so a reader who scrolled the track
 * to see something else keeps it. A track that does not scroll, or a stub without layout, is left
 * alone.
 *
 * @param {HTMLElement|null|undefined} track
 * @param {HTMLElement|null|undefined} item
 */
export function revealInTrack(track, item) {
    if (! item || typeof track?.getBoundingClientRect !== 'function' || typeof item.getBoundingClientRect !== 'function') {
        return;
    }

    if (track.scrollWidth <= track.clientWidth) {
        return;
    }

    const trackBox = track.getBoundingClientRect();
    const box = item.getBoundingClientRect();
    const inset = typeof getComputedStyle === 'function'
        ? parseFloat(getComputedStyle(track).scrollPaddingLeft) || 0
        : 0;

    // Rounded outward: an engine that keeps a scroll offset in whole pixels (WebKit) cuts a fraction
    // off the distance, and the item would stop that fraction past the edge.
    if (box.left - inset < trackBox.left) {
        track.scrollLeft -= Math.ceil(trackBox.left - (box.left - inset));
    } else if (box.right + inset > trackBox.right) {
        track.scrollLeft += Math.ceil(box.right + inset - trackBox.right);
    }
}
