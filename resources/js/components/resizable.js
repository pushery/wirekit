/**
 * WireKit Resizable Handle — Alpine component.
 *
 * Hybrid architecture: CSS keeps layout (flex + width/height + min/max),
 * JavaScript takes over ONLY the drag + keyboard interaction on the
 * divider handle between panels. The previous panel is controlled via
 * inline `width: %` / `height: %` writes; every other layout concern
 * (flex shrink, overflow, containment) stays in `dist/wirekit.css`.
 *
 * Implements the WAI-ARIA Window Splitter pattern:
 *   - role="separator" on the handle
 *   - aria-orientation (perpendicular to the panel layout)
 *   - aria-valuemin / aria-valuemax / aria-valuenow in percent
 *   - aria-controls referencing the controlled (previous) panel
 *   - tabindex="0" so the handle is keyboard-reachable
 *
 * Keyboard bindings (WAI-ARIA Window Splitter):
 *   - ArrowLeft / ArrowRight  — adjust horizontal layout by 1% (×10 with Shift)
 *   - ArrowUp   / ArrowDown   — adjust vertical   layout by 1% (×10 with Shift)
 *   - Home                    — snap to minSize
 *   - End                     — snap to maxSize
 *   - Enter / Space           — reset to the panel's declared defaultSize
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/windowsplitter/
 */
