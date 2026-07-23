/**
 * WireKit Multi-Select Alpine Component.
 *
 * Combobox with multi-value selection. Selected values display as
 * removable pills inside the input. Filter text narrows the dropdown.
 *
 * @param {Object} config
 * @param {Array<{value: string, label: string}>} config.options - Available options
 * @param {string} config.name - Input name for form submission
 * @param {Array<string>} [config.value] - Option keys to pre-select on load
 */
import { position } from '../utils/floating.js';

export default function wirekitMultiSelect(config = {}) {
    return {
        // Seed from the `value` prop so pre-selected pills render on load.
        // Copy the array (don't alias config) so splice/push never mutate it.
        selected: Array.isArray(config.value) ? [...config.value] : [],
        filter: '',
        dropdownOpen: false,

        init() {
            // Position the panel each time it opens. It is `fixed` (to escape a
            // clipping card) and therefore needs an explicit anchor + width; see
            // _place(). $nextTick so the panel is in the DOM before measuring.
            this.$watch('dropdownOpen', (open) => {
                if (open) {
                    this.$nextTick(() => this._place());
                }
            });
        },
        _options: config.options || [],

        /**
         * Get filtered options based on current filter text.
         * Excludes already-selected values.
         */
        get filteredOptions() {
            const term = this.filter.toLowerCase();
            return this._options.filter(
                (opt) =>
                    !this.selected.includes(opt.value) &&
                    opt.label.toLowerCase().includes(term)
            );
        },

        /**
         * Get the label for a value.
         */
        getLabel(value) {
            return this._options.find((o) => o.value === value)?.label || value;
        },

        /**
         * Toggle selection of an option.
         */
        toggle(value) {
            const idx = this.selected.indexOf(value);
            if (idx >= 0) {
                this.selected.splice(idx, 1);
            } else {
                this.selected.push(value);
            }
            this.filter = '';
            this.$refs.filterInput?.focus();
        },

        /**
         * Deselect (remove) a selected value.
         */
        deselect(value) {
            const idx = this.selected.indexOf(value);
            if (idx >= 0) this.selected.splice(idx, 1);
        },

        /**
         * Backspace on empty filter removes the last selected value.
         */
        onBackspace(event) {
            if (event.target.value === '' && this.selected.length > 0) {
                this.selected.pop();
            }
        },

        /**
         * Anchor the panel to the field.
         *
         * The panel used to be `absolute` inside the field wrapper, which made it a
         * descendant of any clipping ancestor — put the field in a card and the open
         * panel was cut off at the card's edge, because clipping is not a z-index
         * question. Positioning it `fixed` against the field escapes that, and
         * `matchReferenceWidth` carries over the width that `w-full` used to provide
         * for free. `fitViewport` caps the height to the room actually available so a
         * long list scrolls instead of running past the fold.
         */
        async _place() {
            const field = this.$refs.field;
            const panel = this.$refs.panel;

            if (! field || ! panel) {
                return;
            }

            await position(field, panel, {
                placement: 'bottom-start',
                offset: 4,
                fitViewport: true,
                matchReferenceWidth: true,
            });
        },
    };
}
