/**
 * WireKit Navigation Menu Alpine Component.
 *
 * Top-level navigation with rich flyout panels (mega menu pattern).
 * Hover/click to open panels. Follows disclosure pattern for a11y.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/disclosure/
 */
import { position } from '../utils/floating.js';

export default function wirekitNavigationMenu() {
    return {
        activeItem: null,
        _hideTimer: null,
        _navCleanup: null,
        _onScroll: null,
        _onResize: null,
        _onPointerDown: null,

        init() {
            this._navCleanup = () => { this.activeItem = null; };
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });

            // Close an open flyout when the page scrolls. The panel is
            // `position: fixed` (positioned once on open via Floating UI), so a
            // page scroll leaves it floating at a stale viewport position while
            // its trigger scrolls away — it ends up hovering over unrelated
            // content. Closing on scroll is the disclosure-menu convention.
            // Capture + passive so it catches scroll on any ancestor scroll
            // container; scrolls that originate INSIDE the open panel are
            // ignored so a long mega-menu can scroll internally.
            this._onScroll = (event) => {
                if (!this.activeItem) return;
                // Panel is teleported to <body>; resolve via the teleport-safe ref.
                const panel = this.$refs[`panel-${this.activeItem}`];
                if (panel && event.target instanceof Node && panel.contains(event.target)) {
                    return;
                }
                this.closeAll();
            };
            window.addEventListener('scroll', this._onScroll, { passive: true, capture: true });

            // A viewport resize invalidates the one-shot fixed position.
            this._onResize = () => { if (this.activeItem) this.closeAll(); };
            window.addEventListener('resize', this._onResize, { passive: true });

            // Outside tap/click closes the flyout. Essential on touch devices,
            // which never fire the `mouseleave` that closes it on desktop —
            // without this a mobile user who opens a panel can't dismiss it by
            // tapping away.
            this._onPointerDown = (event) => {
                if (!this.activeItem) return;
                const target = event.target;
                if (!(target instanceof Node)) return;
                if (this.$root.contains(target)) return;
                // The flyout panel is teleported to <body>, so a tap inside it
                // is NOT inside the nav root — without this guard the panel would
                // close before an in-panel link/button click registered.
                const panel = this.$refs[`panel-${this.activeItem}`];
                if (panel && panel.contains(target)) return;
                this.closeAll();
            };
            document.addEventListener('pointerdown', this._onPointerDown, { capture: true });
        },

        destroy() {
            if (this._navCleanup) {
                document.removeEventListener('livewire:navigating', this._navCleanup);
            }
            if (this._onScroll) {
                window.removeEventListener('scroll', this._onScroll, { capture: true });
            }
            if (this._onResize) {
                window.removeEventListener('resize', this._onResize);
            }
            if (this._onPointerDown) {
                document.removeEventListener('pointerdown', this._onPointerDown, { capture: true });
            }
        },

        /**
         * Open a panel on hover/click.
         */
        async open(name) {
            clearTimeout(this._hideTimer);
            this.activeItem = name;

            await this.$nextTick();

            // Trigger stays in the bar (not teleported). Query from $root (the
            // nav element), NOT $el — open() runs off the trigger's x-on:click
            // (and the wrapper's mouseenter), where Alpine binds $el to the
            // event element rather than the nav root, so $el.querySelector
            // would miss the trigger and positioning would never run. Panel is
            // teleported to <body> → resolve via the teleport-safe ref.
            const trigger = this.$root.querySelector(`[data-wk-nav-trigger="${name}"]`);
            const panel = this.$refs[`panel-${name}`];

            if (trigger && panel) {
                await position(trigger, panel, {
                    placement: 'bottom-start',
                    offset: 4,
                    // Keep wide mega-menu panels inside the viewport on narrow
                    // screens — the default main-axis shift can't pull a panel
                    // back from the edge for a bottom placement's cross axis.
                    crossAxisShift: true,
                });
            }
        },

        /**
         * Delay close — allows moving between trigger and panel.
         * 300ms gives enough time to cross the offset gap between
         * trigger button and fixed-positioned panel without flickering.
         */
        scheduleClose() {
            this._hideTimer = setTimeout(() => {
                this.activeItem = null;
            }, 300);
        },

        /**
         * Cancel pending close (user moved into panel).
         */
        cancelClose() {
            clearTimeout(this._hideTimer);
        },

        /**
         * Close all panels immediately.
         */
        closeAll() {
            clearTimeout(this._hideTimer);
            this.activeItem = null;
        },
    };
}
