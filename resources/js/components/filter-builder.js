/**
 * WireKit Filter Builder Alpine component.
 *
 * An active-filter chip bar plus an add/edit popover with typed operator and
 * value editors. Each active filter is a removable chip (field + operator +
 * value); the "Add filter" popover picks a field, then an operator valid for
 * that field's type, then a typed value editor (text / number / select / date /
 * bool). On every change the normalized filter array [{field, op, value}] is
 * emitted via a bubbling `filter-change` event AND written (as JSON) to a hidden
 * input so the host can bridge it to Livewire / a form.
 *
 * Lifecycle resources held on `this`:
 *   - _onScroll / _onResize (window listeners) — close the fixed-position
 *     popover when the page scrolls or the viewport resizes under it (it is
 *     anchored once on open, so it would strand otherwise). Removed in destroy().
 * Everything else is plain reactive state (`open`) plus Alpine's own
 * `@click.outside` / `@keydown.escape` directives, whose teardown Alpine manages.
 *
 * WHY BOTH SUBTRACTIVE GESTURES PLACE FOCUS AND SAY SOMETHING. Removing a chip and
 * clearing the set are the two primary keyboard gestures here, and both used to end
 * with focus on `<body>` and nothing announced — so the next Tab restarted at the top
 * of the page, and the filter set, which is exactly the state a reader cannot see,
 * changed in silence.
 *
 * The two lose focus by different mechanisms, which is why neither fix covers the other.
 * The chip loop is INDEX-keyed (`:key="i"`), so Alpine drops the element holding the
 * highest key and rewrites the survivors in place: removing the LAST chip destroys the
 * button that was activated, while removing a middle one leaves it attached and silently
 * pointing at a different filter. `clearAll()` is unconditional — the Clear-all button is
 * `x-show`n on a non-empty set, `x-show` writes `display:none`, and the browser blurs a
 * focused element that becomes display:none.
 *
 * The sentences are TEMPLATES handed in from the Blade rather than built here. A sentence
 * assembled from fragments in JavaScript cannot be translated, and word order is not the
 * same in every language — the shape `tags-input` and `wizard` both use.
 *
 * @param {Object} config
 * @param {Array}  config.fields - field definitions [{key,label,type,operators?,options?}]
 * @param {Array}  config.value  - initial active filters [{field,op,value}]
 * @param {Object} [config.announcements] - Translated templates: `removed` (takes `:name`)
 *   and `cleared`.
 */
import { position } from '../utils/floating.js';

/**
 * What counts as focusable inside the popover. Same selector hover-card and
 * navigation-menu use for their teleported panels — same question, so the same
 * answer rather than a fourth list that drifts from the other three.
 */
const PANEL_FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * Is this element actually rendered, or only present?
 *
 * `x-show` writes `display: none`, which leaves the element in the DOM and in
 * every `querySelectorAll` result — but `focus()` on it does nothing and the
 * browser drops focus on `<body>` instead. "Clear all" is exactly that element
 * here: it is hidden until a filter exists, and it is the first thing after the
 * trigger, so the empty-state Tab would have landed nowhere.
 */
function isRendered(el) {
    return typeof el.getClientRects !== 'function' || el.getClientRects().length > 0;
}

