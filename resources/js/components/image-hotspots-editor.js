/**
 * `<x-wirekit::image-hotspots-editor>`: points on an image, set with a click or the Add button,
 * moved by dragging, by the arrow keys or by number fields, and ordered or deleted in the list
 * beside the image.
 *
 * The value is the list `<x-wirekit::image-hotspots>` draws: each point an object with `x` and `y`
 * in percent of the image, `phoneX` and `phoneY` on the phone's file, and whatever else the page
 * keeps on it, a label or a target, which the editor carries along unchanged. `variant` says which
 * of the two images, and so which pair, the reader edits. A point set on the desktop has no pair
 * of its own on the phone until the reader moves it there, and stands where the desktop puts it.
 *
 * Dragging is never the only way (WCAG 2.5.7), and the pointer is never the only way to set a
 * point (WCAG 2.1.1): the Add button sets one in the middle of the image, a selected point moves
 * with the arrow keys, by 1 % a press and by 10 % with Shift, Delete removes it, and every point
 * has an X and a Y field in the list. The order of the list is the order Tab reaches the markers
 * in the shop.
 *
 * A drag moves the marker and writes the point once, when it is let go, so a `wire:model.live`
 * sends one request for a drag rather than one for every frame of it.
 *
 * The focus stays with the reader through the changes that take its element away: a deleted
 * point hands it to the point that takes its place, a reordered row keeps it on the arrow that
 * moved it, and an empty list hands it to the Add button.
 *
 * Lifecycle resources held on `this`, released in destroy():
 *   - _drag — the document listeners of a drag in progress
 *   - _clickGuard — the timer that ends the click a drag leaves behind (see startDrag())
 *   - _modelEvents — `change` and `blur` on the root, so `wire:model.live.change` and
 *     `.live.blur` send when a point is committed and when the reader leaves (utils/model-events.js)
 *   - _stopServerValue — the observer on the points the server renders on the root
 *
 * @param {Object} config
 * @param {Object} [config.announcements] - Translated templates for the status region: `selected`
 *   (takes `:name`, `:x` and `:y`), `moved` (takes `:x` and `:y`), `added` (takes `:name`, `:x`
 *   and `:y`), `removed` (takes `:name`) and `position` (takes `:position` and `:total`).
 * @param {string} [config.pointName] - The name of a point without a label, taking `:number`.
 * @param {Object} [config.labels] - Translated names of a row's controls: `up` and `down` (take
 *   `:item`), `remove` (takes `:name`), `x` and `y` (take `:name`).
 * @param {string} [config.locale] - The application's locale, for the numbers the region reads.
 */
import { focusClicked } from '../utils/focus-clicked.js';
import { formatDecimal } from '../utils/locale-number.js';
import { watchModelEvents } from '../utils/model-events.js';
import { hasModelBinding, jsonValue, observeServerValue } from '../utils/server-value.js';

/** A percentage of the image, held between 0 and 100 and kept to one decimal. */
export function clampPercent(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const number = Number(value);

    if (! Number.isFinite(number)) {
        return null;
    }

    return Math.round(Math.max(0, Math.min(100, number)) * 10) / 10;
}

/**
 * Where a pointer stands in percent of a rectangle, held to its edges.
 *
 * @param {number} clientX
 * @param {number} clientY
 * @param {{ left: number, top: number, width: number, height: number }} rect
 * @returns {{ x: number, y: number }|null} null for a rectangle without a size
 */
export function percentAt(clientX, clientY, rect) {
    if (! rect || ! (rect.width > 0) || ! (rect.height > 0)) {
        return null;
    }

    return {
        x: clampPercent(((clientX - rect.left) / rect.width) * 100),
        y: clampPercent(((clientY - rect.top) / rect.height) * 100),
    };
}

/**
 * The position an arrow key moves a point to: 1 % a press, 10 % with Shift, held on the image.
 *
 * @param {{ x: number, y: number }} at
 * @param {string} key - the KeyboardEvent key
 * @param {boolean} shift
 * @returns {{ x: number, y: number }|null} null for a key that moves nothing
 */
export function nudged(at, key, shift) {
    const step = shift ? 10 : 1;
    const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };

    if (! Object.prototype.hasOwnProperty.call(moves, key)) {
        return null;
    }

    return { x: clampPercent(at.x + moves[key][0]), y: clampPercent(at.y + moves[key][1]) };
}

