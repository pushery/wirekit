/**
 * WireKit Image Compare Alpine Component.
 *
 * Before/after image slider with full keyboard, touch, and pointer support.
 * WAI-ARIA Slider Pattern compliant (role="slider", aria-valuenow, aria-orientation).
 *
 * Config is passed as an object through the Blade template's x-data attribute.
 *
 * @param {Object} config
 * @param {number} [config.value=50]             - Initial handle position (0-100)
 * @param {"horizontal"|"vertical"} [config.orientation="horizontal"] - Slider axis
 * @param {string|null} [config.wireModel=null]  - Livewire property name (for $wire.set)
 * @param {boolean} [config.wireLive=false]      - Whether wire:model has .live modifier
 */
export default function wirekitImageCompare(config = {}) {
    return {
        // Public state ───────────────────────────────────────────────
        value: Math.max(0, Math.min(100, Number(config.value ?? 50))),
        orientation: config.orientation === 'vertical' ? 'vertical' : 'horizontal',

        // Internal state ─────────────────────────────────────────────
        _dragging: false,
        // The document-level drag listeners, held under `_` names rather than in
        // the closure that created them. Two reasons, and the second is the one
        // that made the change worth making: a drag that is torn down mid-gesture
        // (a Livewire morph, a conditional render flipping) never reaches its own
        // pointerup, so a closure-held pair stays on `document` with a dead scope
        // behind it — and the cleanup tooling greps for `this._`, so listeners
        // without such a name are invisible to it even when they are correct.
        _moveHandler: null,
        _upHandler: null,
        _wireModel: config.wireModel ?? null,
        _wireLive: config.wireLive === true,

        init() {
            // If wire:model is present, entangle the Alpine value with the
            // Livewire property. Deferred mode (default) skips per-step network
            // round-trips; only the final value is pushed when drag ends.
            if (this._wireModel && this.$wire) {
                this.$watch('value', (v) => {
                    // Third arg `live` controls whether Livewire immediately
                    // issues a network roundtrip. For deferred bindings we pass
                    // false and let a blur/commit drive the sync.
                    this.$wire.set(this._wireModel, v, this._wireLive);
                });
            }
        },

        // Step keyboard-driven value changes ──────────────────────────
        stepBy(delta) {
            const next = Math.max(0, Math.min(100, this.value + delta));
            if (next !== this.value) {
                this.value = next;
                this._emit();
            }
        },

        setValue(v) {
            const next = Math.max(0, Math.min(100, Math.round(Number(v))));
            if (Number.isNaN(next)) return;
            if (next !== this.value) {
                this.value = next;
                this._emit();
            }
        },

        // Click-to-position: snap handle to click coordinates ────────
        // Guard against click events that are the tail of a drag — those
        // were already handled by the pointermove listeners.
        onTrackClick(event) {
            if (this._dragging) return;
            this._setFromPointer(event);
        },

        // Start a drag sequence. Attaches document-level pointermove and
        // pointerup listeners so the drag continues even when the pointer
        // leaves the component bounds.
        startDrag(event) {
            event.preventDefault();

            // Canceling pointerdown also cancels the FOCUS it would have moved to the
            // pressed control — that move is part of the default action, and this component
            // had no other route to it: there was no focus call in the file at all. Without
            // this line the handle the reader just grabbed is not the focused element, so
            // the focus ring never appears and the ArrowRight pressed to fine-tune scrolls
            // the page instead of moving the divider. That pointer-then-keyboard sequence is
            // how a compare slider is used, not an edge case. Measured in Chromium before
            // the fix: `document.activeElement` was still `body` after a real click on the
            // handle. Same defect, same fix, as the sibling range-slider.
            //
            // `preventScroll` because the control is already under the pointer — there is
            // nothing to bring into view, and scrolling here would pull the track out from
            // under the gesture that is starting.
            this._sliderHandle(event)?.focus?.({ preventScroll: true });

            // A second pointerdown without an intervening pointerup would otherwise
            // overwrite the handles below and strand the previous pair on document.
            this._releaseDragListeners();

            this._dragging = true;

            // Cache the track's rect ONCE per drag — it's stationary while dragging,
            // so re-reading getBoundingClientRect on every pointermove (a hot path)
            // would force a needless layout read per frame. The click path keeps
            // reading fresh (the track may have shifted since the last drag).
            const dragRect = this.$refs.track ? this.$refs.track.getBoundingClientRect() : null;

            this._moveHandler = (e) => this._setFromPointer(e, dragRect);
            this._upHandler = () => {
                this._releaseDragListeners();
                // Defer clearing the drag flag by a microtask so the trailing
                // click event (from the same pointer sequence) still sees
                // _dragging === true and is ignored by onTrackClick.
                queueMicrotask(() => {
                    this._dragging = false;
                });
            };

            // Passive — the move handler only reads pointer coords + sets the
            // divider position; it never calls preventDefault (the pointerdown
            // does, to suppress native drag), so it must not block scroll.
            document.addEventListener('pointermove', this._moveHandler, { passive: true });
            document.addEventListener('pointerup', this._upHandler);
            // pointercancel ends a drag too, and NO pointerup follows it — the
            // browser fires it when it takes the pointer away (a touch that turns
            // into a scroll, a system gesture).
            document.addEventListener('pointercancel', this._upHandler);
        },

        /**
         * The element that must end up focused, which is NOT always the one pressed.
         *
         * `startDrag` is bound on two elements: the handle, which carries `role="slider"`
         * and every keyboard binding, and the track behind it, which starts a drag when the
         * reader presses empty space. So `event.currentTarget` — the obvious answer, and the
         * one the sibling range-slider can use because its binding sits on the thumb alone —
         * is the track on one of the two paths. Focusing the track would be worse than
         * focusing nothing: it is `aria-hidden` with `tabindex="-1"`, so it announces nothing
         * AND leaves the arrow keys, which are bound on the handle, still unreachable.
         *
         * Resolved by role rather than by a ref: the role is the property that matters here,
         * it is already in the markup for assistive technology, and it cannot drift out of
         * step with the element the keyboard bindings sit on the way a second ref could.
         *
         * Fully optional-chained because the ESM factory tests construct this component
         * against a deliberately barren stub with no `$root` and a bare `Event`.
         */
        _sliderHandle(event) {
            const pressed = event?.currentTarget;

            if (pressed?.matches?.('[role="slider"]')) {
                return pressed;
            }

            return this.$root?.querySelector?.('[role="slider"]') ?? null;
        },

        /**
         * Take the drag listeners back off `document`.
         *
         * Guarded on `_moveHandler` so calling it when no drag is running removes
         * nothing — which is what lets both the pointerup path and destroy() go
         * through the same method without either having to know about the other.
         */
        _releaseDragListeners() {
            if (! this._moveHandler) {
                return;
            }

            document.removeEventListener('pointermove', this._moveHandler);
            document.removeEventListener('pointerup', this._upHandler);
            document.removeEventListener('pointercancel', this._upHandler);
            this._moveHandler = null;
            this._upHandler = null;
        },

        /**
         * Alpine tears the component down — a Livewire morph, a conditional render
         * flipping, an SPA navigation — and a drag in flight ends with it.
         *
         * Without this the pointermove listener outlives the element it was moving:
         * it holds the scope (and through `$refs` the removed node) for as long as
         * the page lives, and every frame of the reader's next unrelated drag runs
         * a handler for a component that is gone.
         */
        destroy() {
            this._releaseDragListeners();
            this._dragging = false;
        },

        _setFromPointer(event, cachedRect = null) {
            const track = this.$refs.track;
            if (!track) return;

            // Use the per-drag cached rect when dragging; the click path passes
            // none and reads fresh.
            const rect = cachedRect || track.getBoundingClientRect();
            const pct = this.orientation === 'vertical'
                ? ((event.clientY - rect.top) / rect.height) * 100
                : ((event.clientX - rect.left) / rect.width) * 100;

            this.setValue(pct);
        },

        /**
         * The three geometry bindings, as methods.
         *
         * They were template literals in the template — which is the one place
         * they cannot be: Alpine's CSP build parses expressions instead of
         * compiling them, and a backtick string is not in its grammar. So every
         * page using image-compare was blank under a strict Content-Security
         * Policy, in the quiet way — the images render, the wipe simply never
         * moves.
         *
         * Style strings belong in JavaScript anyway. Reading the clip geometry
         * next to the drag handler that produces `value` is easier than reading
         * it inside an attribute.
         */
        clipStyle() {
            return this.orientation === 'vertical'
                ? `clip-path: inset(${100 - this.value}% 0 0 0)`
                : `clip-path: inset(0 0 0 ${100 - this.value}%)`;
        },

        dividerStyle() {
            const size = 'var(--wk-image-compare-divider-size, 2px)';

            return this.orientation === 'vertical'
                ? `top: ${this.value}%; left: 0; right: 0; height: ${size}; transform: translateY(-50%)`
                : `left: ${this.value}%; top: 0; bottom: 0; width: ${size}; transform: translateX(-50%)`;
        },

        handleStyle() {
            return this.orientation === 'vertical'
                ? `top: ${this.value}%; left: 50%; transform: translate(-50%, -50%)`
                : `left: ${this.value}%; top: 50%; transform: translate(-50%, -50%)`;
        },

        _emit() {
            // Custom DOM event for listeners that want to observe slide
            // changes without a Livewire round-trip. Detail carries the
            // current value + orientation so developers can react polymorphically.
            const detail = { value: this.value, orientation: this.orientation };
            this.$el.dispatchEvent(
                new CustomEvent('slide', { detail, bubbles: true })
            );
            // Also fire input on the hidden form input so plain-HTML form
            // submission picks up the value when no Livewire scope exists.
            this.$refs.hiddenInput?.dispatchEvent(
                new Event('input', { bubbles: true })
            );
        },
    };
}
