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
 * What a render changes (the rows, their avatar tints, the sort, the filter and the
 * hidden columns the server applied) arrives through a `<template data-wk-data-table-state>`
 * carrier rather than through `x-data`, so a Livewire render does not rebuild the component
 * and take the browser-only state with it. The carrier is read on init and after
 * every Livewire commit.
 *
 * Showing or hiding a column dispatches `wirekit:data-table-columns-changed` with the hidden
 * and the visible keys, so an application can keep the choice with the reader's account and
 * hand it back through `hidden` on the next visit.
 *
 * Lifecycle resources held on `this`: `_unhookServerState`, the Livewire commit hook, and
 * `_expectingTimer`, the bound on waiting for a round trip to start; both are released in
 * destroy(). No observers or rAF loops.
 *
 * @param {Object} config
 * @param {Array}  config.rows    - row objects, used when there is no state carrier
 * @param {Array}  config.columns - [{key,label,sortable?,align?,cellType?,
 *   subKey?,intentKey?,avatarKey?}] — the three optional keys let a ROW name its
 *   own second line, its own intent, and its own avatar.
 * @param {Object} config.avatarTints - initials -> {bg,fg}, resolved in PHP
 * @param {string} config.rowKey  - unique id field (default 'id')
 * @param {Array}  config.hidden  - initially-hidden column keys, used when there is no state
 *   carrier or it names none
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

/** Two lists of column keys name the same columns, in whatever order. */
const sameKeys = (a, b) => a.length === b.length && [...a].sort().join('\u0000') === [...b].sort().join('\u0000');

