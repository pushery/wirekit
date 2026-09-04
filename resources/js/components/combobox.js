/**
 * Combobox — a filtering select implementing the WAI-ARIA combobox pattern.
 *
 * This was 140 lines of object literal inside an `x-data` attribute. It had to
 * move: Alpine's CSP build parses expressions instead of compiling them, and
 * method shorthand in an object literal is not in its grammar — so an inline
 * `x-data` cannot define a method at all. Under a strict Content-Security
 * Policy the combobox rendered as an inert text field.
 *
 * Two things are worth knowing before changing anything here.
 *
 * **Disabled options are skipped, not just unclickable.** Arrow keys, Home and
 * End all walk past them. A keyboard user who lands on an option they cannot
 * choose has no way to tell why, so the navigation never stops there.
 *
 * **Only one combobox on a page may be open.** Opening one announces on
 * `window`, and every other instance closes. Without it a long option list
 * spills over the next combobox — visible on any page that stacks two. The
 * mechanism is shared with every other dropdown-like overlay now; see
 * `utils/overlay-coordination.js` for why the announcement carries an
 * identity and why the channel is per-component rather than global.
 *
 * @param {Object} config
 * @param {*}      config.value    the initially selected option's value
 * @param {Array}  config.options  normalized `{ value, label, group?, disabled? }`
 */
import { coordinateOverlay } from '../utils/overlay-coordination.js';

