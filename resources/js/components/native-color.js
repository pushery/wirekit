import { followPageModel, observeServerValue } from '../utils/server-value.js';

/**
 * The readout beside a native color field: `current` mirrors the color the field holds.
 *
 * The color is read from the field rather than written into `x-data`, so that the expression is
 * the same for every color. A Livewire morph that changed it would start the scope again with the
 * color of the request, and the field's `:value` would then put that color back on the swatch over
 * the one the reader picked since.
 *
 * A color the server renders later reaches the field's `value` attribute, and the readout reads the
 * field once the render has settled: with a caller's model, the model holds what the server sent
 * unless the reader picked since, and without one the field holds the color it shows. A color an
 * Alpine page's model writes in the browser reaches the readout through followPageModel(). The field
 * has `x-ref="wkNativeColor"`; it is looked up under the root here, because init() runs before the
 * elements inside it register their refs.
 *
 * Lifecycle: `_stopFieldWatch`, the observer on the field's `value` attribute
 * (utils/server-value.js), released in destroy().
 */
export default function wirekitNativeColor() {
    return {
        current: '',
        _stopFieldWatch: null,

        init() {
            const field = this._field();

            if (field) {
                this.current = field.getAttribute('value') ?? field.value ?? '';
            }

            this._stopFieldWatch = observeServerValue(field, () => {
                if (typeof this.$nextTick === 'function') {
                    this.$nextTick(() => this._readField());
                }
            }, 'value');

            // A color an Alpine page's model writes into the field reaches the readout, as one the
            // server renders does.
            this.$nextTick?.(() => followPageModel(this, this._field(), (value) => {
                if (typeof value === 'string' && value !== '' && value !== this.current) {
                    this.current = value;
                }
            }));
        },

        destroy() {
            this._stopFieldWatch?.();
            this._stopFieldWatch = null;
        },

        /** The native color field, or null. */
        _field() {
            return typeof this.$root?.querySelector === 'function' ? this.$root.querySelector('input[type="color"]') : null;
        },

        /** The color the field holds, into the readout. */
        _readField() {
            const field = this._field();

            if (field && typeof field.value === 'string' && field.value !== this.current) {
                this.current = field.value;
            }
        },
    };
}
