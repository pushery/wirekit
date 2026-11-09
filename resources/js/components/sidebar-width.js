/**
 * WireKit sidebar width — the handle on a sidebar's inner edge that sets its width.
 *
 * The resizable handle's model for a reader, in lengths rather than shares: drag the handle, or
 * step it with the side keys (Shift steps further), Home and End go to the bounds, Enter and
 * Space back to the declared width, and two step controls beside the grip move it without a
 * drag (WCAG 2.2 SC 2.5.7). The control toward a bound the width stands on is hidden until the
 * width leaves it. The handle is a `role="separator"` with its value in pixels, and the
 * width is written as `--wk-sidebar-w` on the sidebar, the property its width class reads.
 *
 * The width is the distance from the edge the sidebar is attached to, so the side keys follow
 * the page: the arrow that points away from that edge widens it, which on a page that reads
 * from the right is Left Arrow for a sidebar at the start of the line.
 *
 * Lifecycle resources held on `this`: `_frame`, the frame that applies a drag, `_pressOutside`, a
 * document listener while the handle has focus, and `_keepThroughUpdates`, the watch that puts
 * back what a Livewire update takes off, all released in destroy(), where the page's text
 * selection, held off during a drag, is given back as well.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/windowsplitter/
 */
import { inlineStep, isRightToLeft } from '../utils/inline-direction.js';
import { frameCoalesce } from '../utils/frame-coalesce.js';
import { readPersistedText, writePersistedText } from '../utils/persisted-flag.js';
import { SPENT_STEPS_ATTRIBUTE, markSpentSteps, spentSteps } from '../utils/spent-steps.js';
import {
    WIDTH_STEP_PX,
    attachedEdge,
    clampWidth,
    widthBounds,
    widthFromKey,
    widthFromPointer,
} from '../utils/sidebar-width.js';

/**
 * @param {Object} config
 * @param {string|null} [config.width]          the declared width, a CSS length in `rem` or `px`
 * @param {string|null} [config.min]            the smallest width
 * @param {string|null} [config.max]            the largest width
 * @param {'start'|'end'} [config.side]         the side of the line the sidebar stands on
 * @param {string|null} [config.persist]        the sidebar's storage key; the width keeps its own under it
 * @param {'local'|'cookie'} [config.persistDriver]
 */