export default function wirekitResizableHandle() {
    return {
        // Runtime state. All read in init() from the closest wrapper +
        // previous-sibling panel so the component can be dropped into
        // any <x-wirekit::resizable> without per-handle config.
        direction: 'horizontal',
        wrapper: null,
        panel: null,
        // The panel that sits immediately AFTER this handle (if any). In
        // 3+ panel layouts, non-last handles use a "symmetric pair drag"
        // that shuffles space between `panel` and `nextPanel` only —
        // every other panel is left untouched. Two-panel layouts (and the
        // very last handle in any N-panel layout) fall back to the older
        // single-panel mode because the next panel there IS the flex:1
        // absorber, which cannot carry an inline width.
        nextPanel: null,
        nextIsLast: false,
        nextMinSize: 10,
        nextMaxSize: 90,
        minSize: 10,
        maxSize: 90,
        defaultSize: 50,
        currentSize: 50,
        dragging: false,
        // Captured at pointerDown when pair-drag mode is active. Frozen
        // for the duration of the drag so mid-move clamping uses a
        // consistent reference and rounding cannot walk the pair sum.
        dragPairSum: null,

        init() {
            // Locate the wrapper + the panel this handle controls. In the
            // Blade template, handles always sit BETWEEN two panels, so the
            // previous sibling is the panel whose size we mutate.
            this.wrapper = this.$el.closest('[data-wk-resizable]');
            this.panel = this.$el.previousElementSibling;
            if (!this.wrapper || !this.panel || !this.panel.matches('[data-wk-resizable-panel]')) {
                return;
            }

            this.direction = this.wrapper.dataset.wkDirection === 'vertical' ? 'vertical' : 'horizontal';

            // Read min/max/default from the panel's data attributes (written
            // there by the panel Blade template from user props).
            this.minSize = this._readNumber(this.panel.dataset.wkMinSize, 10);
            this.maxSize = Math.max(this.minSize, this._readNumber(this.panel.dataset.wkMaxSize, 90));
            this.defaultSize = this._clamp(this._readNumber(this.panel.dataset.wkDefaultSize, 50));
            this.currentSize = this.defaultSize;

            // Detect the panel immediately AFTER this handle, and whether
            // it is the wrapper's last panel child. The last panel uses
            // flex: 1 (see dist/wirekit.css) and has no inline width, so
            // we can't drive it by writing `style.width`. In that case we
            // stay in single-panel mode and let the flex child absorb
            // slack via _computeDynamicMax(). For every OTHER handle (3+
            // panel layouts, non-last position), we enable the symmetric
            // pair-drag that users actually expect from a splitter UI.
            const nextEl = this.$el.nextElementSibling;
            if (nextEl && nextEl.matches('[data-wk-resizable-panel]')) {
                this.nextPanel = nextEl;
                const allPanels = this.wrapper.querySelectorAll(':scope > [data-wk-resizable-panel]');
                this.nextIsLast = allPanels.length > 0 && allPanels[allPanels.length - 1] === nextEl;
                this.nextMinSize = this._readNumber(nextEl.dataset.wkMinSize, 10);
                this.nextMaxSize = Math.max(
                    this.nextMinSize,
                    this._readNumber(nextEl.dataset.wkMaxSize, 90)
                );
            }

            // Ensure the panel has a stable id so aria-controls can reference it.
            if (!this.panel.id) {
                this.panel.id = 'wk-resizable-panel-' + Math.random().toString(36).slice(2, 10);
            }

            // WAI-ARIA Window Splitter attributes. A separator's orientation is
            // PERPENDICULAR to the layout axis: a horizontally-laid-out split
            // is divided by a vertical separator, and vice versa.
            this.$el.setAttribute('role', 'separator');
            this.$el.setAttribute('aria-orientation', this.direction === 'horizontal' ? 'vertical' : 'horizontal');
            this.$el.setAttribute('aria-controls', this.panel.id);
            this.$el.setAttribute('aria-valuemin', String(this.minSize));
            this.$el.setAttribute('aria-valuemax', String(this.maxSize));
            this.$el.setAttribute('aria-valuenow', String(Math.round(this.currentSize)));
            this.$el.setAttribute('tabindex', '0');
        },

        onPointerDown(event) {
            // Only primary pointer. Right-clicks, middle-clicks, and stray
            // touch secondary contacts are ignored so we never hijack the
            // context menu or accidental multi-touch gestures.
            if (event.button !== 0) {
                return;
            }
            if (!this.wrapper || !this.panel) {
                return;
            }
            event.preventDefault();
            this.dragging = true;
            this.$el.dataset.dragging = 'true';
            // Capture the pair sum at drag-start so _setSize can split
            // space between `panel` and `nextPanel` without drift. Only
            // meaningful when nextPanel is a non-last sibling (has an
            // inline width we can mutate). For "next IS last" we fall
            // back to single-panel mode via _computeDynamicMax().
            if (this.nextPanel && !this.nextIsLast) {
                const axisKey = this.direction === 'horizontal' ? 'width' : 'height';
                const panelPct = this._readCurrentSize(this.panel, axisKey);
                const nextPct = this._readCurrentSize(this.nextPanel, axisKey);
                this.dragPairSum = panelPct + nextPct;
            } else {
                this.dragPairSum = null;
            }
            // Pointer capture means all subsequent pointer events route to
            // this element even if the cursor leaves its bounds — no need
            // for window-level listeners, no need for stopPropagation.
            try {
                this.$el.setPointerCapture(event.pointerId);
            } catch (e) {
                // Safari rarely throws NotFoundError if the pointerId has
                // already been released; it's safe to continue — we just
                // won't have capture, which only matters for out-of-bounds
                // drags (the user can still drag inside the handle).
            }
            // Suppress text selection across the page while the drag is
            // active. Without this, a drag whose cursor crosses adjacent
            // panel content highlights that text and the browser repaints
            // the selection mid-drag — visible as a "flicker" and a
            // confusing UX. Restored on pointerUp / lostpointercapture.
            // We capture the prior inline value (NOT the computed style)
            // so a consumer who already set `body { user-select: ... }`
            // via stylesheet keeps that rule on restore.
            this._priorBodyUserSelect = document.body.style.userSelect;
            document.body.style.userSelect = 'none';
        },

        onPointerMove(event) {
            if (!this.dragging) {
                return;
            }
            // Panel-relative drag math (NOT wrapper-relative). In a two-panel
            // layout the controlled panel's left/top edge is flush with the
            // wrapper's, so wrapper-relative math happens to work — but in a
            // 3+ panel layout the controlled panel starts AFTER any preceding
            // siblings, and measuring from the wrapper would make the handle
            // believe "cursor at 50% of container = controlled panel at 50%",
            // overwriting the preceding panels' slots. Measuring from the
            // panel's own edge gives us exactly "cursor delta since panel
            // start" which is the panel's desired new width/height.
            const wrapperRect = this.wrapper.getBoundingClientRect();
            const panelRect = this.panel.getBoundingClientRect();
            let percent;
            if (this.direction === 'horizontal') {
                percent = ((event.clientX - panelRect.left) / wrapperRect.width) * 100;
            } else {
                percent = ((event.clientY - panelRect.top) / wrapperRect.height) * 100;
            }
            this._setSize(percent);
        },

        onPointerUp(event) {
            if (!this.dragging) {
                return;
            }
            this.dragging = false;
            this.dragPairSum = null;
            delete this.$el.dataset.dragging;
            try {
                this.$el.releasePointerCapture(event.pointerId);
            } catch (e) {
                // Same Safari caveat as setPointerCapture above — safe to swallow.
            }
            // Restore the prior body user-select value (see onPointerDown
            // for the rationale). If the value was empty (no inline rule
            // before the drag), we set it back to '' which removes the
            // inline override entirely so the consumer's stylesheet wins.
            document.body.style.userSelect = this._priorBodyUserSelect ?? '';
            this._priorBodyUserSelect = null;
        },

        onKeyDown(event) {
            // WAI-ARIA Window Splitter keyboard bindings. Step size is 1%
            // normally, 10% with Shift for fast travel.
            const step = event.shiftKey ? 10 : 1;
            let handled = false;

            if (this.direction === 'horizontal') {
                if (event.key === 'ArrowLeft') {
                    this._setSize(this.currentSize - step);
                    handled = true;
                } else if (event.key === 'ArrowRight') {
                    this._setSize(this.currentSize + step);
                    handled = true;
                }
            } else {
                if (event.key === 'ArrowUp') {
                    this._setSize(this.currentSize - step);
                    handled = true;
                } else if (event.key === 'ArrowDown') {
                    this._setSize(this.currentSize + step);
                    handled = true;
                }
            }

            if (event.key === 'Home') {
                this._setSize(this.minSize);
                handled = true;
            } else if (event.key === 'End') {
                this._setSize(this.maxSize);
                handled = true;
            } else if (event.key === 'Enter' || event.key === ' ') {
                // Reset to the panel's declared defaultSize — the WAI-ARIA
                // pattern calls this "Return to the splitter's default position".
                this._setSize(this.defaultSize);
                handled = true;
            }

            if (handled) {
                event.preventDefault();
            }
        },

        /**
         * Clamp `percent` and write it as an inline width/height on the
         * controlled panel (+ its drag partner, in pair-drag mode), then
         * mirror the new value into aria-valuenow so screen readers
         * announce it.
         *
         * There are TWO modes:
         *
         * 1. **Symmetric pair-drag** — the default for any handle whose
         *    next sibling is a non-last panel. The drag delta flows
         *    between `panel` and `nextPanel` only; every other panel in
         *    the wrapper (including the flex:1 last panel) is left
         *    untouched. This matches every industry-standard splitter
         *    UI (VSCode, split.js, react-resizable-panels, Figma).
         *
         *    Bounds:
         *        upper = min(panel.maxSize, pairSum − nextPanel.minSize)
         *        lower = max(panel.minSize, pairSum − nextPanel.maxSize)
         *
         *    The pair sum is frozen at pointerDown for drag stability
         *    (no rounding drift mid-move) and recomputed live for
         *    keyboard steps (discrete, so drift-free by nature).
         *
         * 2. **Single-panel (flex absorber)** — kicks in when `nextPanel`
         *    is the last panel (flex:1) or when there is no next sibling.
         *    Only the controlled panel's width/height is written; the
         *    flex:1 last panel absorbs the delta automatically. The
         *    upper bound is computed by `_computeDynamicMax()` which
         *    reserves space for every other non-last panel + the last
         *    panel's declared minSize.
         */
        _setSize(percent) {
            const axisKey = this.direction === 'horizontal' ? 'width' : 'height';

            if (this.nextPanel && !this.nextIsLast) {
                // Pair-drag mode. Use the captured pair sum during an
                // active pointer drag; sample live sizes for keyboard
                // (discrete) adjustments or any stray out-of-drag call.
                let pairSum;
                if (this.dragging && Number.isFinite(this.dragPairSum)) {
                    pairSum = this.dragPairSum;
                } else {
                    pairSum = this._readCurrentSize(this.panel, axisKey)
                        + this._readCurrentSize(this.nextPanel, axisKey);
                }
                const upper = Math.min(this.maxSize, pairSum - this.nextMinSize);
                const lower = Math.max(this.minSize, pairSum - this.nextMaxSize);
                // When the declared mins/maxes make [lower, upper] empty,
                // fall back to `lower` — the panel's own min wins over
                // the next panel's max, and the user is notified visually
                // because the drag refuses to move past the floor.
                const clamped = Math.max(lower, Math.min(Math.max(lower, upper), percent));
                this.currentSize = clamped;
                this.panel.style[axisKey] = clamped + '%';
                this.nextPanel.style[axisKey] = (pairSum - clamped) + '%';
                this.$el.setAttribute('aria-valuenow', String(Math.round(clamped)));
                return;
            }

            // Single-panel fallback: delta flows into the flex:1 last
            // panel. _computeDynamicMax reserves the last panel's min
            // and every other non-last sibling's current size.
            const dynamicMax = this._computeDynamicMax();
            const effectiveMax = Math.min(this.maxSize, dynamicMax);
            // If the reserved space is so large that even our min would
            // overflow, fall back to the declared min (nothing we can do —
            // the user's config is inherently inconsistent).
            const upper = Math.max(this.minSize, effectiveMax);
            const clamped = Math.max(this.minSize, Math.min(upper, percent));
            this.currentSize = clamped;
            this.panel.style[axisKey] = clamped + '%';
            this.$el.setAttribute('aria-valuenow', String(Math.round(clamped)));
        },

        /**
         * Compute how much of the wrapper this panel may occupy, given
         * the space already taken (or reserved) by every other panel.
         *
         * Only used in single-panel (last-handle) mode — when this
         * handle's next sibling is the flex:1 last panel. For pair-drag
         * mode, the bounds come from `dragPairSum` instead.
         *
         * - Non-last siblings contribute their CURRENT inline size (or
         *   their declared default if no drag has touched them yet).
         *   These are "fixed" from the perspective of THIS drag — each
         *   non-last panel is driven by its own handle.
         * - The LAST panel contributes its declared minSize, because it
         *   uses flex:1 and must keep at least that much room visible.
         *   Without this reservation, the last panel can collapse below
         *   its min (or even disappear) during a sibling's drag.
         */
        _computeDynamicMax() {
            const panels = Array.from(
                this.wrapper.querySelectorAll(':scope > [data-wk-resizable-panel]')
            );
            if (panels.length === 0) {
                return 100;
            }
            const myIndex = panels.indexOf(this.panel);
            if (myIndex === -1) {
                return 100;
            }
            const lastIndex = panels.length - 1;
            const axisKey = this.direction === 'horizontal' ? 'width' : 'height';

            let reserved = 0;
            for (let i = 0; i < panels.length; i++) {
                if (i === myIndex) {
                    continue;
                }
                const p = panels[i];
                if (i === lastIndex) {
                    // Last panel: reserve its declared minimum.
                    reserved += this._readNumber(p.dataset.wkMinSize, 10);
                } else {
                    // Non-last sibling: use the current inline width/height
                    // if it has been dragged, otherwise fall back to the
                    // declared defaultSize (which is what CSS is rendering
                    // via the --wk-default-size custom property).
                    reserved += this._readCurrentSize(p, axisKey);
                }
            }
            return Math.max(0, 100 - reserved);
        },

        /**
         * Read an element's current size along the active axis as a
         * percentage (0–100). Prefers the inline style (set by a prior
         * drag) over the declared defaultSize (written by Blade as a
         * data attribute AND mirrored into --wk-default-size for CSS).
         */
        _readCurrentSize(el, axisKey) {
            const inline = el.style[axisKey];
            if (inline && inline.endsWith('%')) {
                const parsed = parseFloat(inline);
                if (Number.isFinite(parsed)) {
                    return parsed;
                }
            }
            return this._readNumber(el.dataset.wkDefaultSize, 50);
        },

        _clamp(n) {
            return Math.max(this.minSize, Math.min(this.maxSize, n));
        },

        _readNumber(raw, fallback) {
            const n = parseFloat(raw);
            return Number.isFinite(n) ? n : fallback;
        },
    };
}
