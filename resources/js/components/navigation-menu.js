/**
 * WireKit Navigation Menu Alpine Component.
 *
 * Top-level navigation with rich flyout panels (mega menu pattern).
 * Hover/click to open panels. Follows the disclosure-navigation pattern: a bar
 * of navigation links is not a composite widget, so every top-level item stays
 * in the tab sequence and there is no roving tabindex, no `role="menu"` and no
 * `aria-haspopup` — a trigger announces its panel with `aria-expanded` plus
 * `aria-controls`, and nothing else.
 *
 * THE KEYBOARD MODEL IS SPELLED OUT HERE BECAUSE THE TELEPORT REMOVED THE ONE
 * THE BROWSER WOULD HAVE GIVEN US FOR FREE. Each panel moves to the overlay
 * root at the end of <body> so its `position: fixed` box escapes every ancestor
 * containing block — and sequential focus order follows the DOM, not the
 * screen. A reader who opened a panel and pressed Tab therefore landed on the
 * NEXT top-level item while the open panel sat in front of them, and its links
 * were reachable only after tabbing through the rest of the page. Escape left
 * focus on <body>. So the three edges the teleport broke are handled
 * explicitly: Tab off the trigger steps into the panel, Tab off the panel's
 * last link steps back out to the item after the trigger, and Escape returns
 * focus to the trigger (WCAG 2.4.3).
 *
 * Focus always moves BEFORE the panel hides. `x-show` writes `display: none`,
 * and hiding the subtree that holds focus makes the browser drop it on <body> —
 * after our own focus() call, which would then have accomplished nothing. Same
 * ordering, same reason, as dropdown.js.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/disclosure/
 */
import { coordinateOverlay } from '../utils/overlay-coordination.js';
import { position } from '../utils/floating.js';

/**
 * What counts as focusable inside a flyout panel. Same selector app-shell's
 * drawer uses — same question, so the same answer rather than a second list
 * that drifts from it.
 */
const PANEL_FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

