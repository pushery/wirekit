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
 * @param {boolean} [config.server]     the options are the server's results for the typed text;
 *                                      see utils/server-search.js, which also takes
 *                                      `searchMinLength`, `searchDebounce` and `searchTexts`
 */
import { coordinateOverlay } from '../utils/overlay-coordination.js';
import { onFormReset } from '../utils/form-reset.js';
import { requiredCheckState } from '../utils/required-check.js';
import { chosenText, optionMatches, optionMediaState } from '../utils/option-media.js';
import { optionPressState } from '../utils/option-press.js';
import { foldForSearch } from '../utils/search-fold.js';
import { typeAheadIndex } from '../utils/roving-focus.js';
import { serverSearchState } from '../utils/server-search.js';
import { sameValue } from '../utils/same-value.js';
import { withOpenAlias } from '../utils/open-alias.js';
import { watchModelEvents } from '../utils/model-events.js';
import { watchCurrent } from '../utils/watch-current.js';
import { jsonValue, observeServerValue, WK_SERVER_VALUE_ATTRIBUTE } from '../utils/server-value.js';

/** The attribute that carries the options outside server mode; see init(). */
const OPTIONS_ATTRIBUTE = 'data-wk-options';

export default function wirekitCombobox(config = {}) {
    return withOpenAlias({
        isOpen: false,
        query: '',
        selected: config.value ?? null,
        highlight: 0,
        allOptions: Array.isArray(config.options) ? config.options : [],

        // Cross-close channel — see utils/overlay-coordination.js.
        _coordination: null,
        // `change` and `blur` on the root, which `wire:model` binds through `x-modelable`, so
        // `wire:model.live.blur` and `.live.change` send when the reader chooses and when they
        // leave (utils/model-events.js). Disposed in destroy().
        _modelEvents: null,
        // The observers that follow the server's value and options; see init(). Disconnected in
        // destroy().
        _stopServerValue: null,
        _stopOptionsSync: null,
        // Puts the starting choice back when the form is reset (utils/form-reset.js), released in
        // destroy(). The choice it returns to is the one the page started with, or the one the
        // server sent last, as a native field returns to the value the server rendered.
        _stopFormReset: null,
        _resetValue: config.value ?? null,
        // `requiredMessage` and `onRequiredInvalid()`: a required combobox stops a submit without a
        // choice (utils/required-check.js).
        ...requiredCheckState(),

        // Set by hoverOption() so the scroll that follows a highlight change is
        // skipped for that one move. See _revealHighlight().
        _movedByPointer: false,

        // markMediaBroken() and showsInitials(), for an avatar whose photo fails to load.
        ...optionMediaState(),
        // notePress() and keepFocusOnPress(): a press on the list with a mouse or a pen leaves
        // the focus in the text field.
        ...optionPressState(),

        // `search-change`, the options that follow the server, and the label the choice keeps
        // after a new search replaced its list. Inert without `server`.
        ...serverSearchState(config),

        // Without a search field the trigger is a select-only combobox: it never filters, so
        // `query` stays empty and `filtered` is always the whole list.
        _searchable: config.searchable !== false,

        // The label this component put into `query` itself, so `filtered` can tell it apart from
        // something the reader typed. Not a mode flag: it is compared by value, so deleting back
        // to the seeded text reopens the whole list, which is the same thing the reader meant.
        _seededQuery: '',

        // What the reader has typed into a select-only trigger, forgotten after half a second.
        _typeAheadBuffer: '',
        _typeAheadTimer: null,

        get filtered() {
            // The server's results are already the answer to the text; filtering them again
            // here would drop a typo-tolerant match the server found on purpose.
            if (this._server) {
                return this.allOptions;
            }

            // The seeded label is not a search, and treating it as one would make every option
            // but the current selection unreachable: the field pre-fills `query` with the chosen
            // row's label so it reads as the choice, and the filter would then match exactly that
            // one row. The documented contract filters "as the user types", and nobody typed it.
            if (this.query === '' || this.query === this._seededQuery) {
                return this.allOptions;
            }

            const q = foldForSearch(this.query);

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
            const match = this._knownOption(this.selected, this.allOptions);

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
            const match = this._knownOption(this.selected, this.allOptions);

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
            // The value and, outside server mode, the options reach this component on attributes
            // of the root rather than in `x-data`, so that attribute renders the same on every
            // update. A morph that changed it would have Alpine reset the component to the new
            // expression and initialize it again, dropping the open list and the reader's typing.
            // They are read here, before the value looks for its label, and followed below. A
            // value or options passed in `config`, as by a factory built outside Blade, come
            // first. `$root` is checked rather than assumed: a test builds this factory with a
            // stub that has no `getAttribute`.
            const readAttribute = (name) => (typeof this.$root?.getAttribute === 'function' ? jsonValue(this.$root.getAttribute(name)) : undefined);

            if (config.value === undefined) {
                const value = readAttribute(WK_SERVER_VALUE_ATTRIBUTE);

                if (value !== undefined) {
                    this.selected = value;
                }
            }

            this._resetValue = this.selected;

            if (! Array.isArray(config.options) && ! this._server) {
                const options = readAttribute(OPTIONS_ATTRIBUTE);

                if (Array.isArray(options)) {
                    this.allOptions = options;
                }
            }

            // In server mode the options come from their own attribute, so they are read before
            // the initial value looks for its label among them.
            this._startServerSearch((options) => {
                this.allOptions = options;

                // A new list is new rows under the old indexes, so the keyboard starts at the
                // first enabled row again, as it does when the reader types.
                this.highlight = -1;
                this.highlightFirst();
            });

            // Seed the query with the label of the initial value, if any.
            const match = this._knownOption(this.selected, this.allOptions);

            if (match && this._searchable) {
                this.query = chosenText(match);
                this._seededQuery = this.query;
            }

            // `selected` also moves from outside: `x-modelable` exposes it, so a `wire:model`
            // round trip, a parent component's `x-model` or a reset all write it directly, and
            // none of them goes through selectOption(). Without this the value would be right
            // and the field would keep showing the previous label, for instance after `phone`
            // moves the country to match a typed dialing code.
            //
            // selectOption() keeps its own call rather than leaning on this: that path runs
            // synchronously inside the click, and a watcher flushes a microtask later.
            watchCurrent(this, 'selected', () => this._syncQuery());

            this._coordination = coordinateOverlay({
                channel: 'wirekit:combobox-open',
                onOther: () => { this.isOpen = false; },
            });

            // Announce on every transition into the open state so siblings close.
            watchCurrent(this, 'isOpen', (val) => {
                if (! val) {
                    // A hidden panel has nothing to follow, and its followers would go on
                    // recomputing against a field the reader has moved away from.
                    this._unplace();

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
            watchCurrent(this, 'highlight', () => {
                this.$nextTick(() => this._revealHighlight());
            });

            // The root carries the binding. The value travels as the event's detail: an event
            // without one would have `x-model` read the root's `value`, which a <div> has not.
            const root = this.$root;
            this._modelEvents = watchModelEvents(root, () => root, { detail: () => this.selected });

            // A value the server changed reaches the choice, and the `selected` watcher above
            // writes its label. A value equal to the choice on screen, such as the reader's own
            // pick coming back from the server, needs nothing done.
            this._stopServerValue = observeServerValue(root, (raw) => {
                const value = jsonValue(raw);

                if (value === undefined) {
                    return;
                }

                this._resetValue = value;

                if (sameValue(value, this.selected)) {
                    return;
                }

                this.selected = value;
            });

            // New options from the server replace the list, and the keyboard starts at the first
            // enabled row again, as it does after a server search. What the reader typed stays;
            // a label this component put into the field follows the new list, which may have
            // renamed the choice or no longer hold it.
            if (! this._server) {
                this._stopOptionsSync = observeServerValue(root, (raw) => {
                    const options = jsonValue(raw);

                    if (! Array.isArray(options)) {
                        return;
                    }

                    const seeded = this.query === this._seededQuery;

                    this.allOptions = options;
                    this.highlight = -1;
                    this.highlightFirst();

                    if (seeded) {
                        this._syncQuery();
                    }
                }, OPTIONS_ATTRIBUTE);
            }

            // The search field joins the form the hidden field joins, and it is there without a
            // name as well. A select-only trigger is a `div`, which joins no form, so the hidden
            // field answers there.
            this._stopFormReset = onFormReset(root, () => {
                const field = this._searchSource();

                if (field && typeof field === 'object' && 'form' in field) {
                    return field;
                }

                return typeof root?.querySelector === 'function' ? root.querySelector('input[type=hidden]') : null;
            }, () => this._restore());
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

            // Both panels, because there are two: the options list and the
            // "No results" panel, which is the same box with different content.
            // Both are `fixed` and teleported to `<body>` to escape the host's
            // stacking context, and a `fixed` element there that nobody positions
            // sits at its static position, far from the field.
            //
            // By id, not by `$refs`, because a ref can land in another scope and
            // leave `$refs.cbxList` null here, and a loop over two nulls positions
            // nothing. The teleport is not what moves it. Alpine carries a ref
            // across an `x-teleport`: the directive sets `_x_teleportBack` on the
            // clone, and `findClosest` hops that back-pointer before it walks up
            // the DOM, so `x-ref` still registers into the scope that declared the
            // template (`context-menu.js` reads `this.$refs.panel` on a teleported
            // panel). The nested scope, immediately below, is what moves it.
            const panels = [
                this._listId ? document.getElementById(this._listId) : this.$refs.cbxList,
                this._emptyId ? document.getElementById(this._emptyId) : this.$refs.cbxEmpty,
            ];

            // The anchor by id as well.
            //
            // `x-ref` registers into the nearest `x-data` scope. With `optimistic`
            // set, the input sits inside the nested optimistic component, so every
            // ref of this component lands in the child scope and `_place()`, which
            // lives in the parent, sees an empty registry: `$refs.cbxInput` is null
            // there, and a positioner handed null places nothing.
            const anchor = this._inputId
                ? document.getElementById(this._inputId)
                : this.$refs.cbxInput;

            if (! anchor) {
                return;
            }

            // Drop the followers from the previous opening before making new ones. Reopening
            // without this would stack one `autoUpdate` per open, all of them writing the same
            // element.
            this._unplace();

            for (const panel of panels) {
                if (! panel) {
                    continue;
                }

                const placement = window.wirekitPosition(anchor, panel, {
                    placement: this._placement,
                    offset: 4,
                    fitViewport: true,
                    // The field's width, or at least the field's width: a panel told to be
                    // `auto` or a length sizes itself and is only bounded here.
                    matchReferenceWidth: this._panelWidth === 'trigger',
                    minReferenceWidth: this._panelWidth !== 'trigger',
                    // Keep following the field — a single placement does not survive a
                    // Livewire update, and the panel does not recover on its own.
                    //
                    // Everything the positioner computes is written as INLINE STYLE: `top`,
                    // `left`, and the width and `max-height` the `size` middleware applies. A
                    // morph patches this node against its template — the teleport does not put
                    // it out of reach, which is exactly why it carries a `wire:key` — and the
                    // template's `style` attribute carries none of those values. So the morph
                    // replaces the whole attribute and every computed value is gone, while
                    // `open` never changed and nothing asks for a new placement.
                    //
                    // An open list would then drop to its static position after one refresh,
                    // far from the field, while `aria-expanded` still says it is open, and
                    // nothing would bring it back.
                    //
                    // `autoUpdate` watches the elements themselves rather than the framework,
                    // so it answers a wipe from any cause: the panel's box changes the moment
                    // its width and cap are dropped, and the recompute writes all four values
                    // back. Nothing here knows what Livewire is, which is the point — this
                    // component ships to pages that have no Livewire at all.
                    autoReposition: true,

                    // And the erasure repair, because `autoReposition` covers that case only
                    // by accident here — it works, and it works for a reason this component does
                    // not control.
                    //
                    // `autoUpdate` recomputes on a BOX change, never on an attribute change. What
                    // makes it notice an erasure at all is that the erasure takes a width with it
                    // and the panel collapses. That width comes from the sizing options above, so
                    // whether the repair happens depends on whether the width being removed was
                    // BINDING on that panel, on that page.
                    //
                    // A `panelWidth` other than `trigger` writes `minWidth`/`maxWidth` rather
                    // than a width, and that does not change the case: on a wide field the
                    // `minWidth` is binding too, so the panel still collapses and the repair
                    // still happens.
                    //
                    // It is kept anyway, and the reason is what the measurement showed rather than
                    // what it failed to show: the repair rests on a coincidence between two
                    // unrelated things — how a panel is sized, and whether a placement survives.
                    // `repairErasure` watches the attribute that is actually removed, so the
                    // question stops depending on geometry. A sibling component in this same
                    // catalog has the erasure and does NOT collapse, and nothing about this one
                    // guarantees it stays on the lucky side of that line.
                    repairErasure: true,
                });

                // The global is documented as something a component asks for WITHOUT depending
                // on it, so it may be absent — and by the same reasoning it may be something other
                // than this package's own helper. A stub that returns a non-thenable makes `.then`
                // throw, which is a worse failure than the missing placement it replaces.

                if (! placement || typeof placement.then !== 'function') {
                    continue;
                }

                placement.then((result) => {
                    if (typeof result?.stop !== 'function') {
                        return;
                    }

                    // Closed while the placement was in flight: `position()` awaits frames and
                    // a promise, so the list can be shut before this resolves. Its follower
                    // would then outlive the panel it follows.
                    if (! this.isOpen) {
                        result.stop();

                        return;
                    }

                    this._placeStops.push(result.stop);
                });
            }
        },

        // The `autoUpdate` teardown handles, one per panel. The helper's own docblock puts this
        // duty on the caller: without it the scroll, resize and observer listeners leak, and a
        // component that reopens often leaks once per opening.
        _placeStops: [],

        _unplace() {
            for (const stop of this._placeStops) {
                stop();
            }

            this._placeStops = [];
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
            this._stopServerValue?.();
            this._stopServerValue = null;
            this._stopOptionsSync?.();
            this._stopOptionsSync = null;
            this._stopFormReset?.();
            this._stopFormReset = null;
            this._modelEvents?.dispose();
            this._modelEvents = null;
            this._coordination?.stop();
            this._coordination = null;
            this._unplace();
            this._forgetTyping();
            this._stopServerSearch();
        },

        // ── Opening ─────────────────────────────────────────────────────────

        /**
         * Typing opens the list and restarts the highlight at the first ENABLED option.
         *
         * Not `highlight = 0`: that is the first row, not the top. When row 0 is disabled —
         * which typing makes ordinary, because the filter decides what lands there —
         * `aria-activedescendant` would name an option nobody can choose:
         * no visible highlight, because the template paints the highlight and the disabled
         * style separately, and Enter silently doing nothing while a screen reader has
         * just announced that option as the active one.
         *
         * Every other entry point already walked to an enabled option; this was the one
         * that did not, and it is the one the user reaches by typing.
         */
        openAndReset() {
            this.isOpen = true;

            // Seeded to "nothing" first, because `highlightFirst()` only assigns when it
            // finds an enabled option — a list filtered down to disabled rows has to end
            // with no active descendant rather than with the previous one.
            this.highlight = -1;
            this.highlightFirst();
            this._queueSearch(this.typedQuery());
        },

        /**
         * Escape folds an open list without choosing, and marks the press as handled so that a
         * modal or a drawer around the field stays open. With the list already folded the press
         * is left to them.
         *
         * @param {KeyboardEvent} event
         */
        escapeList(event) {
            if (! this.isOpen) return;
            event?.preventDefault();
            this.isOpen = false;
        },

        /** The text field, which a `search-change` starts from (see utils/server-search.js). */
        _searchSource() {
            const byId = this._inputId && typeof document !== 'undefined'
                ? document.getElementById(this._inputId)
                : null;

            return byId ?? this.$refs?.cbxInput ?? null;
        },

        /** What the reader typed, which the label this component put into the field is not. */
        typedQuery() {
            return this.query === this._seededQuery ? '' : this.query;
        },

        /** Arrow into the list from the field. */
        openAndMove(delta) {
            this.isOpen = true;
            this.moveHighlight(delta);
        },

        openAtFirst() {
            this.isOpen = true;
            this.highlightFirst();
        },

        openAtLast() {
            this.isOpen = true;
            this.highlightLast();
        },

        /** The chevron toggles, and returns focus to the field either way. */
        toggleAndFocus() {
            this.isOpen = ! this.isOpen;

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
            this.isOpen = false;
            this._syncQuery();
            this._modelEvents?.commit();
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
            const match = this._knownOption(this.selected, this.allOptions);

            // A select-only trigger shows the choice through `selectedText` and never filters,
            // so its query stays empty and the whole list stays reachable.
            this.query = match && this._searchable ? chosenText(match) : '';
            this._seededQuery = this.query;

            // The field now shows the choice, which is not a search: the application goes back
            // to what it lists before one.
            this._queueSearch('');

            this._keepFocusThroughClear();
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
         * Enter in the field. On an open list it chooses the active option; on a closed one it
         * opens the list at the current choice and chooses nothing, the way the select-only
         * trigger and the multi-select answer it. Choosing on a closed list picked an option the
         * reader never saw: the first one in an untouched field, and the suggestion the reader
         * had just dismissed with Escape.
         */
        enterKey() {
            if (! this.isOpen) {
                this._highlightChoice();
                this.isOpen = true;

                return;
            }

            this.activateHighlighted();
        },

        /**
         * Enter on the optimistic path: the value to choose, or `undefined` while it only opens.
         * A choice closes the list, as selectOption() does on the other path; the optimistic
         * layer writes the value and never touches the list.
         */
        enterValue() {
            if (! this.isOpen) {
                this.enterKey();

                return undefined;
            }

            const value = this.highlightedValue();

            if (value !== undefined) {
                this.isOpen = false;
            }

            return value;
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
            if (this.isOpen) {
                this.isOpen = false;

                return;
            }

            this._highlightChoice();
            this.isOpen = true;
        },

        /**
         * Whether this option is the choice. The choice may arrive as a number, as a value bound to
         * an `int` property does, while the option carries its value as text.
         */
        isChosen(value) {
            return sameValue(this.selected, value);
        },

        /**
         * Choose the option with this value, the way a click on it does. The select-only
         * trigger's keyboard hands its choice here, or to the optimistic layer's `runIf()`.
         */
        chooseValue(value) {
            if (value === undefined) {
                return;
            }

            const option = this.allOptions.find((o) => sameValue(o.value, value));

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

            if (! this.isOpen) {
                if (key === 'ArrowDown' || key === 'ArrowUp' || key === 'Enter' || key === ' ') {
                    event.preventDefault();
                    this._highlightChoice();
                    this.isOpen = true;
                } else if (key === 'Home' || key === 'End') {
                    event.preventDefault();
                    this.isOpen = true;
                    key === 'Home' ? this.highlightFirst() : this.highlightLast();
                } else if (printable) {
                    event.preventDefault();
                    this._highlightChoice();
                    this.isOpen = true;
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
                this.escapeList(event);
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
            const index = this.filtered.findIndex((o) => sameValue(o.value, this.selected) && ! o.disabled);

            if (index >= 0) {
                this.highlight = index;
            } else {
                this.highlightFirst();
            }
        },

        /** Close the list and hand back the value of the active option, if it can be chosen. */
        _closeWithChoice() {
            const value = this.highlightedValue();

            this.isOpen = false;
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

        /**
         * Back to the starting choice after a form reset.
         *
         * The reset put the search field back to its own default, which is empty, behind
         * `x-model`; the binding writes the field only when `query` changes, so the label of the
         * choice is written into it here.
         */
        _restore() {
            this.isOpen = false;
            this.selected = this._resetValue;
            this._syncQuery();

            const input = this._searchSource();

            if (input && typeof input === 'object' && 'value' in input) {
                input.value = this.query;
            }

            this.requiredMessage = '';
        },

        /** What the required check reads: empty exactly while nothing is chosen, whatever is typed. */
        get requiredValue() {
            return this.selected === null || this.selected === undefined || this.selected === '' ? '' : String(this.selected);
        },

        /** The field, or the select-only trigger, with the list kept shut, as a clear leaves it. */
        _focusRequiredControl() {
            const field = this._searchSource();

            if (field && typeof field.focus === 'function') {
                field.focus();
                this.isOpen = false;
            }
        },

        clearSelection() {
            this.selected = null;
            this.query = '';
            this.isOpen = false;
            this._queueSearch('');

            // Fire input on the hidden field so wire:model sees the cleared
            // value. Assigning `selected` alone updates the bound attribute and
            // nothing else: Livewire syncs on the event, not on the value.
            //
            // $root, not $el. The clear button is a child, and a lookup scoped
            // to it finds no hidden input: the value would clear on screen while
            // no input event fired, and wire:model would keep the old one. $root
            // is the x-data element whatever the handler sits on.
            const hidden = this.$root.querySelector('input[type=hidden]');

            if (hidden) {
                hidden.value = '';
                hidden.dispatchEvent(new Event('input', { bubbles: true }));
            }

            this._modelEvents?.commit();
            this._keepFocusThroughClear();
        },

        /**
         * Keep the focus in the component when the clear button takes itself away.
         *
         * The button shows only while there is a value (`x-show="selected"`), so clearing hides the
         * control that holds the focus, and the browser drops it to the page: the next Tab starts
         * somewhere the reader never was. The field takes it instead, with the list kept shut,
         * because the field's own `@focus` opens it and a clear is about taking a value away.
         *
         * Called from both ways a clear arrives, the plain one and the optimistic write (through
         * `_syncQuery`, its `after` hook), while the button is still in the document. It acts only
         * when the value is empty and the focus is on the clear button, so a choice, a rollback or
         * a clear the focus was elsewhere for moves nothing.
         */
        _keepFocusThroughClear() {
            if (this.selected !== null || typeof document === 'undefined') {
                return;
            }

            const active = document.activeElement;

            if (! active || typeof active.hasAttribute !== 'function' || ! active.hasAttribute('data-wk-combobox-clear')) {
                return;
            }

            const field = this._searchSource();

            if (field && typeof field.focus === 'function') {
                field.focus();
                this.isOpen = false;
            }
        },
    });
}
