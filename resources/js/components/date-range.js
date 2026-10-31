import { observeServerValue } from '../utils/server-value.js';

/**
 * The two fields of a date range, each holding the other in bounds: `s` and `e` mirror the start
 * and the end, so that the end cannot precede the start and the start cannot follow the end.
 *
 * The dates are read from the fields rather than written into `x-data`, so that the expression is
 * the same for every range. A Livewire morph that changed it would start the scope again with the
 * dates of the request, and the bounds would hold the reader to a range they had changed since.
 *
 * A date the server renders later reaches a field's `value` attribute, and the bounds read the
 * fields once the render has settled: with a caller's model, the model holds what the server sent
 * unless the reader changed it since, and without one the fields hold the dates they show. A date
 * the reader picks arrives through the fields' `change`, which the template writes into `s` and `e`.
 *
 * Lifecycle: `_stopFieldWatch`, the observers on both fields' `value` attributes
 * (utils/server-value.js), released in destroy().
 */
export default function wirekitDateRange() {
    return {
        s: '',
        e: '',
        _stopFieldWatch: null,

        init() {
            const [start, end] = this._fields();

            this.s = start?.getAttribute('value') ?? '';
            this.e = end?.getAttribute('value') ?? '';

            const read = () => {
                if (typeof this.$nextTick === 'function') {
                    this.$nextTick(() => this._readFields());
                }
            };
            const stops = [start, end].map((field) => observeServerValue(field, read, 'value'));

            this._stopFieldWatch = () => stops.forEach((stop) => stop());
        },

        destroy() {
            this._stopFieldWatch?.();
            this._stopFieldWatch = null;
        },

        /**
         * The start and the end field, in that order, or null for one that is missing. Looked up
         * under the root, because init() runs before the elements inside it register their refs.
         *
         * @returns {Array<?HTMLInputElement>}
         */
        _fields() {
            const fields = typeof this.$root?.querySelectorAll === 'function' ? Array.from(this.$root.querySelectorAll('input[type="date"]')) : [];

            return [fields[0] ?? null, fields[1] ?? null];
        },

        /** The dates the fields hold, into the bounds. */
        _readFields() {
            const [start, end] = this._fields();

            if (start && typeof start.value === 'string') {
                this.s = start.value;
            }

            if (end && typeof end.value === 'string') {
                this.e = end.value;
            }
        },
    };
}