export default function wirekitFilterBuilder(config = {}) {
    const announcements = config.announcements && typeof config.announcements === 'object'
        ? config.announcements
        : {};

    // The component's root element, resolved ONCE while something is still attached
    // to resolve it from.
    //
    // ⚠️ `$root` IS RESOLVED WHEN IT IS READ, by walking up from `$el` to the nearest
    // `[x-data]`. `remove()` runs from a chip's own remove button, so `$el` is that
    // button — and the focus move waits for `$nextTick`, by which time removing the
    // last chip has taken the button with it. The walk from a detached node reaches
    // nothing. Same defect, same shape, as the ones fixed in `toast.js` and
    // `tags-input.js`.
    //
    // A closure variable rather than a property: a DOM node stored on the reactive
    // object comes back out as a Proxy, and `.focus()` through that proxy is not the
    // same call.
    let rootEl = null;

    /**
     * @param {{$root?: Element}} ctx
     * @returns {Element|null}
     */
    const rootOf = (ctx) => {
        if (! rootEl && ctx) {
            rootEl = ctx.$root ?? null;
        }

        return rootEl;
    };

    return {
        /**
         * The filter tree, serialized for the hidden input a form
         * (or wire:model) submits. `JSON` is unreachable from a directive under
         * Alpine's CSP build — the evaluator resolves names against the Alpine
         * scope alone — so the encoding happens here.
         */
        filtersJson() {
            return JSON.stringify(this.filters);
        },

        // Field definitions (key/label/type/operators?/options?).
        fields: Array.isArray(config.fields) ? config.fields : [],
        // Active filters. Clone each entry so editing the draft never mutates
        // the seeded array in place before the user applies.
        filters: Array.isArray(config.value) ? config.value.map((f) => ({ ...f })) : [],

        /**
         * What a screen reader is told after the set changes. Empty until one happens.
         *
         * `filterAnnouncement`, not `announcement`: the shorter name belongs to the
         * optimistic layer, and a component that later nests one would shadow this for
         * everything under the layer's element — a property whose value depends on where
         * in the template it is read from.
         */
        filterAnnouncement: '',

        // Popover state.
        open: false,
        editIndex: null, // null = adding a new filter; number = editing existing
        draft: { field: '', op: '', value: '' },

        // Default operator catalog, keyed by field type. A field definition may
        // override with its own `operators` array.
        _operators: {
            text: [
                { op: 'contains', label: 'contains' },
                { op: 'equals', label: 'is' },
                { op: 'starts', label: 'starts with' },
                { op: 'ends', label: 'ends with' },
            ],
            number: [
                { op: 'eq', label: '=' },
                { op: 'gt', label: '>' },
                { op: 'lt', label: '<' },
                { op: 'gte', label: '≥' },
                { op: 'lte', label: '≤' },
            ],
            select: [
                { op: 'is', label: 'is' },
                { op: 'isnot', label: 'is not' },
            ],
            date: [
                { op: 'on', label: 'on' },
                { op: 'before', label: 'before' },
                { op: 'after', label: 'after' },
            ],
            bool: [{ op: 'is', label: 'is' }],
        },

        // ── Lookups ──────────────────────────────────────────────────────
        fieldDef(key) {
            return this.fields.find((f) => f.key === key) || null;
        },
        fieldLabel(key) {
            const def = this.fieldDef(key);
            return def ? def.label : key;
        },
        operatorsFor(key) {
            const def = this.fieldDef(key);
            if (!def) return [];
            if (Array.isArray(def.operators) && def.operators.length) return def.operators;
            return this._operators[def.type] || this._operators.text;
        },
        opLabel(key, op) {
            const found = this.operatorsFor(key).find((o) => o.op === op);
            return found ? found.label : op;
        },

        // Human-readable value for a chip — maps option values to labels, and
        // renders booleans / arrays sensibly.
        displayValue(filter) {
            const def = this.fieldDef(filter.field);
            const v = filter.value;
            if (def && def.type === 'bool') return v ? 'Yes' : 'No';
            if (Array.isArray(v)) return v.join(', ');
            if (def && Array.isArray(def.options)) {
                const opt = def.options.find((o) => String(o.value) === String(v));
                if (opt) return opt.label;
            }
            return String(v ?? '');
        },
        chipText(filter) {
            return `${this.fieldLabel(filter.field)} ${this.opLabel(filter.field, filter.op)} ${this.displayValue(filter)}`;
        },

        // ── Lifecycle ────────────────────────────────────────────────────
        _onScroll: null,
        _onResize: null,
        init() {
            // Close on page scroll / viewport resize. The popover is teleported +
            // position:fixed, anchored ONCE on open — a page scroll under it
            // strands the panel visually detached from the trigger (same class as
            // the notification-center flyout). Scrolling INSIDE the
            // panel keeps working — only outside scrolls dismiss. Capture catches
            // every scroller; passive per perf-hygiene.
            if (typeof window !== 'undefined') {
                this._onScroll = (e) => {
                    if (!this.open) return;
                    const panel = this.$refs.panel;
                    if (panel && e.target instanceof Node && panel.contains(e.target)) return;
                    this.close();
                };
                window.addEventListener('scroll', this._onScroll, { passive: true, capture: true });
                this._onResize = () => { if (this.open) this.close(); };
                window.addEventListener('resize', this._onResize, { passive: true });
            }
        },
        destroy() {
            if (this._onScroll) {
                window.removeEventListener('scroll', this._onScroll, { capture: true });
                this._onScroll = null;
            }
            if (this._onResize) {
                window.removeEventListener('resize', this._onResize);
                this._onResize = null;
            }
        },

        // ── Popover control ──────────────────────────────────────────────
        openAdd() {
            this.editIndex = null;
            const first = this.fields[0];
            this.draft = { field: first ? first.key : '', op: '', value: '' };
            this._syncDraftDefaults();
            this.open = true;
            this._focusFirstControl();
        },
        openEdit(i) {
            this.editIndex = i;
            this.draft = { ...this.filters[i] };
            this.open = true;
            this._focusFirstControl();
        },
        // restoreFocus=true returns focus to the trigger (escape / cancel /
        // apply); click-outside passes false so we don't yank focus back when
        // the user deliberately clicked elsewhere.
        close(restoreFocus = false) {
            this.open = false;
            if (restoreFocus) this.$refs.trigger?.focus();
        },

        /**
         * Focusable controls inside the popover, in DOM order.
         *
         * Resolved through the ref rather than a descendant query on the root:
         * the panel is teleported, so it is not a descendant of anything this
         * component renders inline. Re-read on every keypress because the set is
         * not fixed — the value editor swaps with the field's type, and Apply is
         * disabled until the draft is complete, so "the last control" is Cancel
         * on a half-filled draft and Apply on a finished one.
         */
        _panelFocusables() {
            const panel = this.$refs.panel;

            return panel ? [...panel.querySelectorAll(PANEL_FOCUSABLE)].filter(isRendered) : [];
        },

        /**
         * Move to the control that FOLLOWS the trigger on the page.
         *
         * Where a forward Tab out of the popover belongs: the panel is drawn
         * beside the trigger, so leaving it should continue from the trigger and
         * not from the end of the document, where the panel's markup happens to
         * live. Anything inside the trigger is skipped (a descendant also
         * "follows" it by document position) and so is the overlay root, which
         * holds this panel and every other teleported one.
         */
        _focusAfterTrigger() {
            const trigger = this.$refs.trigger;

            if (! trigger) return;

            const overlayRoot = document.getElementById('wk-overlay-root');

            const next = [...document.querySelectorAll(PANEL_FOCUSABLE)].find((el) => {
                if (trigger.contains(el)) return false;
                if (overlayRoot?.contains(el)) return false;
                if (! isRendered(el)) return false;

                return Boolean(trigger.compareDocumentPosition(el) & Node.DOCUMENT_POSITION_FOLLOWING);
            });

            next?.focus({ preventScroll: true });
        },

        /**
         * Tab pressed inside the popover — handle both of its edges.
         *
         * ⚠️ NEITHER EDGE IS WHAT THE BROWSER WOULD DO, because the panel is
         * teleported to the end of `<body>` while it is drawn beside the trigger,
         * and sequential focus order follows the DOM rather than the screen.
         * Opening the popover moves focus into it (`_focusFirstControl`), so
         * before this existed a Tab off Apply left the DOCUMENT for the browser
         * chrome, and a Shift+Tab off the field select landed on whatever
         * precedes the overlay root — somewhere else entirely on screen, with the
         * dialog still open and painted over the page.
         *
         * Leaving closes it, which is what this popover's other two dismissals
         * already mean: Escape and a click outside both drop the draft and return
         * the reader to the page. A non-modal dialog is left, not escaped from.
         */
        tabWithinPanel(event) {
            const focusables = this._panelFocusables();

            if (! focusables.length) return;

            if (event.shiftKey && document.activeElement === focusables[0]) {
                event.preventDefault();
                // Identical to Cancel: close and hand focus back to the trigger.
                this.close(true);

                return;
            }

            if (! event.shiftKey && document.activeElement === focusables[focusables.length - 1]) {
                event.preventDefault();
                // Focus moves BEFORE the panel hides. `x-show` writes
                // `display: none`, and hiding the subtree that holds focus makes
                // the browser drop it on `<body>` — after our own focus() call,
                // which would then have accomplished nothing.
                this._focusAfterTrigger();
                this.close();
            }
        },

        // When the draft field changes, reset the operator to the first valid
        // one for the new field type and clear the stale value.
        onFieldChange() {
            this.draft.value = '';
            this.draft.op = '';
            this._syncDraftDefaults();
        },
        _syncDraftDefaults() {
            const ops = this.operatorsFor(this.draft.field);
            if (!ops.some((o) => o.op === this.draft.op)) {
                this.draft.op = ops.length ? ops[0].op : '';
            }
            if (this.draftValueType() === 'bool' && this.draft.value === '') {
                this.draft.value = true;
            }
        },
        draftValueType() {
            const def = this.fieldDef(this.draft.field);
            return def ? def.type : 'text';
        },
        draftOptions() {
            const def = this.fieldDef(this.draft.field);
            return def && Array.isArray(def.options) ? def.options : [];
        },

        // Apply is disabled until the draft is complete (a value is required for
        // every type except bool, whose value is always set).
        canApply() {
            if (!this.draft.field || !this.draft.op) return false;
            if (this.draftValueType() === 'bool') return true;
            const v = this.draft.value;
            if (Array.isArray(v)) return v.length > 0;
            return v !== '' && v !== null && v !== undefined;
        },

        // Normalize a draft value before it's committed. For bool fields the <select>
        // can deliver the option's DOM string ("true"/"false") instead of a real
        // boolean (Alpine reads el.value on a non-multiple select); coerce it so the
        // committed filter — and the emitted JSON — always carries a real boolean. The
        // .boolean modifier on the select fixes the source; this is the defensive net
        // for any path that bypasses it (e.g. a stored value re-applied verbatim).
        _coerceValue(field, value) {
            const def = this.fieldDef(field);
            if (def && def.type === 'bool') {
                return typeof value === 'string' ? value === 'true' : Boolean(value);
            }
            return value;
        },

        apply() {
            if (!this.canApply()) return;
            const entry = { field: this.draft.field, op: this.draft.op, value: this._coerceValue(this.draft.field, this.draft.value) };
            if (this.editIndex === null) {
                this.filters.push(entry);
            } else {
                this.filters.splice(this.editIndex, 1, entry);
            }
            this.close(true);
            this._emit();
        },
        /**
         * Drop one chip, then put focus somewhere and say what happened.
         *
         * The chip text is read BEFORE the splice — afterwards the entry is gone and
         * the sentence would name nothing.
         */
        remove(i) {
            const removed = this.filters[i];

            if (removed === undefined) return;

            const name = this.chipText(removed);

            this.filters.splice(i, 1);
            this._emit();
            this._announce(announcements.removed, { name });
            // Resolved HERE — synchronously, while the button that anchors the scope is
            // still in the document. The move itself waits for the tick.
            rootOf(this);
            this._focusAfterRemoval(i);
        },

        /**
         * Empty the set.
         *
         * Focus moves FIRST, and the order is the whole fix: the Clear-all button is
         * `x-show`n on a non-empty set, so emptying it writes `display:none` onto the
         * element the reader just activated and the browser drops focus to `<body>`.
         * Moving to the trigger before that happens means there is nothing to blur.
         * No `$nextTick` for the same reason — a deferred move would arrive after the
         * button had already gone dark.
         */
        clearAll() {
            this.$refs?.trigger?.focus();
            this.filters = [];
            this._emit();
            this._announce(announcements.cleared, {});
        },

        /**
         * Put focus on the chip that took the removed one's place.
         *
         * Same index first — that is the filter that moved up into the gap, and it is
         * where the reader's eye already is. Nothing there means the last chip was the
         * one removed, so the new last chip takes it; an empty set leaves the Add-filter
         * trigger, which is the only control left and where a reader would go next.
         *
         * After a tick, because the chips are re-rendered from the array: querying
         * before the template has caught up finds the buttons as they were. Outside
         * Alpine there is no tick and no DOM — the guards make this a no-op there rather
         * than the throw a bare `this.$nextTick` would be.
         *
         * The root comes from the cache the caller filled, NOT from `$root` read here:
         * by then the button that anchored the scope may be detached.
         */
        _focusAfterRemoval(index) {
            const place = () => {
                const root = rootOf(this);

                if (! root || typeof root.querySelectorAll !== 'function') {
                    this.$refs?.trigger?.focus();

                    return;
                }

                const buttons = root.querySelectorAll('[data-wk-filter-remove]');
                const target = buttons[index] ?? buttons[buttons.length - 1] ?? this.$refs?.trigger;

                if (target && typeof target.focus === 'function') target.focus();
            };

            if (typeof this.$nextTick === 'function') {
                this.$nextTick(place);

                return;
            }

            place();
        },

        /**
         * Write one sentence into the live region.
         *
         * Cleared first, then set in a microtask: writing an identical string into a live
         * region changes nothing, so removing two chips that read the same would be
         * answered once. Same shape, same reason, as `tags-input`.
         */
        _announce(template, replacements) {
            if (typeof template !== 'string' || template === '') return;

            const text = Object.keys(replacements).reduce(
                (carry, key) => carry.split(`:${key}`).join(replacements[key]),
                template
            );

            this.filterAnnouncement = '';
            queueMicrotask(() => { this.filterAnnouncement = text; });
        },

        // ── Emission ─────────────────────────────────────────────────────
        _emit() {
            // Primary contract: a bubbling event carrying the normalized array.
            this.$dispatch('filter-change', { filters: this.filters });
            // Form / Livewire bridge: JSON in a hidden input + native input event.
            if (this.$refs.model) {
                this.$refs.model.value = JSON.stringify(this.filters);
                this.$refs.model.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },

        // Move focus into the popover when it opens (a11y). $nextTick waits for
        // the x-show'd panel to be in the DOM before focusing.
        _focusFirstControl() {
            this.$nextTick(async () => {
                // Anchor the teleported (fixed) panel to the trigger with flip/shift so
                // it opens in the direction with room and never lands off-frame or behind
                // sibling content. crossAxisShift keeps it inside the viewport on narrow
                // screens (mirrors <x-wirekit::popover>). Positions once on open; the
                // click.outside / escape close it.
                if (this.$refs.trigger && this.$refs.panel) {
                    await position(this.$refs.trigger, this.$refs.panel, {
                        placement: 'bottom-start',
                        offset: 6,
                        crossAxisShift: true,
                    });
                }
                this.$refs.fieldSelect?.focus();
            });
        },
    };
}
