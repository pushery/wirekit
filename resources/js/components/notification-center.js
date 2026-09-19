/**
 * WireKit Notification Center Alpine component.
 *
 * A bell trigger with an unread badge that opens a panel of grouped,
 * actionable notifications. Manages read/unread state, type filtering, grouping
 * (by time bucket or type), and optimistic realtime insertion. Read-state
 * changes are emitted via bubbling events (`notification-read` /
 * `notification-read-all`) and the unread count is mirrored to a hidden input
 * for a wire:model bridge.
 *
 * Lifecycle resources held on `this`:
 *   - _rt (window event listener) — OPTIONAL, created only when a
 *     `realtimeEvent` name is configured; removed in destroy(). Its callback
 *     does NOT dereference `this._rt`, so no post-destroy null-guard is needed
 *     (it only calls prepend()).
 *   - _stopRepair (MutationObserver) — puts the panel's placement back when a
 *     framework update erases it; released on every re-anchor, in close() and in
 *     destroy(). See the note beside `repairErasure` in _anchor().
 *
 * ⚠️ The first entry used to end "No observers / timers / rAF loops." It was true
 * when written, and a sentence of that shape is the first thing to become false —
 * the observer above arrived later.
 *
 * @param {Object} config
 * @param {Array}  config.items - notifications [{id,type,title,body?,timeLabel?,read?,group?,href?,actionLabel?}]
 * @param {string} config.groupBy - 'none' | 'time' | 'type'
 * @param {string} config.realtimeEvent - optional window event name to listen for new items
 */
import { focusIsWithin, position } from '../utils/floating.js';
import { anchorMoved, anchorSnapshot } from '../utils/scroll-anchor.js';

/**
 * What counts as a tab stop, for the two edges of the teleported panel.
 *
 * The same shape filter-builder, navigation-menu, hover-card and menubar each
 * declare — one selector per component rather than a shared util, because each
 * one pairs it with its own notion of "and actually usable right now" below.
 */
const PANEL_FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * Is this element a tab stop the reader can actually reach right now?
 *
 * TWO ways it can be present and not reachable, and this panel has both:
 *
 *   - `x-show` writes `display: none`, which leaves the element in the DOM and
 *     in every `querySelectorAll` result. "Mark all read" is exactly that — it
 *     is hidden while nothing is unread — and `focus()` on it does nothing, so
 *     the browser drops focus on `<body>`: the same nowhere the bug produced.
 *   - The type filter is a ROVING-TABINDEX radiogroup, so every radio except the
 *     checked one carries `tabindex="-1"` while still matching
 *     `button:not([disabled])` — the selector clauses are an OR, and a button
 *     satisfies the button clause whatever its tabindex says.
 *
 * ⚠️ The second one is the half a copy of another component's helper does not
 * have, and dropping it is invisible on the first filter: `focusables[0]` becomes
 * a radio the reader can never stand on, the Shift+Tab edge stops matching, and
 * focus leaves the document exactly as it did before — on every filter but "All".
 */
function isTabStop(el) {
    if (typeof el.tabIndex === 'number' && el.tabIndex < 0) return false;

    return typeof el.getClientRects !== 'function' || el.getClientRects().length > 0;
}

