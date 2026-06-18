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
 * @param {Object} config
 * @param {Array}  config.fields - field definitions [{key,label,type,operators?,options?}]
 * @param {Array}  config.value  - initial active filters [{field,op,value}]
 */
import { position } from '../utils/floating.js';

export default function wirekitFilterBuilder(config = {}) {
    return {
        // Field definitions (key/label/type/operators?/options?).
        fields: Array.isArray(config.fields) ? config.fields : [],
        // Active filters. Clone each entry so editing the draft never mutates
        // the seeded array in place before the user applies.
        filters: Array.isArray(config.value) ? config.value.map((f) => ({ ...f })) : [],

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
        remove(i) {
            this.filters.splice(i, 1);
            this._emit();
        },
        clearAll() {
            this.filters = [];
            this._emit();
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
