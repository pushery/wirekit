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
            this._dragging = true;

            const onMove = (e) => this._setFromPointer(e);
            const onUp = () => {
                // Defer clearing the drag flag by a microtask so the trailing
                // click event (from the same pointer sequence) still sees
                // _dragging === true and is ignored by onTrackClick.
                queueMicrotask(() => {
                    this._dragging = false;
                });
                document.removeEventListener('pointermove', onMove);
                document.removeEventListener('pointerup', onUp);
                document.removeEventListener('pointercancel', onUp);
            };

            document.addEventListener('pointermove', onMove);
            document.addEventListener('pointerup', onUp);
            document.addEventListener('pointercancel', onUp);
        },

        _setFromPointer(event) {
            const track = this.$refs.track;
            if (!track) return;

            const rect = track.getBoundingClientRect();
            const pct = this.orientation === 'vertical'
                ? ((event.clientY - rect.top) / rect.height) * 100
                : ((event.clientX - rect.left) / rect.width) * 100;

            this.setValue(pct);
        },

        _emit() {
            // Custom DOM event for listeners that want to observe slide
            // changes without a Livewire round-trip. Detail carries the
            // current value + orientation so consumers can react polymorphically.
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
