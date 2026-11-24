/**
 * `<x-wirekit::image-editor>`: a crop over an image, moved and resized with the pointer on its frame
 * and its eight handles, with the arrow keys, or through four number fields, and held to a ratio
 * when one is given. With `tools` it also draws marks on the image, arrows, lines, ellipses,
 * rectangles and freehand lines, in a palette of colors and three widths, with undo and redo, and
 * draws the edit into an image file on request (rasterize()). The image itself stays as it is.
 *
 * The value is `{ x, y, w, h }` in percent of the image, kept to one decimal, the unit
 * `<x-wirekit::image-hotspots>` places its markers in, so a crop and a marker are positions on the
 * same image and convert without its size (cropPoint()). cropPixels() gives the rectangle in the
 * pixels of an image of a given size. With marks offered the value holds `marks` as well, in the
 * same unit (utils/image-marks.js).
 *
 * A ratio is a ratio of pixels, and a crop in percent keeps one only through the image's size: the
 * `width` and `height` the page gives, otherwise the image's natural size once it has loaded. The
 * smallest side, `min`, is in pixels as well. Until the size is known the crop shows and does not
 * move, and no mark is drawn.
 *
 * A drag moves the crop or a mark and writes the value once, when the pointer is let go, so a
 * `wire:model.live` sends one request for a drag and undo goes back over it in one step. A key
 * press, a field and a button write it at once.
 *
 * Lifecycle resources held on `this`, released in destroy():
 *   - _drag — the document listeners of a drag of the crop or of a mark in progress
 *   - _modelEvents — `change` and `blur` on the root, so `wire:model.live.change` and
 *     `.live.blur` send when the crop is committed and when the reader leaves (utils/model-events.js)
 *   - _stopServerValue — the observer on the crop the server renders on the root
 *   - _onLoad — the image's `load` listener, while the editor waits for its size
 * and in the factory's closure `history`, the steps undo walks back through.
 *
 * @param {Object} config
 * @param {number|null} [config.width] - The image's width in pixels, as the page gives it.
 * @param {number|null} [config.height] - The image's height in pixels.
 * @param {number|null} [config.ratio] - The crop's width over its height, in pixels; null for a free crop.
 * @param {number} [config.min] - The crop's smallest side, in pixels of the image.
 * @param {string} [config.valueText] - The crop in words, taking `:x`, `:y`, `:w` and `:h`.
 * @param {string} [config.locale] - The application's locale, for the numbers in those words.
 * @param {string[]} [config.tools] - The kinds of mark offered, from MARK_TYPES; none for a crop alone.
 * @param {Array<{color: string, label: string}>} [config.palette] - The colors a mark can take; the first one for a new mark.
 * @param {number} [config.history] - How many steps undo goes back.
 * @param {number} [config.maxArea] - The largest area the result is drawn at, in pixels.
 * @param {Object} [config.texts] - The sentences a mark is said with: `mark`, `added`, `removed`,
 *   `start`, `end`, `undone` and `redone`, and `kinds`, the name of each kind.
 */
import { focusClicked } from '../utils/focus-clicked.js';
import { createHistory } from '../utils/image-history.js';
import {
    arrowHeadPoints,
    defaultMark,
    DEFAULT_MARK_WIDTH,
    isLine,
    MARK_TYPES,
    MARK_WIDTHS,
    markAt,
    markBounds,
    markHandles,
    markPath,
    marksFrom,
    moveMark,
    normalizeMark,
    resizeMark,
    simplifyPoints,
    snapLineEnd,
    snapSquareCorner,
    toPercent,
    toPixels,
} from '../utils/image-marks.js';
import { rasterize as rasterizeImage, SEAM_COLOR, seamWidth } from '../utils/image-raster.js';
import { formatDecimal } from '../utils/locale-number.js';
import { watchModelEvents } from '../utils/model-events.js';
import { hasModelBinding, jsonValue, observeServerValue } from '../utils/server-value.js';

/** The handles of a crop, at its corners and the middles of its edges. */
export const CROP_HANDLES = Object.freeze(['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w']);

/** The whole image, the crop of an editor that is given none. */
export const WHOLE_IMAGE = Object.freeze({ x: 0, y: 0, w: 100, h: 100 });

/** The smallest side of a crop unless one is given, in pixels of the image. */
export const DEFAULT_MIN = 16;

const round = (value) => Math.round(value * 10) / 10;

const clamp = (value, low, high) => Math.min(Math.max(value, low), high);

const numberOr = (value, fallback) => {
    const number = Number(value);

    return value !== null && value !== '' && Number.isFinite(number) ? number : fallback;
};

/** A crop from its four edges, each kept to one decimal, so an edge that does not move stays put. */
function fromEdges(left, top, right, bottom) {
    const x = round(left);
    const y = round(top);

    return { x, y, w: round(round(right) - x), h: round(round(bottom) - y) };
}

/**
 * A crop as the editor holds it: four numbers in percent of the image. A value that is no object,
 * and a side that is no number, is the whole image there.
 *
 * @returns {{x: number, y: number, w: number, h: number}}
 */
export function cropFrom(value) {
    if (! value || typeof value !== 'object' || Array.isArray(value)) {
        return { ...WHOLE_IMAGE };
    }

    return { x: numberOr(value.x, 0), y: numberOr(value.y, 0), w: numberOr(value.w, 100), h: numberOr(value.h, 100) };
}

/**
 * A crop held to the image and to its smallest size, kept to one decimal: the size first, then the
 * place, so a crop at the image's edge keeps its size and moves in.
 *
 * @param {{x: number, y: number, w: number, h: number}} crop
 * @param {{w: number, h: number}} [min] - the smallest width and height, in percent
 */
export function clampCrop(crop, min = { w: 0, h: 0 }) {
    const w = round(clamp(crop.w, Math.min(min.w, 100), 100));
    const h = round(clamp(crop.h, Math.min(min.h, 100), 100));

    return { x: round(clamp(crop.x, 0, 100 - w)), y: round(clamp(crop.y, 0, 100 - h)), w, h };
}

