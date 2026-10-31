/**
 * Slider — the mirror of a native range input, and the bubble that follows it.
 *
 * The component is a thin layer over `<input type="range">`: the element owns
 * its value, and `current` only mirrors it so the bubble and the announced text
 * have something reactive to read. That direction matters and is the reason
 * there is no `:value` binding on the input — see the template.
 *
 * A factory rather than an inline `x-data`, because Alpine's CSP build parses one
 * expression and allows neither method shorthand nor getters: under a strict
 * Content-Security-Policy the object literal would fail to build, leaving the
 * element with an empty scope and every directive on it silently doing nothing —
 * no bubble, no announced text, no Livewire resync.
 *
 * Lifecycle resources held on `this`:
 *   - _unhookResync (the function Livewire.hook returns) — released in destroy().
 *   - _stopFormReset — the listener that reads the field again after its form is reset
 *     (utils/form-reset.js), released in destroy().
 *     A post-teardown fire is not harmless: after a livewire:navigate the handler
 *     still runs and reads $refs on a scope that no longer exists.
 *   - _stopServerValue — the observer on the value the server renders on the root
 *     (utils/server-value.js), released in destroy().
 *   - _markResizes — the ResizeObserver on the row of mark labels and on every label, and
 *     _markFrame, a scheduled placement of the labels; both released in destroy().
 *
 * @param {Object} config
 * @param {string} [config.current] the input's value at render time, as a string; without it,
 *                                  the root's `data-wk-server-value`
 * @param {number} config.min       the input's min, mirrored for the pct math
 * @param {number} config.max       the input's max, mirrored for the pct math
 * @param {Object} config.marksMap  value -> label, for the announced text
 * @param {number} [config.places]  the places a value can carry, as the view counts them
 * @param {string} [config.locale]  the application's locale, BCP-47, for the value it writes
 * @param {{group: string, decimal: string}|null} [config.separators]  the application's own marks
 */
import { focusClicked } from '../utils/focus-clicked.js';
import { decimalPlaces, scaleText } from '../utils/locale-number.js';
import { onFormReset } from '../utils/form-reset.js';
import { hasModelBinding, observeServerValue, WK_SERVER_VALUE_ATTRIBUTE } from '../utils/server-value.js';

/** The row the mark labels hang in, under the track. */
export const MARKS_ROW_ATTRIBUTE = 'data-wk-slider-marks';

/** A mark's label, carrying the position of its mark among the slider's marks. */
export const MARK_LABEL_ATTRIBUTE = 'data-wk-slider-mark-label';

/** Half a pixel either way, so that widths read from one layout are compared as equal. */
const PLACEMENT_SLACK = 0.5;

/**
 * The sets of labels to try, the fullest first: every label, then every second one, every third one
 * and so on, each time from the first and with the last.
 *
 * Steps rather than a count, so that what stays has the rhythm of the scale: eleven labels from 0
 * to 100 keep 0, 20, 40, 60, 80 and 100, where the most that fit could be 0, 20, 30, 50, 70, 80 and
 * 100. And five keep the middle one, which on a scale from one opinion to its opposite is the
 * neutral one. Where a step does not land on the last label, the label it ends on can sit close to
 * the last one, so the set is tried once with it and once without it.
 *
 * @param {number} count  at least 2
 * @returns {number[][]}
 */
function labelSets(count) {
    const last = count - 1;
    const sets = [];

    for (let step = 1; step < last; step++) {
        const set = [];

        for (let i = 0; i < last; i += step) {
            set.push(i);
        }

        sets.push([...set, last]);

        if (last % step !== 0 && set.length > 1) {
            sets.push([...set.slice(0, -1), last]);
        }
    }

    sets.push([0, last]);

    return sets;
}

