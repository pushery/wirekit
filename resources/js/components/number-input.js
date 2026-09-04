/**
 * Number input — the stepper's arithmetic.
 *
 * This was an inline `x-data` and could not stay: the literal declared getters
 * and methods, which Alpine's CSP build does not parse. Under a strict
 * Content-Security-Policy both stepper buttons rendered, looked enabled, and
 * did nothing at all.
 *
 * Two arithmetic decisions are load-bearing, and are why this is more than
 * `value + step`:
 *
 *   Precision. Binary floating point turns 19.01 + 0.01 into
 *   19.020000000000003 — which the field then SHOWS, and which oscillates
 *   between representations on every further click. Each result is snapped back
 *   to the decimal places the step itself implies.
 *
 *   Grid snapping. From an off-grid value the step must move to the next grid
 *   POINT, not add to the value: with value 1.78 and step 0.1, adding gives
 *   1.88, which rounds to 1.9 and skips 1.8 entirely. Snapping matches the W3C
 *   contract for a native <input type="number">.
 *
 * @param {Object}  config
 * @param {number}  config.value  starting value
 * @param {?number} config.min    lower bound, or null for unbounded
 * @param {?number} config.max    upper bound, or null for unbounded
 * @param {number}  config.step   grid spacing
 */
export default function wirekitNumberInput(config = {}) {
    return {
        value: config.value,
        // Normalized to null rather than left undefined: every bound check below
        // reads `!== null`, and `undefined` passes it — an absent bound would
        // then be compared against, and produce NaN.
        min: config.min ?? null,
        max: config.max ?? null,
        step: config.step ?? 1,

        /**
         * Decimal places implied by the step. `5` → 0, `0.1` → 1, `0.01` → 2,
         * `0.001` → 3. A non-fractional or scientific-notation step yields 0,
         * which makes round() a no-op — integer steps stay exact.
         */
        get precision() {
            const text = String(this.step);
            const dot = text.indexOf('.');

            return dot === -1 ? 0 : text.length - dot - 1;
        },

        /**
         * toFixed() returns a string; Number() converts it back, so the field
         * shows 19.02 rather than 19.020000000000003.
         */
        round(n) {
            return Number(n.toFixed(this.precision));
        },

        get atMin() {
            return this.min !== null && this.value <= this.min;
        },

        get atMax() {
            return this.max !== null && this.value >= this.max;
        },

        /**
         * Step down to the previous grid point, anchored at `min` (or 0).
         *
         * The 1e-10 tolerance absorbs binary-float drift, so a value that is
         * on-grid in intent but stored as 1.7999999998 steps to the correct
         * neighbor rather than to itself.
         */
        decrease() {
            const origin = this.min !== null ? this.min : 0;
            const ratio = (this.value - origin) / this.step;
            const prevSteps = Math.ceil(ratio - 1e-10) - 1;
            const next = this.round(origin + prevSteps * this.step);

            this.value = this.min !== null ? Math.max(this.min, next) : next;
            this._commit();
        },

        /** Step up to the next grid point. The mirror of decrease(). */
        increase() {
            const origin = this.min !== null ? this.min : 0;
            const ratio = (this.value - origin) / this.step;
            const nextSteps = Math.floor(ratio + 1e-10) + 1;
            const next = this.round(origin + nextSteps * this.step);

            this.value = this.max !== null ? Math.min(this.max, next) : next;
            this._commit();
        },

        /**
         * Hand the value to the optimistic layer, if one is nested here.
         *
         * A stepper click IS a completed decision, so it commits at once;
         * there is nothing continuous to wait out. The FIELD commits separately,
         * when it is left, because typing is not finished until the reader is.
         *
         * No `mark()` here, and the reason is the exit rather than an oversight:
         * this component takes `failure: 'keep'`, so a refusal never writes the
         * value back and the baseline is never read. Marking a gesture whose
         * snapshot nothing consumes would be ceremony.
         *
         * `run` is looked up rather than assumed: without the layer this
         * component behaves exactly as before, down to the byte.
         */
        _commit() {
            if (typeof this.run === 'function') {
                this.run(this.value);
            }
        },

        /**
         * Normalize on blur — the field accepts anything a keyboard can
         * produce, including nothing at all. Rounding here too keeps the typed
         * path and the stepped path on the same precision contract.
         */
        clamp(val) {
            let v = Number(val);

            if (isNaN(v)) {
                v = this.min ?? 0;
            }

            if (this.min !== null) {
                v = Math.max(this.min, v);
            }

            if (this.max !== null) {
                v = Math.min(this.max, v);
            }

            return this.round(v);
        },
    };
}
