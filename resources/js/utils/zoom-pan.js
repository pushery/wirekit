/**
 * Zoom and pan of a picture over the area it may cover.
 *
 * The picture is drawn with `transform: translate(x, y) scale(scale)` and `transform-origin: 0 0`,
 * so a point of the picture at (px, py) in its fitted, unscaled box lands at
 * (x + scale * px, y + scale * py) relative to that box. Two rectangles in the same coordinates
 * (the viewport's, from `getBoundingClientRect()`) describe the geometry:
 *
 *   box    where the picture stands at scale 1, untransformed
 *   frame  the area the enlarged picture may cover, such as the viewer's whole stage
 *
 * The scale never goes below 1. On each axis the picture is settled into the frame: smaller than
 * the frame, it grows around the center of its fitted place and stays inside the frame; larger, it
 * covers the frame and leaves no gap at either edge. At scale 1 that is the fitted place itself.
 */

/** The picture at its fitted size and place. */
export const FITTED = Object.freeze({ scale: 1, x: 0, y: 0 });

/**
 * One axis of `settle()`: the offset of a picture `size` long at `start` in a frame `extent` long.
 *
 * Both ranges close in on the same point where the scaled picture is exactly as long as the frame,
 * so a zoom that passes that size moves the picture without a jump.
 */
function settleAxis(offset, scale, start, size, extent) {
    // The offsets that put the picture's leading edge on the frame's, and its trailing edge on the
    // frame's.
    const leading = -start;
    const trailing = extent - start - scale * size;

    if (scale * size <= extent) {
        return Math.min(trailing, Math.max(leading, ((1 - scale) * size) / 2));
    }

    return Math.min(leading, Math.max(trailing, offset));
}

/**
 * The zoom settled into its frame.
 *
 * @param {{scale: number, x: number, y: number}} zoom
 * @param {{left: number, top: number, width: number, height: number}} box   the fitted picture
 * @param {{left: number, top: number, width: number, height: number}} frame the area it may cover
 * @returns {{scale: number, x: number, y: number}}
 */
export function settle(zoom, box, frame) {
    return {
        scale: zoom.scale,
        x: settleAxis(zoom.x, zoom.scale, box.left - frame.left, box.width, frame.width),
        y: settleAxis(zoom.y, zoom.scale, box.top - frame.top, box.height, frame.height),
    };
}

/**
 * Scale to `scale`, held between 1 and `max`, carrying the point of the picture under `from` to
 * `to`. Both points are relative to the fitted box. A wheel or a button keeps the point where it
 * is (`to` is `from`); a pinch carries it along with the fingers.
 *
 * @param {{scale: number, x: number, y: number}} zoom
 * @param {number} scale
 * @param {{x: number, y: number}} from
 * @param {{x: number, y: number}} to
 * @param {{left: number, top: number, width: number, height: number}} box
 * @param {{left: number, top: number, width: number, height: number}} frame
 * @param {number} max
 * @returns {{scale: number, x: number, y: number}}
 */
export function zoomTo(zoom, scale, from, to, box, frame, max) {
    const next = Math.min(max, Math.max(1, scale));
    const px = (from.x - zoom.x) / zoom.scale;
    const py = (from.y - zoom.y) / zoom.scale;

    return settle({ scale: next, x: to.x - next * px, y: to.y - next * py }, box, frame);
}

/**
 * Move the picture by (dx, dy), as far as the frame lets it.
 *
 * @returns {{scale: number, x: number, y: number}}
 */
export function panBy(zoom, dx, dy, box, frame) {
    return settle({ scale: zoom.scale, x: zoom.x + dx, y: zoom.y + dy }, box, frame);
}