/**
 * Where these labels can start so that none comes closer to the next than `gap`, none leaves the
 * row and every tick stays under its label, or null when they cannot.
 *
 * Each label starts where the server put it. A label that runs into the one before it is pushed
 * along the row, and where a push takes a label past its own room the labels before it are pulled
 * back. No label is pulled further left than the leftmost arrangement would put it, so the two
 * passes find an arrangement whenever one exists, and a label nothing crowds stays where it was.
 *
 * @param {{left: number, width: number, tick: number}[]} labels  in the order of the row
 * @param {number} rowWidth
 * @param {number} gap
 * @returns {number[]|null}
 */
function packLabels(labels, rowWidth, gap) {
    // A label's room: from where it ends at its tick to where it starts at it, within the row.
    const rooms = labels.map(({ width, tick }) => [Math.max(0, tick - width), Math.min(rowWidth - width, tick)]);
    const lefts = labels.map(({ left }, i) => Math.min(Math.max(left, rooms[i][0]), rooms[i][1]));
    const last = labels.length - 1;

    for (let i = 1; i <= last; i++) {
        lefts[i] = Math.max(lefts[i], lefts[i - 1] + labels[i - 1].width + gap);
    }

    lefts[last] = Math.min(lefts[last], rooms[last][1]);

    for (let i = last - 1; i >= 0; i--) {
        lefts[i] = Math.min(lefts[i], rooms[i][1], lefts[i + 1] - labels[i].width - gap);
    }

    const fits = lefts.every((left, i) => left >= rooms[i][0] - PLACEMENT_SLACK
        && left <= rooms[i][1] + PLACEMENT_SLACK
        && (i === 0 || left >= lefts[i - 1] + labels[i - 1].width + gap - PLACEMENT_SLACK));

    return fits ? lefts : null;
}

/**
 * Where each mark label goes along its row, and which labels the row has no room for.
 *
 * The server places every label from its position alone, so on a narrow track two neighbors can
 * run into each other. Labels that come closer than `gap` are moved apart, each at most so far that
 * its tick stays under it, and none past either end of the row. Where that does not make room, the
 * row keeps every second label, or every third and so on, always with the first and the last, and
 * hides the others (labelSets above). If even the first and the last do not fit beside each other,
 * they stay where the server put them and every label between them is hidden.
 *
 * @param {{left: number, width: number, tick: number}[]} labels  in the order of the row: where
 *        each label starts as the server placed it, how wide it is and where its tick sits, each
 *        counted from the start of the row
 * @param {number} rowWidth
 * @param {number} gap  the least space between two labels
 * @returns {{shifts: number[], hidden: number[]}}  how far each label moves along the row, and the
 *          positions of the labels it hides
 */
export function placeMarkLabels(labels, rowWidth, gap) {
    const count = labels.length;
    const stay = labels.map(() => 0);

    if (count < 2 || ! (rowWidth > 0)) {
        return { shifts: stay, hidden: [] };
    }

    for (const kept of labelSets(count)) {
        const lefts = packLabels(kept.map((i) => labels[i]), rowWidth, gap);

        if (lefts !== null) {
            const shifts = [...stay];

            kept.forEach((i, k) => {
                shifts[i] = lefts[k] - labels[i].left;
            });

            return { shifts, hidden: labels.map((_, i) => i).filter((i) => ! kept.includes(i)) };
        }
    }

    return { shifts: stay, hidden: labels.map((_, i) => i).filter((i) => i !== 0 && i !== count - 1) };
}

/**
 * The labels in a row of marks.
 *
 * @param {Element} row
 * @returns {Element[]}
 */
function markLabelsOf(row) {
    return Array.from(row.querySelectorAll(`[${MARK_LABEL_ATTRIBUTE}]`));
}

