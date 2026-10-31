/**
 * `<x-wirekit::image-editor>`: a crop over an image, moved and resized with the pointer on its frame
 * and its eight handles, with the arrow keys, or through four number fields, and held to a ratio
 * when one is given. The image itself stays as it is: the value is the crop.
 *
 * The value is `{ x, y, w, h }` in percent of the image, kept to one decimal, the unit
 * `<x-wirekit::image-hotspots>` places its markers in, so a crop and a marker are positions on the
 * same image and convert without its size (cropPoint()). cropPixels() gives the rectangle in the
 * pixels of an image of a given size.
 *
 * A ratio is a ratio of pixels, and a crop in percent keeps one only through the image's size: the
 * `width` and `height` the page gives, otherwise the image's natural size once it has loaded. The
 * smallest side, `min`, is in pixels as well. Until the size is known the crop shows and does not
 * move.
 *
 * A drag moves the crop and writes the value once, when the pointer is let go, so a
 * `wire:model.live` sends one request for a drag. A key press and a field write it at once.
 *
 * Lifecycle resources held on `this`, released in destroy():
 *   - _drag — the document listeners of a drag in progress
 *   - _modelEvents — `change` and `blur` on the root, so `wire:model.live.change` and
 *     `.live.blur` send when the crop is committed and when the reader leaves (utils/model-events.js)
 *   - _stopServerValue — the observer on the crop the server renders on the root
 *   - _onLoad — the image's `load` listener, while the editor waits for its size
 *
 * @param {Object} config
 * @param {number|null} [config.width] - The image's width in pixels, as the page gives it.
 * @param {number|null} [config.height] - The image's height in pixels.
 * @param {number|null} [config.ratio] - The crop's width over its height, in pixels; null for a free crop.
 * @param {number} [config.min] - The crop's smallest side, in pixels of the image.
 * @param {string} [config.valueText] - The crop in words, taking `:x`, `:y`, `:w` and `:h`.
 * @param {string} [config.locale] - The application's locale, for the numbers in those words.
 */
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

export default function wirekitImageEditor(config = {}) {
    // The root, kept while it is attached: the document listeners of a drag outlive the handler
    // that set them, and `$root` read there finds nothing.
    let rootEl = null;

    return {
        crop: { ...WHOLE_IMAGE },
        // The crop while a drag moves it, written to `crop` when the drag ends.
        draft: null,
        // The image's size in pixels, once known.
        size: null,
        announcement: '',
        _drag: null,
        _modelEvents: null,
        _stopServerValue: null,
        _onLoad: null,

        init() {
            const root = this.$root;

            rootEl = root ?? null;
            this.size = sizeOf(config.width, config.height);

            const seed = jsonValue(typeof root?.getAttribute === 'function' ? root.getAttribute('data-wk-server-value') : null);

            if (seed !== null) {
                this.crop = cropFrom(seed);
            }

            if (this.size === null) {
                this._waitForImage();
            }

            this._modelEvents = watchModelEvents(root, () => root, { detail: () => ({ ...this.shown() }) });

            this._stopServerValue = observeServerValue(root, (raw) => {
                // With a caller's model the crop arrives through the binding.
                if (hasModelBinding(root)) {
                    return;
                }

                this.crop = cropFrom(jsonValue(raw));
            });
        },

        destroy() {
            this._endDrag();
            this.draft = null;
            this._stopWaiting();
            this._modelEvents?.dispose();
            this._modelEvents = null;
            this._stopServerValue?.();
            this._stopServerValue = null;
            rootEl = null;
        },

        /** Whether the editor knows the image's size, which a drag, a key and a field need. */
        isReady() {
            return this.size !== null;
        },

        /** The crop as it shows: the one a drag is moving, else the value, held to the image. */
        shown() {
            return this.draft ?? this._settle(this.crop);
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
         * A press on the frame moves the crop, a press on a handle resizes it, and the crop is
         * written when the pointer is let go. Each move reads the image's box anew, since the page
         * can scroll or the image change its size during a drag, and measures the pointer against
         * the image as it stands then.
         */
        startDrag(handle, event) {
            const area = this.$refs.area;
            const doc = rootEl?.ownerDocument;

            if (event.button > 0 || ! this.isReady() || ! area || typeof area.getBoundingClientRect !== 'function' || ! doc) {
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

                this.crop = crop;
                this._changed();
            };

            // Passive: a move only reads where the pointer is. The frame and the handles are
            // `touch-none`, so a drag on a touch screen moves the crop and not the page.
            doc.addEventListener('pointermove', onMove, { passive: true });
            doc.addEventListener('pointerup', onEnd);
            doc.addEventListener('pointercancel', onEnd);
            this._drag = { doc, onMove, onEnd };
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
            this.crop = crop;
            this._changed();
        },

        /**
         * A number field: one side of the crop in percent. With a ratio, the other side follows.
         *
         * A field left empty, holding no number, or written before the image's size is known changes
         * nothing, and then shows the crop's value again: the crop did not change, so its binding
         * does not run, and the field would keep what was typed.
         */
        setField(axis, value, field = null) {
            const number = Number(value);

            if (! ['x', 'y', 'w', 'h'].includes(axis)) {
                return;
            }

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

            this.crop = this._settle(crop);
            this._changed();
        },

        /** Back to the whole image, or with a ratio to the largest crop of it, centered. */
        reset() {
            if (! this.isReady()) {
                return;
            }

            this.crop = this._settle(null);
            this._changed();
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

        /** Say the crop, commit it to a binding, and tell the page. */
        _changed() {
            this._say();
            this._modelEvents?.commit();
            rootEl?.dispatchEvent(new CustomEvent('wirekit:image-editor-change', {
                detail: { crop: { ...this.crop } },
                bubbles: true,
            }));
        },

        /** Emptied first, so the same sentence twice in a row is read twice. */
        _say() {
            this.announcement = '';
            queueMicrotask(() => {
                this.announcement = this.valueText();
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