/** Move a crop by a distance in percent, keeping its size; it stops at the image's edge. */
export function moveCrop(crop, dx, dy) {
    return {
        x: round(clamp(crop.x + dx, 0, 100 - crop.w)),
        y: round(clamp(crop.y + dy, 0, 100 - crop.h)),
        w: crop.w,
        h: crop.h,
    };
}

/**
 * Resize a crop from one handle by a distance in percent. The edges the handle names move, the
 * opposite ones stay, and every edge stops at the image's edge and at the smallest size.
 *
 * With a ratio, `k`, the crop's width over its height in percent of the image, the crop keeps it: a
 * corner takes the larger of the two sizes the pointer asks for, held at the opposite corner, and
 * an edge sizes its own axis while the other side follows around its middle. At the image's edge
 * both sides stop together.
 *
 * @param {{x: number, y: number, w: number, h: number}} crop
 * @param {string} handle - one of CROP_HANDLES
 * @param {number} dx
 * @param {number} dy
 * @param {{w: number, h: number}} [min] - the smallest width and height, in percent
 * @param {number|null} [k]
 */
export function resizeCrop(crop, handle, dx, dy, min = { w: 0, h: 0 }, k = null) {
    const west = handle.includes('w');
    const east = handle.includes('e');
    const north = handle.includes('n');
    const south = handle.includes('s');
    const right = crop.x + crop.w;
    const bottom = crop.y + crop.h;

    if (! (k > 0)) {
        const left = west ? clamp(crop.x + dx, 0, right - min.w) : crop.x;
        const top = north ? clamp(crop.y + dy, 0, bottom - min.h) : crop.y;
        const far = east ? clamp(right + dx, crop.x + min.w, 100) : right;
        const low = south ? clamp(bottom + dy, crop.y + min.h, 100) : bottom;

        return fromEdges(left, top, far, low);
    }

    // The smallest width the ratio and the smallest size allow together.
    const minW = Math.max(min.w, min.h * k);

    if ((west || east) && (north || south)) {
        const anchorX = west ? right : crop.x;
        const anchorY = north ? bottom : crop.y;
        const asked = Math.max(west ? crop.w - dx : crop.w + dx, (north ? crop.h - dy : crop.h + dy) * k);
        const room = Math.min(west ? anchorX : 100 - anchorX, (north ? anchorY : 100 - anchorY) * k);
        const w = clamp(asked, Math.min(minW, room), room);
        const h = w / k;
        const left = west ? anchorX - w : anchorX;
        const top = north ? anchorY - h : anchorY;

        return fromEdges(left, top, left + w, top + h);
    }

    if (west || east) {
        const anchorX = west ? right : crop.x;
        const middle = crop.y + crop.h / 2;
        const room = Math.min(west ? anchorX : 100 - anchorX, 100 * k);
        const w = clamp(west ? crop.w - dx : crop.w + dx, Math.min(minW, room), room);
        const h = w / k;
        const left = west ? anchorX - w : anchorX;
        const top = clamp(middle - h / 2, 0, 100 - h);

        return fromEdges(left, top, left + w, top + h);
    }

    const anchorY = north ? bottom : crop.y;
    const middle = crop.x + crop.w / 2;
    const room = Math.min(north ? anchorY : 100 - anchorY, 100 / k);
    const h = clamp(north ? crop.h - dy : crop.h + dy, Math.min(minW / k, room), room);
    const w = h * k;
    const top = north ? anchorY - h : anchorY;
    const left = clamp(middle - w / 2, 0, 100 - w);

    return fromEdges(left, top, left + w, top + h);
}

/**
 * A crop held to a ratio: one in another ratio keeps the middle of its box and its longer side
 * shrinks until it fits, so the whole image becomes the largest crop of the ratio, centered.
 *
 * @param {{x: number, y: number, w: number, h: number}} crop
 * @param {number|null} k - width over height, in percent of the image
 */
export function fitRatio(crop, k) {
    if (! (k > 0) || ! (crop.w > 0) || ! (crop.h > 0)) {
        return crop;
    }

    if (crop.w / crop.h > k) {
        const w = crop.h * k;
        const left = crop.x + (crop.w - w) / 2;

        return fromEdges(left, crop.y, left + w, crop.y + crop.h);
    }

    const h = crop.w / k;
    const top = crop.y + (crop.h - h) / 2;

    return fromEdges(crop.x, top, crop.x + crop.w, top + h);
}

/**
 * Where an arrow key moves or sizes a crop: by 1 % a press, a tenth of that with `fine`. With
 * `shift` it sizes the crop from its far edges, Right and Down growing it, Left and Up shrinking it.
 *
 * @returns {{x: number, y: number, w: number, h: number}|null} null for a key that does nothing
 */
export function nudgedCrop(crop, key, { shift = false, fine = false } = {}, min = { w: 0, h: 0 }, k = null) {
    const step = fine ? 0.1 : 1;
    const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };

    if (! Object.prototype.hasOwnProperty.call(moves, key)) {
        return null;
    }

    const [dx, dy] = moves[key];

    if (! shift) {
        return moveCrop(crop, dx, dy);
    }

    return dx !== 0 ? resizeCrop(crop, 'e', dx, 0, min, k) : resizeCrop(crop, 's', 0, dy, min, k);
}

/**
 * A point of the whole image as a point of the crop, both in percent, or null where it falls
 * outside the crop. The other keys of the point stay with it: a marker of `image-hotspots` keeps
 * its label and its target.
 */
export function cropPoint(crop, point) {
    if (! point || typeof point !== 'object' || ! (crop.w > 0) || ! (crop.h > 0)) {
        return null;
    }

    const x = ((Number(point.x) - crop.x) / crop.w) * 100;
    const y = ((Number(point.y) - crop.y) / crop.h) * 100;

    if (! Number.isFinite(x) || ! Number.isFinite(y) || x < 0 || x > 100 || y < 0 || y > 100) {
        return null;
    }

    return { ...point, x: round(x), y: round(y) };
}

