/**
 * WireKit Context Menu Alpine Component.
 *
 * Right-click (contextmenu) triggered floating menu, with touch parity via
 * long-press (touch-and-hold) on devices that have no right-click.
 * Uses Floating UI for positioning at cursor / touch-point coordinates.
 * Follows WAI-ARIA menu pattern with arrow key navigation.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/menu/
 */
import { coordinateOverlay } from '../utils/overlay-coordination.js';
import { position } from '../utils/floating.js';

// Long-press tuning. 500ms is the platform-conventional touch-hold threshold
// (matches iOS/Android long-press); a 10px movement budget distinguishes a
// deliberate hold from the start of a scroll/drag gesture.
const LONG_PRESS_MS = 500;
const LONG_PRESS_MOVE_TOLERANCE_PX = 10;

/*
 * Takes no configuration. It used to declare a `config` parameter documented as coming
 * from Blade — but the template calls `wirekitContextMenu()` with no arguments and the
 * body never read it, so the doc described a channel that did not exist.
 */
export default function wirekitContextMenu() {
    return {
        open: false,
        // NO `_focusIndex` HERE, AND ITS ABSENCE IS THE POINT. This component used to hold
        // the focused entry as a number and step it on every press, while `_getItems()` read
        // the list back from the DOM each time — so the number and the list could describe
        // different pages. `utils/roving-focus.js` opens with why that is not a style choice:
        // an index captured before a morph survives it and points into a list that no longer
        // exists, and the failure is quiet. It was the only one of this package's four menu
        // models that worked that way; dropdown, menubar and submenu all resolve the current
        // entry from `document.activeElement` per press, and `handleKeydown()` below now
        // does too.
        _navCleanup: null,
        // Cross-close channel — see utils/overlay-coordination.js.
        _coordination: null,
        // Stable identity for the "close every other instance" coordination.
        // We previously used `this` for the source check, but Alpine wraps
        // each component in a reactive Proxy and the identity of `this` is
        // not guaranteed to be stable across event listener invocations vs
        // dispatchEvent calls (the Proxy can wrap-and-unwrap depending on
        // call site). A plain Symbol() created once in init() is bulletproof
        // — it's a primitive value, never proxied, and `===` comparison is
        // identity-based, so each instance gets its own unforgeable token.
        // Long-press (touch) state.
        _pressTimer: null,
        _pressStartX: 0,
        _pressStartY: 0,

        init() {
            this._navCleanup = () => this._forceClose();
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });

            // Auto-close cooperation: when ANY context menu broadcasts that it's
            // about to open, every OTHER instance closes itself. This prevents the
            // "two menus open at once" bug when multiple <x-wirekit::context-menu>
            // siblings live in the same page (e.g. one per table row). The event
            // carries the opening instance's Symbol as `detail.source` so each
            // instance can skip closing itself.
            this._coordination = coordinateOverlay({
                channel: 'wirekit:context-menu-open',
                onOther: () => this._forceClose(),
            });

            // Close on page scroll — the panel is fixed at the pointer position, so
            // a scroll strands it detached from the row it belongs to (same class
            // as the notification-center flyout; also the OS-native context-menu
            // convention). In-panel scrolls (a long menu) keep working. Capture
            // catches every scroller; passive per perf-hygiene.
            this._onScroll = (e) => {
                if (!this.open) return;
                const panel = this.$refs.panel;
                if (panel && e.target instanceof Node && panel.contains(e.target)) return;
                this._forceClose();
            };
            window.addEventListener('scroll', this._onScroll, { passive: true, capture: true });
        },

        destroy() {
            if (this._navCleanup) {
                document.removeEventListener('livewire:navigating', this._navCleanup);
            }
            this._coordination?.stop();
            this._coordination = null;
            if (this._onScroll) {
                window.removeEventListener('scroll', this._onScroll, { capture: true });
                this._onScroll = null;
            }
            this._clearPressTimer();
            this._forceClose();
        },

        /**
         * Open context menu at cursor position (right-click).
         * @param {MouseEvent} event - The contextmenu event
         */
        async openAt(event) {
            event.preventDefault();
            await this._openAtCoords(event.clientX, event.clientY);
        },

        /**
         * Shared open routine — positions the panel at viewport coordinates.
         * Used by both the right-click (openAt) and touch long-press paths.
         * @param {number} clientX
         * @param {number} clientY
         */
        async _openAtCoords(clientX, clientY) {
            // Announced BEFORE flipping `open`, so a sibling is already closing
            // while this one renders. The helper carries the identity that keeps
            // this instance from closing itself on its own announcement.
            this._coordination?.announce();

            this.open = true;

            await this.$nextTick();

            const panel = this.$refs.panel;
            if (!panel) return;

            // Position panel at the cursor / touch point using a virtual reference.
            const virtualRef = {
                getBoundingClientRect() {
                    return {
                        width: 0,
                        height: 0,
                        x: clientX,
                        y: clientY,
                        top: clientY,
                        left: clientX,
                        right: clientX,
                        bottom: clientY,
                    };
                },
            };

            await position(virtualRef, panel, {
                placement: 'bottom-start',
                offset: 2,
            });

            // Focus the first item — AFTER positioning, so the panel is already at its
            // final coordinates and nothing has to be re-measured.
            //
            // This is what makes the menu operable at all, and it is not merely polish.
            // Every item carries `tabindex="-1"` (the roving-focus half of the WAI-ARIA
            // menu pattern), so nothing in the panel is reachable by tabbing; without a
            // deliberate focus call the panel opens with `document.activeElement` still on
            // the document, and from there arrow keys, Home, End and Escape all bubble
            // past the component. The panel is visible and completely inert.
            //
            // Unconditional, on the pointer path as well as the keyboard one: a right-click
            // menu that highlights its first entry is the platform convention, and it is
            // the only shape in which the next keypress has somewhere to start from.
            // `preventScroll` so a menu opened near the fold does not jump the page.
            const items = this._getItems();
            if (items.length) {
                items[0].focus({ preventScroll: true });
            }
        },

        /**
         * Touch long-press — opens the menu after a hold, giving touch devices
         * (which have no right-click) parity with the contextmenu trigger. A
         * scroll/drag gesture (movement beyond the tolerance) cancels the press.
         * @param {TouchEvent} event
         */
        onTouchStart(event) {
            // Only a single-finger press is a long-press candidate; multi-touch
            // (pinch/zoom) is never a context-menu intent.
            if (event.touches.length !== 1) {
                this._clearPressTimer();
                return;
            }

            const touch = event.touches[0];
            this._pressStartX = touch.clientX;
            this._pressStartY = touch.clientY;

            this._clearPressTimer();
            this._pressTimer = setTimeout(() => {
                this._pressTimer = null;
                this._openAtCoords(this._pressStartX, this._pressStartY);
            }, LONG_PRESS_MS);
        },

        /**
         * Cancel the pending long-press if the finger moves far enough to be a
         * scroll/drag rather than a hold.
         * @param {TouchEvent} event
         */
        onTouchMove(event) {
            if (!this._pressTimer) return;

            const touch = event.touches[0];
            if (!touch) return;

            const dx = Math.abs(touch.clientX - this._pressStartX);
            const dy = Math.abs(touch.clientY - this._pressStartY);
            if (dx > LONG_PRESS_MOVE_TOLERANCE_PX || dy > LONG_PRESS_MOVE_TOLERANCE_PX) {
                this._clearPressTimer();
            }
        },

        /**
         * Finger lifted / gesture canceled before the threshold — abort the
         * pending long-press.
         */
        onTouchEnd() {
            this._clearPressTimer();
        },

        /**
         * Clear the pending long-press timer (idempotent).
         */
        _clearPressTimer() {
            if (this._pressTimer) {
                clearTimeout(this._pressTimer);
                this._pressTimer = null;
            }
        },

        /**
         * Close context menu.
         */
        close() {
            // Nothing to close, and — the reason this line is first — nothing to focus.
            // `x-on:click.outside` is wired to the document, so one click anywhere fires
            // close() once per menu on the page, including every menu that is already
            // shut. Without this return, each of those closed menus would run the focus
            // block below and the last one would win, pulling focus out of whatever the
            // reader just clicked.
            if (!this.open) return;

            // Was the reader inside the panel when it closed? The answer has to be taken
            // BEFORE anything hides, and it decides whether focus is ours to move.
            //
            // Escape, and activating an item, both close with focus on a menu item — and
            // that item is about to be hidden, which drops focus on <body> and restarts a
            // keyboard reader at the top of the page (WCAG 2.4.3). A click on some other
            // control also lands here, and there focus belongs where the reader just put
            // it; pulling it back to the trigger would take it out of the field they
            // clicked. So the move is conditional on where focus already is.
            const panel = this.$refs.panel;
            const focusWasInside = panel ? panel.contains(document.activeElement) : false;

            if (focusWasInside) {
                // The interactive descendant, not the wrapper: `$refs.trigger` is a plain
                // div around whatever the caller passed, and focusing a div announces
                // nothing. Where the trigger area holds no focusable element there is
                // nothing to return to, and doing nothing is the honest outcome.
                this.$refs.trigger?.querySelector(
                    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                )?.focus({ preventScroll: true });
            }

            // BEFORE the hide, not after. The panel leaves through an `x-transition`, so
            // `display: none` is applied when that transition ends — after any focus call
            // made on this tick, which would therefore accomplish nothing and leave focus
            // on <body> anyway. Moving focus out of the panel first means there is never a
            // moment where the focused element sits inside a subtree that is going away.
            this.open = false;
        },

        /**
         * Force close — SPA navigation, page scroll, and the cross-instance handoff.
         *
         * Deliberately focus-neutral, unlike close(). Every caller here is the environment
         * ending the menu rather than the reader dismissing it: the page is navigating away,
         * the panel has been stranded by a scroll, or a sibling menu is opening and is about
         * to focus its own first item. Returning focus to this trigger in those states would
         * either be pointless or would fight the element that is taking over.
         */
        _forceClose() {
            this.open = false;
        },

        /**
         * Get all menuitem elements at THIS level of the panel.
         *
         * Items nested inside a submenu's child panel (`[data-wk-submenu-panel]`)
         * are excluded so top-level roving focus stays flat — the submenu owns
         * its own level via wirekitSubmenu. The submenu PARENT item is itself a
         * `[role="menuitem"]` NOT inside a submenu panel, so it stays included.
         */
        _getItems() {
            const panel = this.$refs.panel;
            if (!panel) return [];
            return [...panel.querySelectorAll('[role="menuitem"]:not([aria-disabled="true"])')]
                .filter((el) => !el.closest('[data-wk-submenu-panel]'));
        },

        /**
         * Handle keyboard navigation within the context menu.
         */
        handleKeydown(event) {
            if (!this.open) return;

            const items = this._getItems();
            if (!items.length) return;

            // Where the reader IS, asked of the document, rather than where this component
            // last put them. The list on the line above was just read from the DOM; taking
            // the position from anywhere else lets the two describe different pages.
            //
            // Two ordinary routes move focus without these keys, and a held index missed
            // both: a Livewire morph rebuilding entries that depend on server state, and
            // returning from a submenu — `submenu.js` focuses the parent item itself when it
            // closes, which this component never heard about.
            //
            // `-1` when focus is outside the menu is handled by the arithmetic rather than
            // by a branch, exactly as in `dropdown.js`: ArrowDown lands on the first entry
            // and ArrowUp on the second-to-last, so a press always moves somewhere real
            // instead of silently doing nothing.
            const currentIndex = items.indexOf(document.activeElement);

            switch (event.key) {
                case 'ArrowDown':
                    event.preventDefault();
                    items[(currentIndex + 1) % items.length]?.focus();
                    break;

                case 'ArrowUp':
                    event.preventDefault();
                    items[(currentIndex - 1 + items.length) % items.length]?.focus();
                    break;

                case 'Home':
                    event.preventDefault();
                    items[0]?.focus();
                    break;

                case 'End':
                    event.preventDefault();
                    items[items.length - 1]?.focus();
                    break;

                case 'Escape':
                    event.preventDefault();
                    this.close();
                    break;
            }
        },
    };
}