import { pluralize } from '../utils/plural.js';

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
        // Two directions exist. Anything else used to pass straight through, and ariaSort()
        // reads every value but "asc" as descending.
        sortDir: config.sortDir === 'desc' ? 'desc' : 'asc',
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

        // ── State from the server ────────────────────────────────────────
        // The last state read from the carrier, and its raw text, for the comparison below.
        _serverState: null,
        _serverStateRaw: null,
        _unhookServerState: null,

        // ── Waiting on the server ────────────────────────────────────────
        // The translated sentence the status region speaks while the table waits.
        loadingText: typeof config.loadingText === 'string' ? config.loadingText : '',
        // A wait the server declared through the `loading` prop.
        _serverBusy: false,
        // Round trips this table started and has not seen come back.
        _requestsOut: 0,
        // A sort or search was just sent out; the next commit of the table's component carries it.
        _expectingServer: false,
        _expectingTimer: null,

        init() {
            this._readServerState();

            if (typeof window !== 'undefined' && window.Livewire?.hook) {
                this._unhookServerState = window.Livewire.hook('commit', ({ component, succeed, fail }) => {
                    // The commit that carries a sort or search this table just sent: the first one
                    // of the Livewire component the table sits in. Any other commit only brings
                    // state, and must not make the table announce a wait it did not start.
                    const carriesOurs = this._expectingServer && component?.el?.contains?.(this.$root) === true;

                    if (carriesOurs) {
                        this._expectingServer = false;
                        clearTimeout(this._expectingTimer);
                        this._expectingTimer = null;
                        this._requestsOut++;
                    }

                    succeed(() => queueMicrotask(() => {
                        this._readServerState();

                        if (carriesOurs) {
                            this._roundTripBack();
                        }
                    }));

                    // An error or a canceled request ends the wait just as a response does.
                    if (carriesOurs) {
                        fail(() => queueMicrotask(() => this._roundTripBack()));
                    }
                });
            }
        },

        destroy() {
            if (this._unhookServerState) {
                this._unhookServerState();
                this._unhookServerState = null;
            }
            clearTimeout(this._expectingTimer);
            this._expectingTimer = null;
        },

        /**
         * Whether the table is waiting on the server.
         *
         * `loading` was the only source of this, and it is read when the server renders. A
         * Livewire response is rendered after the wait is over, so from a render the prop was
         * false for the whole wait, or, as the documented recipe had it, true for good. The
         * table knows when a round trip it started is out, so that is the half it now tracks
         * itself; the prop remains for a wait only the server knows about.
         */
        get busy() {
            return this._serverBusy || this._requestsOut > 0;
        },

        /** `aria-busy` while waiting, and no attribute at all otherwise. */
        ariaBusy() {
            return this.busy ? 'true' : null;
        },

        /** What the one status region says: the wait wins over the empty result. */
        get statusAnnouncement() {
            return this.busy ? this.loadingText : this.emptyAnnouncement;
        },

        /**
         * A sort or search was just sent out. In server mode the next commit of the table's own
         * component carries it; a listener that starts no round trip at all must not leave the
         * table expecting one, so the expectation lapses if no commit starts shortly.
         */
        _expectRoundTrip() {
            if (this.mode !== 'server') {
                return;
            }

            this._expectingServer = true;
            clearTimeout(this._expectingTimer);
            this._expectingTimer = setTimeout(() => {
                this._expectingServer = false;
                this._expectingTimer = null;
            }, 1000);
        },

        _roundTripBack() {
            this._requestsOut = Math.max(0, this._requestsOut - 1);
        },

        /**
         * Take what the server rendered into the state carrier.
         *
         * The rows and their avatar tints are the server's outright. The sort and the filter
         * are shared with the reader, so each is taken only when the server moved it AND the
         * local value still equals what the server said last time. Otherwise the reader has
         * moved on while the round trip was out: a query typed one letter further than the
         * results that just arrived, or a second click on a header. Overwriting that would
         * throw away input, and the reader's own change is already on its way to the server.
         */
        _readServerState() {
            const carrier = this.$root?.querySelector?.(':scope > template[data-wk-data-table-state]');
            const raw = carrier ? carrier.getAttribute('data-wk-data-table-state') : null;

            if (raw === null || raw === this._serverStateRaw) {
                return;
            }

            let next;

            try {
                next = JSON.parse(raw);
            } catch {
                return;
            }

            const previous = this._serverState;
            this._serverState = next;
            this._serverStateRaw = raw;

            this.rows = Array.isArray(next.rows) ? next.rows.map((r) => ({ ...r })) : [];
            this.avatarTints = next.avatarTints && typeof next.avatarTints === 'object' ? next.avatarTints : {};
            this._serverBusy = next.loading === true;

            const sortKey = next.sortKey || null;
            const sortDir = next.sortDir === 'desc' ? 'desc' : 'asc';

            if (previous === null) {
                this.sortKey = sortKey;
                this.sortDir = sortDir;
            } else {
                const previousKey = previous.sortKey || null;
                const previousDir = previous.sortDir === 'desc' ? 'desc' : 'asc';
                const serverMoved = sortKey !== previousKey || sortDir !== previousDir;
                const readerStill = this.sortKey === previousKey && this.sortDir === previousDir;

                if (serverMoved && readerStill) {
                    this.sortKey = sortKey;
                    this.sortDir = sortDir;
                }
            }

            if (typeof next.search === 'string') {
                const previousSearch = previous && typeof previous.search === 'string' ? previous.search : null;

                if (previousSearch === null || (next.search !== previousSearch && this.search === previousSearch)) {
                    this.search = next.search;
                }
            }

            // The hidden columns are shared the same way. A render that names a list the server
            // moved replaces the reader's, provided the reader has not changed theirs since: then
            // the reader's own choice is on its way to the server and the answer to it is still
            // coming. A render that names no list leaves the reader's alone.
            if (Array.isArray(next.hidden)) {
                const nextHidden = next.hidden.map(String);
                const previousHidden = previous && Array.isArray(previous.hidden) ? previous.hidden.map(String) : null;

                if (previousHidden === null || (! sameKeys(nextHidden, previousHidden) && sameKeys(this.hiddenKeys, previousHidden))) {
                    this.hiddenKeys = nextHidden;
                }
            }
        },

        // ── Columns ──────────────────────────────────────────────────────
        get visibleColumns() {
            return this.columns.filter((c) => !this.hiddenKeys.includes(c.key));
        },
        isColumnVisible(key) {
            return !this.hiddenKeys.includes(key);
        },
        /**
         * Is this the column that freezes against the inline start edge? The position comes
         * from the loop's own order rather than from `:first-child`, so hiding a column moves
         * the frozen cell with it instead of freezing something the reader cannot see.
         *
         * It lives here rather than in the directive because the shape it needs there —
         * `visibleColumns[0]?.key` — is outside Alpine's CSP grammar. An expression the CSP
         * build cannot parse is never evaluated, so the binding would be silently inert on
         * that bundle and the column would simply not freeze, with nothing reporting it.
         * Every column is hideable, so the empty case is reachable: guard the lookup rather
         * than compare against `undefined`.
         */
        isFrozenColumn(col) {
            const first = this.visibleColumns[0];

            return first !== undefined && first.key === col.key;
        },
        toggleColumn(key) {
            this.hiddenKeys = this.hiddenKeys.includes(key)
                ? this.hiddenKeys.filter((k) => k !== key)
                : [...this.hiddenKeys, key];

            // The choice leaves the browser only through this event. Both lists, so a listener
            // that stores the visible columns does not have to know the column set.
            this.$dispatch('wirekit:data-table-columns-changed', {
                hidden: [...this.hiddenKeys],
                visible: this.visibleColumns.map((c) => c.key),
            });
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
                // A column picks its own first direction. A column of counts is clicked to see
                // the most first, so `sortStart: 'desc'` saves that second click every time.
                this.sortDir = col.sortStart === 'desc' ? 'desc' : 'asc';
            }
            this.$dispatch('sort-change', { key: this.sortKey, dir: this.sortDir });
            this._expectRoundTrip();
        },
        ariaSort(key) {
            if (this.sortKey !== key) return 'none';
            return this.sortDir === 'asc' ? 'ascending' : 'descending';
        },
        onSearch() {
            this.$dispatch('search-change', { value: this.search });
            this._expectRoundTrip();
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
        // Sample counts -> translated templates, and the app locale. The selection count
        // only exists in the browser, so the plural form is chosen here.
        _selectionPhrases: config.selectionPhrases || {},
        _locale: config.locale || 'en',

        // The selection readout, plural-correct in the reader's locale. It shipped as a
        // bare English word inside an aria-live region.
        get selectionSummary() {
            return pluralize(this._selectionPhrases, this.selectedCount, this._locale);
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
         * The URL a link cell points at, or '' for a row without one.
         *
         * Only a URL that cannot run script comes back. Rows are data, often data somebody typed,
         * and a `javascript:` value bound to an href runs on the click. Browsers drop tabs and
         * newlines anywhere in a URL, and control characters and spaces before it, before they
         * read the scheme, so the scheme is read here the same way; a check on the raw string
         * lets `java\tscript:` through.
         */
        cellHref(row, col) {
            if (col.cellType !== 'link' || ! col.hrefKey) {
                return '';
            }

            const raw = row[col.hrefKey];
            const href = raw === null || raw === undefined ? '' : String(raw).trim();
            let start = 0;

            while (start < href.length && href.charCodeAt(start) <= 0x20) {
                start++;
            }

            const scheme = href.slice(start).replace(/[\t\n\r]/g, '').match(/^([a-z][a-z0-9+.-]*):/i);

            if (scheme && ! ['http', 'https', 'mailto', 'tel'].includes(scheme[1].toLowerCase())) {
                return '';
            }

            return href;
        },
        /**
         * Whether a cell draws as plain text: a text column, a link column whose row has no URL,
         * and a column whose type this table does not know. That last case used to match no
         * branch of the template at all, so the cell rendered EMPTY — which is exactly how a
         * `cellType: 'link'` column looked before the link cell existed, and how a typo still
         * would.
         */
        isPlainCell(row, col) {
            if (col.cellType === 'link') {
                return this.cellHref(row, col) === '';
            }

            return ! ['badge', 'badges', 'number', 'code'].includes(col.cellType);
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
                // The field keeps its JSON for a plain form post, and the model gets the list
                // itself. `wire:model` compiles to Alpine's x-model, which reads `detail` from a
                // CustomEvent and the field's value from any other event. With a plain event the
                // bound property received the JSON string, a property declared `array` refused
                // it, and Livewire answered 419 to every request after the first selection.
                this.$refs.selModel.value = JSON.stringify(this.selected);
                this.$refs.selModel.dispatchEvent(new CustomEvent('input', { detail: [...this.selected], bubbles: true }));
            }
        },
    };
}
