/**
 * WireKit Multi-Select Alpine Component.
 *
 * Combobox with multi-value selection. Selected values display as
 * removable pills inside the input. Filter text narrows the dropdown.
 *
 * @param {Object} config
 * @param {Array<{value: string, label: string}>} config.options - Available options
 * @param {string} config.name - Input name for form submission
 */
export default function wirekitMultiSelect(config = {}) {
    return {
        selected: [],
        filter: '',
        dropdownOpen: false,
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
    };
}
