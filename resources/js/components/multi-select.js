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
 * @param {string} [config.fieldId] - the text field's id: the caller's `id` when one was given,
 *   the stem with `-input` after it otherwise
 * @param {string} [config.placement] - Where the panel opens against the field (Floating UI placement)
 * @param {string} [config.panelWidth] - 'trigger' matches the field; anything else lets the panel
 *   be wider than the field but never narrower
 * @param {boolean} [config.server] - the options are the server's results for the typed text; see
 *   utils/server-search.js, which also takes `searchMinLength`, `searchDebounce` and `searchTexts`
 */
import { controlIsDisabled } from '../utils/fieldset-disabled.js';
import { position } from '../utils/floating.js';
import { onFormReset } from '../utils/form-reset.js';
import { requiredCheckState } from '../utils/required-check.js';
import { chosenText, optionMatches, optionMediaState } from '../utils/option-media.js';
import { optionPressState } from '../utils/option-press.js';
import { foldForSearch } from '../utils/search-fold.js';
import { serverSearchState } from '../utils/server-search.js';
import { sameValue } from '../utils/same-value.js';
import { watchModelEvents } from '../utils/model-events.js';
import { watchCurrent } from '../utils/watch-current.js';
import { jsonValue, observeServerValue, WK_SERVER_VALUE_ATTRIBUTE } from '../utils/server-value.js';

/** The attribute that carries the options outside server mode; see init(). */
const OPTIONS_ATTRIBUTE = 'data-wk-options';