export default function wirekitNotificationCenter(config = {}) {
    return {
        // The summary's middle phrase is translated server-side and travels in,
        // because the expression that used to build the line lived in the
        // template and interpolated it there.
        _latestLabel: config.latestLabel || 'unread. Latest:',

        /**
         * The summary line, and the key a group renders under.
         *
         * Both used optional chaining or nullish coalescing in the template —
         * neither exists in the grammar Alpine's CSP build parses, so this
         * component went silent under a strict Content-Security-Policy. The
         * fallbacks are the point of both expressions, so they move here whole
         * rather than being spelled out again with && chains at the binding.
         */
        get summaryLine() {
            if (this.unreadCount <= 0) {
                return '';
            }

            const latest = this.items.length > 0 && this.items[0].title ? this.items[0].title : '';

            return this.unreadCount + ' ' + this._latestLabel + ' ' + latest;
        },

        groupKey(group) {
            return group && group.label ? group.label : 'all';
        },

        items: Array.isArray(config.items) ? config.items.map((i) => ({ ...i })) : [],
        groupBy: config.groupBy || 'none',
        activeFilter: 'all',
        open: !!config.open, // start open (inline embeds, docs demos)
        _rt: null,
        _onScroll: null,
        // Disconnects the observer that puts the placement back after a framework update erases
        // it. See the note beside `repairErasure` in _anchor().
        _stopRepair: null,
        // Where the bell stood when the panel opened — see utils/scroll-anchor.js.
        _anchorAt: null,
        _onResize: null,

        init() {
            // Optional realtime bridge: dispatch `new CustomEvent(name, {detail})`
            // on window (e.g. from Laravel Echo) and the panel prepends it.
            if (config.realtimeEvent) {
                this._rt = (e) => this.prepend(e.detail);
                window.addEventListener(config.realtimeEvent, this._rt);
            }
            // Close on page scroll / viewport resize. The teleported panel is
            // position:fixed, anchored ONCE on open — when the page scrolls under
            // it, the panel strands visually detached from the bell (it stays in
            // place when the page scrolls away under it). Scrolling INSIDE the
            // panel (a long notification list) must keep working — only scrolls
            // originating outside the panel dismiss it. Capture catches every
            // scroller (document + nested containers); passive per perf-hygiene.
            // Same pattern as the navigation-menu flyout.
            if (typeof window !== 'undefined') {
                this._onScroll = (e) => {
                    if (!this.open) return;
                    const panel = this.$refs.panel;
                    if (panel && e.target instanceof Node && panel.contains(e.target)) return;
                    // Only a scroll that moved the bell has stranded anything — utils/scroll-anchor.js.
                    if (!anchorMoved(this._anchorAt, this.$refs.bell)) return;
                    this.close();
                };
                window.addEventListener('scroll', this._onScroll, { passive: true, capture: true });
                // A resize invalidates the one-shot fixed anchor the same way.
                this._onResize = () => { if (this.open) this.close(); };
                window.addEventListener('resize', this._onResize, { passive: true });
            }
            // Demos / inline embeds can start open — anchor the teleported panel
            // once it's in the DOM.
            if (this.open) this.$nextTick(() => this._anchor());
        },
        destroy() {
            this._stopRepair?.();
            this._stopRepair = null;

            if (this._rt && config.realtimeEvent) {
                window.removeEventListener(config.realtimeEvent, this._rt);
                this._rt = null;
            }
            if (this._onScroll) {
                window.removeEventListener('scroll', this._onScroll, { capture: true });
                this._onScroll = null;
            }
            if (this._onResize) {
                window.removeEventListener('resize', this._onResize);
                this._onResize = null;
            }
        },

        // ── Derived state ────────────────────────────────────────────────
        get unreadCount() {
            return this.items.filter((i) => !i.read).length;
        },
        // Distinct types present, for the filter tabs.
        get types() {
            return [...new Set(this.items.map((i) => i.type).filter(Boolean))];
        },
        get filteredItems() {
            if (this.activeFilter === 'all') return this.items;
            return this.items.filter((i) => i.type === this.activeFilter);
        },
        // [{label, items}] grouped per groupBy ('none' → one unlabeled group).
        get groups() {
            const list = this.filteredItems;
            if (this.groupBy === 'none') return [{ label: null, items: list }];
            const key = this.groupBy === 'type' ? 'type' : 'group';
            const map = new Map();
            list.forEach((i) => {
                const g = i[key] || 'Other';
                if (!map.has(g)) map.set(g, []);
                map.get(g).push(i);
            });
            return [...map.entries()].map(([label, items]) => ({ label, items }));
        },
        get isEmpty() {
            return this.filteredItems.length === 0;
        },

        // ── Panel control ────────────────────────────────────────────────
        toggle() {
            this.open = !this.open;
            if (this.open) {
                this._anchorAt = anchorSnapshot(this.$refs.bell);
                this.$nextTick(async () => {
                    await this._anchor();
                    // Not when the reader already moved into the panel while it was being positioned.
                    if (! focusIsWithin(this.$refs.panel)) this.$refs.panel?.focus();
                });
            }
        },
        close(restoreFocus = false) {
            this.open = false;
            this._stopRepair?.();
            this._stopRepair = null;

            if (restoreFocus) this.$refs.bell?.focus();
        },

        /**
         * The panel's tab stops, in DOM order, re-read on every keypress.
         *
         * Never cached: "Mark all read" appears and disappears with the unread
         * count, the filter row appears once a second type exists, the empty
         * state replaces the rows, and the checked radio moves. Any of those
         * changes which element is the first or the last one.
         */
        _panelFocusables() {
            const panel = this.$refs.panel;

            return panel ? [...panel.querySelectorAll(PANEL_FOCUSABLE)].filter(isTabStop) : [];
        },

        /**
         * Move to the control that FOLLOWS the bell on the page.
         *
         * Where a forward Tab out of the flyout belongs: the panel is drawn
         * beside the bell, so leaving it should continue from the bell and not
         * from the end of the document, where the panel's markup happens to
         * live. Anything inside the bell is skipped (a descendant also "follows"
         * it by document position) and so is the overlay root, which holds this
         * panel and every other teleported one.
         */
        _focusAfterBell() {
            const bell = this.$refs.bell;

            if (! bell) return;

            const overlayRoot = document.getElementById('wk-overlay-root');

            const next = [...document.querySelectorAll(PANEL_FOCUSABLE)].find((el) => {
                if (bell.contains(el)) return false;
                if (overlayRoot?.contains(el)) return false;
                if (! isTabStop(el)) return false;

                return Boolean(bell.compareDocumentPosition(el) & Node.DOCUMENT_POSITION_FOLLOWING);
            });

            next?.focus({ preventScroll: true });
        },

        /**
         * Tab pressed inside the flyout — handle both of its edges.
         *
         * ⚠️ NEITHER EDGE IS WHAT THE BROWSER WOULD DO, because the panel is
         * teleported to the end of `<body>` while it is drawn beside the bell,
         * and sequential focus order follows the DOM rather than the screen.
         * Opening moves focus into the panel, so before this existed a Tab off
         * the last notification left the DOCUMENT for the browser chrome, and a
         * Shift+Tab landed on whatever precedes the overlay root — both with
         * `role="dialog"` still open and painted over the page. The reader lost
         * the panel and their place in one keystroke, and the keyboard table in
         * the component's docs promised the opposite.
         *
         * Leaving closes it, which is what this flyout's other three dismissals
         * already mean: Escape, a click outside and a page scroll all return the
         * reader to the page. A non-modal dialog is left, not escaped from — so
         * a focus TRAP is deliberately not used here, the same call filter-builder
         * records for the identical shape. There is no `aria-modal` and no scroll
         * lock, and a trap would also have to be taught about the roving-tabindex
         * radiogroup and the `tabindex="0"` scroll region the panel already owns.
         *
         * ⚠️ The BACKWARD edge also fires on the panel container itself. Opening
         * focuses `$refs.panel`, which is `tabindex="-1"` and therefore not a tab
         * stop and never equal to `focusables[0]` — so the very first Shift+Tab
         * after opening is the one edge a `focusables`-only check does not catch.
         */
        tabWithinPanel(event) {
            const panel = this.$refs.panel;

            if (! panel) return;

            const focusables = this._panelFocusables();

            if (event.shiftKey) {
                if (document.activeElement !== panel && document.activeElement !== focusables[0]) return;

                event.preventDefault();
                // Identical to Escape: close and hand focus back to the bell.
                this.close(true);

                return;
            }

            // With nothing focusable inside, the container is both the first and
            // the last stop the reader can be standing on.
            const last = focusables.length ? focusables[focusables.length - 1] : panel;

            if (document.activeElement !== last) return;

            event.preventDefault();
            // Focus moves BEFORE the panel hides. `x-show` writes `display: none`,
            // and hiding the subtree that holds focus makes the browser drop it on
            // `<body>` — after our own focus() call, which would then have
            // accomplished nothing.
            this._focusAfterBell();
            this.close();
        },

        // Anchor the teleported (fixed) panel to the bell. Prefers opening toward
        // the inline-end (bottom-start = left-aligned, so the panel extends to the
        // RIGHT into available space); crossAxisShift pulls it back on-screen when
        // the right edge would overflow (e.g. a top-right navbar bell). Teleporting
        // to <body> escapes any clipping/stacking ancestor so the flyout is never
        // hidden behind sibling content (mirrors <x-wirekit::context-menu>).
        async _anchor() {
            if (this.$refs.bell && this.$refs.panel) {
                this._stopRepair?.();
                this._stopRepair = null;

                const placement = await position(this.$refs.bell, this.$refs.panel, {
                    placement: 'bottom-start',
                    offset: 8,
                    crossAxisShift: true,

                    // Everything this call writes is inline style, and a framework update patches
                    // the panel against its own template, whose `style` attribute carries none of
                    // it. Measured on /overlay-placement-seam across one refresh: `top` 398.5px →
                    // empty, same node, box unchanged at 352x172.
                    //
                    // ⚠️ The unchanged box is why this is `repairErasure` and not
                    // `autoReposition`: no resize means `autoUpdate` sees nothing, because it
                    // observes boxes rather than the style attribute.
                    //
                    // ⚠️ This measurement only became POSSIBLE once an unnamed widget stopped
                    // getting a fresh root id on every render. Before that it did not lose its
                    // placement — it lost the whole component, and the panel that came back was a
                    // closed replacement whose bell never opened again. Nothing was left to
                    // re-place, so the question could not be asked.
                    repairErasure: true,
                });

                if (placement && typeof placement.stop === 'function') {
                    if (this.open) {
                        this._stopRepair = placement.stop;
                    } else {
                        placement.stop();
                    }
                }
            }
        },

        // ── Mutations ────────────────────────────────────────────────────
        markRead(id) {
            this.items = this.items.map((i) => (i.id === id ? { ...i, read: true } : i));
            this._emit('notification-read', { id });
        },
        // Row activation = mark read + announce WHICH notification was clicked
        // via a bubbling `notification-action` (id + href when present). The
        // developer listens and navigates / opens a panel / calls Livewire —
        // the component never navigates by itself. Rows WITH `href` also render
        // as real links (native navigation + middle-click keep working on top
        // of the event).
        activate(item) {
            this.markRead(item.id);
            this._emit('notification-action', { id: item.id, href: item.href ?? null });
        },
        markAllRead() {
            this.items = this.items.map((i) => ({ ...i, read: true }));
            this._emit('notification-read-all', {});
        },
        setFilter(type) {
            this.activeFilter = type;
        },
        // Radio-group keyboard model for the filter row: arrows move AND select
        // (selection follows focus, per the ARIA radio pattern), wrapping at the
        // ends. Focus lands on the newly active radio via its data-filter hook.
        //
        // Resolved through `$refs.panel`, NEVER `$root`. The radios live in the
        // panel, and the panel is teleported to `#wk-overlay-root` at the end of
        // <body> — so it is not a descendant of the element `$root` is bound to,
        // and a downward `querySelector` from there finds nothing. Selection had
        // already moved by then, which is what made the failure quiet rather than
        // loud: `activeFilter` changed, the roving `:tabindex` turned the radio
        // under the reader's focus into -1 and `:aria-checked` turned it false,
        // and the ring stayed on a radio that is no longer the selected one. Same
        // reason the scroll guard and the panel focus walk above read the ref.
        filterMove(dir) {
            const order = ['all', ...this.types];
            const i = Math.max(0, order.indexOf(this.activeFilter));
            const next = order[(i + dir + order.length) % order.length];
            this.setFilter(next);
            this.$nextTick(() => {
                const sel = (typeof CSS !== 'undefined' && CSS.escape) ? CSS.escape(next) : next;
                const panel = this.$refs.panel;
                const radio = panel && panel.querySelector(`[data-filter="${sel}"]`);
                if (radio) radio.focus();
            });
        },
        // Optimistic realtime insert — dedup by id, newest first, unread.
        prepend(item) {
            if (item && item.id !== undefined && !this.items.some((i) => i.id === item.id)) {
                this.items = [{ ...item, read: false }, ...this.items];
                this._emit('notification-new', { id: item.id });
            }
        },

        _emit(name, detail) {
            this.$dispatch(name, detail);
            if (this.$refs.model) {
                this.$refs.model.value = String(this.unreadCount);
                this.$refs.model.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },
    };
}
