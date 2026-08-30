/**
 * WireKit Sidebar Listbox — the keyboard model for a sidebar in selection mode.
 *
 * A navigating sidebar and a SELECTING one are two different ARIA contracts, and the
 * difference is not cosmetic. A navigation column is a list of links: each one is in the
 * tab order, Enter follows it, and the current page is marked `aria-current`. A selection
 * column is one control holding many choices: the CONTAINER is in the tab order once, the
 * arrows move an `aria-activedescendant` marker without moving focus, and the chosen row
 * is marked `aria-selected`. Emitting both would announce a page you are on AND a value
 * you have picked, which are not the same claim.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/listbox/
 *
 * WHY THERE IS NO OBSERVER IN HERE. The option list is re-read on demand rather than
 * watched. A MutationObserver would need a matching disconnect, and the house rules for
 * these plugins exist because a missing one throws a null-callback error into every
 * developer's browser tests. Re-reading costs a `querySelectorAll` on a list that is a
 * navigation column — tens of rows, not thousands — and it survives a Livewire morph for
 * free, which an observer only does if somebody remembers to re-attach it.
 */

/**
 * @param {Object} config
 * @param {string} [config.value] - The value selected when the column first renders.
 */
export default function wirekitSidebarListbox(config = {}) {
    return {
        /** The option currently marked by `aria-activedescendant`. */
        activeId: null,

        /** The chosen value. Mirrors what the rows render as `aria-selected`. */
        selectedValue: typeof config.value === 'string' ? config.value : null,

        initListbox() {
            const options = this.listboxOptions();
            if (options.length === 0) return;

            // Start on the selected row when there is one — an arrow press should
            // continue from where the reader is, not from the top of a list they have
            // already made a choice in.
            const selected = options.find((el) => el.getAttribute('aria-selected') === 'true');

            this.activeId = (selected || options[0]).id || null;
        },

        /**
         * The rows, in document order, read fresh.
         *
         * Scoped to `$el` so a nested listbox — a filter column inside a shell that also
         * has one — cannot capture the other's rows.
         */
        listboxOptions() {
            // `$root`, not `$el`. Today every caller of this reaches it from a handler on the
            // container itself, so the two are the same element — but `selectOption()` is
            // called from a directive on an OPTION, and one refactor moving a call across
            // that line would search inside a row and find nothing. The same shape made a
            // sibling component's gate inert while every markup assertion stayed green.
            const root = this.$root || this.$el;
            if (!root || typeof root.querySelectorAll !== 'function') return [];

            return Array.from(root.querySelectorAll('[role="option"]'));
        },

        /**
         * Move the marker by `delta`, clamped rather than wrapped.
         *
         * Clamped on purpose: the APG allows either, and a column that jumps from the last
         * row back to the first is disorienting when the list is long enough to scroll —
         * the view leaps and nothing announces why.
         */
        moveActive(delta) {
            const options = this.listboxOptions();
            if (options.length === 0) return;

            const current = options.findIndex((el) => el.id === this.activeId);
            const next = Math.min(options.length - 1, Math.max(0, (current === -1 ? 0 : current) + delta));

            this.markActive(options[next]);
        },

        moveToFirst() {
            const options = this.listboxOptions();
            if (options.length > 0) this.markActive(options[0]);
        },

        moveToLast() {
            const options = this.listboxOptions();
            if (options.length > 0) this.markActive(options[options.length - 1]);
        },

        markActive(el) {
            if (!el) return;

            this.activeId = el.id || null;

            // Keep the marked row in view. Focus never moves in this pattern, so the
            // browser will not scroll for us — without this the marker walks off the
            // bottom of a scrolling column and the reader follows nothing.
            if (typeof el.scrollIntoView === 'function') {
                el.scrollIntoView({ block: 'nearest' });
            }
        },

        /**
         * Choose the marked row.
         *
         * Clicks the row rather than reimplementing what choosing means: the developer's
         * own handler — `wire:click`, an `x-on:click`, whatever they put on the item —
         * already sits there, and the keyboard must reach exactly the same code the
         * pointer does. A separate keyboard path is how the two drift.
         */
        selectActive() {
            const el = this.listboxOptions().find((o) => o.id === this.activeId);
            if (!el) return;

            this.selectedValue = el.getAttribute('data-wk-option-value');
            el.click();
        },

        /** Pointer selection, so both routes end in the same state. */
        selectOption(el) {
            if (!el) return;

            this.markActive(el);
            this.selectedValue = el.getAttribute('data-wk-option-value');
        },

        isSelected(value) {
            return this.selectedValue !== null && this.selectedValue === value;
        },
    };
}