/** A crop in the pixels of an image of a size, whole pixels from edge to edge. */
export function cropPixels(crop, width, height) {
    const x = Math.round((crop.x / 100) * width);
    const y = Math.round((crop.y / 100) * height);

    return {
        x,
        y,
        w: Math.round(((crop.x + crop.w) / 100) * width) - x,
        h: Math.round(((crop.y + crop.h) / 100) * height) - y,
    };
}

/** A size from a width and a height, or null where either is no positive number. */
function sizeOf(width, height) {
    const w = Number(width);
    const h = Number(height);

    return w > 0 && h > 0 ? { width: w, height: h } : null;
}

const fill = (template, values) => String(template ?? '').replace(/:(x|y|w|h)\b/g, (whole, name) => (name in values ? String(values[name]) : whole));

/** A sentence with its `:name` placeholders filled. */
const fillWords = (template, values) => String(template ?? '').replace(/:([a-z]+)\b/g, (whole, name) => (name in values ? String(values[name]) : whole));

const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

/** How far beside a stroke a press still finds the mark, in CSS pixels. */
const REACH = 8;

/** How far a pointer may travel and still be a tap, which places a mark of a default size, in CSS pixels. */
const TAP = 4;

const ARROW_KEYS = Object.freeze({ ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] });

const SVG = 'http://www.w3.org/2000/svg';

function svgNode(doc, name, attributes) {
    const node = doc.createElementNS(SVG, name);

    for (const [key, value] of Object.entries(attributes)) {
        node.setAttribute(key, String(value));
    }

    return node;
}

/**
 * A mark as SVG, in the image's pixels: a light seam under the stroke, then the stroke in its
 * color, and an arrow's head on each, the order the result is drawn in.
 */
function markNodes(doc, mark) {
    const nodes = [];

    for (const [color, width] of [[SEAM_COLOR, mark.stroke + seamWidth(mark.stroke) * 2], [mark.color, mark.stroke]]) {
        nodes.push(svgNode(doc, 'path', { d: markPath(mark), fill: 'none', stroke: color, 'stroke-width': width, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }));

        if (mark.type === 'arrow') {
            nodes.push(svgNode(doc, 'polygon', { points: arrowHeadPoints(mark), fill: color, stroke: color, 'stroke-width': width, 'stroke-linejoin': 'round' }));
        }
    }

    return nodes;
}

/** The file extension of an image type a canvas encodes to. */
function extensionOf(type) {
    return { 'image/jpeg': 'jpg', 'image/webp': 'webp' }[type] ?? 'png';
}

