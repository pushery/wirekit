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
import { position } from '../utils/floating.js';

// Long-press tuning. 500ms is the platform-conventional touch-hold threshold
// (matches iOS/Android long-press); a 10px movement budget distinguishes a
// deliberate hold from the start of a scroll/drag gesture.
const LONG_PRESS_MS = 500;
const LONG_PRESS_MOVE_TOLERANCE_PX = 10;

/**
 * @param {Object} config - Context menu configuration from Blade
 */
export default function wirekitContextMenu(config = {}) {
    return {
        open: false,
        _focusIndex: -1,
        _navCleanup: null,
        _otherOpenCleanup: null,
        // Stable identity for the "close every other instance" coordination.
        // We previously used `this` for the source check, but Alpine wraps
        // each component in a reactive Proxy and the identity of `this` is
        // not guaranteed to be stable across event listener invocations vs
        // dispatchEvent calls (the Proxy can wrap-and-unwrap depending on
        // call site). A plain Symbol() created once in init() is bulletproof
        // — it's a primitive value, never proxied, and `===` comparison is
        // identity-based, so each instance gets its own unforgeable token.
        _uid: null,
        // Long-press (touch) state.
        _pressTimer: null,
        _pressStartX: 0,
        _pressStartY: 0,

        init() {
            this._uid = Symbol('wirekitContextMenu');
            this._navCleanup = () => this._forceClose();
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });

            // Auto-close cooperation: when ANY context menu broadcasts that it's
            // about to open, every OTHER instance closes itself. This prevents the
            // "two menus open at once" bug when multiple <x-wirekit::context-menu>
            // siblings live in the same page (e.g. one per table row). The event
            // carries the opening instance's Symbol as `detail.source` so each
            // instance can skip closing itself.
            this._otherOpenCleanup = (event) => {
                if (event.detail?.source !== this._uid) {
                    this._forceClose();
                }
            };
            window.addEventListener('wirekit:context-menu-open', this._otherOpenCleanup);

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
            if (this._otherOpenCleanup) {
                window.removeEventListener('wirekit:context-menu-open', this._otherOpenCleanup);
            }
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
            // Broadcast BEFORE flipping `open` so other instances close first.
            // The `source: this._uid` payload (a stable Symbol per instance)
            // lets sibling instances skip self-closing without relying on
            // Alpine's Proxy-wrapped `this` identity.
            window.dispatchEvent(new CustomEvent('wirekit:context-menu-open', {
                detail: { source: this._uid },
            }));

            this.open = true;
            this._focusIndex = -1;

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
            this.open = false;
            this._focusIndex = -1;
        },

        /**
         * Force close — SPA navigation cleanup.
         */
        _forceClose() {
            this.open = false;
            this._focusIndex = -1;
        },

        /**
         * Get all menuitem elements in the panel.
         */
        _getItems() {
            const panel = this.$refs.panel;
            if (!panel) return [];
            return [...panel.querySelectorAll('[role="menuitem"]:not([aria-disabled="true"])')];
        },

        /**
         * Handle keyboard navigation within the context menu.
         */
        handleKeydown(event) {
            if (!this.open) return;

            const items = this._getItems();
            if (!items.length) return;

            switch (event.key) {
                case 'ArrowDown':
                    event.preventDefault();
                    this._focusIndex = (this._focusIndex + 1) % items.length;
                    items[this._focusIndex]?.focus();
                    break;

                case 'ArrowUp':
                    event.preventDefault();
                    this._focusIndex = this._focusIndex <= 0 ? items.length - 1 : this._focusIndex - 1;
                    items[this._focusIndex]?.focus();
                    break;

                case 'Home':
                    event.preventDefault();
                    this._focusIndex = 0;
                    items[0]?.focus();
                    break;

                case 'End':
                    event.preventDefault();
                    this._focusIndex = items.length - 1;
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
