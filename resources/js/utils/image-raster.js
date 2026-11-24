/**
 * The edit of `<x-wirekit::image-editor>` drawn into an image file: the crop of the original, with
 * every mark drawn on it.
 *
 * The original is decoded once, its crop copied into a canvas of the crop's size, and each mark
 * drawn on top with the canvas 2D API, in the image's pixels: first a light seam, wider than the
 * stroke, then the stroke in its color, so a mark reads on a dark image and on a light one. Nothing
 * is serialized as SVG on the way, so no data: image is made and nothing else leaves the browser.
 *
 * A browser keeps a canvas to an area: WebKit draws nothing at all, and reports no error, into one
 * above about 16.7 million pixels. A crop larger than the budget is drawn smaller by one factor on
 * both sides, and the marks with it.
 */
import { arrowHead, toPixels } from './image-marks.js';

/** The light seam under every stroke, as the editor draws it over the image too. */
export const SEAM_COLOR = 'rgba(255, 255, 255, 0.9)';

/** The largest canvas area drawn into, in pixels, unless the editor is given another. */
export const DEFAULT_AREA_BUDGET = 16_700_000;

/** How much the seam shows on each side of a stroke, in pixels. */
export function seamWidth(stroke) {
    return Math.max(2, Math.round(stroke / 2));
}

/**
 * The size of the canvas a crop is drawn into, and the scale that took it there: the crop's own
 * size, or below the budget by one factor on both sides.
 *
 * @param {{w: number, h: number}} crop - in pixels
 * @param {number} [areaBudget] - the largest area in pixels; no positive number is no limit
 * @returns {{width: number, height: number, scale: number}}
 */
export function canvasSize(crop, areaBudget = DEFAULT_AREA_BUDGET) {
    const area = crop.w * crop.h;
    const scale = Number.isFinite(areaBudget) && areaBudget > 0 && area > areaBudget ? Math.sqrt(areaBudget / area) : 1;

    return {
        width: Math.max(1, Math.floor(crop.w * scale)),
        height: Math.max(1, Math.floor(crop.h * scale)),
        scale,
    };
}

/**
 * A crop in percent as whole pixels of an image of a size, from edge to edge, at least one pixel on
 * each side.
 *
 * @param {{x: number, y: number, w: number, h: number}} crop
 * @param {{width: number, height: number}} size
 */
export function cropInPixels(crop, size) {
    const x = Math.round((crop.x / 100) * size.width);
    const y = Math.round((crop.y / 100) * size.height);

    return {
        x,
        y,
        w: Math.max(1, Math.round(((crop.x + crop.w) / 100) * size.width) - x),
        h: Math.max(1, Math.round(((crop.y + crop.h) / 100) * size.height) - y),
    };
}

/**
 * The edited image as a file's bytes: the crop of the original, with every mark drawn on it.
 *
 * The edit is in percent, as the editor's value is, and is drawn in the pixels of the image as it
 * decodes: a page that gives the editor another size than the file's own still gets the crop and
 * the marks where they were drawn.
 *
 * @param {Blob} source - the original image
 * @param {{crop: {x: number, y: number, w: number, h: number}, marks: object[]}} edit - in percent
 * @param {{areaBudget?: number, type?: string, quality?: number, decode?: Function, createCanvas?: Function}} [options]
 *   `type` and `quality` as a canvas encodes them; `decode` and `createCanvas` stand in for the
 *   browser's decoding and canvas.
 * @returns {Promise<Blob>}
 */