export default function wirekitImageEditor(config = {}) {
    // The root, kept while it is attached: the document listeners of a drag outlive the handler
    // that set them, and `$root` read there finds nothing.
    let rootEl = null;
    // The steps undo walks back through. A step is a copy of a state, so it lives outside the
    // component's reactive data, where nothing watches it.
    let history = null;

    const tools = (Array.isArray(config.tools) ? config.tools : []).filter((tool) => MARK_TYPES.includes(tool));
    const palette = (Array.isArray(config.palette) ? config.palette : []).filter((entry) => entry && typeof entry.color === 'string');
    const colors = palette.map((entry) => entry.color);
    const texts = config.texts && typeof config.texts === 'object' ? config.texts : {};

    return {
        crop: { ...WHOLE_IMAGE },
        // The crop while a drag moves it, written to `crop` when the drag ends.
        draft: null,
        // The image's size in pixels, once known.
        size: null,
        announcement: '',
        // What the pointer does on the image: `crop`, or draw one of the kinds of mark offered.
        tool: 'crop',
        // The color and the width the next mark is drawn in, and a selected mark takes on a change.
        color: colors[0] ?? null,
        markWidth: DEFAULT_MARK_WIDTH,
        // The index of the selected mark, or null.
        selected: null,
        // The mark a gesture draws, moves or sizes, written to the value when the gesture ends.
        markDraft: null,
        // The end of a selected line or arrow that Shift and the arrow keys move.
        activeEnd: 'end',
        canUndo: false,
        canRedo: false,
        _drag: null,
        _modelEvents: null,
        _stopServerValue: null,
        _onLoad: null,

        init() {
            const root = this.$root;

            rootEl = root ?? null;
            this.size = sizeOf(config.width, config.height);
            history = tools.length > 0 ? createHistory(config.history) : null;

            const seed = jsonValue(typeof root?.getAttribute === 'function' ? root.getAttribute('data-wk-server-value') : null);

            if (seed !== null) {
                this.crop = this._fromValue(seed);
            }

            if (this.size === null) {
                this._waitForImage();
            }

            this._modelEvents = watchModelEvents(root, () => root, { detail: () => this._valueNow() });

            this._stopServerValue = observeServerValue(root, (raw) => {
                // With a caller's model the crop arrives through the binding.
                if (hasModelBinding(root)) {
                    return;
                }

                // A value from the server starts afresh: undo does not step back past it.
                this.crop = this._fromValue(jsonValue(raw));
                this.selected = null;
                history?.clear();
                this._historyFlags();
            });
        },

        destroy() {
            this._endDrag();
            this.draft = null;
            this.markDraft = null;
            this._stopWaiting();
            this._modelEvents?.dispose();
            this._modelEvents = null;
            this._stopServerValue?.();
            this._stopServerValue = null;
            history = null;
            rootEl = null;
        },

        /** Whether the editor knows the image's size, which a drag, a key and a field need. */
        isReady() {
            return this.size !== null;
        },

        /** Whether the editor offers marks. Without any it is a crop alone. */
        marking() {
            return tools.length > 0;
        },

        /** Whether the pointer draws a mark on the image rather than moving the crop. */
        drawing() {
            return this.marking() && this.tool !== 'crop';
        },

        /** Whether the pointer moves and sizes the crop, which shows its handles. */
        cropping() {
            return ! this.drawing();
        },

        /** The crop as it shows: the one a drag is moving, else the value, held to the image. */
        shown() {
            return this.draft ?? this._settle(this.crop);
        },

        /** The marks of the value, each held to the image, in their order. */
        marks() {
            return marksFrom(this.crop?.marks, colors);
        },

        /** The marks as they show: with the one a gesture is drawing, moving or sizing. */
        shownMarks() {
            const marks = this.marks();
            const draft = this.markDraft;

            if (draft === null || draft.mark === null) {
                return marks;
            }

            return draft.index === null ? [...marks, draft.mark] : marks.map((mark, index) => (index === draft.index ? draft.mark : mark));
        },

        /** The crop's place on the image, as an object Alpine sets property by property. */
        regionStyle() {
            const crop = this.shown();

            return { left: `${crop.x}%`, top: `${crop.y}%`, width: `${crop.w}%`, height: `${crop.h}%` };
        },

        /** A handle's place: at a corner or the middle of an edge of the crop. */
        handleStyle(handle) {
            const crop = this.shown();
            const x = handle.includes('w') ? crop.x : handle.includes('e') ? crop.x + crop.w : crop.x + crop.w / 2;
            const y = handle.includes('n') ? crop.y : handle.includes('s') ? crop.y + crop.h : crop.y + crop.h / 2;

            return { left: `${x}%`, top: `${y}%` };
        },

        /** The shade outside the crop: the image's box with the crop cut out, in a 100 × 100 view box. */
        shadePath() {
            const { x, y, w, h } = this.shown();

            return `M0 0H100V100H0Z M${x} ${y}V${y + h}H${x + w}V${y}Z`;
        },

        /** The value of a number field: one side of the crop, in percent. */
        fieldValue(axis) {
            return this.shown()[axis];
        },

        /** The crop in words, for the region's description and the status region. */
        valueText() {
            const crop = this.shown();
            const number = (value) => formatDecimal(value, config.locale, 1);

            return fill(config.valueText ?? 'Crop: :x% across, :y% down, :w% wide, :h% high', {
                x: number(crop.x),
                y: number(crop.y),
                w: number(crop.w),
                h: number(crop.h),
            });
        },

        /**
         * The marks drawn into the layer over the image, in the image's pixels, and the outline of
         * the selected one. Run by `x-effect`, so it follows the value, a gesture and the selection.
         * The layer is built here rather than in the template: a `template` inside an `svg` holds
         * no content to repeat, and SVG attributes set from a script leave a strict content
         * security policy working.
         */
        renderMarks() {
            const layer = this.$refs.marks;
            const size = this.size;
            const marks = this.shownMarks();
            const selected = this.selected;

            if (! layer || typeof layer.replaceChildren !== 'function') {
                return;
            }

            if (size === null) {
                layer.replaceChildren();

                return;
            }

            layer.ownerSVGElement?.setAttribute('viewBox', `0 0 ${size.width} ${size.height}`);
            layer.replaceChildren(...marks.flatMap((mark) => markNodes(layer.ownerDocument, toPixels(mark, size))));
            this._placeSelection(selected === null ? null : marks[selected] ?? null);
        },

        /** One focus target per mark, over its box, named by its kind and its place. */
        markTargets() {
            return this.marks().map((mark, index) => {
                const box = markBounds(mark);

                return {
                    index,
                    label: this._markLabel(mark),
                    pressed: index === this.selected ? 'true' : 'false',
                    style: { left: `${box.x + box.w / 2}%`, top: `${box.y + box.h / 2}%`, width: `${box.w}%`, height: `${box.h}%` },
                };
            });
        },

        /** The handles of the selected mark, each at its place on the image. */
        markHandleList() {
            const mark = this.selected === null ? null : this.shownMarks()[this.selected] ?? null;

            if (mark === null) {
                return [];
            }

            // The cursor names the directions a handle sizes the mark in, as the crop's handles do;
            // the end of a line moves freely.
            const cursor = (name) => ({ nw: 'cursor-nwse-resize', se: 'cursor-nwse-resize', ne: 'cursor-nesw-resize', sw: 'cursor-nesw-resize', n: 'cursor-ns-resize', s: 'cursor-ns-resize', e: 'cursor-ew-resize', w: 'cursor-ew-resize' })[name] ?? 'cursor-move';

            return Object.entries(markHandles(mark)).map(([name, at]) => ({ name, cursor: cursor(name), style: { left: `${at.x}%`, top: `${at.y}%` } }));
        },

        /** `aria-pressed` of a tool's button. */
        toolPressed(name) {
            return this.tool === name ? 'true' : 'false';
        },

        /** `aria-pressed` of a color's button: the selected mark's color, else the color of the next mark. */
        colorPressed(index) {
            const mark = this.selected === null ? null : this.marks()[this.selected] ?? null;

            return palette[index]?.color === (mark?.color ?? this.color) ? 'true' : 'false';
        },

        /** `aria-pressed` of a width's button, read the same way as a color's. */
        widthPressed(name) {
            const mark = this.selected === null ? null : this.marks()[this.selected] ?? null;

            return MARK_WIDTHS[name] === (mark?.width ?? this.markWidth) ? 'true' : 'false';
        },

        undoDisabled() {
            return ! this.canUndo;
        },

        redoDisabled() {
            return ! this.canRedo;
        },

        removeDisabled() {
            return this.selected === null || ! this.marks()[this.selected];
        },

        /**
         * A press on the frame moves the crop, a press on a handle resizes it, and the crop is
         * written when the pointer is let go. Each move reads the image's box anew, since the page
         * can scroll or the image change its size during a drag, and measures the pointer against
         * the image as it stands then.
         */
        startDrag(handle, event) {
            const area = this.$refs.area;
            const doc = rootEl?.ownerDocument;

            if (event.button > 0 || ! this.isReady() || this.drawing() || ! area || typeof area.getBoundingClientRect !== 'function' || ! doc) {
                return;
            }

            this._endDrag();

            const at = (pointer) => {
                const box = area.getBoundingClientRect();

                return box.width > 0 && box.height > 0
                    ? { x: ((pointer.clientX - box.left) / box.width) * 100, y: ((pointer.clientY - box.top) / box.height) * 100 }
                    : null;
            };
            const start = at(event);
            const from = this.shown();
            const before = this._snapshot();

            if (start === null) {
                return;
            }

            const onMove = (e) => {
                const now = at(e);

                if (now === null) {
                    return;
                }

                const dx = now.x - start.x;
                const dy = now.y - start.y;

                this.draft = handle === 'move' ? moveCrop(from, dx, dy) : resizeCrop(from, handle, dx, dy, this._min(), this._k());
            };
            const onEnd = (e) => {
                const crop = this.draft;

                this._endDrag();
                this.draft = null;

                if (e.type === 'pointercancel' || crop === null || this._same(crop, from)) {
                    return;
                }

                this._commit(crop, this.marks(), before);
            };

            // Passive: a move only reads where the pointer is. The frame and the handles are
            // `touch-none`, so a drag on a touch screen moves the crop and not the page.
            doc.addEventListener('pointermove', onMove, { passive: true });
            doc.addEventListener('pointerup', onEnd);
            doc.addEventListener('pointercancel', onEnd);
            this._drag = { doc, onMove, onEnd };
        },

        /**
         * A press on the image with a mark's tool: on a mark's stroke it selects the mark and a drag
         * moves it; elsewhere a drag draws a new mark, and a tap places one of a default size, so a
         * mark needs no dragging movement. Shift holds a line to steps of 45° and a box square. The
         * mark is written when the pointer is let go, one step of undo for the whole gesture.
         */
        startMark(event) {
            const gesture = this._pressed(event);

            if (gesture === null) {
                return;
            }

            const marks = this.marks();
            const { start } = gesture;
            const point = { x: (start.x / 100) * this.size.width, y: (start.y / 100) * this.size.height };
            const hit = markAt(marks.map((mark) => toPixels(mark, this.size)), point, REACH * (this.size.width / start.boxWidth));

            if (hit !== null) {
                this.select(hit);
                this._follow({ ...gesture, kind: 'move', index: hit, from: marks[hit] });

                return;
            }

            this.selected = null;
            this._follow({ ...gesture, kind: 'draw', type: this.tool, points: [start.x, start.y] });
        },

        /** A press on a handle of the selected mark sizes it from there. */
        startMarkResize(handle, event) {
            const mark = this.selected === null ? null : this.marks()[this.selected] ?? null;
            const gesture = mark === null ? null : this._pressed(event, true);

            if (gesture !== null) {
                this._follow({ ...gesture, kind: 'resize', index: this.selected, from: mark, handle });
            }
        },

        /** The arrow keys move the crop, with Shift they size it, and Alt makes the step a tenth. */
        onKey(event) {
            if (! this.isReady()) {
                return;
            }

            const crop = nudgedCrop(this.shown(), event.key, { shift: event.shiftKey, fine: event.altKey }, this._min(), this._k());

            if (crop === null) {
                return;
            }

            event.preventDefault();
            this._commit(crop, this.marks(), this._snapshot());
        },

        /**
         * A key on a mark's focus target: the arrows move it by 1 % and with Alt by a tenth of
         * that, with Shift they size it from its far corner, a line from its active end, which
         * Space switches. Delete and Backspace remove it.
         */
        onMarkKey(index, event) {
            const marks = this.marks();
            const mark = marks[index];

            if (! mark || ! this.isReady()) {
                return;
            }

            if (event.key === 'Delete' || event.key === 'Backspace') {
                event.preventDefault();
                this.removeMark(index);

                return;
            }

            if (event.key === ' ' && isLine(mark)) {
                event.preventDefault();
                this.activeEnd = this.activeEnd === 'end' ? 'start' : 'end';
                this._say(this.activeEnd === 'end' ? texts.end : texts.start);

                return;
            }

            const move = Object.prototype.hasOwnProperty.call(ARROW_KEYS, event.key) ? ARROW_KEYS[event.key] : null;

            if (move === null) {
                return;
            }

            event.preventDefault();

            const step = event.altKey ? 0.1 : 1;
            const changed = event.shiftKey
                ? resizeMark(mark, isLine(mark) ? this.activeEnd : 'se', move[0] * step, move[1] * step)
                : moveMark(mark, move[0] * step, move[1] * step);
            const kept = changed === null ? null : normalizeMark(changed, colors);

            if (kept !== null && ! same(kept, mark)) {
                this._writeMarks(marks.map((other, at) => (at === index ? kept : other)), this._snapshot(), index, 'moved');
            }
        },

        /**
         * Ctrl or Cmd with Z goes back a step, with Shift as well, or Ctrl with Y, forward. A
         * number field keeps the keys for its own text.
         */
        onEditorKey(event) {
            const target = event.target;

            if (! this.marking() || ! (event.ctrlKey || event.metaKey) || event.altKey) {
                return;
            }

            if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable)) {
                return;
            }

            const key = String(event.key).toLowerCase();

            if (key === 'z') {
                event.preventDefault();

                if (event.shiftKey) {
                    this.redo();
                } else {
                    this.undo();
                }
            } else if (key === 'y') {
                event.preventDefault();
                this.redo();
            }
        },

        /**
         * A press in the toolbar: the button it landed on takes the focus where the engine did
         * not, so Ctrl+Z, Ctrl+Y and Enter on a tool reach the editor after a click in WebKit
         * as they do after one in Blink (utils/focus-clicked.js).
         *
         * @param {MouseEvent} event
         */
        focusPressed(event) {
            const button = event?.target?.closest?.('button');

            if (button && event.currentTarget?.contains?.(button)) {
                focusClicked(button);
            }
        },

        /** A focus target took the focus: its mark is the selected one. */
        select(index) {
            if (this.selected !== index) {
                this.selected = index;
                this.activeEnd = 'end';
            }
        },

        /**
         * A tool's button. Pressed with a pointer it picks what the pointer draws. Pressed from the
         * keyboard it also places a mark of its kind in the middle of the crop, selects it and
         * moves the focus to it, so marking needs no pointer. A freehand line has no shape of its
         * own and is only drawn.
         */
        pickTool(name, event = null) {
            if (name !== 'crop' && ! tools.includes(name)) {
                return;
            }

            this.tool = name;

            if (name === 'crop' || ! event || event.detail !== 0 || ! this.isReady()) {
                return;
            }

            const crop = this._settle(this.crop);
            const middle = { x: ((crop.x + crop.w / 2) / 100) * this.size.width, y: ((crop.y + crop.h / 2) / 100) * this.size.height };
            const made = defaultMark(name, this.size, middle, { color: this.color, width: this.markWidth });
            const mark = made === null ? null : normalizeMark(toPercent(made, this.size), colors);

            if (mark === null) {
                return;
            }

            const marks = this.marks();

            this._writeMarks([...marks, mark], this._snapshot(), marks.length, 'added');
            this._focusMark(marks.length);
        },

        /** A color of the palette: for the next mark, and for the selected one. */
        pickColor(index) {
            const entry = palette[index];

            if (! entry) {
                return;
            }

            this.color = entry.color;
            this._restyle({ color: entry.color });
        },

        /** A width, `thin`, `medium` or `thick`: for the next mark, and for the selected one. */
        pickWidth(name) {
            if (! Object.prototype.hasOwnProperty.call(MARK_WIDTHS, name)) {
                return;
            }

            this.markWidth = MARK_WIDTHS[name];
            this._restyle({ width: MARK_WIDTHS[name] });
        },

        /** Remove a mark, the selected one unless another is named, and move the focus on. */
        removeMark(index = this.selected) {
            const marks = this.marks();
            const removed = index === null ? null : marks[index] ?? null;

            if (removed === null) {
                return;
            }

            const next = marks.filter((mark, at) => at !== index);
            const before = this._snapshot();

            this.selected = null;
            this._commit(this._settle(this.crop), next, before, fillWords(texts.removed ?? ':mark removed.', { mark: this._kind(removed.type) }));

            // The focus goes to the mark that took its place, else the one before it, else the crop.
            const focus = index < next.length ? index : next.length - 1;

            if (focus >= 0) {
                this._focusMark(focus);
            } else {
                this.$nextTick?.(() => this.$refs.cropRegion?.focus?.());
            }
        },

        undo() {
            this._step('undo');
        },

        redo() {
            this._step('redo');
        },

        /** A number field: one side of the crop in percent. With a ratio, the other side follows. */
        setField(axis, value, field = null) {
            const number = Number(value);

            if (! ['x', 'y', 'w', 'h'].includes(axis)) {
                return;
            }

            // A field left empty, holding no number, or written before the image's size is known
            // changes nothing, and then shows the crop's value again: the crop did not change, so
            // its binding does not run, and the field would keep what was typed.
            if (! this.isReady() || value === '' || ! Number.isFinite(number)) {
                if (field && typeof field === 'object' && 'value' in field) {
                    field.value = String(this.fieldValue(axis));
                }

                return;
            }

            const crop = { ...this.shown(), [axis]: number };
            const k = this._k();

            if (k && axis === 'w') {
                crop.h = crop.w / k;
            } else if (k && axis === 'h') {
                crop.w = crop.h * k;
            }

            this._commit(this._settle(crop), this.marks(), this._snapshot());
        },

        /** Back to the whole image, or with a ratio to the largest crop of it, centered. The marks stay. */
        reset() {
            if (! this.isReady()) {
                return;
            }

            this._commit(this._settle(null), this.marks(), this._snapshot());
        },

        /**
         * The edited image as a file: the crop of the image with every mark drawn on it, a PNG
         * unless `type` asks for `image/jpeg` or `image/webp`, with `quality` between 0 and 1 for
         * those. It is also dispatched as `wirekit:image-editor-result` with the crop and the
         * marks. An image that cannot be read, such as one from another origin that does not allow
         * it, resolves null and dispatches `wirekit:image-editor-error` with the error.
         *
         * @param {{type?: string, quality?: number}} [options]
         * @returns {Promise<File|null>}
         */
        async rasterize(options = {}) {
            const image = this.$refs.image;
            const crop = this._settle(this.crop);
            const marks = this.marks();

            try {
                const src = image?.currentSrc || image?.src;

                if (! src) {
                    throw new Error('WireKit image-editor: the editor has no image to draw.');
                }

                const response = await fetch(src, { credentials: 'same-origin' });

                if (! response.ok) {
                    throw new Error(`WireKit image-editor: the image could not be read, the server answered ${response.status}.`);
                }

                const blob = await rasterizeImage(await response.blob(), { crop, marks }, {
                    areaBudget: Number(config.maxArea) > 0 ? Number(config.maxArea) : undefined,
                    type: options?.type,
                    quality: options?.quality,
                });
                const file = new File([blob], `image.${extensionOf(blob.type)}`, { type: blob.type });

                rootEl?.dispatchEvent(new CustomEvent('wirekit:image-editor-result', { detail: { file, crop, marks }, bubbles: true }));

                return file;
            } catch (error) {
                rootEl?.dispatchEvent(new CustomEvent('wirekit:image-editor-error', { detail: { error }, bubbles: true }));

                return null;
            }
        },

        /** Width over height in percent of the image, from the ratio of pixels; null for a free crop. */
        _k() {
            const ratio = Number(config.ratio);

            return ratio > 0 && this.size ? (ratio * this.size.height) / this.size.width : null;
        },

        /** The smallest width and height in percent, from the smallest side in pixels. */
        _min() {
            if (! this.size) {
                return { w: 0, h: 0 };
            }

            const side = Math.min(numberOr(config.min, DEFAULT_MIN), this.size.width, this.size.height);

            return { w: (side / this.size.width) * 100, h: (side / this.size.height) * 100 };
        },

        /** A value held to the image, its smallest size and its ratio, as the editor shows it. */
        _settle(value) {
            const min = this._min();

            return clampCrop(fitRatio(clampCrop(cropFrom(value), min), this._k()), min);
        },

        _same(a, b) {
            return a.x === b.x && a.y === b.y && a.w === b.w && a.h === b.h;
        },

        /** A value as the editor keeps it: the crop, and with marks offered the marks with it. */
        _fromValue(value) {
            const crop = cropFrom(value);

            return this.marking() ? { ...crop, marks: marksFrom(value?.marks, colors) } : crop;
        },

        /** The value as it stands, in the shape the binding receives. */
        _valueNow() {
            return this.marking() ? { ...this.shown(), marks: this.marks() } : { ...this.shown() };
        },

        /** The state undo returns to: the crop and the marks. */
        _snapshot() {
            return { crop: this._settle(this.crop), marks: this.marks() };
        },

        /**
         * Write a crop and the marks to the value, record the state before for undo, and tell the
         * reader and the page. Without marks offered the value is the crop alone, as it always was.
         */
        _commit(crop, marks, before = null, sentence = null) {
            if (this.marking()) {
                // A change that changed nothing, as an arrow key at the image's edge, is no step:
                // undo would go back over it and show no change.
                if (before !== null && history !== null && ! same(before, { crop: this._settle(crop), marks })) {
                    history.record(before);
                }

                this.crop = { ...crop, marks };
                this._historyFlags();
            } else {
                this.crop = crop;
            }

            this._changed(sentence);
        },

        /** Write the marks, select one of them, and say what happened to it. */
        _writeMarks(marks, before, index, change) {
            this.selected = index !== null && index < marks.length ? index : null;

            const mark = this.selected === null ? null : marks[this.selected];
            const sentence = mark === null ? null : change === 'added'
                ? `${fillWords(texts.added ?? ':mark added.', { mark: this._kind(mark.type) })} ${this._markLabel(mark)}`
                : this._markLabel(mark);

            this._commit(this._settle(this.crop), marks, before, sentence);
        },

        /** Change the style of the selected mark, one step of undo. */
        _restyle(patch) {
            const marks = this.marks();
            const index = this.selected;
            const mark = index === null ? null : marks[index] ?? null;
            const restyled = mark === null ? null : normalizeMark({ ...mark, ...patch }, colors);

            if (restyled !== null && ! same(restyled, mark)) {
                this._writeMarks(marks.map((other, at) => (at === index ? restyled : other)), this._snapshot(), index, 'moved');
            }
        },

        /** Undo or redo one step, the crop and the marks together. The selection is let go. */
        _step(direction) {
            if (history === null || ! this.isReady()) {
                return;
            }

            const state = direction === 'undo' ? history.undo(this._snapshot()) : history.redo(this._snapshot());

            if (state === null) {
                return;
            }

            this.selected = null;
            this.markDraft = null;
            this.crop = { ...cropFrom(state.crop), marks: marksFrom(state.marks, colors) };
            this._historyFlags();
            this._changed(direction === 'undo' ? (texts.undone ?? 'Undone.') : (texts.redone ?? 'Redone.'));
        },

        _historyFlags() {
            this.canUndo = history?.canUndo() ?? false;
            this.canRedo = history?.canRedo() ?? false;
        },

        /** A mark's kind in words. */
        _kind(type) {
            return texts.kinds?.[type] ?? type;
        },

        /** A mark in words: its kind and where its box starts. */
        _markLabel(mark) {
            const box = markBounds(mark);
            const number = (value) => formatDecimal(value, config.locale, 1);

            return fillWords(texts.mark ?? ':mark, :x% across, :y% down', { mark: this._kind(mark.type), x: number(box.x), y: number(box.y) });
        },

        /** Move the focus to a mark's target once it is rendered. */
        _focusMark(index) {
            this.$nextTick?.(() => {
                rootEl?.querySelector?.(`[data-wk-mark-target="${index}"]`)?.focus?.();
            });
        },

        /**
         * The start of a gesture on the image: the point in percent of the image and the image's
         * width on the screen, which turns a length on the screen into one on the image. Null for a
         * press the editor leaves alone.
         */
        _pressed(event, anyTool = false) {
            const area = this.$refs.area;

            if (event.button > 0 || ! this.isReady() || ! this.marking() || (! anyTool && ! this.drawing()) || ! area || typeof area.getBoundingClientRect !== 'function' || ! rootEl?.ownerDocument) {
                return null;
            }

            const box = area.getBoundingClientRect();

            if (! (box.width > 0) || ! (box.height > 0)) {
                return null;
            }

            this._endDrag();
            event.preventDefault?.();

            return {
                start: {
                    x: clamp(((event.clientX - box.left) / box.width) * 100, 0, 100),
                    y: clamp(((event.clientY - box.top) / box.height) * 100, 0, 100),
                    boxWidth: box.width,
                    boxHeight: box.height,
                },
                travel: 0,
                before: this._snapshot(),
            };
        },

        /** Follow a gesture on a mark until the pointer is let go, then write it. */
        _follow(gesture) {
            const area = this.$refs.area;
            const doc = rootEl.ownerDocument;
            const { start } = gesture;
            const at = (pointer) => {
                const box = area.getBoundingClientRect();

                return box.width > 0 && box.height > 0
                    ? { x: clamp(((pointer.clientX - box.left) / box.width) * 100, 0, 100), y: clamp(((pointer.clientY - box.top) / box.height) * 100, 0, 100) }
                    : null;
            };

            const onMove = (e) => {
                const now = at(e);

                if (now === null) {
                    return;
                }

                const dx = now.x - start.x;
                const dy = now.y - start.y;

                gesture.travel = Math.max(gesture.travel, Math.hypot((dx / 100) * start.boxWidth, (dy / 100) * start.boxHeight));

                if (gesture.kind === 'move') {
                    this.markDraft = { index: gesture.index, mark: moveMark(gesture.from, dx, dy) };
                } else if (gesture.kind === 'resize') {
                    const sized = resizeMark(gesture.from, gesture.handle, dx, dy);

                    if (sized !== null) {
                        this.markDraft = { index: gesture.index, mark: sized };
                    }
                } else {
                    this.markDraft = { index: null, mark: this._drawn(gesture, now, e.shiftKey) };
                }
            };
            const onEnd = (e) => {
                const draft = this.markDraft;

                this._endDrag();
                this.markDraft = null;

                if (e.type !== 'pointercancel') {
                    this._ended(gesture, draft);
                }
            };

            doc.addEventListener('pointermove', onMove, { passive: true });
            doc.addEventListener('pointerup', onEnd);
            doc.addEventListener('pointercancel', onEnd);
            this._drag = { doc, onMove, onEnd };
        },

        /** The mark a drawing gesture has drawn so far, in percent, held with Shift. */
        _drawn(gesture, now, held) {
            const { start, type } = gesture;
            const style = { type, color: this.color, width: this.markWidth };

            if (type === 'freehand') {
                gesture.points.push(now.x, now.y);

                return { ...style, points: [...gesture.points] };
            }

            let end = now;

            if (held) {
                const across = this.size.width / 100;
                const down = this.size.height / 100;
                const line = type === 'arrow' || type === 'line';
                const snapped = line
                    ? snapLineEnd(start.x * across, start.y * down, now.x * across, now.y * down)
                    : snapSquareCorner(start.x * across, start.y * down, now.x * across, now.y * down);

                end = { x: clamp(snapped.x / across, 0, 100), y: clamp(snapped.y / down, 0, 100) };
            }

            if (type === 'arrow' || type === 'line') {
                return { ...style, x1: start.x, y1: start.y, x2: end.x, y2: end.y };
            }

            return { ...style, x: Math.min(start.x, end.x), y: Math.min(start.y, end.y), w: Math.abs(end.x - start.x), h: Math.abs(end.y - start.y) };
        },

        /** Write what a gesture on a mark did: one step of undo, and the reader told. */
        _ended(gesture, draft) {
            const marks = this.marks();

            if (gesture.kind !== 'draw') {
                const kept = draft === null ? null : normalizeMark(draft.mark, colors);

                if (kept !== null && ! same(kept, marks[gesture.index])) {
                    this._writeMarks(marks.map((mark, at) => (at === gesture.index ? kept : mark)), gesture.before, gesture.index, 'moved');
                }

                return;
            }

            let mark = draft?.mark ?? null;

            if (gesture.travel < TAP || mark === null) {
                const at = { x: (gesture.start.x / 100) * this.size.width, y: (gesture.start.y / 100) * this.size.height };
                const made = defaultMark(gesture.type, this.size, at, { color: this.color, width: this.markWidth });

                mark = made === null ? null : toPercent(made, this.size);
            } else if (mark.type === 'freehand') {
                // Simplified in the image's pixels, to within one pixel of the screen.
                const across = this.size.width / 100;
                const down = this.size.height / 100;
                const pixels = mark.points.map((value, index) => value * (index % 2 === 0 ? across : down));
                const kept = simplifyPoints(pixels, this.size.width / gesture.start.boxWidth);

                mark = { ...mark, points: kept.map((value, index) => value / (index % 2 === 0 ? across : down)) };
            }

            const kept = mark === null ? null : normalizeMark(mark, colors);

            if (kept !== null) {
                this._writeMarks([...marks, kept], gesture.before, marks.length, 'added');
            }
        },

        /** The outline of the selected mark: a light edge and a dark one, a little outside its stroke. */
        _placeSelection(mark) {
            const outlines = [this.$refs.selectionLight, this.$refs.selectionDark].filter((node) => node && typeof node.setAttribute === 'function');

            if (mark === null || this.size === null) {
                outlines.forEach((node) => node.setAttribute('visibility', 'hidden'));

                return;
            }

            const pixels = toPixels(mark, this.size);
            const box = markBounds(pixels);
            const screen = this.$refs.area?.getBoundingClientRect?.()?.width ?? 0;
            const margin = pixels.stroke / 2 + (screen > 0 ? (4 * this.size.width) / screen : 0);

            outlines.forEach((node) => {
                node.setAttribute('x', String(box.x - margin));
                node.setAttribute('y', String(box.y - margin));
                node.setAttribute('width', String(box.w + margin * 2));
                node.setAttribute('height', String(box.h + margin * 2));
                node.setAttribute('visibility', 'visible');
            });
        },

        /** Say the crop or what happened, commit it to a binding, and tell the page. */
        _changed(sentence = null) {
            this._say(sentence);
            this._modelEvents?.commit();
            rootEl?.dispatchEvent(new CustomEvent('wirekit:image-editor-change', {
                detail: this.marking() ? { crop: this._settle(this.crop), marks: this.marks() } : { crop: { ...this.crop } },
                bubbles: true,
            }));
        },

        /** Emptied first, so the same sentence twice in a row is read twice. Without one, the crop. */
        _say(sentence = null) {
            this.announcement = '';
            queueMicrotask(() => {
                this.announcement = sentence ?? this.valueText();
            });
        },

        /** The size from the image itself, once it has loaded. */
        _waitForImage() {
            const image = this.$refs.image;

            if (! image) {
                return;
            }

            const take = () => {
                const size = sizeOf(image.naturalWidth, image.naturalHeight);

                if (size !== null) {
                    this.size = size;
                    this._stopWaiting();
                }
            };

            if (image.complete) {
                take();
            }

            if (this.size === null) {
                this._onLoad = { image, take };
                image.addEventListener('load', take);
            }
        },

        _stopWaiting() {
            if (this._onLoad) {
                this._onLoad.image.removeEventListener('load', this._onLoad.take);
                this._onLoad = null;
            }
        },

        _endDrag() {
            const drag = this._drag;

            if (! drag) {
                return;
            }

            drag.doc.removeEventListener('pointermove', drag.onMove);
            drag.doc.removeEventListener('pointerup', drag.onEnd);
            drag.doc.removeEventListener('pointercancel', drag.onEnd);
            this._drag = null;
        },
    };
}
