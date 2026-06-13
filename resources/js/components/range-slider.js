/**
 * WireKit Range Slider Alpine Component.
 *
 * Dual-handle slider for selecting a value range.
 * Supports keyboard navigation, pointer drag, and step increments.
 *
 * @param {Object} config
 * @param {number} config.min - Minimum track value
 * @param {number} config.max - Maximum track value
 * @param {number} config.step - Step increment
 * @param {number} config.minValue - Initial minimum selection
 * @param {number} config.maxValue - Initial maximum selection
 * @param {string} config.name - Input name for form submission
 */
export default function wirekitRangeSlider(config = {}) {
    return {
        minVal: config.minValue ?? config.min ?? 0,
        maxVal: config.maxValue ?? config.max ?? 100,
        _min: config.min ?? 0,
        _max: config.max ?? 100,
        _step: config.step ?? 1,
        _dragging: null,
        // True when the two thumbs are close enough that their individual value
        // badges would overlap — the blade then shows ONE merged "min – max"
        // badge instead. Set by _measureBubbles() (measured, not a guessed %).
        _merged: false,

        get minPercent() {
            return ((this.minVal - this._min) / (this._max - this._min)) * 100;
        },

        get maxPercent() {
            return ((this.maxVal - this._min) / (this._max - this._min)) * 100;
        },

        /**
         * Step the minimum value by direction (-1 or +1).
         */
        stepMin(direction) {
            const newVal = this.minVal + (direction * this._step);
            this.minVal = Math.max(this._min, Math.min(newVal, this.maxVal - this._step));
            this._dispatchInputEvent();
        },

        /**
         * Step the maximum value by direction (-1 or +1).
         */
        stepMax(direction) {
            const newVal = this.maxVal + (direction * this._step);
            this.maxVal = Math.min(this._max, Math.max(newVal, this.minVal + this._step));
            this._dispatchInputEvent();
        },

        /**
         * Start drag on a thumb.
         */
        startDrag(handle, event) {
            event.preventDefault();
            this._dragging = handle;

            // Cache the track's rect ONCE per drag — the track is stationary while
            // dragging, so re-reading getBoundingClientRect on every pointermove (a
            // hot path) would force a needless layout read per frame. Mirrors the
            // color-picker drag optimization.
            this._dragRect = this.$refs.track ? this.$refs.track.getBoundingClientRect() : null;

            const onMove = (e) => this._onDrag(e);
            const onUp = () => {
                this._dragging = null;
                this._dragRect = null;
                document.removeEventListener('pointermove', onMove);
                document.removeEventListener('pointerup', onUp);
                document.removeEventListener('pointercancel', onUp);
            };

            // Passive — onDrag only computes the value from pointer position;
            // it never calls preventDefault, so it must not block scroll.
            document.addEventListener('pointermove', onMove, { passive: true });
            document.addEventListener('pointerup', onUp);
            document.addEventListener('pointercancel', onUp);
        },

        /**
         * Handle drag movement — calculate value from pointer position.
         */
        _onDrag(event) {
            if (!this._dragging) return;

            const track = this.$refs.track;
            if (!track) return;

            // Use the per-drag cached rect (set in startDrag); fall back to a
            // fresh read only if it's somehow absent.
            const rect = this._dragRect || track.getBoundingClientRect();
            const percent = Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width));
            const rawValue = this._min + percent * (this._max - this._min);

            // Snap to step
            const stepped = Math.round(rawValue / this._step) * this._step;

            if (this._dragging === 'min') {
                this.minVal = Math.max(this._min, Math.min(stepped, this.maxVal - this._step));
            } else {
                this.maxVal = Math.min(this._max, Math.max(stepped, this.minVal + this._step));
            }

            this._dispatchInputEvent();
        },

        /**
         * Merge the two value badges into one "min – max" badge when the thumbs
         * sit close enough that the individual badges would overlap (the issue a
         * docs blueprint used to work around by printing the value in a separate
         * row). Measures the rendered badge widths against the gap between the
         * thumb centers — robust to track width and digit count, unlike a guessed
         * % threshold. Driven by an x-effect on minVal/maxVal, so it tracks live
         * during a drag, plus first paint. The individual badges stay in layout
         * (toggled via opacity, not display) so they remain measurable.
         */
        _measureBubbles() {
            const track = this.$refs.track;
            const lo = this.$refs.minBubble;
            const hi = this.$refs.maxBubble;
            if (!track || !lo || !hi) return;
            const tw = track.getBoundingClientRect().width;
            if (!tw) return;
            const minCenter = (this.minPercent / 100) * tw;
            const maxCenter = (this.maxPercent / 100) * tw;
            // Overlap when the gap between the two badge centers is less than
            // their combined half-widths plus a small breathing gap (6px).
            const need = (lo.offsetWidth + hi.offsetWidth) / 2 + 6;
            this._merged = (maxCenter - minCenter) < need;
        },

        /**
         * Dispatch input events on hidden inputs for Livewire.
         */
        _dispatchInputEvent() {
            this.$refs.minInput?.dispatchEvent(new Event('input', { bubbles: true }));
            this.$refs.maxInput?.dispatchEvent(new Event('input', { bubbles: true }));
        },
    };
}
