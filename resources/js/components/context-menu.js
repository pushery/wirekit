/**
 * WireKit Context Menu Alpine Component.
 *
 * Right-click (contextmenu) triggered floating menu.
 * Uses Floating UI for positioning at cursor coordinates.
 * Follows WAI-ARIA menu pattern with arrow key navigation.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/menu/
 */
import { position } from '../utils/floating.js';

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
        },

        destroy() {
            if (this._navCleanup) {
                document.removeEventListener('livewire:navigating', this._navCleanup);
            }
            if (this._otherOpenCleanup) {
                window.removeEventListener('wirekit:context-menu-open', this._otherOpenCleanup);
            }
            this._forceClose();
        },

        /**
         * Open context menu at cursor position.
         * @param {MouseEvent} event - The contextmenu event
         */
        async openAt(event) {
            event.preventDefault();

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

            // Position panel at cursor coordinates using a virtual reference element
            const virtualRef = {
                getBoundingClientRect() {
                    return {
                        width: 0,
                        height: 0,
                        x: event.clientX,
                        y: event.clientY,
                        top: event.clientY,
                        left: event.clientX,
                        right: event.clientX,
                        bottom: event.clientY,
                    };
                },
            };

            await position(virtualRef, panel, {
                placement: 'bottom-start',
                offset: 2,
            });
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
