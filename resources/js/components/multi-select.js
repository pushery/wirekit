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
 * @param {string} [config.id] - DOM id stem the option ids are minted from
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
            // The list may be shorter than when it was last closed — a pointer
            // pick or a Backspace removal both change it — so the index gets
            // pulled back in before it is published as an active descendant.
            this._clampHighlight();
        },

        // Seed from the `value` prop so pre-selected pills render on load.
        // Copy the array (don't alias config) so splice/push never mutate it.
        selected: Array.isArray(config.value) ? [...config.value] : [],
        filter: '',
        dropdownOpen: false,
        // Where the keyboard is standing, as an index into `filteredOptions`.
        // Focus stays on the filter input for the whole interaction — that is
        // what a combobox is — so this index, published through
        // `aria-activedescendant`, is the ONLY thing that tells a reader which
        // option Enter would take. Without it the control announced a listbox
        // it gave no way to reach.
        highlight: 0,
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

            // Follow the highlight with the panel's scroll box. Every mover —
            // both arrow keys, Home, End, a fresh filter, a pick that shortens
            // the list — writes `highlight` and nothing else, so one watcher
            // covers all of them and a mover added later cannot forget to
            // scroll. $nextTick because Home and End can open the list and jump
            // in one keystroke, so the row for the new index is rendered by the
            // same flush that moved the index.
            this.$watch('highlight', () => {
                this.$nextTick(() => this._revealHighlight());
            });
        },

        // Alpine teardown (Livewire morph / SPA nav): stop autoUpdate if the panel
        // was still open, since the $watch only fires on an open→closed CHANGE.
        destroy() {
            this._stopAutoUpdate?.();
            this._stopAutoUpdate = null;
        },
        _options: config.options || [],
        // The stem every option id is built from. Handed in by the Blade rather
        // than minted here so the row's `id` and the input's
        // `aria-activedescendant` are two readings of ONE string — the pairing
        // comes apart the moment those are written independently.
        _id: config.id || null,

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

        // ── Keyboard model ──────────────────────────────────────────────────
        //
        // The WAI-ARIA combobox pattern, which this control announced through
        // `role="combobox"` + `aria-haspopup="listbox"` + `aria-autocomplete="list"`
        // long before it implemented it: the options are `role="option"` divs
        // with no tab stop, so a pointer could reach every one of them and a
        // keyboard could reach none. Filtering worked, picking did not.

        /**
         * The DOM id of the option at `index`.
         *
         * Returns null when the component was given no id stem, which keeps
         * `aria-activedescendant` absent rather than pointing at `null-opt-3`.
         */
        optionId(index) {
            return this._id ? this._id + '-opt-' + index : null;
        },

        /**
         * What `aria-activedescendant` publishes, or null when it must not be
         * set at all — a closed list has no active option, and an index past
         * the end of the filtered list would name an element nobody rendered.
         */
        get activeDescendantId() {
            if (! this.dropdownOpen || ! this.filteredOptions[this.highlight]) {
                return null;
            }

            return this.optionId(this.highlight);
        },

        /**
         * What the polite region reads after a pick or a removal.
         *
         * A combobox normally announces a pick for free: `aria-selected` flips
         * on the very row `aria-activedescendant` names. That cannot happen
         * here, because `filteredOptions` DROPS an option the moment it is
         * chosen — the row the reader was standing on stops existing. So the
         * selection itself is read back, as the labels the pills now show.
         *
         * Emptying the selection reads nothing: a live region that becomes
         * empty announces nothing anywhere, and the field's own placeholder
         * returns visibly at the same moment.
         */
        get selectionAnnouncement() {
            return this.selected.map((value) => this.getLabel(value)).join(', ');
        },

        /** Typing narrows the list, so the old index means nothing — start over. */
        openAndReset() {
            this.dropdownOpen = true;
            this.highlight = 0;
        },

        /**
         * Arrow into the list.
         *
         * Opening IS the first move: a closed list has no active option, so the
         * first ArrowDown must land ON the first row rather than step past it,
         * and the first ArrowUp lands on the last.
         */
        openAndMove(delta) {
            if (! this.dropdownOpen) {
                this.dropdownOpen = true;

                if (delta > 0) {
                    this.highlightFirst();
                } else {
                    this.highlightLast();
                }

                return;
            }

            this.moveHighlight(delta);
        },

        openAtFirst() {
            this.dropdownOpen = true;
            this.highlightFirst();
        },

        openAtLast() {
            this.dropdownOpen = true;
            this.highlightLast();
        },

        /**
         * Walk one step in `delta` direction.
         *
         * Stops at either end rather than wrapping — the same choice the
         * combobox makes, so the two comboboxes in this library answer an arrow
         * key alike. No disabled-option skip here: multi-select takes a plain
         * value => label map, so every row it renders is choosable.
         */
        moveHighlight(delta) {
            const max = this.filteredOptions.length - 1;

            if (max < 0) {
                return;
            }

            this.highlight = Math.max(0, Math.min(max, this.highlight + delta));
        },

        highlightFirst() {
            if (this.filteredOptions.length > 0) {
                this.highlight = 0;
            }
        },

        highlightLast() {
            const max = this.filteredOptions.length - 1;

            if (max >= 0) {
                this.highlight = max;
            }
        },

        /**
         * Pointing at a row makes it the active one, so the pointer and the
         * arrow keys share one idea of where the user is — otherwise hovering
         * row three and pressing Enter takes row one.
         *
         * The flag suppresses the scroll for THIS move only. The cursor is
         * already on the row, so scrolling it into view would slide the list
         * out from under the pointer, which then hovers a different row, which
         * scrolls again.
         */
        _movedByPointer: false,

        hoverOption(index) {
            this._movedByPointer = true;
            this.highlight = index;
        },

        /** Enter on the highlighted row — the keyboard's version of a click. */
        activateHighlighted() {
            const option = this.filteredOptions[this.highlight];

            if (option) {
                this.toggle(option.value);
            }
        },

        /**
         * Enter, on the plain (non-optimistic) path.
         *
         * Reopening a list the user closed with Escape is the documented
         * behavior and costs nothing; only the second Enter chooses.
         */
        onEnter() {
            if (! this.dropdownOpen) {
                this.dropdownOpen = true;

                return;
            }

            this.activateHighlighted();
        },

        /**
         * Enter, on the optimistic path: the selection it would produce, or
         * `undefined` when it would produce none.
         *
         * `runIf()` reads exactly that distinction — `run(undefined)` would ask
         * the server for a selection nobody made and then roll back from it.
         * The reopen branch returns `undefined` for the same reason: opening a
         * list is not a mutation.
         */
        enterNext() {
            if (! this.dropdownOpen) {
                this.dropdownOpen = true;

                return undefined;
            }

            const option = this.filteredOptions[this.highlight];

            if (! option) {
                return undefined;
            }

            return this.nextWith(option.value);
        },

        /**
         * Keep the active row inside the scrolling panel.
         *
         * Focus never enters the list, so the browser scrolls nothing on our
         * behalf — without this the marker walks off the bottom of a capped
         * panel and Enter takes an option the reader cannot see.
         *
         * By id rather than through `$refs`: the panel is teleported out of
         * this component's subtree, and a ref does not survive that move.
         */
        _revealHighlight() {
            if (this._movedByPointer) {
                this._movedByPointer = false;

                return;
            }

            const id = this.optionId(this.highlight);

            if (! id || typeof document === 'undefined') {
                return;
            }

            const option = document.getElementById(id);

            if (option && typeof option.scrollIntoView === 'function') {
                option.scrollIntoView({ block: 'nearest' });
            }
        },

        /**
         * Pull the index back inside the list it points into.
         *
         * A pick REMOVES that option from `filteredOptions`, so the index the
         * keyboard was standing on can end up past the end — and then
         * `aria-activedescendant` names an id no element carries, which reads
         * to a screen reader as the control having lost its place. Runs on the
         * optimistic rollback too, where the option comes back and the list
         * grows again.
         */
        _clampHighlight() {
            const max = this.filteredOptions.length - 1;

            this.highlight = max < 0 ? 0 : Math.min(this.highlight, max);
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
            this._clampHighlight();
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
        /**
         * The field and the panel, resolved so that a nested `x-data` cannot hide them.
         *
         * `x-ref` registers into the NEAREST `x-data` scope, and with `optimistic` set this
         * component wraps its own field in a second one — `wirekitOptimistic(...)` opens
         * before the field in the Blade and closes after the panel's teleport template. Every
         * ref of this component therefore lands in that CHILD scope, while `_place()` runs in
         * the parent and sees an empty registry. Measured on the optimistic preview before
         * this change: `$refs.field` null, `$refs.panel` null, `_stopAutoUpdate` never set,
         * no inline top/left/width/max-height on the panel — and the panel sitting 616.75px
         * below the field's bottom, 12px off its inline start and 334px narrower than it.
         * All four things `_place()` exists for were absent, and nothing threw.
         *
         * ⚠️ THE TELEPORT IS NOT THE CAUSE, though it looks like the obvious suspect and was
         * reported as one. Alpine's closest-element walk follows `_x_teleportBack`, so a ref
         * on a teleported node resolves back through its template into the component that
         * owns it — verified: outside the optimistic wrapper `$refs.panel` resolves to
         * exactly the teleported panel and the placement is correct. The sibling combobox
         * carries a comment blaming the teleport for its own version of this; the half of
         * that comment which holds is the one about the nested optimistic scope.
         *
         * The panel goes by id because it has one and an id is scope-free. The field has no
         * id, so it is found by its ref ATTRIBUTE — present in the rendered HTML regardless
         * of which scope registered it — scoped to `$root` so a second multi-select on the
         * page cannot answer for this one.
         */
        _panelElement() {
            if (this._id && typeof document !== 'undefined') {
                const byId = document.getElementById(this._id + '-listbox');

                if (byId) {
                    return byId;
                }
            }

            return this.$refs?.panel ?? null;
        },

        _fieldElement() {
            return this.$refs?.field ?? this.$root?.querySelector?.('[x-ref="field"]') ?? null;
        },

        async _place() {
            const field = this._fieldElement();
            const panel = this._panelElement();

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
