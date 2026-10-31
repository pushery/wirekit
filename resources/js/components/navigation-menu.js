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
 * The keyboard model is spelled out here because the teleport removed the one
 * the browser would have given us for free. Each panel moves to the overlay
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
import { focusClicked } from '../utils/focus-clicked.js';
import { coordinateOverlay } from '../utils/overlay-coordination.js';
import { closeOnOutsideRelease } from '../utils/outside-release.js';
import { belongsThroughTeleports } from '../utils/teleport.js';
import { focusIsWithin, position } from '../utils/floating.js';
import { isComposing } from '../utils/ime.js';
import { isRendered } from '../utils/rendered.js';
import { anchorMoved, anchorSnapshot } from '../utils/scroll-anchor.js';
import { watchCurrent } from '../utils/watch-current.js';

/**
 * What counts as focusable inside a flyout panel. Same selector app-shell's
 * drawer uses — same question, so the same answer rather than a second list
 * that drifts from it.
 */
const PANEL_FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

export default function wirekitNavigationMenu() {
    return {
        activeItem: null,
        // The menu's name as a landmark, bound to its `aria-label` where the menu has no name of the
        // caller's. See init().
        landmarkName: null,
        _hideTimer: null,
        // Whether the pointer has MOVED over the open panel since it opened (see leavePanel()).
        _pointerMovedInPanel: false,
        _navCleanup: null,
        _onScroll: null,
        _onResize: null,
        // Removes the outside-release listeners (utils/outside-release.js).
        _stopOutsideRelease: null,

        // Cross-close channel — see utils/overlay-coordination.js. Two navigation
        // menus on one page could each hold a panel open, and they overlap.
        _coordination: null,
        // Where the open item's trigger stood when its panel opened — see utils/scroll-anchor.js.
        _anchorAt: null,
        // Disconnects the observer that puts the placement back after a framework update erases
        // it. See the note beside `repairErasure` in open().
        _stopRepair: null,

        init() {
            // A menu named by default inside a navigation landmark of the same name, a navbar the
            // usual one, takes the name it was given for that place. Two nested landmarks of one
            // name read the same twice in a screen reader's list of them, and axe reports them.
            const nestedName = this.$el?.getAttribute?.('data-wk-nested-name') ?? null;

            if (nestedName) {
                const ownName = this.$el.getAttribute('aria-label');
                const around = this.$el.parentElement?.closest?.('nav, [role="navigation"]') ?? null;

                this.landmarkName = around && around.getAttribute('aria-label') === ownName ? nestedName : ownName;
            }

            // Release the placement observer whenever a panel closes, whichever way it closed.
            // FOUR separate places clear `activeItem` — the SPA cleanup, the delayed close, the
            // immediate close and a switch to another item — so hooking one of them would leave
            // an observer alive on the other three.
            watchCurrent(this, 'activeItem', (name) => {
                if (name) {
                    return;
                }

                this._stopRepair?.();
                this._stopRepair = null;
            });

            this._navCleanup = () => { this.activeItem = null; };
            // Not `once`: a component inside `@persist` is carried to the next page without a new
            // `init()`, and still has to close on every page change after the first.
            document.addEventListener('livewire:navigating', this._navCleanup);

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
                // The panel is teleported to the overlay root at the end of <body>; resolve
                // it via the teleport-safe ref.
                const panel = this.$refs[`panel-${this.activeItem}`];
                if (panel && event.target instanceof Node && panel.contains(event.target)) {
                    return;
                }
                // Only a scroll that moved the open item's trigger has stranded anything.
                if (!anchorMoved(this._anchorAt, this._getTrigger(this.activeItem))) return;
                this.closeAll();
            };
            window.addEventListener('scroll', this._onScroll, { passive: true, capture: true });

            // A viewport resize invalidates the one-shot fixed position.
            this._onResize = () => { if (this.activeItem) this.closeAll(); };
            window.addEventListener('resize', this._onResize, { passive: true });

            // Outside tap/click closes the flyout. Essential on touch devices,
            // which never fire the `mouseleave` that closes it on desktop —
            // without this a mobile user who opens a panel can't dismiss it by
            // tapping away. It closes on the release of a press that began and
            // ended outside, never on the press (utils/outside-release.js).
            this._stopOutsideRelease = closeOnOutsideRelease(document, {
                // The flyout panel is teleported to the overlay root, so a tap inside it
                // is NOT inside the nav root — without the panel here it would close
                // before an in-panel link/button click registered.
                // The panel through the teleports, so a press in a panel opened from inside it counts too.
                contains: (node) => this.$root.contains(node)
                    || Boolean(this.$refs[`panel-${this.activeItem}`] && belongsThroughTeleports(node, this.$refs[`panel-${this.activeItem}`])),
                isOpen: () => Boolean(this.activeItem),
                close: () => this.closeAll(),
            });

            // Another navigation menu opening a panel closes this one's.
            this._coordination = coordinateOverlay({
                channel: 'wirekit:navigation-menu-open',
                root: this.$root,
                onOther: () => this.closeAll(),
            });
        },

        destroy() {
            // Teardown does not necessarily pass through a close, so the watcher above cannot be
            // relied on here.
            this._stopRepair?.();
            this._stopRepair = null;

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
            this._stopOutsideRelease?.();
            this._stopOutsideRelease = null;

            // The close delay outlives the component otherwise. Every listener
            // above is released here; this timer was the one resource that kept
            // running, and its callback writes state on a component the teardown
            // has already finished with.
            clearTimeout(this._hideTimer);
            this._hideTimer = null;
        },

        /**
         * Open a panel on hover/click. `clicked` is the trigger a click came from, which takes the
         * focus where the engine did not, so the arrows go into the panel from it
         * (utils/focus-clicked.js). A hover passes none: it moves no focus.
         */
        async open(name, clicked = null) {
            focusClicked(clicked);
            clearTimeout(this._hideTimer);
            this._pointerMovedInPanel = false;
            this.activeItem = name;
            this._anchorAt = anchorSnapshot(this._getTrigger(name));
            this._coordination?.announce();

            await this.$nextTick();

            // Trigger stays in the bar (not teleported). Query from $root (the
            // nav element), NOT $el — open() runs off the trigger's x-on:click
            // (and the wrapper's mouseenter), where Alpine binds $el to the
            // event element rather than the nav root, so $el.querySelector
            // would miss the trigger and positioning would never run. The panel is
            // teleported to the overlay root → resolve it via the teleport-safe ref.
            const trigger = this.$root.querySelector(`[data-wk-nav-trigger="${name}"]`);
            const panel = this.$refs[`panel-${name}`];

            if (trigger && panel) {
                // Switching straight from one item to another never passes through a closed
                // state, so the watcher above does not fire — drop the previous panel's observer
                // here as well.
                this._stopRepair?.();
                this._stopRepair = null;

                const placement = await position(trigger, panel, {
                    placement: 'bottom-start',
                    offset: 4,

                    // Everything this call writes is inline style, and a framework update patches
                    // the panel against its own template, whose `style` attribute carries none of
                    // it. The placement is gone while the flyout is still open — and the panel is
                    // teleported out of the bar, so with no `top` it sits at the END of the
                    // document instead of under its trigger. The box does not change.
                    //
                    // The unchanged box is why this is `repairErasure` and not
                    // `autoReposition`: no resize means `autoUpdate` sees nothing, since it
                    // observes boxes rather than the style attribute.
                    repairErasure: true,
                    // The default shift runs along the trigger, which for a bottom placement is
                    // the horizontal axis: it keeps a wide mega-menu panel inside the viewport on
                    // a narrow screen. There is no shift across it. Below the trigger that axis is
                    // the vertical one, and a shift there would lift a tall panel over the trigger,
                    // which keeps the focus while the panel is open (WCAG 2.4.11).
                    //
                    // Caps the panel to the room on its side of the trigger, and its scroller below
                    // takes the rest. Without it a panel taller than a short window (a mega menu at
                    // 400% zoom) stands past the bottom edge: it is fixed, and a scroll of the page
                    // closes it, so the entries down there could not be reached at all.
                    fitViewport: true,
                });

                if (placement && typeof placement.stop === 'function') {
                    // `activeItem` is compared rather than merely checked for truthiness: a
                    // reader who crossed to another item while this placement was in flight has
                    // a DIFFERENT panel open, and storing this handle would leak one observer
                    // and disconnect the wrong one on the next close.
                    if (this.activeItem === name) {
                        this._stopRepair = placement.stop;
                    } else {
                        placement.stop();
                    }
                }
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
         * The pointer moved over the open panel. Bound to the panel's `pointermove`.
         */
        notePanelPointerMove() {
            this._pointerMovedInPanel = true;
        },

        /**
         * The pointer left the panel: close after the delay, but only when it moved in it first.
         *
         * A panel opened from the keyboard renders one frame unplaced, at the end of the overlay
         * root, before its position is set. Blink re-reads the hover after that layout: a pointer
         * resting where the frame landed "enters" the panel and "leaves" it when the panel moves
         * under its trigger, without moving at all. Those boundary events come with no
         * `pointermove`, and a panel that closed on them shut itself on a reader who opened it
         * with Enter and never touched the mouse.
         */
        leavePanel() {
            if (! this._pointerMovedInPanel) return;

            this._pointerMovedInPanel = false;
            this.scheduleClose();
        },

        /**
         * Close all panels immediately.
         */
        closeAll() {
            clearTimeout(this._hideTimer);
            this._pointerMovedInPanel = false;
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

            if (!panel) return [];

            const focusables = [...panel.querySelectorAll(PANEL_FOCUSABLE)];

            // An entry hidden inside a drawn panel cannot take the focus. A panel still on its way
            // open has no boxes yet, and its entries are taken as they stand.
            return isRendered(panel) ? focusables.filter(isRendered) : focusables;
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

            // Not when the reader already moved into the panel while it was being positioned.
            if (focusIsWithin(this.$refs[`panel-${name}`])) return;

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
         * Escape anywhere while a flyout is open.
         *
         * Heard on the WINDOW: a flyout a pointer opened leaves the focus where it was, and a key
         * pressed there, on `<body>` or in the page's content, reaches neither the bar's handler
         * nor the teleported panel's. WCAG 1.4.13 asks for content shown on hover to be
         * dismissible without moving the pointer or the focus, and Escape is the mechanism it
         * names.
         *
         * Heard in the CAPTURE phase, and a press that closes the flyout is marked as handled, so
         * a modal or a drawer around the reader leaves it alone. With the focus in the bar or in
         * the open panel this does nothing: their own handlers run next, close the flyout and hand
         * the focus back to its trigger.
         *
         * @param {KeyboardEvent} event
         */
        escapeAnywhere(event) {
            // An Escape that abandons an input method's conversion belongs to the input method.
            if (isComposing(event)) return;

            if (this.activeItem === null) return;

            const focused = document.activeElement;
            const panel = this.$refs[`panel-${this.activeItem}`];

            if (focused && (this.$root.contains(focused) || (panel && belongsThroughTeleports(focused, panel)))) return;

            event.preventDefault();
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

            // The panel, or a panel opened from inside it.
            const panel = this.$refs[`panel-${name}`];
            if (panel && belongsThroughTeleports(next, panel)) return;

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
