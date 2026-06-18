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
 *     (it only calls prepend()). No observers / timers / rAF loops.
 *
 * @param {Object} config
 * @param {Array}  config.items - notifications [{id,type,title,body?,timeLabel?,read?,group?,href?,actionLabel?}]
 * @param {string} config.groupBy - 'none' | 'time' | 'type'
 * @param {string} config.realtimeEvent - optional window event name to listen for new items
 */
import { position } from '../utils/floating.js';

export default function wirekitNotificationCenter(config = {}) {
    return {
        items: Array.isArray(config.items) ? config.items.map((i) => ({ ...i })) : [],
        groupBy: config.groupBy || 'none',
        activeFilter: 'all',
        open: !!config.open, // start open (inline embeds, docs demos)
        _rt: null,
        _onScroll: null,
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
                this.$nextTick(async () => {
                    await this._anchor();
                    this.$refs.panel?.focus();
                });
            }
        },
        close(restoreFocus = false) {
            this.open = false;
            if (restoreFocus) this.$refs.bell?.focus();
        },

        // Anchor the teleported (fixed) panel to the bell. Prefers opening toward
        // the inline-end (bottom-start = left-aligned, so the panel extends to the
        // RIGHT into available space); crossAxisShift pulls it back on-screen when
        // the right edge would overflow (e.g. a top-right navbar bell). Teleporting
        // to <body> escapes any clipping/stacking ancestor so the flyout is never
        // hidden behind sibling content (mirrors <x-wirekit::context-menu>).
        async _anchor() {
            if (this.$refs.bell && this.$refs.panel) {
                await position(this.$refs.bell, this.$refs.panel, {
                    placement: 'bottom-start',
                    offset: 8,
                    crossAxisShift: true,
                });
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
        filterMove(dir) {
            const order = ['all', ...this.types];
            const i = Math.max(0, order.indexOf(this.activeFilter));
            const next = order[(i + dir + order.length) % order.length];
            this.setFilter(next);
            this.$nextTick(() => {
                const sel = (typeof CSS !== 'undefined' && CSS.escape) ? CSS.escape(next) : next;
                const radio = this.$root && this.$root.querySelector(`[data-filter="${sel}"]`);
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
