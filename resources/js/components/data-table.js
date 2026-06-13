/**
 * WireKit Data Table Alpine component (client mode).
 *
 * The ergonomic 80%-case wrapper: hand it a `rows` array and a `columns`
 * definition and it sorts, searches, selects, toggles column visibility, and
 * switches density entirely client-side — no backend round-trip. For 10k+ rows
 * a developer drives the same UI from Livewire instead (the server contract:
 * sort-change / search-change / selection-change events + wire:model bridges).
 *
 * Selection emits the id list via a `selection-change` event AND a JSON hidden
 * input; sort + search emit `sort-change` / `search-change` so server mode can
 * re-query.
 *
 * Lifecycle resources held on `this`: NONE. Pure reactive state — no observers,
 * timers, rAF loops, or document listeners, so no destroy() hook is required.
 *
 * @param {Object} config
 * @param {Array}  config.rows    - row objects (client mode)
 * @param {Array}  config.columns - [{key,label,sortable?,align?,cellType?}]
 * @param {string} config.rowKey  - unique id field (default 'id')
 * @param {Array}  config.hidden  - initially-hidden column keys
 * @param {string} config.density - 'comfortable' | 'compact'
 * @param {string} config.mode    - 'client' (sort/filter here) | 'server'
 */
export default function wirekitDataTable(config = {}) {
    return {
        rows: Array.isArray(config.rows) ? config.rows.map((r) => ({ ...r })) : [],
        columns: Array.isArray(config.columns) ? config.columns : [],
        rowKey: config.rowKey || 'id',
        mode: config.mode || 'client',
        sortKey: config.sortKey || null,
        sortDir: config.sortDir || 'asc',
        search: '',
        selected: [],
        density: config.density || 'comfortable',
        hiddenKeys: Array.isArray(config.hidden) ? [...config.hidden] : [],

        // ── Columns ──────────────────────────────────────────────────────
        get visibleColumns() {
            return this.columns.filter((c) => !this.hiddenKeys.includes(c.key));
        },
        isColumnVisible(key) {
            return !this.hiddenKeys.includes(key);
        },
        toggleColumn(key) {
            this.hiddenKeys = this.hiddenKeys.includes(key)
                ? this.hiddenKeys.filter((k) => k !== key)
                : [...this.hiddenKeys, key];
        },

        // ── Search + sort (client mode) ─────────────────────────────────
        get filteredRows() {
            const q = this.search.trim().toLowerCase();
            if (!q || this.mode === 'server') return this.rows;
            return this.rows.filter((r) => this.columns.some((c) => String(r[c.key] ?? '').toLowerCase().includes(q)));
        },
        get displayRows() {
            if (this.mode === 'server' || !this.sortKey) return this.filteredRows;
            const rows = [...this.filteredRows];
            const key = this.sortKey;
            const dir = this.sortDir === 'asc' ? 1 : -1;
            rows.sort((a, b) => {
                let av = a[key];
                let bv = b[key];
                if (typeof av !== 'number' || typeof bv !== 'number') {
                    av = String(av ?? '').toLowerCase();
                    bv = String(bv ?? '').toLowerCase();
                }
                if (av < bv) return -1 * dir;
                if (av > bv) return 1 * dir;
                return 0;
            });
            return rows;
        },
        toggleSort(key) {
            const col = this.columns.find((c) => c.key === key);
            if (!col || col.sortable === false) return;
            if (this.sortKey === key) {
                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortKey = key;
                this.sortDir = 'asc';
            }
            this.$dispatch('sort-change', { key: this.sortKey, dir: this.sortDir });
        },
        ariaSort(key) {
            if (this.sortKey !== key) return 'none';
            return this.sortDir === 'asc' ? 'ascending' : 'descending';
        },
        onSearch() {
            this.$dispatch('search-change', { value: this.search });
        },

        // ── Selection ────────────────────────────────────────────────────
        rowId(row) {
            return row[this.rowKey];
        },
        isSelected(row) {
            return this.selected.includes(this.rowId(row));
        },
        toggleSelect(row) {
            const id = this.rowId(row);
            this.selected = this.selected.includes(id)
                ? this.selected.filter((s) => s !== id)
                : [...this.selected, id];
            this._emitSelection();
        },
        get allSelected() {
            const ids = this.displayRows.map((r) => this.rowId(r));
            return ids.length > 0 && ids.every((id) => this.selected.includes(id));
        },
        get someSelected() {
            return this.selected.length > 0 && !this.allSelected;
        },
        toggleSelectAll() {
            this.selected = this.allSelected ? [] : this.displayRows.map((r) => this.rowId(r));
            this._emitSelection();
        },
        clearSelection() {
            this.selected = [];
            this._emitSelection();
        },
        get selectedCount() {
            return this.selected.length;
        },

        // ── Density + state ──────────────────────────────────────────────
        setDensity(d) {
            this.density = d;
        },
        get isEmpty() {
            return this.displayRows.length === 0;
        },

        // ── Cell helpers ─────────────────────────────────────────────────
        cellText(row, col) {
            const v = row[col.key];
            return v === null || v === undefined ? '' : String(v);
        },
        // Status-word → intent token suffix for a `cellType: 'badge'` column.
        badgeIntent(value) {
            const v = String(value).toLowerCase();
            if (['met', 'pass', 'paid', 'active', 'done', 'success', 'completed', 'approved'].includes(v)) return 'success';
            if (['pending', 'at-risk', 'warning', 'review', 'processing'].includes(v)) return 'warning';
            if (['failed', 'error', 'inactive', 'overdue', 'rejected', 'canceled'].includes(v)) return 'danger';
            return 'neutral';
        },

        _emitSelection() {
            this.$dispatch('selection-change', { selected: this.selected });
            if (this.$refs.selModel) {
                this.$refs.selModel.value = JSON.stringify(this.selected);
                this.$refs.selModel.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },
    };
}
