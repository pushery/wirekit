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
 * @param {Array}  config.columns - [{key,label,sortable?,align?,cellType?,
 *   subKey?,intentKey?,avatarKey?}] — the three optional keys let a ROW name its
 *   own second line, its own intent, and its own avatar.
 * @param {Object} config.avatarTints - initials -> {bg,fg}, resolved in PHP
 * @param {string} config.rowKey  - unique id field (default 'id')
 * @param {Array}  config.hidden  - initially-hidden column keys
 * @param {string} config.density - 'comfortable' | 'compact'
 * @param {string} config.mode    - 'client' (sort/filter here) | 'server'
 * @param {string} config.emptyText - the already-translated "no rows" sentence,
 *   handed in rather than assembled here: a sentence built from fragments in
 *   JavaScript cannot be translated, and word order is not the same in every
 *   language. Same shape as the wizard's announcement template.
 */
/**
 * The intents a cell may wear — the SAME seven `<x-wirekit::badge>` validates
 * against, and the same seven the Blade class table carries. Module scope
 * because two methods read it now: the built-in status-word scan and the
 * row-declared intent. Two copies of a closed list is how one of them quietly
 * stops accepting a word the other does.
 */
const KNOWN_INTENTS = ['primary', 'accent', 'info', 'success', 'warning', 'danger', 'neutral'];