export default function wirekitMultiSelect(config = {}) {
    return {
        /**
         * Whether the field takes no input now: disabled by its own attribute, by a fieldset around
         * it, or by a state Livewire set after the page loaded. Read from the field each time,
         * because its own `disabled` stays false inside a disabled fieldset
         * (utils/fieldset-disabled.js), and the options and the frame are no native controls a
         * fieldset could disable.
         */
        get locked() {
            return controlIsDisabled(this.$refs?.filterInput);
        },

        /** Focus the filter and open the list — one act, so one method. */
        focusAndOpen() {
            // A click on the frame of a disabled field opens nothing. The field itself takes
            // neither focus nor keys then, so this is the one way in.
            if (this.locked) {
                return;
            }

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

        // markMediaBroken() and showsInitials(), for an avatar whose photo fails to load.
        ...optionMediaState(),
        // notePress() and keepFocusOnPress(): a press on the list with a mouse or a pen leaves
        // the focus in the filter input.
        ...optionPressState(),
        // `search-change`, the options that follow the server, and the labels a chosen value
        // keeps after a new search replaced its list. Inert without `server`.
        ...serverSearchState(config),
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
        // `change` and `blur` on the root, which `wire:model` binds through `x-modelable`, so
        // `wire:model.live.blur` and `.live.change` send when the reader chooses and when they
        // leave (utils/model-events.js). Disposed in destroy().
        _modelEvents: null,
        // The observers that follow the server's value and options; see init(). Disconnected in
        // destroy().
        _stopServerValue: null,
        _stopOptionsSync: null,
        // Puts the starting selection back when the form is reset (utils/form-reset.js), released
        // in destroy(); the selection it returns to is the one the page started with, or the one
        // the server sent last, as a native field returns to the value the server rendered.
        _stopFormReset: null,
        _resetValue: Array.isArray(config.value) ? [...config.value] : [],
        // `requiredMessage` and `onRequiredInvalid()`: a required multi-select stops an empty
        // submit (utils/required-check.js).
        ...requiredCheckState(),
        // Up while the component itself puts the focus on the filter input after the last value
        // was removed, so that this one focus does not open the list; see `_focusFieldQuietly()`.
        _quietFocus: false,

        init() {
            // The value and, outside server mode, the options reach this component on attributes
            // of the root rather than in `x-data`, so that attribute renders the same on every
            // update. A morph that changed it would have Alpine reset the component to the new
            // expression and initialize it again, which emptied the selection, and `wire:model.live`
            // then sent the empty list to the server. They are read here and followed below. A
            // value or options passed in `config`, as by a factory built outside Blade, come
            // first. `$root` is checked rather than assumed: a test builds this factory with a
            // stub that has no `getAttribute`.
            const readAttribute = (name) => (typeof this.$root?.getAttribute === 'function' ? jsonValue(this.$root.getAttribute(name)) : undefined);

            if (config.value === undefined) {
                const value = readAttribute(WK_SERVER_VALUE_ATTRIBUTE);

                if (Array.isArray(value)) {
                    this.selected = value.map(String);
                }
            }

            this._resetValue = [...this.selected];

            if (! Array.isArray(config.options) && ! this._server) {
                const options = readAttribute(OPTIONS_ATTRIBUTE);

                if (Array.isArray(options)) {
                    this._options = options;
                }
            }

            // Position the panel each time it opens. It is `fixed` (to escape a
            // clipping card) and therefore needs an explicit anchor + width; see
            // _place(). $nextTick so the panel is in the DOM before measuring.
            watchCurrent(this, 'dropdownOpen', (open) => {
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
            watchCurrent(this, 'highlight', () => {
                this.$nextTick(() => this._revealHighlight());
            });

            // A new list is new rows under the old indexes, so the keyboard starts at the top
            // again, as it does when the reader types. Left alone, the index could also point
            // past a shorter list, and `aria-activedescendant` would name a row nobody rendered.
            this._startServerSearch((options) => {
                this._options = options;
                this.highlight = 0;
            });

            // The root carries the binding, and the selection travels as the event's detail.
            const root = this.$root;
            this._modelEvents = watchModelEvents(root, () => root, { detail: () => [...this.selected] });

            // A selection the server changed reaches the pills. One that holds the same values as
            // the pills on screen, such as the reader's own picks coming back, needs nothing done.
            this._stopServerValue = observeServerValue(root, (raw) => {
                const value = jsonValue(raw);

                if (! Array.isArray(value)) {
                    return;
                }

                const next = value.map(String);

                this._resetValue = [...next];

                if (next.length === this.selected.length && next.every((v) => this.isChosen(v))) {
                    return;
                }

                this.selected = next;
            });

            // New options from the server replace the list, and the keyboard starts at the top
            // again, as it does after a server search.
            if (! this._server) {
                this._stopOptionsSync = observeServerValue(root, (raw) => {
                    const options = jsonValue(raw);

                    if (! Array.isArray(options)) {
                        return;
                    }

                    this._options = options;
                    this.highlight = 0;
                }, OPTIONS_ATTRIBUTE);
            }

            // One hidden field per chosen value: an empty selection has none, which the helper
            // covers with the form it found while there was one.
            this._stopFormReset = onFormReset(root, () => (typeof root?.querySelector === 'function' ? root.querySelector('input[type="hidden"]') : null), () => this._restore());
        },

        // Alpine teardown (Livewire morph / SPA nav): stop autoUpdate if the panel
        // was still open, since the $watch only fires on an open→closed CHANGE.
        destroy() {
            this._stopServerValue?.();
            this._stopServerValue = null;
            this._stopOptionsSync?.();
            this._stopOptionsSync = null;
            this._stopFormReset?.();
            this._stopFormReset = null;
            this._modelEvents?.dispose();
            this._modelEvents = null;
            this._stopAutoUpdate?.();
            this._stopAutoUpdate = null;
            this._stopServerSearch();
        },
        /**
         * Back to the starting selection after a form reset, which also emptied the filter field.
         * The hidden fields follow the selection.
         */
        _restore() {
            this.selected = [...this._resetValue];
            this.filter = '';
            this.highlight = 0;
            this.requiredMessage = '';
        },

        /** What the required check reads: empty exactly while nothing is chosen. */
        get requiredValue() {
            return this.selected.length > 0 ? String(this.selected.length) : '';
        },

        /** The field a reader types into, focused without opening the list. */
        _focusRequiredControl() {
            this._focusFieldQuietly(this._searchSource());
        },
        _options: config.options || [],
        // The stem every option id is built from. Handed in by the Blade rather
        // than minted here so the row's `id` and the input's
        // `aria-activedescendant` are two readings of ONE string — the pairing
        // comes apart the moment those are written independently.
        _id: config.id || null,
        // The text field's id comes from the Blade too: it is the caller's `id` when one was
        // given, so a label of the caller's reaches the field, and the stem with `-input` otherwise.
        _fieldId: config.fieldId || (config.id ? config.id + '-input' : null),
        // Validated by the Blade, which falls back to these same defaults.
        _placement: config.placement || 'bottom-start',
        _panelWidth: config.panelWidth || 'trigger',

        /**
         * Get filtered options based on current filter text.
         * Excludes already-selected values.
         */
        get filteredOptions() {
            // The server's results are already the answer to the text; filtering them again
            // here would drop a typo-tolerant match the server found on purpose.
            if (this._server) {
                return this._options.filter((opt) => ! this.isChosen(opt.value));
            }

            const term = foldForSearch(this.filter);
            return this._options.filter(
                (opt) =>
                    ! this.isChosen(opt.value) &&
                    optionMatches(opt, term)
            );
        },

        /**
         * The options the list layout shows. A server search shows every result it was given and a
         * list in the browser every option the text matches, chosen ones included in both: there
         * a checkbox says whether an option is chosen, where the dropdown drops it from the rows.
         */
        get listOptions() {
            if (this._server) {
                return this._options;
            }

            const term = foldForSearch(this.filter);

            return this._options.filter((opt) => optionMatches(opt, term));
        },

        /** The list layout's search field sends its text. There is no panel to open. */
        onListInput() {
            this._queueSearch(this.filter);
        },

        /**
         * A checkbox of the list layout. The choice changes, and the text and the focus stay where
         * they are, so a reader checks several results of one search in a row.
         */
        toggleFromList(value) {
            const idx = this._indexOf(value);

            if (idx >= 0) {
                this.selected.splice(idx, 1);
            } else {
                this.selected.push(value);
            }

            this._modelEvents?.commit();
        },

        /**
         * A remove button of the list layout's selection. The focus moves to the remove button that
         * takes its place, or to the search field once the selection is empty, instead of falling
         * to the page with the button that was pressed.
         */
        removeFromList(value) {
            const idx = this._indexOf(value);

            if (idx < 0) {
                return;
            }

            this.selected.splice(idx, 1);
            this._modelEvents?.commit();
            this._focusAfterListRemoval(idx);
        },

        /**
         * The same remove button under the optimistic layer nested here. The removal travels as
         * `run(nextWith(value))`, like a pick, and focus moves where `removeFromList()` puts it,
         * but only when the layer took the change: a refused run removed nothing, and the
         * pressed button still holds focus.
         *
         * @param {string|number} value - The value whose remove button was pressed.
         */
        runListRemoval(value) {
            const idx = this._indexOf(value);

            if (typeof this.run !== 'function' || idx < 0) {
                return;
            }

            if (this.run(this.nextWith(value))) {
                this._focusAfterListRemoval(idx);
            }
        },

        /**
         * Put focus on the list layout's remove button that took the removed one's place, or on
         * the search field once the selection is empty.
         *
         * @param {number} idx - The position the removed value held.
         */
        _focusAfterListRemoval(idx) {
            // Resolved now, while the pressed button is still in the document. `$root` is found
            // by walking up from the element whose handler called in, which is this button, and
            // the removal takes it out of the list before the tick below runs: read there, the
            // walk reached nothing and the focus fell to the search field.
            const root = this.$root;

            const place = () => {
                const buttons = [...(root?.querySelectorAll?.('[data-wk-multi-select-remove]') ?? [])];
                const target = buttons[idx] ?? buttons[buttons.length - 1] ?? this._searchSource();

                if (target && typeof target.focus === 'function') {
                    target.focus();
                }
            };

            if (typeof this.$nextTick === 'function') {
                this.$nextTick(place);
            } else {
                place();
            }
        },

        /** The heading over the list layout's selection: "2 selected". */
        get selectionHeading() {
            const count = this.selected.length;
            const template = String(count === 1 ? config.selectedOne ?? '' : config.selectedMany ?? '');

            return template.replace('__COUNT__', String(count));
        },

        /**
         * Where a value stands in the choice, or -1. A chosen value may be a number, as an array
         * of ids bound with `wire:model` arrives, while an option carries its value as text.
         */
        _indexOf(value) {
            return this.selected.findIndex((v) => sameValue(v, value));
        },

        /** Whether a value is chosen, a number and its text naming the same option. */
        isChosen(value) {
            return this._indexOf(value) !== -1;
        },

        /**
         * Get the label for a value.
         */
        getLabel(value) {
            return this._knownOption(value, this._options)?.label || value;
        },

        /**
         * The language a chosen value's words are in, from its option's `lang`, or null for the
         * page's own. A pill and the list of chosen values name it, as the option's row does
         * (WCAG 3.1.2).
         */
        getLang(value) {
            return this._knownOption(value, this._options)?.lang ?? null;
        },

        /**
         * The text a pill shows: the option's `selectedLabel` when it has one, its label otherwise.
         * The remove button and the announcement keep the full label, since a pill is short on
         * room and a listener is not.
         */
        pillLabel(value) {
            const option = this._knownOption(value, this._options);

            return option ? chosenText(option) : value;
        },

        /**
         * A pill's option as a list of at most one, when it has a medium to draw. A list because
         * `x-for` is the directive that gives the pill's medium an option to bind to.
         */
        pillMedia(value) {
            const option = this._knownOption(value, this._options);

            return option && option.media ? [option] : [];
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

        /** The text field, which a `search-change` starts from (see utils/server-search.js). */
        _searchSource() {
            const byId = this._fieldId && typeof document !== 'undefined'
                ? document.getElementById(this._fieldId)
                : null;

            return byId ?? this.$refs?.filterInput ?? null;
        },

        /**
         * The filter input took the focus. A reader who tabs or clicks into the field is asking
         * for the options, so the list opens. A focus this component placed after a removal is
         * not (`_focusFieldQuietly()`), and the list then opens with the next key that asks.
         */
        onFilterFocus() {
            if (this._quietFocus) {
                return;
            }

            this.dropdownOpen = true;
        },

        /** Typing narrows the list, so the old index means nothing — start over. */
        openAndReset() {
            this.dropdownOpen = true;
            this.highlight = 0;
            this._queueSearch(this.filter);
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
         * Escape folds an open list and marks the press as handled, so that a modal or a drawer
         * around the field stays open. With the list already folded the press is left to them.
         *
         * @param {KeyboardEvent} event
         */
        escapeDropdown(event) {
            if (! this.dropdownOpen) return;
            event?.preventDefault();
            this.dropdownOpen = false;
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
                this.toggleValue(option.value);
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
         * By id rather than through `$refs`, for the reason the docblock of
         * `_panelElement()` spells out in full: with `optimistic` set the refs register into
         * a nested scope this component cannot read, and an id is scope-free. NOT
         * the teleport — Alpine's closest-element walk follows `_x_teleportBack`,
         * and that same docblock records the measurement proving it.
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
         * The selection this value would produce, as a NEW array.
         *
         * The optimistic layer needs it: toggleValue() mutates `selected` in place
         * with splice/push, and an in-place mutation is invisible to a layer
         * that has to write the value itself in order to snapshot what it
         * replaced. Returning a fresh array keeps both halves honest — the
         * snapshot points at the old one, the write installs the new one.
         */
        nextWith(value) {
            const idx = this._indexOf(value);

            if (idx >= 0) {
                return this.selected.filter((v) => ! sameValue(v, value));
            }

            return this.selected.concat([value]);
        },

        /**
         * The part of toggleValue() that is not the selection itself — and NOT the
         * focus move.
         *
         * Split out so the optimistic layer can run it after ITS write. It runs
         * on the rollback too, which is exactly why focus is not in here: an
         * undo arrives on the server's schedule, and pulling focus back to the
         * filter at that moment would take the user out of wherever they had
         * got to. Clearing the stale filter text is safe; moving focus is not.
         */
        _afterToggle() {
            // A server search keeps its text: the results on screen answer it, and a reader
            // picking several of them would otherwise have to type the search again for each.
            if (! this._server) {
                this.filter = '';
            }

            this._clampHighlight();
        },

        /**
         * Toggle one option in or out of the selection — the plain path.
         *
         * NOT called `toggle`: the optimistic layer mounts inside this component and
         * declares `toggle()` as part of its own contract, and Alpine resolves a name to
         * the NEAREST scope that has it. Anything under the layer's element calling
         * `toggle(value)` would reach the layer's method, which takes no argument and
         * flips the whole value to a boolean. Nothing calls it from there today — the
         * optimistic path runs `run(nextWith(...))` instead — which is exactly the kind
         * of safety that lasts until the next edit.
         */
        toggleValue(value) {
            const idx = this._indexOf(value);
            if (idx >= 0) {
                this.selected.splice(idx, 1);
            } else {
                this.selected.push(value);
            }
            this._modelEvents?.commit();
            this._afterToggle();
            this.$refs.filterInput?.focus();
        },

        /**
         * Deselect (remove) a selected value.
         *
         * The removal takes the focused element away with it, and nothing else
         * puts focus back. The button that calls this sits INSIDE the pill, the
         * pills are keyed by VALUE, so Alpine drops exactly the node holding
         * focus — and the field leaves a press on that button out of its own
         * `focusAndOpen()` (`data-wk-pill-remove`), so no other handler runs
         * either. A destroyed active element leaves focus on `<body>`, which
         * starts the next Tab at the top of the document (WCAG 2.4.3). Same
         * shape and same treatment as tags-input's chip removal.
         */
        deselect(value) {
            const idx = this._indexOf(value);

            if (idx < 0) return;

            this.selected.splice(idx, 1);
            this._modelEvents?.commit();
            this._focusAfterRemoval(idx);
        },

        /**
         * Remove a pill through the optimistic layer nested here, and put focus
         * where `deselect()` puts it.
         *
         * The change travels as `run(nextWith(value))`, a new array and the same
         * server mutation as a pick, so it is undone the same way. Focus moves
         * here, at the reader's press, and never in the layer's after-hook,
         * which runs again when the server refuses. It moves only when the layer
         * took the change: a refused run removed nothing, and the pressed button
         * still holds focus. `run` belongs to the layer, the nearest scope that
         * has it, as in tags-input's `_commit()`.
         *
         * @param {string|number} value - The value whose pill was pressed.
         */
        runRemoval(value) {
            const idx = this._indexOf(value);

            if (typeof this.run !== 'function' || idx < 0) return;

            if (this.run(this.nextWith(value))) this._focusAfterRemoval(idx);
        },

        /**
         * Put focus on the remove button that took the removed pill's place.
         *
         * Same position first — that is the pill which moved up into the gap and
         * where the reader's eye already is, so removing several in a row keeps
         * working. Nothing there means the last pill was the one removed, so the
         * new last one takes focus; an empty set leaves only the filter input,
         * which is where `toggle()` and `onBackspace()` both end up anyway.
         *
         * The filter input takes it quietly (`_focusFieldQuietly()`): its own
         * focus handler opens the dropdown, and that would pop the option list
         * open on a gesture that was about taking a value away. For the same
         * reason it is not the target while a pill is left.
         *
         * After a tick, because the pills are re-rendered from the array — a
         * query before the template has caught up finds the buttons as they
         * were. The field element is resolved BEFORE the tick and the query runs
         * in it: the field survives the removal (only the pill inside it is
         * dropped), while the button the reader pressed does not. It goes
         * through `_fieldElement()` so a nested optimistic `x-data` cannot hide
         * the ref. Outside Alpine there is no tick and no DOM, so the guards make
         * this a no-op there rather than the throw a bare `this.$nextTick` would
         * be.
         */
        _focusAfterRemoval(index) {
            // Both resolved now, while the pressed button is still in the document. Alpine
            // resolves `$refs` and `$root` from the element whose expression called this, which
            // is that button, and by the tick it has gone with its pill: a walk up from a
            // detached node finds nothing, and focus stayed on `<body>`. The field and the
            // filter input outlive the removal, so holding them is safe.
            const field = this._fieldElement();
            const filterInput = this.$refs?.filterInput;
            const place = () => {
                const buttons = field && typeof field.querySelectorAll === 'function'
                    ? [...field.querySelectorAll('button')]
                    : [];
                const button = buttons[index] ?? buttons[buttons.length - 1];

                if (button) {
                    if (typeof button.focus === 'function') button.focus();

                    return;
                }

                this._focusFieldQuietly(filterInput);
            };

            if (typeof this.$nextTick === 'function') {
                this.$nextTick(place);
            } else {
                place();
            }
        },

        /**
         * Put focus on the filter input without opening the list.
         *
         * The input's own focus handler opens the list, which is right for a reader who tabs or
         * clicks into the field and wrong after a removal: that press took a value away and asked
         * for no options. `focus()` dispatches its event before it returns, so the flag is up for
         * exactly that one event and never swallows a focus the reader brings later.
         *
         * @param {HTMLElement|null|undefined} input - The filter input.
         */
        _focusFieldQuietly(input) {
            if (! input || typeof input.focus !== 'function') {
                return;
            }

            this._quietFocus = true;

            try {
                input.focus();
            } finally {
                this._quietFocus = false;
            }
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
         * The field and the panel, resolved so that a nested `x-data` cannot hide them.
         *
         * `x-ref` registers into the nearest `x-data` scope, and with `optimistic` set this
         * component wraps its own field in a second one — `wirekitOptimistic(...)` opens
         * before the field in the Blade and closes after the panel's teleport template. Every
         * ref of this component therefore lands in that child scope, while `_place()` runs in
         * the parent and would see an empty registry: the panel would be neither placed, sized
         * nor followed, and nothing would throw.
         *
         * The teleport is not the cause, though it looks like the obvious suspect. Alpine's
         * closest-element walk follows `_x_teleportBack`, so a ref on a teleported node
         * resolves back through its template into the component that owns it: outside the
         * optimistic wrapper `$refs.panel` resolves to exactly the teleported panel. The
         * cause is the nested optimistic scope, as in the sibling combobox.
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

        /**
         * Anchor the panel to the field.
         *
         * An `absolute` panel inside the field wrapper would be cut off at the edge
         * of any clipping ancestor, a card for instance, because clipping is not a
         * z-index question. Positioning it `fixed` against the field escapes that,
         * and `matchReferenceWidth` gives it the field's width. `fitViewport` caps the height to the room actually available so a
         * long list scrolls instead of running past the fold.
         */
        async _place() {
            const field = this._fieldElement();
            const panel = this._panelElement();

            if (! field || ! panel) {
                return;
            }

            this._stopAutoUpdate?.();
            const { stop } = await position(field, panel, {
                placement: this._placement,
                offset: 4,
                fitViewport: true,
                matchReferenceWidth: this._panelWidth === 'trigger',
                minReferenceWidth: this._panelWidth !== 'trigger',
                // Follow the field on scroll/resize; this is the panel the v2.19.0
                // fixed-positioning switch made most visibly pin on scroll.
                autoReposition: true,
                // A framework update patches the teleported panel against its template, whose
                // `style` carries none of what this call writes: the placement is gone while the
                // panel stays open, with its box unchanged, so `autoReposition`, which watches
                // boxes, sees nothing. This watches the attribute that is actually removed.
                repairErasure: true,
            });

            // The placement can wait frames for the panel to get a box, and the list can
            // close meanwhile: its observer would then follow a hidden panel.
            if (! this.dropdownOpen) {
                stop();
                return;
            }

            // One observer at a time: a placement that began after this one may have stored
            // its own first.
            this._stopAutoUpdate?.();
            this._stopAutoUpdate = stop;
        },
    };
}