export default function wirekitSidebarWidth(config = {}) {
    return {
        width: 0,
        dragging: false,
        _bounds: { min: 0, max: 0, initial: 0 },
        _key: config.persist ? `${config.persist}-width` : null,
        _driver: config.persistDriver === 'cookie' ? 'cookie' : 'local',
        _side: config.side === 'end' ? 'end' : 'start',
        _pending: null,
        _frame: null,
        _pressOutside: null,
        _priorUserSelect: null,
        _priorTransition: null,
        _keepThroughUpdates: null,

        init() {
            const root = typeof window !== 'undefined' && typeof window.getComputedStyle === 'function'
                ? parseFloat(window.getComputedStyle(document.documentElement).fontSize)
                : NaN;

            this._bounds = widthBounds({ min: config.min, max: config.max, initial: config.width }, root > 0 ? root : 16);

            // A stored width wins over the declared one, clamped to the bounds of today: a sidebar
            // whose bounds were narrowed since keeps a width inside them.
            const stored = Number(readPersistedText(this._key, this._driver));

            this._apply(Number.isFinite(stored) && stored > 0 ? stored : this._bounds.initial, false);
            this._keepThroughUpdates = this._watchUpdates();
        },

        destroy() {
            this._keepThroughUpdates?.disconnect();
            this._keepThroughUpdates = null;
            this._frame?.cancel();
            this._frame = null;
            this._pending = null;
            this._releasePressOutside();
            this._restoreUserSelect();
        },

        /** The sidebar this handle sizes: the column it stands in. */
        _sidebar() {
            return this.$el?.closest?.('.wk-sidebar') ?? null;
        },

        /** The physical edge the sidebar is attached to, read from the page's direction now. */
        _edge() {
            return attachedEdge(this._side, isRightToLeft(this.$el));
        },

        /**
         * The separator, wherever the call came from. `$el` is the element whose directive is
         * running, and for a step control's click that is the control, not the handle a reader is
         * on; `$root` is the handle, which holds this component.
         */
        _handle() {
            return this.$root ?? this.$el;
        },

        /**
         * Clamp, write the width onto the sidebar and into the separator's value, and keep it. The
         * step control toward a bound the width stands on is listed as spent, which hides it: the
         * width is a whole pixel and a bound in `rem` need not be, so half a pixel counts as on it.
         */
        _apply(px, remember = true) {
            const { min, max } = this._bounds;

            this.width = clampWidth(px, min, max);
            this._paint();

            if (remember) {
                writePersistedText(this._key, this.width, this._driver);
            }
        },

        /**
         * Write the width onto the sidebar and the separator's value onto the handle, each only
         * where it differs: the watch in `_watchUpdates()` sees these writes as well, and a round
         * that writes nothing ends it.
         */
        _paint() {
            const { min, max } = this._bounds;
            const handle = this._handle();
            const style = this._sidebar()?.style;
            const width = `${this.width}px`;

            if (style && style.getPropertyValue?.('--wk-sidebar-w') !== width) {
                style.setProperty('--wk-sidebar-w', width);
            }

            for (const [name, value] of [['aria-valuenow', String(this.width)], ['aria-valuemin', String(Math.round(min))], ['aria-valuemax', String(Math.round(max))]]) {
                if (handle?.getAttribute?.(name) !== value) {
                    handle?.setAttribute?.(name, value);
                }
            }

            markSpentSteps(handle, spentSteps({ current: this.width, lower: min, upper: max, grows: this._edge() === 'left' ? 1 : -1, tolerance: 0.5 }));
        },

        /**
         * Put back what a Livewire update took off. The sidebar's `style` and the handle are the
         * server's markup: the morph of an update writes the declared width back over the
         * reader's, and takes off the separator's value, which the server never renders. Without
         * `MutationObserver` there is nothing to watch, and nothing to put back.
         *
         * @returns {MutationObserver|null}
         */
        _watchUpdates() {
            if (typeof MutationObserver === 'undefined') {
                return null;
            }

            const observer = new MutationObserver(() => this._paint());
            const sidebar = this._sidebar();
            const handle = this._handle();

            if (sidebar) {
                observer.observe(sidebar, { attributes: true, attributeFilter: ['style'] });
            }

            if (handle) {
                observer.observe(handle, { attributes: true, attributeFilter: ['aria-valuenow', 'aria-valuemin', 'aria-valuemax', SPENT_STEPS_ATTRIBUTE] });
            }

            return observer;
        },

        onPointerDown(event) {
            // The primary button only, so a right click keeps its menu, and not on a step control,
            // where a press is the start of a click.
            if (event.button !== 0 || event.target?.closest?.('[data-wk-resizable-step]')) {
                return;
            }

            this.$el.focus?.({ preventScroll: true });
            event.preventDefault();
            this.dragging = true;
            this.$el.dataset.dragging = 'true';

            try {
                this.$el.setPointerCapture(event.pointerId);
            } catch {
                // Without capture a drag still works while the pointer stays over the handle.
            }

            // No text selection across the page while the drag lasts, given back on release.
            this._priorUserSelect = document.body.style.userSelect;
            document.body.style.userSelect = 'none';

            // A folding column animates its width, which would trail the pointer through the whole
            // drag. Held off inline, which wins over the column's own transition class, and given
            // back on release.
            const sidebar = this._sidebar();

            if (sidebar?.style) {
                this._priorTransition = sidebar.style.transition;
                sidebar.style.transition = 'none';
            }
        },

        onPointerMove(event) {
            if (! this.dragging) {
                return;
            }

            // Stored here and measured once a frame, where the layout read is already current.
            this._pending = event.clientX;
            this._frame ??= frameCoalesce(() => this._applyPointer());
            this._frame.schedule();
        },

        _applyPointer() {
            const sidebar = this._sidebar();

            if (this._pending === null || ! this.dragging || ! sidebar) {
                return;
            }

            this._apply(widthFromPointer(this._pending, sidebar.getBoundingClientRect(), this._edge()), false);
        },

        onPointerUp(event) {
            if (! this.dragging) {
                return;
            }

            // The release lands where the pointer last was, and a frame still waiting holds it.
            if (this._pending !== null) {
                this._applyPointer();
            }

            this.dragging = false;
            this._frame?.cancel();
            this._pending = null;
            delete this.$el.dataset.dragging;

            try {
                this.$el.releasePointerCapture(event.pointerId);
            } catch {
                // Released already; nothing to undo.
            }

            this._restoreUserSelect();
            writePersistedText(this._key, this.width, this._driver);
        },

        _restoreUserSelect() {
            if (this._priorTransition !== null) {
                const sidebar = this._sidebar();

                if (sidebar?.style) {
                    sidebar.style.transition = this._priorTransition;
                }

                this._priorTransition = null;
            }

            if (this._priorUserSelect === null) {
                return;
            }

            document.body.style.userSelect = this._priorUserSelect;
            this._priorUserSelect = null;
        },

        onKeyDown(event) {
            const next = widthFromKey(event, {
                width: this.width,
                ...this._bounds,
                toward: inlineStep(event, this.$el),
                side: this._side,
            });

            if (next === null) {
                return;
            }

            event.preventDefault();
            this._apply(next);
        },

        /**
         * One step from a control beside the grip. The controls stand left and right of the line
         * and point that way, so `-1` moves the line left and `1` moves it right: for a sidebar
         * attached at the left that narrows and widens it, for one attached at the right the
         * other way round.
         */
        stepFromControl(physical) {
            const widens = this._edge() === 'left' ? physical : -physical;

            this._apply(this.width + widens * WIDTH_STEP_PX);
        },

        // On a touch screen the step controls show while the handle has focus, so a press
        // anywhere else takes the focus away, as it does for the resizable handle.
        onFocus() {
            if (this._pressOutside) {
                return;
            }

            this._pressOutside = (event) => {
                if (! this.$el.contains(event.target)) {
                    this.$el.blur();
                }
            };
            document.addEventListener('pointerdown', this._pressOutside, true);
        },

        onBlur() {
            this._releasePressOutside();
        },

        _releasePressOutside() {
            if (this._pressOutside) {
                document.removeEventListener('pointerdown', this._pressOutside, true);
                this._pressOutside = null;
            }
        },
    };
}