export default function wirekitSlider(config = {}) {
    return {
        current: config.current != null ? String(config.current) : '',
        min: Number(config.min ?? 0),
        max: Number(config.max ?? 100),
        marksMap: config.marksMap || {},
        // Without a count from the view, a value carries the places it has.
        _places: Number.isInteger(config.places) && config.places >= 0 ? config.places : null,
        _locale: typeof config.locale === 'string' && config.locale !== '' ? config.locale : 'en',
        _separators: config.separators && typeof config.separators === 'object' ? config.separators : null,

        // How far each mark label moves along its row, by the position of its mark, and the
        // positions of the marks whose label the row has no room for (placeMarkLabels above).
        markShifts: {},
        hiddenMarks: [],

        _resync: null,
        _unhookResync: null,
        _stopFormReset: null,
        _stopServerValue: null,
        _markResizes: null,
        // The width each observed element last reported, so a notification about its height
        // alone places nothing.
        _markWidths: null,
        _markFrame: null,

        /**
         * Follow a form reset. The browser puts the range back to its default value and fires
         * no `input`, so the mirror would go on showing the value the reader left: the bubble and
         * the announced text read the field again once the reset has happened.
         *
         * And follow the value the server renders. It is not in `x-data`, so that the expression
         * stays the same for every value: a Livewire morph that changed it would start the
         * component again, and the answer to a `.live` drag would put the bubble back where the
         * request left it.
         */
        init() {
            const root = this.$root;

            // `$root` is checked rather than assumed, as a unit harness hands a bare stub.
            if (config.current === undefined && typeof root?.getAttribute === 'function') {
                const seed = root.getAttribute(WK_SERVER_VALUE_ATTRIBUTE);

                if (seed !== null) {
                    this.current = seed;
                }
            }

            this._stopFormReset = onFormReset(root, () => this.$refs?.control, () => this._restore());

            // A field with a caller's model belongs to the model, which holds what the server sent
            // unless the reader moved the thumb since, so the mirror reads the field. Without one,
            // the value is the server's to set, on the mirror and on the thumb alike.
            this._stopServerValue = observeServerValue(root, (value) => {
                if (hasModelBinding(this.$refs?.control)) {
                    if (typeof this.$nextTick === 'function') {
                        this.$nextTick(() => this.syncFromInput());
                    }

                    return;
                }

                if (value !== String(this.current)) {
                    this.current = value;
                    this.syncToInput();
                }
            });

            this._watchMarks();
        },

        /** The row of mark labels under the track, or null for a slider without labeled marks. */
        _marksRow() {
            const root = this.$root;

            // `$root` is checked rather than assumed, as a unit harness hands a bare stub.
            return typeof root?.querySelector === 'function' ? root.querySelector(`[${MARKS_ROW_ATTRIBUTE}]`) : null;
        },

        /**
         * Place the mark labels now, and again whenever the row or a label changes its width: the
         * track grows and shrinks with its container, and a web font that lands after the first
         * measurement widens every label while the row keeps its width.
         */
        _watchMarks() {
            const row = this._marksRow();

            if (! row) {
                return;
            }

            if (typeof ResizeObserver === 'function') {
                this._markWidths = new WeakMap();
                this._markResizes = new ResizeObserver((entries) => {
                    // A notification queued before destroy() can still arrive after it.
                    if (! this._markResizes) {
                        return;
                    }

                    let changed = false;

                    entries.forEach((entry) => {
                        const width = entry.contentRect?.width;

                        if (this._markWidths.get(entry.target) !== width) {
                            this._markWidths.set(entry.target, width);
                            changed = true;
                        }
                    });

                    if (changed) {
                        this._scheduleMarks();
                    }
                });
                this._markResizes.observe(row);
                markLabelsOf(row).forEach((label) => this._markResizes.observe(label));
            }

            this.fitMarks();
        },

        _scheduleMarks() {
            if (this._markFrame !== null || typeof requestAnimationFrame !== 'function') {
                return;
            }

            this._markFrame = requestAnimationFrame(() => {
                this._markFrame = null;
                this.fitMarks();
            });
        },

        /**
         * Measure the mark labels where the server put them and move apart the ones that run into
         * each other, or hide the ones the row has no room for (placeMarkLabels above).
         *
         * Only the label moves, never the mark around it, so every tick stays on its value. And a
         * hidden label keeps its box, so the measurement of the next round reads the same widths.
         */
        fitMarks() {
            const row = this._marksRow();

            if (! row) {
                return;
            }

            const labels = markLabelsOf(row);

            if (labels.length === 0) {
                return;
            }

            const rowBox = row.getBoundingClientRect();
            const boxes = labels.map((label) => {
                // The mark is as wide as its label and starts where the label starts unmoved; its
                // first child is the tick.
                const mark = label.parentElement.getBoundingClientRect();
                const tick = label.parentElement.firstElementChild.getBoundingClientRect();

                return {
                    position: Number(label.getAttribute(MARK_LABEL_ATTRIBUTE)),
                    left: mark.left - rowBox.left,
                    width: mark.width,
                    tick: tick.left + tick.width / 2 - rowBox.left,
                };
            }).sort((a, b) => a.tick - b.tick);

            // Half the labels' own text size apart, so the space between two labels grows with them.
            const gap = (Number.parseFloat(getComputedStyle(labels[0]).fontSize) || 0) / 2;
            const placed = placeMarkLabels(boxes, rowBox.width, gap);
            const shifts = {};

            boxes.forEach((label, i) => {
                const shift = Math.round(placed.shifts[i] * 10) / 10;

                if (shift !== 0) {
                    shifts[label.position] = shift;
                }
            });

            const hidden = placed.hidden.map((i) => boxes[i].position).sort((a, b) => a - b);

            // Written only when it changed, so a measurement that confirms the last one re-runs
            // nothing that reads it.
            if (JSON.stringify(shifts) !== JSON.stringify(this.markShifts)) {
                this.markShifts = shifts;
            }

            if (JSON.stringify(hidden) !== JSON.stringify(this.hiddenMarks)) {
                this.hiddenMarks = hidden;
            }
        },

        /**
         * The style of the label of the mark at `position`: moved along its row, or hidden. An
         * object, which Alpine merges property by property, and empty values take a property away.
         *
         * @param {number} position
         * @returns {{transform: string, visibility: string}}
         */
        markLabelStyle(position) {
            const shift = this.markShifts[position] ?? 0;

            return {
                transform: shift !== 0 ? `translateX(${shift}px)` : '',
                visibility: this.hiddenMarks.includes(position) ? 'hidden' : '',
            };
        },

        /** The field after a form reset, read back into the mirror. */
        _restore() {
            this.syncFromInput();
        },

        /** Pull the element's own value back into the mirror. */
        syncFromInput() {
            const el = this.$refs.control;

            if (el && String(el.value) !== String(this.current)) {
                this.current = String(el.value);
            }
        },

        /**
         * Push the mirror back onto the element — the other direction, and only
         * for the optimistic layer.
         *
         * The template deliberately does not bind `:value="current"`, because
         * Alpine would then re-assert a stale mirror over whatever Livewire just
         * wrote. That leaves one case unserved: a rollback writes `current`, and
         * without this the element keeps the refused value — the thumb sits
         * where the server said no while everything derived from the mirror
         * says otherwise.
         *
         * Named in the layer's `after`, which runs after EVERY write including
         * the undo, so the two can never be one apart.
         */
        syncToInput() {
            const el = this.$refs.control;

            if (el && String(el.value) !== String(this.current)) {
                el.value = String(this.current);
            }
        },

        /**
         * Give the range the focus a click on it did not. WebKit does not focus an
         * `<input type="range">` it clicks, so after a click or a drag in Safari the arrow
         * keys reached nothing, where Blink and Gecko move the thumb. Called from the
         * input's click with the input itself (utils/focus-clicked.js).
         *
         * @param {HTMLElement} el
         */
        focusControl(el) {
            focusClicked(el);
        },

        /**
         * Watch for a value that changed without the browser telling us.
         *
         * Assigning `el.value` fires no event and mutates no attribute, so it is
         * invisible to both x-effect and a MutationObserver (verified). Livewire's
         * x-model effect does exactly that on a server-side change, so the one
         * reliable signal is Livewire's own commit hook — after a round trip, look
         * at the element again. Apps without Livewire never call it and pay
         * nothing; there, only the browser moves the thumb and @input suffices.
         */
        initResync() {
            if (typeof window === 'undefined' || ! window.Livewire?.hook) {
                return;
            }

            // The answer may also have changed the marks: a label the morph added is watched like
            // the first ones, and every label is placed again where the new marks put it.
            this._resync = () => queueMicrotask(() => {
                this.syncFromInput();

                const row = this._marksRow();

                if (row && this._markResizes) {
                    markLabelsOf(row).forEach((label) => this._markResizes.observe(label));
                }

                if (row) {
                    this._scheduleMarks();
                }
            });

            // The return value is an unhook function, and dropping it is what kept
            // this handler alive past its component.
            this._unhookResync = window.Livewire.hook('commit', ({ succeed }) => succeed(this._resync));

            // `x-model` writes the bound value onto the field after this runs, and a range field
            // turns a value it cannot hold, such as the empty one a `null` property gives, into the
            // middle of its scale. Read the field once that has happened, so the announced value,
            // the bubble and the thumb agree before the first round trip.
            if (typeof this.$nextTick === 'function') {
                this.$nextTick(() => this.syncFromInput());
            }
        },

        destroy() {
            if (this._unhookResync) {
                this._unhookResync();
                this._unhookResync = null;
            }

            this._stopFormReset?.();
            this._stopFormReset = null;
            this._stopServerValue?.();
            this._stopServerValue = null;
            this._markResizes?.disconnect();
            this._markResizes = null;
            this._markWidths = null;

            if (this._markFrame !== null) {
                cancelAnimationFrame(this._markFrame);
                this._markFrame = null;
            }
        },

        get pct() {
            const r = (this.max - this.min) || 1;

            return Math.max(0, Math.min(100, ((Number(this.current) - this.min) / r) * 100));
        },

        get valueText() {
            // Labeled-mark map wins (announces 'Low' for value 0); otherwise the
            // number is the value, with the locale's decimal mark and the places the view
            // counted. Kept live so keyboard / drag updates the announced text as the thumb moves.
            const mapped = this.marksMap[this.current];

            if (mapped !== undefined && mapped !== null) {
                return mapped;
            }

            return this.current === ''
                ? ''
                : scaleText(Number(this.current), this._locale, this._places ?? decimalPlaces(this.current), this._separators);
        },

        /**
         * Where the bubble sits, and how far it shifts back — both as OBJECTS.
         *
         * Alpine's `bind:style` given a STRING replaces the element's whole
         * `style` attribute on every reactive update, taking the static styles
         * with it. Neither element carries static inline styles today, so the
         * string form these replaced was not yet broken — but it is one static
         * style away from the "element loses its background on first update"
         * regression already documented on reading-progress, and the object form
         * merges property by property instead.
         */
        bubbleStyle() {
            // From the inline start: the browser runs a range from the start of the reading
            // direction, so under `dir="rtl"` the lowest value sits on the right.
            return { insetInlineStart: `${this.pct}%` };
        },

        /**
         * The bubble moves back by its own position so it stays WITHIN the track at
         * the extremes: at the lowest value it starts where the thumb starts, at the
         * highest it ends where the thumb ends, and it centers in the middle. It
         * moves in the direction the track runs, `--wk-slider-run` on the overlay:
         * 1 from left to right, -1 from right to left. The native thumb exposes no
         * width to JS, so pct doubles as the shift.
         */
        bubbleShiftStyle() {
            return { transform: `translateX(calc(var(--wk-slider-run, 1) * -${this.pct}%))` };
        },
    };
}