export default function wirekitDataTable(config = {}) {
    return {
        /**
         * The selected row ids, serialized for the hidden input a form
         * (or wire:model) submits. `JSON` is unreachable from a directive under
         * Alpine's CSP build — the evaluator resolves names against the Alpine
         * scope alone — so the encoding happens here.
         */
        selectedJson() {
            return JSON.stringify(this.selected);
        },

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
        emptyText: typeof config.emptyText === 'string' ? config.emptyText : '',
        // initials -> {bg, fg}, resolved in PHP by `AvatarPalette` and handed over as
        // data. Deliberately NOT computed here: the palette is a crc32 hash, and a
        // second implementation of it would drift silently against the avatar
        // component the same person is rendered with two rows further up the page.
        avatarTints: config.avatarTints && typeof config.avatarTints === 'object' ? config.avatarTints : {},
        // prominence -> class, resolved in PHP so Tailwind compiles the literals and the drift
        // inventory can trace them. Read through a method rather than indexed in the template:
        // the fallback needs `??`, which is outside Alpine's CSP grammar, and an expression
        // outside that grammar is never evaluated on the CSP bundle — the binding goes inert
        // and nothing reports it.
        prominenceClasses: config.prominenceClasses && typeof config.prominenceClasses === 'object' ? config.prominenceClasses : {},

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

        /**
         * What the table's live region says right now.
         *
         * Searching a table is a status message in the WCAG 4.1.3 sense: the result
         * set changes under the reader without focus moving, and until this existed
         * the only announced state change in the whole table was the selection
         * count. Typing a query that matches nothing left the empty sentence on
         * screen and said nothing at all.
         *
         * It is bound to a live region's TEXT rather than to the visible empty
         * block's visibility, and that is the load-bearing part: a live region
         * announces a content change, and toggling an element between
         * `display: none` and visible is not one that every screen reader reports.
         * A text swap is.
         *
         * Non-empty returns the empty string on purpose. The alternative — a row
         * count on every keystroke — turns a status region into a metronome, and
         * the recovered case needs no announcement: the rows are back, and the
         * reader meets them by moving through the table.
         */
        get emptyAnnouncement() {
            return this.isEmpty ? this.emptyText : '';
        },

        // ── Cell helpers ─────────────────────────────────────────────────
        cellText(row, col) {
            const v = row[col.key];
            return v === null || v === undefined ? '' : String(v);
        },
        /**
         * The quieter second line of a cell, when the column asks for one.
         *
         * An admin table's ordinary cell is two lines, not one: order number over date,
         * customer over email, product over SKU. Measured across this package's four admin
         * data grids, between 29% and 50% of their cells are that shape — which is why none
         * of those pages could be built on this component and all of them are still on the
         * plain table.
         *
         * Deliberately a SECOND KEY rather than a template. A template per column is the
         * complete answer and a much larger one: the body is an Alpine `x-for` over rows, so
         * there is no Blade cell to hand back, and getting one means a real API. This covers
         * the measured majority, costs one optional field, and forecloses none of it — a
         * column can gain a template later and this stays the shortcut for the common case.
         *
         * Empty behaves as absent: a row whose sub-field is null renders one line, not one
         * line and a gap. Whether a row HAS the second value is data, not configuration.
         */
        subText(row, col) {
            if (! col.subKey) {
                return '';
            }
            const v = row[col.subKey];

            return v === null || v === undefined ? '' : String(v);
        },

        /**
         * The intent a ROW declares for itself, when the column points at a field
         * holding one.
         *
         * This is what makes the admin threshold cell expressible — `stock === 0`
         * reading `Out` in red, `stock < 10` reading `3 low` in amber, and anything
         * above it a plain tabular number. Three things vary there and all three are
         * functions of the value: the intent, the LABEL, and whether the cell is a
         * pill at all. A value -> intent map on the column can only ever express the
         * first, so a column-side threshold syntax would have closed one third of the
         * gap while the ticket read as closed.
         *
         * Handing the intent over per row moves the comparison back into the
         * application, where it is ordinary PHP next to the query that produced the
         * number — testable, translatable, and not a dialect this component has to
         * parse. The label comes along for free: it is just the cell's value.
         *
         * Empty behaves as absent, exactly as `subText` treats a missing sub-field —
         * that is the "no pill" arm, not a degenerate case. And an unrecognized name
         * returns empty rather than itself, because the class table would hand back
         * `undefined` for it and the pill would render with no classes at all: the
         * closed-list defect one level up, reached through the hatch built to escape one.
         */
        rowIntent(row, col) {
            if (! col.intentKey) {
                return '';
            }

            const v = row[col.intentKey];

            if (v === null || v === undefined || v === '') {
                return '';
            }

            const name = String(v).toLowerCase();

            return KNOWN_INTENTS.includes(name) ? name : '';
        },

        /**
         * The entries of a list-valued cell — one pill per entry.
         *
         * The tags cell of a customer table is the shape this exists for: a row carries none,
         * one, or four of them, and how many is DATA. `badge` draws exactly one pill from one
         * value, so a column of tags could not be expressed at all and the page stayed on the
         * plain table.
         *
         * A scalar is normalized into a one-element list rather than rejected. The alternative
         * — return nothing for a non-array — fails silently: the cell renders empty, which is
         * indistinguishable from a row that legitimately has no tags, and nothing anywhere says
         * the column was misconfigured. Being forgiving here has no failure mode; being strict
         * has one that cannot be seen.
         *
         * Empty entries drop out for the same reason `subText` treats empty as absent: a pill
         * containing nothing is a visual defect, not a value.
         */
        badgeItems(row, col) {
            const v = row[col.key];

            if (v === null || v === undefined || v === '') {
                return [];
            }

            return (Array.isArray(v) ? v : [v])
                .filter((entry) => entry !== null && entry !== undefined && entry !== '')
                .map((entry) => String(entry));
        },

        /**
         * How loud a column reads — one axis, three positions, the middle one being the absence
         * of an entry. An unrecognized value resolves to the middle rather than to `undefined`,
         * which is a value Alpine's class binding cannot use.
         */
        prominenceClass(col) {
            return this.prominenceClasses[col.prominence] || '';
        },

        /** The initials a row shows in its avatar circle, when the column asks for one. */
        avatarText(row, col) {
            if (! col.avatarKey) {
                return '';
            }

            const v = row[col.avatarKey];

            return v === null || v === undefined ? '' : String(v);
        },

        /**
         * The circle's own color pair, looked up rather than computed — see
         * `avatarTints` above for why the hash stays on the PHP side. A key with no
         * entry yields no style, so the circle falls back to its class-based default
         * instead of painting with `undefined`. That arm is reachable: an application
         * may push rows into `rows` client-side, and those never passed through the
         * render that built the map.
         */
        avatarStyle(row, col) {
            const tint = this.avatarTints[this.avatarText(row, col)];

            return tint ? `background-color: ${tint.bg}; color: ${tint.fg};` : '';
        },
        /**
         * Status word -> intent for a `cellType: 'badge'` column.
         *
         * The column is consulted FIRST, and that is the whole change. The built-in
         * vocabulary below is a closed list of English status words, and every application
         * whose statuses are not on it — another language, a domain word, `vip`, `lapsed`,
         * `storniert` — got `neutral` with no way to say otherwise. The list was also
         * documented as "common status words (paid, pending, failed, ...)", where the
         * ellipsis stood in for a set that existed nowhere but this function.
         *
         * `intents` on the column is a plain map of value -> intent, matched
         * case-insensitively for the same reason the built-in scan lowercases: a status
         * arriving as `Paid` from a database is the same word as `paid`.
         *
         * An unknown intent NAME falls back to neutral rather than returning a key the
         * class table has no entry for — indexing that table with a miss yields undefined,
         * and the pill would render with no classes at all. That would be this same defect
         * one level up: a silent closed list, reached from the escape hatch built to
         * escape one.
         */
        badgeIntent(value, col = null) {
            const v = String(value).toLowerCase();
            // Held four until 2026-08-29, so a column declaring
            // `'intents' => ['processing' => 'accent']` named a value the badge component
            // accepts and got `neutral` back — silently, because an unknown intent falls
            // back rather than complaining. One library, one word, two vocabularies is the
            // defect; the fallback only hid it. It lives at module scope now (see
            // `KNOWN_INTENTS`) because `rowIntent` reads the same list.
            const known = KNOWN_INTENTS;

            if (col && col.intents) {
                for (const key of Object.keys(col.intents)) {
                    if (String(key).toLowerCase() !== v) {
                        continue;
                    }

                    const intent = String(col.intents[key]).toLowerCase();

                    return known.includes(intent) ? intent : 'neutral';
                }
            }

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
