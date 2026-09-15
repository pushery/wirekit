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
 * @param {Array}  config.options  normalized `{ value, label, group?, disabled? }`, plus the optional
 *                                 `media`, `iconRef`, `src`, `initials`, `description`, `keywords`
 *                                 and `selectedLabel` an option uses (see utils/option-media.js)
 * @param {string} [config.placement]   where the panel opens against the field (Floating UI placement)
 * @param {string} [config.panelWidth]  'trigger' matches the field; anything else lets the panel
 *                                      be wider than the field but never narrower
 * @param {boolean} [config.searchable] false renders a select-only trigger instead of a text field;
 *                                      see selectOnlyKeydown() for its keyboard
 */
import { coordinateOverlay } from '../utils/overlay-coordination.js';
import { chosenText, optionMatches, optionMediaState } from '../utils/option-media.js';
import { typeAheadIndex } from '../utils/roving-focus.js';

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

        // markMediaBroken() and showsInitials(), for an avatar whose photo fails to load.
        ...optionMediaState(),

        // Without a search field the trigger is a select-only combobox: it never filters, so
        // `query` stays empty and `filtered` is always the whole list.
        _searchable: config.searchable !== false,

        // What the reader has typed into a select-only trigger, forgotten after half a second.
        _typeAheadBuffer: '',
        _typeAheadTimer: null,

        get filtered() {
            if (this.query === '') {
                return this.allOptions;
            }

            const q = this.query.toLowerCase();

            return this.allOptions.filter((o) => optionMatches(o, q));
        },

        /**
         * The chosen option, as a list of at most one, while the field shows it and it has a
         * medium to draw at the start of the field.
         *
         * A list because the template binds the medium to an option through `x-for`, which is
         * the one directive that introduces a name. Empty as soon as the text differs from the
         * option's own, since the reader is then typing a new search and the medium of the last
         * choice would sit beside words that no longer name it.
         */
        get fieldMedia() {
            const match = this.allOptions.find((o) => o.value === this.selected);

            if (! match || ! match.media) {
                return [];
            }

            // A select-only trigger always shows the choice, so its medium always shows with it.
            if (! this._searchable) {
                return [match];
            }

            return this.query === chosenText(match) ? [match] : [];
        },

        /** The chosen option's text on a select-only trigger, or an empty string before a choice. */
        get selectedText() {
            const match = this.allOptions.find((o) => o.value === this.selected);

            return match ? chosenText(match) : '';
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

            if (match && this._searchable) {
                this.query = chosenText(match);
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
        // wherever they end up. See the note in _place(): the refs land in a
        // nested scope this component cannot read, and an id is indifferent to
        // scope AND to the teleport.
        _listId: config.listId || null,
        _emptyId: config.emptyId || null,
        _inputId: config.inputId || null,
        // Validated by the Blade, which falls back to these same defaults, so a value
        // arriving here is one the component accepts.
        _placement: config.placement || 'bottom-start',
        _panelWidth: config.panelWidth || 'trigger',

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
            // By ID, not by `$refs`. Measured after the panels moved to `<body>`,
            // `$refs.cbxList` is null, so this loop ran over two nulls and
            // positioned nothing at all. The symptom looked like bad arithmetic —
            // a panel at 0,1117 against a field at 12,451 — and was the absence of
            // any arithmetic: a `fixed` element with no top/left sits at its static
            // position, and getComputedStyle reports that resolved.
            //
            // ⚠️ THE TELEPORT IS NOT THE CAUSE, and this comment said it was for
            // long enough to teach it. Alpine DOES carry a ref across an
            // `x-teleport`: the directive sets `_x_teleportBack` on the clone, and
            // `findClosest` hops that back-pointer before it walks up the DOM, so
            // `x-ref` still registers into the scope that declared the template.
            // Read in the installed Alpine 3.16.3 rather than inferred, and
            // `context-menu.blade.php` says the same thing in prose while
            // `context-menu.js` reads `this.$refs.panel` on a teleported panel and
            // works. The real cause is the nested scope, immediately below.
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
                    placement: this._placement,
                    offset: 4,
                    fitViewport: true,
                    // The field's width, or at least the field's width: a panel told to be
                    // `auto` or a length sizes itself and is only bounded here.
                    matchReferenceWidth: this._panelWidth === 'trigger',
                    minReferenceWidth: this._panelWidth !== 'trigger',
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
         * `_place()`: with `optimistic` set the refs register into a nested scope
         * this component cannot read. Not the teleport — a ref survives that.
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
            this._forgetTyping();
        },

        // ── Opening ─────────────────────────────────────────────────────────

        /**
         * Typing opens the list and restarts the highlight at the first ENABLED option.
         *
         * ⚠️ THIS SET `highlight = 0` AND THAT IS NOT THE TOP, it is the first ROW. When
         * row 0 is disabled — which typing makes ordinary, because the filter decides what
         * lands there — `aria-activedescendant` then named an option nobody can choose:
         * no visible highlight, because the template paints the highlight and the disabled
         * style separately, and Enter silently doing nothing while a screen reader has
         * just announced that option as the active one.
         *
         * Every other entry point already walked to an enabled option; this was the one
         * that did not, and it is the one the user reaches by typing.
         */
        openAndReset() {
            this.open = true;

            // Seeded to "nothing" first, because `highlightFirst()` only assigns when it
            // finds an enabled option — a list filtered down to disabled rows has to end
            // with no active descendant rather than with the previous one.
            this.highlight = -1;
            this.highlightFirst();
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

            // A select-only trigger shows the choice through `selectedText` and never filters,
            // so its query stays empty and the whole list stays reachable.
            this.query = match && this._searchable ? chosenText(match) : '';
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

        /**
         * Open or close a select-only trigger on a click. Opening marks the current choice, as
         * every other way of opening does.
         */
        toggleSelectOnly() {
            if (this.open) {
                this.open = false;

                return;
            }

            this._highlightChoice();
            this.open = true;
        },

        /**
         * Choose the option with this value, the way a click on it does. The select-only
         * trigger's keyboard hands its choice here, or to the optimistic layer's `runIf()`.
         */
        chooseValue(value) {
            if (value === undefined) {
                return;
            }

            const option = this.allOptions.find((o) => o.value === value);

            if (option) {
                this.selectOption(option);
            }
        },

        /**
         * The keyboard of a select-only trigger, after the WAI-ARIA APG select-only combobox.
         *
         * Returns the value to CHOOSE when a key picks the active option (Enter, Space, Tab or
         * Alt+ArrowUp on an open list) and `undefined` for every other key. The template does the
         * choosing, because with `optimistic` the choice goes through the optimistic layer's
         * `runIf()`, which lives in a nested scope this factory cannot call.
         *
         * Closed: ArrowDown, ArrowUp, Alt+ArrowDown, Enter and Space open the list at the current
         * choice; Home and End open it at the first or last option; a printable character opens
         * it at the first option that starts with what has been typed. ArrowUp opening in place
         * follows the example's code, where its prose says the first option: opening at the
         * choice is what the other four opening keys do, and a key that jumped away from it would
         * be the one exception.
         *
         * Open: the arrows move one option and stop at the ends, Home and End jump to them,
         * PageUp and PageDown move ten, Escape closes without choosing, and Tab chooses and lets
         * focus move on. Disabled options are skipped by every one of these.
         *
         * Space joins a search while one is being typed, so "New York" can be typed in full;
         * otherwise it opens the list or chooses, as a native select does.
         */
        selectOnlyKeydown(event) {
            const { key, altKey, ctrlKey, metaKey } = event;
            const printable = [...key].length === 1 && ! altKey && ! ctrlKey && ! metaKey;

            if (key === ' ' && this._typeAheadBuffer !== '') {
                event.preventDefault();
                this._typeAhead(key);

                return undefined;
            }

            if (! this.open) {
                if (key === 'ArrowDown' || key === 'ArrowUp' || key === 'Enter' || key === ' ') {
                    event.preventDefault();
                    this._highlightChoice();
                    this.open = true;
                } else if (key === 'Home' || key === 'End') {
                    event.preventDefault();
                    this.open = true;
                    key === 'Home' ? this.highlightFirst() : this.highlightLast();
                } else if (printable) {
                    event.preventDefault();
                    this._highlightChoice();
                    this.open = true;
                    this._typeAhead(key);
                }

                return undefined;
            }

            if ((key === 'ArrowUp' && altKey) || key === 'Enter' || key === ' ') {
                event.preventDefault();

                return this._closeWithChoice();
            }

            if (key === 'Tab') {
                // No preventDefault: the choice is made and focus still moves on.
                return this._closeWithChoice();
            }

            if (key === 'Escape') {
                this.open = false;
            } else if (key === 'ArrowDown' && ! altKey) {
                event.preventDefault();
                this.moveHighlight(1);
            } else if (key === 'ArrowUp') {
                event.preventDefault();
                this.moveHighlight(-1);
            } else if (key === 'Home' || key === 'End') {
                event.preventDefault();
                key === 'Home' ? this.highlightFirst() : this.highlightLast();
            } else if (key === 'PageDown' || key === 'PageUp') {
                event.preventDefault();
                this._pageHighlight(key === 'PageDown' ? 10 : -10);
            } else if (printable) {
                event.preventDefault();
                this._typeAhead(key);
            }

            return undefined;
        },

        /** Mark the current choice, or the first enabled option when there is none to mark. */
        _highlightChoice() {
            const index = this.filtered.findIndex((o) => o.value === this.selected && ! o.disabled);

            if (index >= 0) {
                this.highlight = index;
            } else {
                this.highlightFirst();
            }
        },

        /** Close the list and hand back the value of the active option, if it can be chosen. */
        _closeWithChoice() {
            const value = this.highlightedValue();

            this.open = false;
            this._forgetTyping();

            return value;
        },

        /**
         * Move the active option ten places, stopping at the ends. A disabled option where the
         * move lands is passed in the direction of travel, and back toward the start of the move
         * when every option beyond it is disabled too.
         */
        _pageHighlight(delta) {
            const max = this.filtered.length - 1;

            if (max < 0) {
                return;
            }

            const step = delta > 0 ? 1 : -1;
            const landed = Math.max(0, Math.min(max, Math.max(this.highlight, 0) + delta));

            for (let i = landed; i >= 0 && i <= max; i += step) {
                if (! this.filtered[i].disabled) {
                    this.highlight = i;

                    return;
                }
            }

            for (let i = landed - step; i >= 0 && i <= max; i -= step) {
                if (! this.filtered[i].disabled) {
                    this.highlight = i;

                    return;
                }
            }
        },

        /**
         * Add a character to the search and mark the option it reaches. The arithmetic is the
         * shared `typeAheadIndex`, the same the menus use; a disabled option is handed to it as
         * an empty label, which nothing can start with, so the search passes over it. A search
         * that reaches nothing starts over with the next key, as in the APG example.
         */
        _typeAhead(char) {
            this._typeAheadBuffer += char;

            if (this._typeAheadTimer) {
                clearTimeout(this._typeAheadTimer);
            }

            this._typeAheadTimer = setTimeout(() => this._forgetTyping(), 500);

            const labels = this.filtered.map((o) => (o.disabled ? '' : o.label));
            const index = typeAheadIndex(labels, this._typeAheadBuffer, this.highlight);

            if (index >= 0) {
                this.highlight = index;
            } else {
                this._forgetTyping();
            }
        },

        _forgetTyping() {
            if (this._typeAheadTimer) {
                clearTimeout(this._typeAheadTimer);
            }

            this._typeAheadTimer = null;
            this._typeAheadBuffer = '';
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
