/**
 * The determinate bar, driven from a value that only exists in the browser.
 *
 * `<x-wirekit::progress value="…">` reads its value in PHP, at render time. An
 * upload percentage does not exist then — it arrives later, in Alpine state that
 * Livewire events advance. Binding to the rendered element does not help either:
 * `x-bind:value` sets a `value` attribute on a `<div>` that nothing reads, so the
 * bar stays indeterminate, `show-value` prints nothing, and there is no
 * `aria-valuenow` at all. A screen reader then hears "progressbar" with no
 * number, which for `role="progressbar"` is the one attribute carrying the state.
 *
 * ⚠️ EVERY DERIVED VALUE IS A METHOD, AND THAT IS THE CSP CONSTRAINT RATHER THAN
 * A STYLE CHOICE. Under Alpine's CSP build there is no expression evaluator, so
 * `x-bind:style="'width: ' + percent + '%'"` is never evaluated and the binding
 * goes inert with nothing reported. A call to a method on the component's own
 * data IS allowed — `reading-progress` already binds `x-bind:style="fillStyle()"`
 * for the same reason. So the arithmetic lives here, and the template only ever
 * names a method.
 *
 * The value itself is read through Alpine's scope chain: a nested `x-data` sees
 * the properties of the one above it, so `from: 'percent'` resolves against the
 * caller's own state without this component knowing anything about it.
 */
export default function wirekitProgress(config = {}) {
    return {
        // The PROPERTY NAME to read from the surrounding scope, not the value.
        _from: typeof config.from === 'string' && config.from !== '' ? config.from : 'value',

        _max: Number.isFinite(Number(config.max)) && Number(config.max) > 0 ? Number(config.max) : 100,

        /**
         * The caller's current value, or null while there is not one yet.
         *
         * Read with bracket notation off `this`, which is Alpine's merged reactive
         * proxy — so the read is tracked and the bar re-renders when the caller's
         * property changes, exactly as a direct reference would.
         *
         * A missing property answers `undefined`, and a caller can legitimately
         * start at null before the first progress event. Both mean "nothing to
         * show yet" and both must land on the indeterminate branch rather than on
         * a bar drawn at zero — a 0% bar is a claim that no work has been done,
         * which is a different statement from not knowing.
         */
        _value() {
            const raw = this[this._from];

            if (raw === null || raw === undefined || raw === '') {
                return null;
            }

            const n = Number(raw);

            return Number.isFinite(n) ? n : null;
        },

        /** The value held inside the bar's own range. */
        _clamped() {
            const v = this._value();

            return v === null ? null : Math.min(Math.max(v, 0), this._max);
        },

        /** True while there is no usable value — the bar animates instead of claiming one. */
        isIndeterminate() {
            return this._clamped() === null;
        },

        /**
         * The fill width.
         *
         * Not rounded: the visible readout and `aria-valuenow` are both derived
         * from the same clamped number, and rounding only here is how a bar drawn
         * at 94% ends up beside the text "4 / 5".
         */
        fillStyle() {
            const c = this._clamped();

            return c === null ? '' : `width: ${(c / this._max) * 100}%`;
        },

        /** What a screen reader announces. Empty while indeterminate, so the attribute is absent. */
        ariaValueNow() {
            const c = this._clamped();

            return c === null ? null : String(c);
        },

        _determinate: typeof config.determinate === 'string' ? config.determinate : '',
        _indeterminate: typeof config.indeterminate === 'string' ? config.indeterminate : '',

        /**
         * The fill's class SET, not one class on top of a static list.
         *
         * ⚠️ The two states differ in LAYOUT, not just in appearance: the
         * indeterminate keyframes animate `left`, so that variant needs
         * `absolute inset-y-0` and supplies its own width, while the determinate
         * one is `h-full` with a width transition. Binding a single class on top
         * of a static list cannot express that — and worse, Alpine only removes
         * classes it added itself, so a statically present `wk-progress-indeterminate`
         * survives every binding. Measured in the browser: the value resolved and
         * the width was right while the bar still animated.
         *
         * Both strings arrive from Blade so the Tailwind literals stay in the
         * template, where the class-drift guards can see them.
         */
        fillClasses() {
            return this.isIndeterminate() ? this._indeterminate : this._determinate;
        },

        /** The visible "42 / 100" readout, in the same shape the server-rendered branch prints. */
        valueText() {
            const c = this._clamped();

            return c === null ? '' : `${c} / ${this._max}`;
        },
    };
}