export default function wirekitCombobox(config = {}) {
    return {
        open: false,
        query: '',
        selected: config.value ?? null,
        highlight: 0,
        allOptions: Array.isArray(config.options) ? config.options : [],

        // Cross-close channel — see utils/overlay-coordination.js.
        _coordination: null,

        // Set by hoverOption() so the scroll that follows a highlight change is
        // skipped for that one move. See _revealHighlight().
        _movedByPointer: false,

        get filtered() {
            if (this.query === '') {
                return this.allOptions;
            }

            const q = this.query.toLowerCase();

            return this.allOptions.filter((o) => o.label.toLowerCase().includes(q));
        },

        /**
         * The filtered options bucketed by group, in first-seen order.
         *
         * Each option keeps `_idx`, its position in the FLAT filtered list, so
         * grouping changes nothing about the keyboard model or
         * aria-activedescendant. A group whose options all filtered out is
         * absent rather than empty, because it is built from `filtered`.
         */
        get filteredGroups() {
            const groups = [];
            const byLabel = new Map();

            this.filtered.forEach((opt, idx) => {
                const label = opt.group || null;
                let bucket = byLabel.get(label);

                if (! bucket) {
                    bucket = { label, options: [] };
                    byLabel.set(label, bucket);
                    groups.push(bucket);
                }

                bucket.options.push({ ...opt, _idx: idx });
            });

            return groups;
        },

        /** The value the hidden input submits — never null, so the field is present. */
        get submittedValue() {
            return this.selected ?? '';
        },

        /** A group's key for x-for; ungrouped options share one stable bucket. */
        groupKey(group) {
            return group && group.label ? group.label : '__wk_ungrouped';
        },

        init() {
            // Seed the query with the label of the initial value, if any.
            const match = this.allOptions.find((o) => o.value === this.selected);

            if (match) {
                this.query = match.label;
            }

            this._coordination = coordinateOverlay({
                channel: 'wirekit:combobox-open',
                onOther: () => { this.open = false; },
            });

            // Announce on every transition into the open state so siblings close.
            this.$watch('open', (val) => {
                if (! val) {
                    return;
                }

                this._coordination.announce();

                // Anchor the panel once it is shown. `fixed` lets it escape a
                // clipping card; wirekitPosition carries the field width over
                // and caps the height so a long list scrolls instead of running
                // past the fold.
                this.$nextTick(() => {
                    this._place();

                    // Reopening does not change `highlight`, so the watcher
                    // below stays quiet — and hiding the panel drops its scroll
                    // offset, which would put the marked option back out of
                    // view. Revealing on the open transition covers that.
                    this._revealHighlight();
                });
            });

            // Follow the highlight with the panel's scroll box. Every keyboard
            // mover — both arrow keys, Home, End, a fresh filter — writes
            // `highlight` and nothing else, so one watcher covers all of them
            // and a mover added later cannot forget to scroll. A pointer move
            // opts out at the other end; see hoverOption().
            //
            // $nextTick because the row for the new index may not exist yet:
            // Home and End open the list and jump in the same keystroke, so the
            // option is rendered by the same flush that moved the highlight.
            this.$watch('highlight', () => {
                this.$nextTick(() => this._revealHighlight());
            });
        },

        // Panel ids, handed in by the Blade so `_place()` can find the panels
        // after they teleport. See the note in _place() — a ref does not survive
        // the move and an id does.
        _listId: config.listId || null,
        _emptyId: config.emptyId || null,
        _inputId: config.inputId || null,

        _place() {
            // No-op on the core bundle, which ships no overlays and no position
            // helper — the panel simply stays put. The full bundle exposes it.
            if (typeof window.wirekitPosition !== 'function') {
                return;
            }

            // BOTH panels, because there are two: the options list and the
            // "No results" panel, which is the same box with different content.
            // Only the list was ever positioned — the empty state sat inline and
            // `fixed`, so it landed wherever its static position happened to be.
            // Teleporting them to escape the host's stacking context made that
            // visible rather than causing it: an unpositioned fixed element at
            // `<body>` goes to the viewport origin.
            // By ID, not by `$refs`. Alpine does not carry a ref across an
            // `x-teleport`: measured after the panels moved to `<body>`,
            // `$refs.cbxList` is null, so this loop ran over two nulls and
            // positioned nothing at all. The symptom looked like bad arithmetic —
            // a panel at 0,1117 against a field at 12,451 — and was the absence of
            // any arithmetic: a `fixed` element with no top/left sits at its static
            // position, and getComputedStyle reports that resolved.
            const panels = [
                this._listId ? document.getElementById(this._listId) : this.$refs.cbxList,
                this._emptyId ? document.getElementById(this._emptyId) : this.$refs.cbxEmpty,
            ];

            // The ANCHOR by id as well, and this is the half that actually bit.
            //
            // `x-ref` registers into the NEAREST `x-data` scope. With `optimistic`
            // set, the input sits inside the nested optimistic component — so every
            // ref of this component lands in the CHILD scope and `_place()`, which
            // lives in the parent, sees an empty registry. Measured: `$refs` has no
            // keys at all and `$refs.cbxInput` is null, so the positioner ran twice
            // against a null reference and did nothing. The panel then sat at its
            // static position and looked like a placement bug rather than an
            // absent one.
            const anchor = this._inputId
                ? document.getElementById(this._inputId)
                : this.$refs.cbxInput;

            if (! anchor) {
                return;
            }

            for (const panel of panels) {
                if (! panel) {
                    continue;
                }

                window.wirekitPosition(anchor, panel, {
                    placement: 'bottom-start',
                    offset: 4,
                    fitViewport: true,
                    matchReferenceWidth: true,
                });
            }
        },

        /**
         * Bring the active option inside the panel's visible area.
         *
         * The list is a capped scroller and the active option is published
         * through `aria-activedescendant`, so focus never moves into it — and a
         * browser only auto-scrolls what it focuses. Without this the marker
         * walks off the bottom after the first screenful and Enter chooses an
         * option the reader cannot see. `block: 'nearest'` scrolls only when the
         * row is actually outside, so a move that stays on screen costs nothing.
         *
         * By id rather than through `$refs`, for the reason spelled out in
         * `_place()`: the panels are teleported, and a ref does not survive the
         * move.
         */
        _revealHighlight() {
            if (this._movedByPointer) {
                this._movedByPointer = false;

                return;
            }

            if (! this._listId || typeof document === 'undefined') {
                return;
            }

            const option = document.getElementById(`${this._listId}-opt-${this.highlight}`);

            if (option && typeof option.scrollIntoView === 'function') {
                option.scrollIntoView({ block: 'nearest' });
            }
        },

        destroy() {
            this._coordination?.stop();
            this._coordination = null;
        },

        // ── Opening ─────────────────────────────────────────────────────────

        /** Typing opens the list and restarts the highlight at the top. */
        openAndReset() {
            this.open = true;
            this.highlight = 0;
        },

        /** Arrow into the list from the field. */
        openAndMove(delta) {
            this.open = true;
            this.moveHighlight(delta);
        },

        openAtFirst() {
            this.open = true;
            this.highlightFirst();
        },

        openAtLast() {
            this.open = true;
            this.highlightLast();
        },

        /** The chevron toggles, and returns focus to the field either way. */
        toggleAndFocus() {
            this.open = ! this.open;

            if (this.$refs.cbxInput) {
                this.$refs.cbxInput.focus();
            }
        },

        /** Hovering an enabled option previews it; a disabled one is inert. */
        hoverOption(option, index) {
            if (! option.disabled) {
                // A hover is already looking at the row, so the scroll that
                // follows a keyboard move would only pull the list out from
                // under the pointer — the half-visible row at the panel edge is
                // the case: revealing it shifts a different option beneath the
                // cursor, and the next click lands on something else.
                this._movedByPointer = true;
                this.highlight = index;
            }
        },

        // ── Choosing ────────────────────────────────────────────────────────

        selectOption(opt) {
            if (opt.disabled) {
                return;
            }

            this.selected = opt.value;
            this.open = false;
            this._syncQuery();
        },

        /**
         * Make the text field agree with `selected`.
         *
         * Split out of selectOption() because the optimistic layer writes
         * `selected` itself, on the flip AND on the rollback, and the field has
         * to follow both. Deriving the label from the value rather than taking
         * it from the clicked option is what makes the rollback correct: after
         * an undo the field must read the label of the PREVIOUS selection, and
         * that option is not the one anybody clicked.
         *
         * A selection the option list does not contain clears the field rather
         * than leaving a stale label standing — the field would otherwise claim
         * a choice the component cannot show.
         */
        _syncQuery() {
            const match = this.allOptions.find((o) => o.value === this.selected);

            this.query = match ? match.label : '';
        },

        /**
         * Walk in `delta` direction to the next ENABLED option.
         *
         * Stops at either end rather than wrapping, and gives up after a full
         * pass so a list of only-disabled options cannot spin.
         */
        moveHighlight(delta) {
            const max = this.filtered.length - 1;

            if (max < 0) {
                return;
            }

            let next = this.highlight;

            for (let step = 0; step < this.filtered.length; step++) {
                next = Math.max(0, Math.min(max, next + delta));

                if (! this.filtered[next].disabled) {
                    this.highlight = next;

                    return;
                }

                if (next === 0 && delta < 0) {
                    return;
                }

                if (next === max && delta > 0) {
                    return;
                }
            }
        },

        /** Home — the first ENABLED option. */
        highlightFirst() {
            const max = this.filtered.length - 1;

            for (let i = 0; i <= max; i++) {
                if (! this.filtered[i].disabled) {
                    this.highlight = i;

                    return;
                }
            }
        },

        /** End — the last ENABLED option. */
        highlightLast() {
            const max = this.filtered.length - 1;

            for (let i = max; i >= 0; i--) {
                if (! this.filtered[i].disabled) {
                    this.highlight = i;

                    return;
                }
            }
        },

        activateHighlighted() {
            const option = this.filtered[this.highlight];

            if (option && ! option.disabled) {
                this.selectOption(option);
            }
        },

        /**
         * The value Enter would choose, or `undefined` when it would choose
         * nothing.
         *
         * The optimistic layer's `runIf()` reads that distinction: `undefined`
         * means there was no candidate and no request should go out, while a
         * real value — including a falsy one — is a choice. Without it, Enter
         * on an empty or all-disabled list would send the server a mutation for
         * a value nobody picked.
         */
        highlightedValue() {
            const option = this.filtered[this.highlight];

            if (! option || option.disabled) {
                return undefined;
            }

            return option.value;
        },

        clearSelection() {
            this.selected = null;
            this.query = '';
            this.open = false;

            // Fire input on the hidden field so wire:model sees the cleared
            // value. Assigning `selected` alone updates the bound attribute and
            // nothing else: Livewire syncs on the event, not on the value.
            //
            // $root, not $el. The clear button is a CHILD, and a lookup scoped
            // to it finds no hidden input — measured: the value cleared on
            // screen while zero input events fired, so wire:model kept the old
            // one. $root is the x-data element whatever the handler sits on.
            const hidden = this.$root.querySelector('input[type=hidden]');

            if (hidden) {
                hidden.value = '';
                hidden.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },
    };
}
