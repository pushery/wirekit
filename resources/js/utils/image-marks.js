/**
 * The marks `<x-wirekit::image-editor>` draws on an image: arrows, lines, ellipses, rectangles and
 * freehand lines, and the geometry the editor needs to draw, find, move and size them.
 *
 * A mark in the editor's value is in percent of the image with one decimal, the unit its crop is
 * in: a line or an arrow from (x1, y1) to (x2, y2), an ellipse or a rectangle at (x, y) with w and h,
 * a freehand line as a flat list of points, x and y alternating. Its `width` is percent of the
 * image's longer side, so a mark keeps its weight in the result whatever size the editor showed the
 * image at.
 *
 * A percent across and a percent down are different lengths on an image that is not square, so a
 * distance, a 45° step and an arrow's head are measured in the image's pixels: toPixels() converts
 * a mark there, with `stroke`, its width in pixels, and toPercent() converts it back. Moving and
 * sizing are the same in both units and take the extent of the unit they are given.
 *
 * Nothing here touches the DOM.
 */
import { parseColor } from './color.js';

/** The kinds of mark, in the order a toolbar offers them. */
export const MARK_TYPES = Object.freeze(['arrow', 'line', 'ellipse', 'rect', 'freehand']);

/** The three stroke widths a toolbar offers, in percent of the image's longer side. */
export const MARK_WIDTHS = Object.freeze({ thin: 0.4, medium: 0.8, thick: 1.2 });

/** The width a mark takes where it names none. */
export const DEFAULT_MARK_WIDTH = MARK_WIDTHS.medium;

/** The widest stroke a mark keeps, in percent of the image's longer side. */
const MAX_MARK_WIDTH = 10;

const LINES = new Set(['arrow', 'line']);
const BOXES = new Set(['ellipse', 'rect']);

const round = (value) => Math.round(value * 10) / 10;

const clamp = (value, low, high) => Math.min(Math.max(value, low), Math.max(low, high));

const finite = (value) => {
    const number = Number(value);

    return value !== null && value !== '' && Number.isFinite(number) ? number : 0;
};

/** Whether a mark runs from one point to another: a line or an arrow. */
export const isLine = (mark) => LINES.has(mark?.type);

/** Whether a mark is drawn in a box: an ellipse or a rectangle. */
export const isBox = (mark) => BOXES.has(mark?.type);

/**
 * A mark as the value holds it: a known kind, a color, a positive width and its coordinates held
 * to the image and kept to one decimal. A mark whose color is no color takes the first of
 * `colors`. A mark without extent, which is what a click without a drag makes, is null.
 *
 * @param {object} mark
 * @param {string[]} [colors] - the palette's colors, the first one for a mark without a color
 * @returns {object|null}
 */
export function normalizeMark(mark, colors = []) {
    if (! mark || typeof mark !== 'object' || ! MARK_TYPES.includes(mark.type)) {
        return null;
    }

    // A color in a form a swatch takes, read as the server's CssColor reads it.
    const color = typeof mark.color === 'string' && parseColor(mark.color) !== null ? mark.color.trim() : (colors[0] ?? null);
    const width = Number(mark.width);
    const style = {
        type: mark.type,
        color,
        width: Number.isFinite(width) && width > 0 ? Math.max(0.1, round(Math.min(width, MAX_MARK_WIDTH))) : DEFAULT_MARK_WIDTH,
    };

    if (color === null) {
        return null;
    }

    const at = (value) => round(clamp(finite(value), 0, 100));

    if (isLine(mark)) {
        const line = { ...style, x1: at(mark.x1), y1: at(mark.y1), x2: at(mark.x2), y2: at(mark.y2) };

        return line.x1 === line.x2 && line.y1 === line.y2 ? null : line;
    }

    if (isBox(mark)) {
        const left = at(mark.x);
        const top = at(mark.y);
        const right = at(finite(mark.x) + finite(mark.w));
        const bottom = at(finite(mark.y) + finite(mark.h));
        const x = Math.min(left, right);
        const y = Math.min(top, bottom);
        const box = { ...style, x, y, w: round(Math.max(left, right) - x), h: round(Math.max(top, bottom) - y) };

        return box.w === 0 || box.h === 0 ? null : box;
    }

    const raw = Array.isArray(mark.points) ? mark.points : [];
    const points = [];

    for (let index = 0; index + 1 < raw.length; index += 2) {
        points.push(at(raw[index]), at(raw[index + 1]));
    }

    const bounds = pointBounds(points);

    return points.length < 4 || (bounds.w === 0 && bounds.h === 0) ? null : { ...style, points };
}

