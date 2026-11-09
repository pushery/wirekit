/**
 * The width a sidebar takes from its handle, in lengths rather than shares.
 *
 * `resizable` splits a container between panels in percent, which is right for a layout of
 * equals. A sidebar is not one of equals: its bounds are lengths (`12rem` to `24rem`), and the
 * content beside it takes whatever is left, so a share would make the sidebar grow with the
 * window. The arithmetic lives here, apart from the DOM, so the ESM chain can hold it.
 */

/** How far one arrow key or one step control moves the handle, in CSS pixels. */
export const WIDTH_STEP_PX = 10;

/** How far a step goes with Shift held, as the resizable handle steps ten times further. */
export const WIDTH_STEP_SHIFT_PX = 50;

/**
 * A CSS length in pixels: a number is pixels already, `px` and `rem` are read, anything else is
 * not a length here and answers `NaN`, so the caller keeps its default.
 *
 * @param {number|string|null|undefined} value
 * @param {number} rootFontSize  the root element's font size in pixels, for `rem`
 * @returns {number}
 */
export function lengthInPixels(value, rootFontSize = 16) {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : NaN;
    }

    const match = /^\s*(-?\d+(?:\.\d+)?)(px|rem)\s*$/.exec(String(value ?? ''));

    if (! match) {
        return NaN;
    }

    const amount = Number(match[1]);

    return match[2] === 'rem' ? amount * rootFontSize : amount;
}

/**
 * The bounds of a sidebar's width in pixels, with the minimum never above the maximum and the
 * declared width between them.
 *
 * @returns {{ min: number, max: number, initial: number }}
 */
export function widthBounds({ min, max, initial }, rootFontSize = 16) {
    const lower = lengthInPixels(min, rootFontSize);
    const upper = lengthInPixels(max, rootFontSize);
    const start = lengthInPixels(initial, rootFontSize);

    const minPx = Number.isFinite(lower) && lower >= 0 ? lower : 12 * rootFontSize;
    const maxPx = Math.max(minPx, Number.isFinite(upper) ? upper : 24 * rootFontSize);
    const initialPx = Number.isFinite(start) ? start : 16 * rootFontSize;

    return { min: minPx, max: maxPx, initial: clampWidth(initialPx, minPx, maxPx) };
}

/** A width held between the bounds, rounded to a whole pixel. */
export function clampWidth(width, min, max) {
    if (! Number.isFinite(width)) {
        return min;
    }

    return Math.round(Math.min(max, Math.max(min, width)));
}

/**
 * Which physical edge of the screen a sidebar is attached to, from its logical side and the
 * page's direction: `start` is the left from left to right and the right from right to left.
 *
 * @param {'start'|'end'} side
 * @param {boolean} rightToLeft
 * @returns {'left'|'right'}
 */
export function attachedEdge(side, rightToLeft) {
    return (side === 'end') !== rightToLeft ? 'right' : 'left';
}

/**
 * The width a drag asks for: the distance from the edge the sidebar is attached to, to the
 * pointer.
 *
 * @param {number} clientX
 * @param {{ left: number, right: number }} rect  the sidebar's box
 * @param {'left'|'right'} edge  from `attachedEdge()`
 */
export function widthFromPointer(clientX, rect, edge) {
    return edge === 'left' ? clientX - rect.left : rect.right - clientX;
}

/**
 * The width a key asks for, or `null` for a key the handle does not take.
 *
 * `toward` is the step of a side key along the line, 1 toward its end and -1 toward its start
 * (`inlineStep()`), so the arrow that points away from the sidebar's edge widens it: for a
 * sidebar at the start of the line that is the key toward the end, for one at the end the key
 * toward the start. Home and End go to the bounds, Enter and Space back to the declared width,
 * as the Window Splitter pattern asks.
 *
 * @param {{ key: string, shiftKey?: boolean }} event
 * @param {{ width: number, min: number, max: number, initial: number, toward: number, side: 'start'|'end' }} state
 * @returns {number|null}
 */
export function widthFromKey(event, { width, min, max, initial, toward, side }) {
    const step = event.shiftKey ? WIDTH_STEP_SHIFT_PX : WIDTH_STEP_PX;

    if (toward !== 0) {
        return clampWidth(width + (side === 'end' ? -toward : toward) * step, min, max);
    }

    switch (event.key) {
        case 'Home':
            return min;
        case 'End':
            return max;
        case 'Enter':
        case ' ':
            return initial;
        default:
            return null;
    }
}