/** A point as the editor holds it: its keys as given, its positions as numbers. */
export function pointFrom(value) {
    const point = value && typeof value === 'object' && ! Array.isArray(value) ? { ...value } : {};

    point.x = clampPercent(point.x) ?? 50;
    point.y = clampPercent(point.y) ?? 50;

    for (const key of ['phoneX', 'phoneY']) {
        if (key in point) {
            const number = clampPercent(point[key]);

            if (number === null) {
                delete point[key];
            } else {
                point[key] = number;
            }
        }
    }

    return point;
}

const fill = (template, values) => String(template ?? '').replace(/:(number|total|position|item|name|x|y)\b/g, (whole, name) => (name in values ? String(values[name]) : whole));

/** How far a pointer travels before a press on a point is a drag rather than a click, in pixels. */
const DRAG_THRESHOLD = 3;

export default function wirekitImageHotspotsEditor(config = {}) {
    const announcements = config.announcements ?? {};
    const labels = config.labels ?? {};
    // The root, kept while it is attached: a focus move after a deletion runs from a handler whose
    // element the deletion took away, and `$root` read there finds nothing.
    let rootEl = null;

    return {
        // The points, which a binding may replace at any time: the markers and the rows are keyed by
        // their place in the list, so nothing else has to follow such a replacement.
        points: [],
        variant: 'desktop',
        selected: null,
        announcement: '',
        // The marker a drag moves, and where it stands, until the drag writes the point.
        dragging: null,
        _drag: null,
        _clickGuard: null,
        _modelEvents: null,
        _stopServerValue: null,

        init() {
            const root = this.$root;

            rootEl = root ?? null;

            const seed = jsonValue(typeof root?.getAttribute === 'function' ? root.getAttribute('data-wk-server-value') : null);

            if (Array.isArray(seed)) {
                this._replace(seed.map(pointFrom));
            }

            this._modelEvents = watchModelEvents(root, () => root, { detail: () => this.points.map((point) => ({ ...point })) });

            this._stopServerValue = observeServerValue(root, (raw) => {
                const value = jsonValue(raw);

                // With a caller's model the points arrive through the binding.
                if (! Array.isArray(value) || hasModelBinding(root)) {
                    return;
                }

                this._replace(value.map(pointFrom));
                this.selected = this.selected !== null && this.selected < this.points.length ? this.selected : null;
            });
        },

        destroy() {
            this._endDrag();
            this.dragging = null;
            clearTimeout(this._clickGuard);
            this._clickGuard = null;
            this._modelEvents?.dispose();
            this._modelEvents = null;
            this._stopServerValue?.();
            this._stopServerValue = null;
            rootEl = null;
        },

        /** Which image is edited: `desktop` or, where the editor has a phone file, `phone`. */
        show(variant) {
            this.variant = variant === 'phone' ? 'phone' : 'desktop';
        },

        isShown(variant) {
            return this.variant === variant;
        },

        /** X of the edited image, where a drag holds the marker while it moves. */
        xOf(point, index) {
            if (this.dragging && this.dragging.index === index) {
                return this.dragging.x;
            }

            return this.variant === 'phone' && typeof point?.phoneX === 'number' ? point.phoneX : point?.x;
        },

        yOf(point, index) {
            if (this.dragging && this.dragging.index === index) {
                return this.dragging.y;
            }

            return this.variant === 'phone' && typeof point?.phoneY === 'number' ? point.phoneY : point?.y;
        },

        /** The marker's place on the image, as an object Alpine sets property by property. */
        styleOf(point, index) {
            return { left: `${this.xOf(point, index)}%`, top: `${this.yOf(point, index)}%` };
        },

        nameOf(point, index) {
            const label = typeof point?.label === 'string' ? point.label.trim() : '';

            return label !== '' ? label : fill(config.pointName ?? 'Point :number', { number: index + 1 });
        },

        isSelected(index) {
            return this.selected === index;
        },

        isDragged(index) {
            return this.dragging !== null && this.dragging.index === index && this.dragging.moved;
        },

        /** The name of a control in the row of a point: its number field or one of its buttons. */
        labelFor(kind, point, index) {
            const name = this.nameOf(point, index);
            const fallback = { up: 'Move :item up', down: 'Move :item down', remove: 'Remove :name', x: ':name, X in percent', y: ':name, Y in percent' };

            return fill(labels[kind] ?? fallback[kind] ?? ':name', { item: name, name });
        },

        /** A click on the image, not on a point, sets a point there. */
        add(event) {
            if (this._clickGuard !== null || ! event || event.target?.closest?.('[data-wk-hotspot-point]')) {
                return;
            }

            const at = percentAt(event.clientX, event.clientY, this.$refs.area?.getBoundingClientRect());

            if (at) {
                this._insert(at);
            }
        },

        /** The Add button sets a point in the middle of the image, for the keyboard to move. */
        addAtCenter() {
            this._insert({ x: 50, y: 50 });
        },

        /**
         * A marker or the name in a row selects its point. The click a drag ends with lands on the
         * marker it moved, which the drag has selected and announced already, so it only takes the
         * focus for the arrow keys.
         */
        select(index, el = null) {
            if (index < 0 || index >= this.points.length) {
                return;
            }

            focusClicked(el);

            if (this._clickGuard !== null) {
                return;
            }

            this.selected = index;
            this._say('selected', index);
            this._announceSelection(index);
        },

        /** The arrow keys move the point, Delete and Backspace remove it. */
        onKey(index, event) {
            if (event.key === 'Delete' || event.key === 'Backspace') {
                event.preventDefault();
                this.remove(index, 'point');

                return;
            }

            const point = this.points[index];
            const to = point ? nudged({ x: this.xOf(point, index), y: this.yOf(point, index) }, event.key, event.shiftKey) : null;

            if (! to) {
                return;
            }

            event.preventDefault();
            this.selected = index;
            this._place(index, to.x, to.y);
            this._say('moved', index);
            this._commit();
        },

        /** A number field of the list: X or Y of the edited image. */
        setAxis(index, axis, value) {
            const point = this.points[index];
            const number = clampPercent(value);

            if (! point || number === null) {
                return;
            }

            this._place(index, axis === 'x' ? number : this.xOf(point, index), axis === 'y' ? number : this.yOf(point, index));
            this._commit();
        },

        moveUp(index) {
            this._swap(index, index - 1, 'up');
        },

        moveDown(index) {
            this._swap(index, index + 1, 'down');
        },

        /**
         * Delete a point. `from` says where the reader stood, `point` on the image or `list` in its
         * row, so the focus goes to the same kind of control that takes the deleted one's place.
         */
        remove(index, from = 'list') {
            if (index < 0 || index >= this.points.length) {
                return;
            }

            const name = this.nameOf(this.points[index], index);

            this._replace(this.points.filter((point, i) => i !== index));

            const next = this.points.length === 0 ? null : Math.min(index, this.points.length - 1);

            this.selected = next;
            this._speak(fill(announcements.removed ?? 'Removed :name', { name }));
            this._commit();
            this.$nextTick(() => {
                if (next === null) {
                    this._focus('[data-wk-hotspots-add]');
                } else if (from === 'point') {
                    this._focus(`[data-wk-hotspot-point="${next}"]`);
                } else {
                    this._focus(`[data-wk-hotspots-row="${next}"] [data-wk-row-control="remove"]`);
                }
            });
        },

        /**
         * A press on a point starts a drag that moves the marker with the pointer, and the point is
         * written when the pointer is let go.
         */
        startDrag(index, event) {
            if (event.button > 0 || ! this.points[index]) {
                return;
            }

            this._endDrag();

            const area = this.$refs.area;
            const doc = rootEl?.ownerDocument;

            if (! area || typeof area.getBoundingClientRect !== 'function' || ! doc) {
                return;
            }

            const point = this.points[index];
            const startX = event.clientX;
            const startY = event.clientY;

            this.dragging = { index, x: this.xOf(point, index), y: this.yOf(point, index), moved: false };

            const onMove = (e) => {
                const drag = this.dragging;

                if (! drag) {
                    return;
                }

                if (! drag.moved && Math.hypot(e.clientX - startX, e.clientY - startY) < DRAG_THRESHOLD) {
                    return;
                }

                // The box as it stands now: the page may have scrolled, or the image changed its
                // size, since the press.
                const at = percentAt(e.clientX, e.clientY, area.getBoundingClientRect());

                if (at) {
                    this.dragging = { index, x: at.x, y: at.y, moved: true };
                }
            };
            const onEnd = () => {
                const drag = this.dragging;

                this._endDrag();
                this.dragging = null;

                if (! drag || ! drag.moved) {
                    return;
                }

                this.selected = index;
                this._place(index, drag.x, drag.y);
                this._say('moved', index);
                this._commit();

                // The click that follows the release lands on the image wherever the pointer was let
                // go there, and would set a point. It is dispatched in the same task as the release,
                // so a timer ends the guard right after it, and a release with no click after it
                // keeps no guard standing.
                clearTimeout(this._clickGuard);
                this._clickGuard = setTimeout(() => {
                    this._clickGuard = null;
                }, 0);
            };

            // Passive: a move only reads where the pointer is. The marker is `touch-none`, so a
            // drag on a touch screen moves the point and not the page without a preventDefault().
            doc.addEventListener('pointermove', onMove, { passive: true });
            doc.addEventListener('pointerup', onEnd);
            doc.addEventListener('pointercancel', onEnd);
            this._drag = { doc, onMove, onEnd };
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

        /** Add a point at `at` on the edited image, select it, and give its marker the focus. */
        _insert(at) {
            const point = this.variant === 'phone'
                ? { x: at.x, y: at.y, phoneX: at.x, phoneY: at.y, label: '' }
                : { x: at.x, y: at.y, label: '' };
            const index = this.points.length;

            this._replace([...this.points, point]);
            this.selected = index;
            this._say('added', index);
            this._commit();
            this._announceSelection(index);
            this.$nextTick(() => this._focus(`[data-wk-hotspot-point="${index}"]`));
        },

        /** Write the edited image's pair of point `index`, leaving every other key as it was. */
        _place(index, x, y) {
            const point = { ...this.points[index] };

            if (this.variant === 'phone') {
                point.phoneX = x;
                point.phoneY = y;
            } else {
                point.x = x;
                point.y = y;
            }

            this._replace(this.points.map((p, i) => (i === index ? point : p)));
        },

        /** Swap two rows, and keep the focus on the arrow that moved the row, or on the other one. */
        _swap(from, to, arrow) {
            if (from < 0 || to < 0 || from >= this.points.length || to >= this.points.length) {
                return;
            }

            const points = [...this.points];

            [points[from], points[to]] = [points[to], points[from]];
            this._replace(points);
            this.selected = to;
            this._speak(fill(announcements.position ?? 'Position :position of :total.', { position: to + 1, total: this.points.length }));
            this._commit();

            // An arrow at the end of the list is disabled, so the focus takes the other one there.
            const atEnd = (arrow === 'up' && to === 0) || (arrow === 'down' && to === this.points.length - 1);
            const control = atEnd ? (arrow === 'up' ? 'down' : 'up') : arrow;

            this.$nextTick(() => this._focus(`[data-wk-hotspots-row="${to}"] [data-wk-row-control="${control}"]`));
        },

        /**
         * Replace the list rather than change it in place, so each change is one assignment for the
         * binding to carry.
         */
        _replace(points) {
            this.points = points;
        },

        _say(kind, index) {
            const point = this.points[index];

            if (! point) {
                return;
            }

            const fallback = { selected: ':name, :x% across, :y% down', moved: ':x% across, :y% down', added: 'Added :name, :x% across, :y% down' };
            const number = (value) => formatDecimal(value, config.locale, 1);

            this._speak(fill(announcements[kind] ?? fallback[kind], {
                name: this.nameOf(point, index),
                x: number(this.xOf(point, index)),
                y: number(this.yOf(point, index)),
            }));
        },

        /** Emptied first, so the same sentence twice in a row is read twice. */
        _speak(text) {
            this.announcement = '';
            queueMicrotask(() => {
                this.announcement = text;
            });
        },

        /** Tell the page which point the reader is on, so it can offer that point's own fields. */
        _announceSelection(index) {
            const point = this.points[index];

            if (point && rootEl) {
                rootEl.dispatchEvent(new CustomEvent('wirekit:image-hotspots-editor-select', {
                    detail: { index, point: { ...point } },
                    bubbles: true,
                }));
            }
        },

        _focus(selector) {
            rootEl?.querySelector?.(selector)?.focus?.({ preventScroll: true });
        },

        _commit() {
            this._modelEvents?.commit();
        },
    };
}