/**
 * The marks of a value: each one normalized, the ones that are no mark left out, in their order.
 *
 * @param {unknown} value - a list of marks
 * @param {string[]} [colors]
 * @returns {object[]}
 */
export function marksFrom(value, colors = []) {
    return (Array.isArray(value) ? value : []).map((mark) => normalizeMark(mark, colors)).filter((mark) => mark !== null);
}

/** The scale of a percent of the longer side, in pixels of an image of a size. */
const longer = (size) => Math.max(size.width, size.height) / 100;

/**
 * A mark in the pixels of an image of a size, with `stroke`, its width in pixels. The other keys
 * stay as they are.
 *
 * @param {object} mark - in percent
 * @param {{width: number, height: number}} size
 */
export function toPixels(mark, size) {
    return convert(mark, size.width / 100, size.height / 100, { stroke: mark.width * longer(size) });
}

/**
 * A mark in percent of an image of a size, from one in its pixels. `stroke` is left out, and the
 * width stays the percent it is: a mark is drawn in pixels and kept in percent.
 *
 * @param {object} mark - in pixels
 * @param {{width: number, height: number}} size
 */
export function toPercent(mark, size) {
    const { stroke: _stroke, ...rest } = mark;

    return convert(rest, 100 / size.width, 100 / size.height, {});
}

function convert(mark, sx, sy, extra) {
    if (isLine(mark)) {
        return { ...mark, ...extra, x1: mark.x1 * sx, y1: mark.y1 * sy, x2: mark.x2 * sx, y2: mark.y2 * sy };
    }

    if (isBox(mark)) {
        return { ...mark, ...extra, x: mark.x * sx, y: mark.y * sy, w: mark.w * sx, h: mark.h * sy };
    }

    return { ...mark, ...extra, points: mark.points.map((value, index) => value * (index % 2 === 0 ? sx : sy)) };
}

/**
 * The rectangle a mark covers, without its stroke, in its own unit.
 *
 * @returns {{x: number, y: number, w: number, h: number}}
 */
export function markBounds(mark) {
    if (isLine(mark)) {
        return pointBounds([mark.x1, mark.y1, mark.x2, mark.y2]);
    }

    if (isBox(mark)) {
        return { x: mark.x, y: mark.y, w: mark.w, h: mark.h };
    }

    return pointBounds(mark.points);
}

function pointBounds(points) {
    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;

    for (let index = 0; index + 1 < points.length; index += 2) {
        minX = Math.min(minX, points[index]);
        maxX = Math.max(maxX, points[index]);
        minY = Math.min(minY, points[index + 1]);
        maxY = Math.max(maxY, points[index + 1]);
    }

    return minX === Infinity ? { x: 0, y: 0, w: 0, h: 0 } : { x: minX, y: minY, w: maxX - minX, h: maxY - minY };
}

/**
 * A mark moved by a distance. It keeps its form and stops where a point would leave the image.
 *
 * @param {object} mark
 * @param {number} dx
 * @param {number} dy
 * @param {{width: number, height: number}} [extent] - the image in the mark's unit, 100 × 100 in percent
 */
export function moveMark(mark, dx, dy, extent = { width: 100, height: 100 }) {
    const box = markBounds(mark);
    const mx = clamp(finite(dx), -box.x, extent.width - (box.x + box.w));
    const my = clamp(finite(dy), -box.y, extent.height - (box.y + box.h));

    if (isLine(mark)) {
        return { ...mark, x1: mark.x1 + mx, y1: mark.y1 + my, x2: mark.x2 + mx, y2: mark.y2 + my };
    }

    if (isBox(mark)) {
        return { ...mark, x: mark.x + mx, y: mark.y + my };
    }

    return { ...mark, points: mark.points.map((value, index) => value + (index % 2 === 0 ? mx : my)) };
}

