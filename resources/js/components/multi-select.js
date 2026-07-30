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
        /** Focus the filter and open the list — one act, so one method. */
        focusAndOpen() {
            if (this.$refs.filterInput) {
                this.$refs.filterInput.focus();
            }
            this.dropdownOpen = true;
        },

        // Seed from the `value` prop so pre-selected pills render on load.
        // Copy the array (don't alias config) so splice/push never mutate it.
        selected: Array.isArray(config.value) ? [...config.value] : [],
        filter: '',
        dropdownOpen: false,
        // Floating UI autoUpdate teardown handle — set in _place(), cleared when the
        // panel closes (the $watch else-branch) and on destroy() (torn down while
        // still open). Keeps the fixed panel following its field on scroll/resize
        // without leaking listeners (every teardown path must call stop()).
        _stopAutoUpdate: null,

        init() {
            // Position the panel each time it opens. It is `fixed` (to escape a
            // clipping card) and therefore needs an explicit anchor + width; see
            // _place(). $nextTick so the panel is in the DOM before measuring.
            this.$watch('dropdownOpen', (open) => {
                if (open) {
                    this.$nextTick(() => this._place());
                } else {
                    this._stopAutoUpdate?.();
                    this._stopAutoUpdate = null;
                }
            });
        },

        // Alpine teardown (Livewire morph / SPA nav): stop autoUpdate if the panel
        // was still open, since the $watch only fires on an open→closed CHANGE.
        destroy() {
            this._stopAutoUpdate?.();
            this._stopAutoUpdate = null;
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
        /**
         * The selection this value would produce, as a NEW array.
         *
         * The optimistic layer needs it: toggle() mutates `selected` in place
         * with splice/push, and an in-place mutation is invisible to a layer
         * that has to write the value itself in order to snapshot what it
         * replaced. Returning a fresh array keeps both halves honest — the
         * snapshot points at the old one, the write installs the new one.
         */
        nextWith(value) {
            const idx = this.selected.indexOf(value);

            if (idx >= 0) {
                return this.selected.filter((v) => v !== value);
            }

            return this.selected.concat([value]);
        },

        /**
         * The part of toggle() that is not the selection itself — and NOT the
         * focus move.
         *
         * Split out so the optimistic layer can run it after ITS write. It runs
         * on the rollback too, which is exactly why focus is not in here: an
         * undo arrives on the server's schedule, and pulling focus back to the
         * filter at that moment would take the user out of wherever they had
         * got to. Clearing the stale filter text is safe; moving focus is not.
         */
        _afterToggle() {
            this.filter = '';
        },

        toggle(value) {
            const idx = this.selected.indexOf(value);
            if (idx >= 0) {
                this.selected.splice(idx, 1);
            } else {
                this.selected.push(value);
            }
            this._afterToggle();
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

            this._stopAutoUpdate?.();
            const { stop } = await position(field, panel, {
                placement: 'bottom-start',
                offset: 4,
                fitViewport: true,
                matchReferenceWidth: true,
                // Follow the field on scroll/resize; this is the panel the v2.19.0
                // fixed-positioning switch made most visibly pin on scroll.
                autoReposition: true,
            });
            this._stopAutoUpdate = stop;
        },
    };
}
