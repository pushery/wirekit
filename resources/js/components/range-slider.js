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

            const onMove = (e) => this._onDrag(e);
            const onUp = () => {
                this._dragging = null;
                document.removeEventListener('pointermove', onMove);
                document.removeEventListener('pointerup', onUp);
            };

            document.addEventListener('pointermove', onMove);
            document.addEventListener('pointerup', onUp);
        },

        /**
         * Handle drag movement — calculate value from pointer position.
         */
        _onDrag(event) {
            if (!this._dragging) return;

            const track = this.$refs.track;
            if (!track) return;

            const rect = track.getBoundingClientRect();
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
         * Dispatch input events on hidden inputs for Livewire.
         */
        _dispatchInputEvent() {
            this.$refs.minInput?.dispatchEvent(new Event('input', { bubbles: true }));
            this.$refs.maxInput?.dispatchEvent(new Event('input', { bubbles: true }));
        },
    };
}