/**
 * A mark sized from one of its handles, as markHandles() names them. A line or an arrow moves the
 * point its handle names, `start` or `end`. A box moves the edges its handle names and keeps the
 * opposite ones, and a freehand line scales its points within its box from the same eight handles.
 * Every point stays on the image. A change that leaves the mark without extent is null.
 *
 * @param {object} mark
 * @param {string} handle
 * @param {number} dx
 * @param {number} dy
 * @param {{width: number, height: number}} [extent]
 * @returns {object|null}
 */
export function resizeMark(mark, handle, dx, dy, extent = { width: 100, height: 100 }) {
    const mx = finite(dx);
    const my = finite(dy);
    const atX = (value) => clamp(value, 0, extent.width);
    const atY = (value) => clamp(value, 0, extent.height);

    if (isLine(mark)) {
        const line = handle === 'start'
            ? { ...mark, x1: atX(mark.x1 + mx), y1: atY(mark.y1 + my) }
            : { ...mark, x2: atX(mark.x2 + mx), y2: atY(mark.y2 + my) };

        return line.x1 === line.x2 && line.y1 === line.y2 ? null : line;
    }

    const box = markBounds(mark);
    const left = atX(box.x + (handle.includes('w') ? mx : 0));
    const right = atX(box.x + box.w + (handle.includes('e') ? mx : 0));
    const top = atY(box.y + (handle.includes('n') ? my : 0));
    const bottom = atY(box.y + box.h + (handle.includes('s') ? my : 0));

    if (isBox(mark)) {
        const sized = { ...mark, x: Math.min(left, right), y: Math.min(top, bottom), w: Math.abs(right - left), h: Math.abs(bottom - top) };

        return sized.w === 0 || sized.h === 0 ? null : sized;
    }

    // A freehand line keeps its form: every point keeps its place relative to the box, and a box
    // that had no width or no height keeps that axis as it is.
    if (right === left && box.w !== 0) {
        return null;
    }

    if (bottom === top && box.h !== 0) {
        return null;
    }

    const sx = box.w === 0 ? 1 : (right - left) / box.w;
    const sy = box.h === 0 ? 1 : (bottom - top) / box.h;

    return {
        ...mark,
        points: mark.points.map((value, index) => (index % 2 === 0 ? left + (value - box.x) * sx : top + (value - box.y) * sy)),
    };
}

/**
 * The handles of a mark, by name, at their centers: the two ends of a line or an arrow, and the
 * eight a box has, at its corners and the middles of its edges, for the other kinds.
 *
 * @returns {Record<string, {x: number, y: number}>}
 */
export function markHandles(mark) {
    if (isLine(mark)) {
        return { start: { x: mark.x1, y: mark.y1 }, end: { x: mark.x2, y: mark.y2 } };
    }

    const { x, y, w, h } = markBounds(mark);
    const right = x + w;
    const bottom = y + h;
    const middleX = x + w / 2;
    const middleY = y + h / 2;

    return {
        nw: { x, y },
        n: { x: middleX, y },
        ne: { x: right, y },
        e: { x: right, y: middleY },
        se: { x: right, y: bottom },
        s: { x: middleX, y: bottom },
        sw: { x, y: bottom },
        w: { x, y: middleY },
    };
}

/**
 * The topmost mark a point falls on, or null. A mark is found on its stroke, not inside it, so a
 * mark drawn around something does not take every press meant for what it surrounds.
 *
 * @param {object[]} marks - in pixels, in drawing order, the last one on top
 * @param {{x: number, y: number}} point - in pixels
 * @param {number} tolerance - how far beside the stroke still counts, in pixels
 * @returns {number|null} the mark's index
 */
export function markAt(marks, point, tolerance) {
    for (let index = marks.length - 1; index >= 0; index -= 1) {
        const mark = marks[index];

        if (distanceToMark(mark, point) <= tolerance + (mark.stroke ?? 0) / 2) {
            return index;
        }
    }

    return null;
}