export default function wirekitNavigationMenu() {
    return {
        activeItem: null,
        _hideTimer: null,
        _navCleanup: null,
        _onScroll: null,
        _onResize: null,
        _onPointerDown: null,

        // Cross-close channel — see utils/overlay-coordination.js. Two navigation
        // menus on one page could each hold a panel open, and they overlap.
        _coordination: null,

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

            // Another navigation menu opening a panel closes this one's.
            this._coordination = coordinateOverlay({
                channel: 'wirekit:navigation-menu-open',
                onOther: () => this.closeAll(),
            });
        },

        destroy() {
            this._coordination?.stop();
            this._coordination = null;

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

            // The close delay outlives the component otherwise. Every listener
            // above is released here; this timer was the one resource that kept
            // running, and its callback writes state on a component the teardown
            // has already finished with.
            clearTimeout(this._hideTimer);
            this._hideTimer = null;
        },

        /**
         * Open a panel on hover/click.
         */
        async open(name) {
            clearTimeout(this._hideTimer);
            this.activeItem = name;
            this._coordination?.announce();

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

        /**
         * Top-level items in the bar, in DOM order — flyout triggers AND plain
         * link items, because both are things a reader arrows across.
         *
         * `$root`, not `$el`: a keydown reaches this from the trigger button or
         * from a link, and Alpine binds `$el` to whichever element the listener
         * sits on. Same reason open() resolves its trigger from `$root`.
         *
         * The panels are teleported out of `$root`, so a link inside an open
         * panel is never matched here — that is what keeps the two levels apart
         * without a marker attribute on every link.
         */
        _getTopLevelItems() {
            return [...this.$root.querySelectorAll('[data-wk-nav-trigger], a[href]')];
        },

        /**
         * Focusable elements inside one panel, in DOM order.
         */
        _getPanelFocusables(name) {
            // Panel is teleported to the overlay root; resolve via the
            // teleport-safe ref rather than a descendant query on the nav.
            const panel = this.$refs[`panel-${name}`];

            return panel ? [...panel.querySelectorAll(PANEL_FOCUSABLE)] : [];
        },

        /**
         * The trigger button that owns a panel. It stays in the bar.
         */
        _getTrigger(name) {
            return this.$root.querySelector(`[data-wk-nav-trigger="${name}"]`);
        },

        /**
         * Move focus along the bar, wrapping at both ends.
         *
         * Modulo twice: JS keeps the sign of the dividend, so -1 % 3 is -1
         * rather than 2 and the backwards wrap would land nowhere.
         * `preventScroll` keeps a long page from jumping to an item that is
         * already in view.
         */
        _focusTopLevel(index) {
            const items = this._getTopLevelItems();
            if (!items.length) return;

            items[((index % items.length) + items.length) % items.length]
                ?.focus({ preventScroll: true });
        },

        /**
         * Open a panel and step into it — what ArrowDown on a trigger means.
         */
        async openAndFocus(name) {
            await this.open(name);
            this._getPanelFocusables(name)[0]?.focus({ preventScroll: true });
        },

        /**
         * Close the open panel and put focus back on the trigger that opened it.
         */
        closeAndFocusTrigger(name) {
            // Focus first, hide second — see the ordering note in the module
            // docstring.
            this._getTrigger(name)?.focus({ preventScroll: true });
            this.closeAll();
        },

        /**
         * Keyboard model for the bar. Bound on the `<nav>` root so it serves
         * plain link items too: a keydown on a top-level link bubbles here,
         * while one on a trigger button arrives with a resolvable panel name.
         */
        handleBarKeydown(event) {
            const trigger = event.target?.closest?.('[data-wk-nav-trigger]');
            const name = trigger?.dataset?.wkNavTrigger ?? null;
            const isOpen = name !== null && this.activeItem === name;

            switch (event.key) {
                // Toggle. Handled on keydown rather than on the click binding so
                // the pointer path keeps its hover-then-click behavior untouched;
                // preventDefault also suppresses the button's synthesized click,
                // which would otherwise re-open what this just closed.
                case 'Enter':
                case ' ':
                case 'Spacebar':
                    if (name === null) break;
                    event.preventDefault();
                    if (isOpen) {
                        this.closeAll();
                    } else {
                        this.open(name);
                    }
                    break;

                case 'ArrowDown':
                    if (name === null) break;
                    event.preventDefault();
                    this.openAndFocus(name);
                    break;

                case 'Tab': {
                    // Step INTO the open panel. Without this the reader tabs to
                    // the next top-level item instead, because the panel is
                    // teleported past the end of the nav.
                    if (!isOpen || event.shiftKey) break;
                    const first = this._getPanelFocusables(name)[0];
                    if (!first) break;
                    event.preventDefault();
                    first.focus({ preventScroll: true });
                    break;
                }

                case 'Escape':
                    // Also fires with focus on a plain link while a hover-opened
                    // panel is up, which is why it does not require a trigger.
                    if (!this.activeItem) break;
                    event.preventDefault();
                    this.closeAll();
                    break;

                case 'ArrowRight':
                case 'ArrowLeft':
                case 'Home':
                case 'End': {
                    const items = this._getTopLevelItems();
                    const current = items.indexOf(trigger ?? event.target);
                    if (current === -1) break;
                    event.preventDefault();
                    // A panel left open would cover the item focus is moving to.
                    this.closeAll();
                    this._focusTopLevel({
                        ArrowRight: current + 1,
                        ArrowLeft: current - 1,
                        Home: 0,
                        End: items.length - 1,
                    }[event.key]);
                    break;
                }
            }
        },

        /**
         * Keyboard model inside a flyout panel. Bound on the panel itself: it is
         * teleported to the overlay root, so its events bubble to <body> and
         * never reach the nav root's listener.
         */
        handlePanelKeydown(event, name) {
            if (event.key === 'Escape') {
                event.preventDefault();
                this.closeAndFocusTrigger(name);

                return;
            }

            if (event.key !== 'Tab') return;

            const focusables = this._getPanelFocusables(name);
            if (!focusables.length) return;

            // Both edges are spelled out because the browser's own sequential
            // order would send the reader off the end of <body> forwards, and to
            // whatever precedes the overlay root backwards — neither is anywhere
            // near the nav the panel visually belongs to.
            if (event.shiftKey && document.activeElement === focusables[0]) {
                event.preventDefault();
                this.closeAndFocusTrigger(name);

                return;
            }

            if (!event.shiftKey && document.activeElement === focusables[focusables.length - 1]) {
                event.preventDefault();

                const items = this._getTopLevelItems();
                const index = items.indexOf(this._getTrigger(name));
                // The item after the trigger — or the trigger itself when it is
                // the last one in the bar, from where the next Tab leaves the nav
                // in document order, which is correct again once the panel closed.
                const next = index >= 0 && index + 1 < items.length
                    ? items[index + 1]
                    : items[index];

                next?.focus({ preventScroll: true });
                this.closeAll();
            }
        },

        /**
         * Close when focus leaves both the panel and the bar — a reader who
         * tabbed or clicked away is done with the panel, and one hanging over
         * unrelated content is the same stale-overlay problem the scroll guard
         * exists for.
         */
        handlePanelFocusOut(event, name) {
            const next = event.relatedTarget;
            // `relatedTarget` is null when focus left the document entirely (a
            // click in browser chrome, a window blur). Closing then would dismiss
            // a panel the reader is still working in, so only a focus that landed
            // on another element counts.
            if (!(next instanceof Node)) return;
            if (this.$root.contains(next)) return;

            const panel = this.$refs[`panel-${name}`];
            if (panel && panel.contains(next)) return;

            this.closeAll();
        },

        /**
         * The same rule seen from the other side of the teleport: focus leaving
         * the BAR for something that is neither the bar nor the open panel
         * closes the panel too. Without it a reader who opened a flyout and then
         * shift-tabbed back off its trigger left it hanging over the page with
         * nothing focused inside it — the panel never saw a focusout, because
         * focus had never been in it.
         */
        handleBarFocusOut(event) {
            if (!this.activeItem) return;

            this.handlePanelFocusOut(event, this.activeItem);
        },
    };
}
