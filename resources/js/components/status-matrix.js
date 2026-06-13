/**
 * WireKit Status Matrix Alpine component.
 *
 * A 2D grid of typed status cells (rows x columns). Four cell types:
 *   - tristate: inherit -> allow -> deny (cycles on click / Enter / Space)
 *   - toggle:   off <-> on
 *   - status:   read-only status badge
 *   - heat:     read-only value with a color-scaled background
 *
 * Editable cell types (tristate / toggle) manage their value here and emit the
 * normalized cell map [rowKey:colKey => value] on every change — via a bubbling
 * `cell-change` event AND a JSON hidden input (so wire:model / a form bridge).
 * A `_baseline` snapshot powers the `isChanged` diff highlight (role-matrix).
 *
 * Keyboard grid navigation: arrow keys move focus between interactive cells
 * (roving focus via data-r / data-c coordinates); Enter / Space activates.
 *
 * Lifecycle resources held on `this`: NONE. No observers, timers, rAF loops, or
 * document-scoped listeners — only reactive state + Alpine-managed @keydown
 * bindings, so no destroy() hook is required.
 *
 * @param {Object} config
 * @param {Object} config.cells - initial value map { "rowKey:colKey": value }
 * @param {string} config.cellType - tristate | toggle | status | heat
 * @param {boolean} config.editable - whether interactive cell types mutate
 */
export default function wirekitStatusMatrix(config = {}) {
    return {
        cells: config.cells && typeof config.cells === 'object' ? { ...config.cells } : {},
        cellType: config.cellType || 'status',
        editable: !!config.editable,
        rowCount: Number(config.rowCount) || 0,
        colCount: Number(config.colCount) || 0,

        // Tristate cycle order. inherit is the neutral default.
        tristateOrder: ['inherit', 'allow', 'deny'],

        // Snapshot for the diff highlight; cloned so later edits don't mutate it.
        _baseline: config.cells && typeof config.cells === 'object' ? { ...config.cells } : {},

        key(rowKey, colKey) {
            return `${rowKey}:${colKey}`;
        },
        cellValue(rowKey, colKey) {
            const k = this.key(rowKey, colKey);
            return Object.prototype.hasOwnProperty.call(this.cells, k) ? this.cells[k] : null;
        },

        // tristate resolves a missing value to 'inherit' so a sparse seed still
        // renders a meaningful default state.
        tristateValue(rowKey, colKey) {
            return this.cellValue(rowKey, colKey) ?? 'inherit';
        },
        tristateLabel(rowKey, colKey) {
            return { allow: 'Allowed', deny: 'Denied', inherit: 'Inherited' }[this.tristateValue(rowKey, colKey)];
        },
        toggleOn(rowKey, colKey) {
            return this.cellValue(rowKey, colKey) === true || this.cellValue(rowKey, colKey) === 'on';
        },

        setCell(rowKey, colKey, value) {
            // Reassign the whole object so Alpine reliably tracks the change.
            this.cells = { ...this.cells, [this.key(rowKey, colKey)]: value };
            this._emit();
        },
        cycleTristate(rowKey, colKey) {
            if (!this.editable) return;
            const order = this.tristateOrder;
            const idx = order.indexOf(this.tristateValue(rowKey, colKey));
            this.setCell(rowKey, colKey, order[(idx + 1) % order.length]);
        },
        toggleCell(rowKey, colKey) {
            if (!this.editable) return;
            this.setCell(rowKey, colKey, !this.toggleOn(rowKey, colKey));
        },

        // Activate the right mutation for the cell type (Enter / Space / click).
        activate(rowKey, colKey) {
            if (this.cellType === 'tristate') this.cycleTristate(rowKey, colKey);
            else if (this.cellType === 'toggle') this.toggleCell(rowKey, colKey);
        },

        // Diff vs the seeded baseline — drives the "changed since save" ring.
        isChanged(rowKey, colKey) {
            const k = this.key(rowKey, colKey);
            return (this._baseline[k] ?? null) !== (this.cells[k] ?? null);
        },
        get changedCount() {
            const keys = new Set([...Object.keys(this.cells), ...Object.keys(this._baseline)]);
            let n = 0;
            keys.forEach((k) => {
                if ((this._baseline[k] ?? null) !== (this.cells[k] ?? null)) n += 1;
            });
            return n;
        },

        // ── Heat scaling ─────────────────────────────────────────────────
        // Normalize a numeric value into [0,1] across the matrix's min..max so
        // the cell background can color-mix between the surface and the scale
        // color. The value LABEL is always rendered too (never color-only).
        heatRatio(value) {
            const min = Number(config.heatMin ?? 0);
            const max = Number(config.heatMax ?? 100);
            if (max <= min) return 0;
            const v = Number(value);
            if (Number.isNaN(v)) return 0;
            return Math.max(0, Math.min(1, (v - min) / (max - min)));
        },

        // ── Keyboard grid navigation (roving focus) ──────────────────────
        moveFocus(e, r, c) {
            const deltas = {
                ArrowUp: [-1, 0], ArrowDown: [1, 0], ArrowLeft: [0, -1], ArrowRight: [0, 1],
            };
            if (e.key in deltas) {
                e.preventDefault();
                const nr = Math.max(0, Math.min(this.rowCount - 1, r + deltas[e.key][0]));
                const nc = Math.max(0, Math.min(this.colCount - 1, c + deltas[e.key][1]));
                this.$root.querySelector(`[data-r="${nr}"][data-c="${nc}"]`)?.focus();
            }
        },

        _emit() {
            this.$dispatch('cell-change', { cells: this.cells });
            if (this.$refs.model) {
                this.$refs.model.value = JSON.stringify(this.cells);
                this.$refs.model.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },
    };
}