/** The distance from a point to the stroke of a mark, both in pixels. */
export function distanceToMark(mark, point) {
    if (isLine(mark)) {
        return distanceToSegment(point, mark.x1, mark.y1, mark.x2, mark.y2);
    }

    if (mark.type === 'rect') {
        const right = mark.x + mark.w;
        const bottom = mark.y + mark.h;

        return Math.min(
            distanceToSegment(point, mark.x, mark.y, right, mark.y),
            distanceToSegment(point, right, mark.y, right, bottom),
            distanceToSegment(point, right, bottom, mark.x, bottom),
            distanceToSegment(point, mark.x, bottom, mark.x, mark.y),
        );
    }

    if (mark.type === 'ellipse') {
        return distanceToEllipse(point, mark.x + mark.w / 2, mark.y + mark.h / 2, mark.w / 2, mark.h / 2);
    }

    let nearest = Infinity;

    for (let index = 0; index + 3 < mark.points.length; index += 2) {
        nearest = Math.min(nearest, distanceToSegment(point, mark.points[index], mark.points[index + 1], mark.points[index + 2], mark.points[index + 3]));
    }

    return nearest;
}

function distanceToSegment(point, x1, y1, x2, y2) {
    const dx = x2 - x1;
    const dy = y2 - y1;
    const lengthSquared = dx * dx + dy * dy;
    const t = lengthSquared === 0 ? 0 : clamp(((point.x - x1) * dx + (point.y - y1) * dy) / lengthSquared, 0, 1);

    return Math.hypot(point.x - (x1 + t * dx), point.y - (y1 + t * dy));
}

/**
 * The distance to an ellipse's outline, close enough for finding a mark under a pointer: the
 * outline is sampled, which stays within a fraction of a pixel of the true distance at the sizes an
 * image on a page has.
 */
function distanceToEllipse(point, cx, cy, rx, ry) {
    const samples = 72;
    let nearest = Infinity;
    let previous = { x: cx + rx, y: cy };

    for (let step = 1; step <= samples; step += 1) {
        const angle = (step / samples) * Math.PI * 2;
        const next = { x: cx + rx * Math.cos(angle), y: cy + ry * Math.sin(angle) };

        nearest = Math.min(nearest, distanceToSegment(point, previous.x, previous.y, next.x, next.y));
        previous = next;
    }

    return nearest;
}

/**
 * The end of a line held to a multiple of 45° around its start, as Shift does while drawing. Its
 * length along the held direction stays. In pixels.
 *
 * @returns {{x: number, y: number}}
 */
export function snapLineEnd(x1, y1, x2, y2) {
    const length = Math.hypot(x2 - x1, y2 - y1);
    const step = Math.PI / 4;
    const angle = Math.round(Math.atan2(y2 - y1, x2 - x1) / step) * step;

    return { x: x1 + length * Math.cos(angle), y: y1 + length * Math.sin(angle) };
}

/**
 * The far corner of a box held square around its first corner, as Shift does for a rectangle or an
 * ellipse. The longer side wins, and the direction of the drag stays. In pixels.
 *
 * @returns {{x: number, y: number}}
 */
export function snapSquareCorner(x1, y1, x2, y2) {
    const side = Math.max(Math.abs(x2 - x1), Math.abs(y2 - y1));

    return { x: x1 + side * (x2 < x1 ? -1 : 1), y: y1 + side * (y2 < y1 ? -1 : 1) };
}

/**
 * The two back corners of an arrow's head, for a shaft from (x1, y1) to the tip (x2, y2), in pixels.
 *
 * The head grows with the stroke, so a thick arrow keeps a point that shows, and is never longer
 * than half the shaft, so a short arrow is still an arrow and not a triangle.
 *
 * @returns {{left: {x: number, y: number}, right: {x: number, y: number}, length: number}}
 */
export function arrowHead(x1, y1, x2, y2, stroke) {
    const shaft = Math.hypot(x2 - x1, y2 - y1);
    const length = Math.min(Math.max(stroke * 4, 12), shaft / 2);
    const angle = Math.atan2(y2 - y1, x2 - x1);
    const spread = Math.PI / 7;

    return {
        left: { x: x2 - length * Math.cos(angle - spread), y: y2 - length * Math.sin(angle - spread) },
        right: { x: x2 - length * Math.cos(angle + spread), y: y2 - length * Math.sin(angle + spread) },
        length,
    };
}

/**
 * A mark as SVG path data, in pixels: a line, the shaft of an arrow up to where its head begins, the
 * outline of a rectangle or an ellipse, and a freehand line curved through the midpoints between its
 * points, the same curve the result is drawn with.
 */
