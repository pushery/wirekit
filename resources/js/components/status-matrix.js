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
 * (roving focus via data-r / data-c coordinates), Home / End jump to the first
 * and last cell of the row and Ctrl+Home / Ctrl+End to the first and last of the
 * grid; Enter / Space activates. Navigation works in a read-only matrix too —
 * only activation is gated on `editable`.
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
        /**
         * The cell grid, serialized for the hidden input a form
         * (or wire:model) submits. `JSON` is unreachable from a directive under
         * Alpine's CSP build — the evaluator resolves names against the Alpine
         * scope alone — so the encoding happens here.
         */
        cellsJson() {
            return JSON.stringify(this.cells);
        },

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
        // Home / End are part of the grid pattern, not an extra: with a single
        // tab stop into the matrix, arrows are the ONLY way across, so a wide
        // permission matrix costs one keypress per column to cross and there is
        // no way back to the row label but to hold the key down. Ctrl+Home /
        // Ctrl+End are the whole-grid pair, same as in a spreadsheet.
        //
        // The handler is bound whether or not the matrix is editable. Reading a
        // frozen grid is a legitimate use, and a role="grid" that promises
        // navigation and delivers none is worse than a plain table.
        moveFocus(e, r, c) {
            const deltas = {
                ArrowUp: [-1, 0], ArrowDown: [1, 0], ArrowLeft: [0, -1], ArrowRight: [0, 1],
            };

            // Declared without a seed: every branch below either assigns both or
            // returns, so a seed would be a value no statement can read — and one
            // that quietly suggests "unchanged" is a reachable outcome here.
            let nr;
            let nc;

            if (e.key in deltas) {
                nr = r + deltas[e.key][0];
                nc = c + deltas[e.key][1];
            } else if (e.key === 'Home') {
                // Ctrl+Home is the first cell of the grid; bare Home the first of the row.
                nr = e.ctrlKey || e.metaKey ? 0 : r;
                nc = 0;
            } else if (e.key === 'End') {
                nr = e.ctrlKey || e.metaKey ? this.rowCount - 1 : r;
                nc = this.colCount - 1;
            } else {
                return;
            }

            e.preventDefault();
            nr = Math.max(0, Math.min(this.rowCount - 1, nr));
            nc = Math.max(0, Math.min(this.colCount - 1, nc));
            this.$root.querySelector(`[data-r="${nr}"][data-c="${nc}"]`)?.focus();
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
