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

        /**
         * Gates the badge fade so it never runs on the FIRST measurement.
         *
         * `_merged` starts false and the first measurement may immediately flip
         * it, which — with the fade applied unconditionally — animated the
         * merged badge in from nothing on page load. Two costs: the reader sees
         * a blink where the layout was never in doubt, and for the duration of
         * that fade the text sits at a PARTIAL opacity. A near-black at partial
         * opacity over white is mid-gray, so an accessibility scan that samples
         * mid-fade reads ~4.3:1 against a token that is 15:1 when settled — that
         * is what reddened CI on the storefront blueprint while every local run
         * passed: the scan raced the fade.
         */
        _ready: false,

        /**
         * Value-text overrides, keyed by value.
         *
         * What a thumb ANNOUNCES. The map wins where it has an entry, otherwise
         * the number is the value. Live, so dragging and arrow keys update the
         * announcement as the thumb moves — the same contract the single-value
         * slider keeps.
         */
        marksMap: config.marksMap && typeof config.marksMap === 'object' ? config.marksMap : {},

        /** The geometry, built here — a template literal cannot be parsed
         *  by Alpine's CSP build, and these are all one expression each. */
        rangeFillStyle() {
            return `left: ${this.minPercent}%; width: ${this.maxPercent - this.minPercent}%`;
        },

        minThumbStyle() { return `left: ${this.minPercent}%`; },
        maxThumbStyle() { return `left: ${this.maxPercent}%`; },

        minBadgeStyle() {
            return `left: ${this.minPercent}%; transform: translateX(-${this.minPercent}%)`;
        },

        maxBadgeStyle() {
            return `left: ${this.maxPercent}%; transform: translateX(-${this.maxPercent}%)`;
        },

        mergedBadgeStyle() {
            return `left: ${(this.minPercent + this.maxPercent) / 2}%`;
        },

        valueTextFor(value) {
            const mapped = this.marksMap[String(value)];

            return mapped === undefined || mapped === null ? String(value) : mapped;
        },

        /** The merged badge's text, and the live region's sentence. */
        mergedBadgeText() {
            return `${this.valueTextFor(this.minVal)} – ${this.valueTextFor(this.maxVal)}`;
        },

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
            this._markGesture();
            const newVal = this.minVal + (direction * this._step);
            this.minVal = Math.max(this._min, Math.min(newVal, this.maxVal - this._step));
            this._dispatchInputEvent();
            this._commit();
        },

        /**
         * Step the maximum value by direction (-1 or +1).
         */
        stepMax(direction) {
            this._markGesture();
            const newVal = this.maxVal + (direction * this._step);
            this.maxVal = Math.min(this._max, Math.max(newVal, this.minVal + this._step));
            this._dispatchInputEvent();
            this._commit();
        },

        /**
         * Tell the optimistic layer the gesture starts HERE, before the pair
         * moves.
         *
         * Both commit paths change `minVal`/`maxVal` before `_commit()` runs —
         * a keypress steps first, a drag moves the thumb for the whole gesture.
         * Without this the layer would snapshot the value it is about to be
         * handed, and a refused range would roll back onto itself: the thumb
         * stays where the user left it and the refusal is silent.
         *
         * Looked up rather than assumed, like `run` below: without a layer this
         * component behaves exactly as it did before, down to the byte.
         */
        _markGesture() {
            if (typeof this.mark === 'function') {
                this.mark();
            }
        },

        /**
         * Hand the pair to the optimistic layer, if one is nested here.
         *
         * §10 — the commit boundary. A keypress calls this at once, because one
         * press IS a completed decision; a drag calls it only at pointerup, not
         * per frame. There is no timer either way.
         *
         * `run` is looked up rather than assumed: without the layer this
         * component behaves exactly as before, down to the byte, which is the
         * property the whole opt-in shape rests on.
         */
        _commit() {
            if (typeof this.run === 'function') {
                this.run([this.minVal, this.maxVal]);
            }
        },

        /**
         * Start drag on a thumb.
         */
        startDrag(handle, event) {
            event.preventDefault();
            // The gesture starts at pointerdown, not at the pointerup that
            // commits it — the pair moves for every frame in between.
            this._markGesture();
            this._dragging = handle;

            // Cache the track's rect ONCE per drag — the track is stationary while
            // dragging, so re-reading getBoundingClientRect on every pointermove (a
            // hot path) would force a needless layout read per frame. Mirrors the
            // color-picker drag optimization.
            this._dragRect = this.$refs.track ? this.$refs.track.getBoundingClientRect() : null;

            const onMove = (e) => this._onDrag(e);
            const onUp = () => {
                // The commit boundary for a drag. pointercancel lands here too,
                // and that is not an oversight: a canceled drag still leaves
                // the thumb somewhere, and a value on screen the server was
                // never told is the one state this layer exists to prevent.
                this._commit();

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

            // Enable the fade only AFTER the first measurement has painted.
            // Deferring by a frame is what makes the initial state a paint
            // rather than an animation — setting it synchronously here would
            // still let the very first `_merged` flip transition.
            if (! this._ready) {
                requestAnimationFrame(() => { this._ready = true; });
            }
        },

        /** x-effect entry point: re-measure after the values settle. */
        /**
         * Re-measure whenever either handle moves.
         *
         * Bound to `x-effect`, which re-runs whatever its expression READ. The
         * template used to spell that out as `minVal; maxVal; remeasure()` —
         * three statements, which Alpine's CSP build does not parse. The reads
         * have to stay, and stay BEFORE the call: an effect only tracks what it
         * actually touches, so dropping them would leave the geometry stale
         * until something else happened to re-run it.
         */
        remeasureOnValueChange() {
            // Deliberately assigned rather than discarded: a bare member
            // expression statement is what a minifier removes first, and the
            // whole point is that reading them registers the dependency.
            const tracked = [this.minVal, this.maxVal];

            this.remeasure();

            return tracked.length;
        },

        remeasure() {
            this.$nextTick(() => this._measureBubbles());
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
