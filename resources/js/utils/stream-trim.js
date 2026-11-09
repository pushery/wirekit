/**
 * How many points a strict ApexCharts stream drops from the start of each series before the next
 * points go in.
 *
 * `appendData()` only ever grows a series, and cutting it through `updateSeries()` on every tick
 * makes ApexCharts move each position toward the value of its neighbor, so the line would run
 * backwards on each point. The stream therefore grows to half again its cap and is then cut back
 * to the cap in one update without animation, before the new points slide in.
 *
 * Only a chart whose window `xaxis.range` bounds is cut, and never a point that window draws, nor
 * the point before it, from which the line enters the window. A chart without a window draws every
 * point it holds, so a cut would change the drawing, and it keeps them all. Where a point carries
 * no x of its own, its x is its position, so a cut moves every point and the window with it, and
 * what the window draws stays where it was.
 */

/** The x of a point: its own (`{x, y}` or `[x, y]`), or its position counted from 1. */
function xOf(point, index) {
    if (Array.isArray(point) && typeof point[0] === 'number') {
        return point[0];
    }

    if (point && typeof point === 'object' && typeof point.x === 'number') {
        return point.x;
    }

    return index + 1;
}

/**
 * @param {Array<Array<*>|undefined>} series - each series' data as the chart holds it
 * @param {Array<Array<*>|undefined>} incoming - the points each series is about to receive
 * @param {number} cap - the most points a series keeps
 * @param {number|undefined} range - the width of the drawn window (`xaxis.range`), if any
 * @returns {number} the points to drop from the start of every series, 0 while none is due
 */
export function streamTrimCount(series, incoming, cap, range) {
    if (! Array.isArray(series) || series.length === 0 || ! (cap > 0)) {
        return 0;
    }

    if (! (typeof range === 'number' && Number.isFinite(range) && range > 0)) {
        return 0;
    }

    const after = series.map((data, i) => [
        ...(Array.isArray(data) ? data : []),
        ...(Array.isArray(incoming?.[i]) ? incoming[i] : []),
    ]);
    const longest = Math.max(0, ...after.map((points) => points.length));

    if (longest <= cap + Math.max(1, Math.ceil(cap / 2))) {
        return 0;
    }

    let cut = longest - cap;
    const xs = after.map((points) => points.map(xOf));
    const right = Math.max(...xs.flat());
    const left = right - range;

    for (const list of xs) {
        const first = list.findIndex((x) => x >= left);

        // Everything before the point the line enters from may go; a series the window does not
        // reach at all is drawn by nothing there and may go to its cap.
        cut = Math.min(cut, first < 0 ? list.length : Math.max(0, first - 1));
    }

    return Math.max(0, cut);
}