export function markPath(mark) {
    if (mark.type === 'arrow') {
        const head = arrowHead(mark.x1, mark.y1, mark.x2, mark.y2, mark.stroke);

        return `M${mark.x1} ${mark.y1}L${(head.left.x + head.right.x) / 2} ${(head.left.y + head.right.y) / 2}`;
    }

    if (mark.type === 'line') {
        return `M${mark.x1} ${mark.y1}L${mark.x2} ${mark.y2}`;
    }

    if (mark.type === 'rect') {
        return `M${mark.x} ${mark.y}H${mark.x + mark.w}V${mark.y + mark.h}H${mark.x}Z`;
    }

    if (mark.type === 'ellipse') {
        const rx = mark.w / 2;
        const ry = mark.h / 2;
        const cy = mark.y + ry;

        return `M${mark.x} ${cy}A${rx} ${ry} 0 1 0 ${mark.x + mark.w} ${cy}A${rx} ${ry} 0 1 0 ${mark.x} ${cy}Z`;
    }

    const points = mark.points;
    let path = `M${points[0]} ${points[1]}`;

    for (let index = 2; index + 3 < points.length; index += 2) {
        path += `Q${points[index]} ${points[index + 1]} ${(points[index] + points[index + 2]) / 2} ${(points[index + 1] + points[index + 3]) / 2}`;
    }

    return `${path}L${points[points.length - 2]} ${points[points.length - 1]}`;
}

/** The head of an arrow as the three corners of a triangle, for an SVG polygon's `points`, in pixels. */
export function arrowHeadPoints(mark) {
    const head = arrowHead(mark.x1, mark.y1, mark.x2, mark.y2, mark.stroke);

    return `${mark.x2},${mark.y2} ${head.left.x},${head.left.y} ${head.right.x},${head.right.y}`;
}

/**
 * A mark of a kind in a size that reads on the image, centered on a point: where a tap landed, or
 * the middle of the crop for a mark made from the keyboard. In pixels. A freehand line has no size
 * of its own and is not made this way.
 *
 * @param {string} type
 * @param {{width: number, height: number}} size
 * @param {{x: number, y: number}} at
 * @param {{color?: string, width?: number}} [style]
 * @returns {object|null}
 */
export function defaultMark(type, size, at, style = {}) {
    const side = Math.max(8, Math.round(Math.min(size.width, size.height) / 5));
    const half = side / 2;

    if (LINES.has(type)) {
        return { type, ...style, x1: at.x - half, y1: at.y, x2: at.x + half, y2: at.y };
    }

    if (BOXES.has(type)) {
        return { type, ...style, x: at.x - side * 0.75, y: at.y - half, w: side * 1.5, h: side };
    }

    return null;
}

/**
 * Fewer points along the same line: the Ramer-Douglas-Peucker simplification, which leaves out
 * every point closer than `tolerance` to the line between its neighbors. A pointer reports dozens of
 * points a second, and most of them say nothing a straighter line would not.
 *
 * @param {number[]} points - a flat list, x and y alternating
 * @param {number} tolerance - in the points' unit
 * @returns {number[]}
 */
export function simplifyPoints(points, tolerance) {
    const count = Math.floor(points.length / 2);

    if (count <= 2) {
        return points.slice(0, count * 2);
    }

    const keep = new Array(count).fill(false);
    const stack = [[0, count - 1]];

    keep[0] = true;
    keep[count - 1] = true;

    while (stack.length > 0) {
        const [first, last] = stack.pop();
        let farthest = -1;
        let distance = 0;

        for (let index = first + 1; index < last; index += 1) {
            const d = distanceToSegment(
                { x: points[index * 2], y: points[index * 2 + 1] },
                points[first * 2],
                points[first * 2 + 1],
                points[last * 2],
                points[last * 2 + 1],
            );

            if (d > distance) {
                distance = d;
                farthest = index;
            }
        }

        if (farthest !== -1 && distance > tolerance) {
            keep[farthest] = true;
            stack.push([first, farthest], [farthest, last]);
        }
    }

    return keep.flatMap((kept, index) => (kept ? [points[index * 2], points[index * 2 + 1]] : []));
}