export async function rasterize(source, edit, options = {}) {
    const decode = options.decode ?? decodeImage;
    const createCanvas = options.createCanvas ?? canvasElement;
    const image = await decode(source);

    try {
        const natural = { width: image.naturalWidth || image.width, height: image.naturalHeight || image.height };

        if (! (natural.width > 0) || ! (natural.height > 0)) {
            throw new Error('WireKit image-editor: the image decoded to no size, so there is nothing to draw.');
        }

        const crop = cropInPixels(edit.crop, natural);
        const marks = edit.marks.map((mark) => toPixels(mark, natural));
        const size = canvasSize(crop, options.areaBudget ?? DEFAULT_AREA_BUDGET);
        const canvas = createCanvas(size.width, size.height);
        const context = canvas.getContext('2d');

        if (! context) {
            throw new Error('WireKit image-editor: the browser gave no 2D canvas to draw the edited image into.');
        }

        context.drawImage(image, crop.x, crop.y, crop.w, crop.h, 0, 0, size.width, size.height);

        // The marks are in the image's pixels. Scaled and moved by the crop, they land where they
        // were drawn, also when the canvas had to be smaller than the crop.
        context.save();
        context.scale(size.width / crop.w, size.height / crop.h);
        context.translate(-crop.x, -crop.y);

        for (const mark of marks) {
            drawMark(context, mark);
        }

        context.restore();

        return await encode(canvas, options.type ?? 'image/png', options.quality);
    } finally {
        // A decoded image holds its whole size in memory until it is closed, which a phone feels.
        image.close?.();
    }
}

/** One mark: the seam first, wider, then the stroke in its color, and an arrow's head on each. */
export function drawMark(context, mark) {
    context.lineCap = 'round';
    context.lineJoin = 'round';

    for (const [color, width] of [[SEAM_COLOR, mark.stroke + seamWidth(mark.stroke) * 2], [mark.color, mark.stroke]]) {
        context.strokeStyle = color;
        context.fillStyle = color;
        context.lineWidth = width;
        traceMark(context, mark);
        context.stroke();

        if (mark.type === 'arrow') {
            traceArrowHead(context, mark);
            context.fill();
            context.stroke();
        }
    }
}

function traceMark(context, mark) {
    context.beginPath();

    if (mark.type === 'arrow') {
        // The shaft ends where the head begins, so a thick stroke does not poke through the point.
        const head = arrowHead(mark.x1, mark.y1, mark.x2, mark.y2, mark.stroke);

        context.moveTo(mark.x1, mark.y1);
        context.lineTo((head.left.x + head.right.x) / 2, (head.left.y + head.right.y) / 2);
    } else if (mark.type === 'line') {
        context.moveTo(mark.x1, mark.y1);
        context.lineTo(mark.x2, mark.y2);
    } else if (mark.type === 'rect') {
        context.rect(mark.x, mark.y, mark.w, mark.h);
    } else if (mark.type === 'ellipse') {
        context.ellipse(mark.x + mark.w / 2, mark.y + mark.h / 2, mark.w / 2, mark.h / 2, 0, 0, Math.PI * 2);
    } else {
        traceFreehand(context, mark.points);
    }
}

function traceArrowHead(context, mark) {
    const head = arrowHead(mark.x1, mark.y1, mark.x2, mark.y2, mark.stroke);

    context.beginPath();
    context.moveTo(mark.x2, mark.y2);
    context.lineTo(head.left.x, head.left.y);
    context.lineTo(head.right.x, head.right.y);
    context.closePath();
}

/** A freehand line through its points, smoothed by curving through the midpoints between them. */
function traceFreehand(context, points) {
    context.moveTo(points[0], points[1]);

    for (let index = 2; index + 3 < points.length; index += 2) {
        context.quadraticCurveTo(points[index], points[index + 1], (points[index] + points[index + 2]) / 2, (points[index + 1] + points[index + 3]) / 2);
    }

    context.lineTo(points[points.length - 2], points[points.length - 1]);
}

/**
 * The image decoded for drawing. An image `createImageBitmap()` cannot decode, as an SVG file in
 * some engines, is decoded through an image element instead.
 */
async function decodeImage(source) {
    try {
        return await createImageBitmap(source);
    } catch (error) {
        const url = URL.createObjectURL(source);

        try {
            const image = new Image();

            image.src = url;
            await image.decode();

            return image;
        } catch {
            throw error;
        } finally {
            URL.revokeObjectURL(url);
        }
    }
}

function canvasElement(width, height) {
    const canvas = document.createElement('canvas');

    canvas.width = width;
    canvas.height = height;

    return canvas;
}

function encode(canvas, type, quality) {
    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
            if (blob) {
                resolve(blob);
            } else {
                reject(new Error('WireKit image-editor: the edited image could not be encoded.'));
            }
        }, type, quality);
    });
}
