/**
 * WireKit Dropdown Alpine Component.
 *
 * Handles positioning via Floating UI, keyboard navigation (arrow keys),
 * click-outside closing, and ARIA menu pattern.
 */
import { position } from '../utils/floating.js';

/**
 * @param {Object} config - Dropdown configuration from Blade
 * @param {string} config.placement - Floating UI placement
 * @param {number} config.offset - Distance between trigger and panel in px
 */
export default function wirekitDropdown(config = {}) {
    return {
        open: false,
        _placement: config.placement || 'bottom-start',
        _offset: config.offset || 8,

        // Stored cleanup handler for destroy()
        _navCleanup: null,

        init() {
            // Cleanup on Livewire SPA navigation
            this._navCleanup = () => { this.open = false; };
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });
        },

        destroy() {
            if (this._navCleanup) {
                document.removeEventListener('livewire:navigating', this._navCleanup);
            }
        },

        /**
         * Toggle dropdown open/close state.
         */
        toggle() {
            if (this.open) {
                this.close();
            } else {
                this.show();
            }
        },

        /**
         * Open dropdown and position panel relative to trigger.
         */
        async show() {
            this.open = true;

            // Wait for Alpine to render the panel, then position it
            await this.$nextTick();

            const trigger = this.$refs.trigger;
            const panel = this.$refs.panel;

            if (trigger && panel) {
                await position(trigger, panel, {
                    placement: this._placement,
                    offset: this._offset,
                });

                // Focus first menu item for keyboard users
                this._focusFirstItem();
            }
        },

        /**
         * Close dropdown and return focus to trigger.
         */
        close() {
            this.open = false;
            // Return focus to the trigger button. Use preventScroll so the page
            // does not jump to the trigger when the dropdown closes — the trigger
            // is already in view (the user just clicked it) and browser scroll
            // alignment would otherwise cause a visible jump on long pages.
            const target = this.$refs.trigger?.querySelector('button, [role="button"], a')
                ?? this.$refs.trigger;
            target?.focus({ preventScroll: true });
        },

        /**
         * Navigate menu items with arrow keys.
         * Implements WAI-ARIA menu keyboard pattern.
         */
        handleKeydown(e) {
            if (!this.open) return;

            const items = this._getItems();
            if (!items.length) return;

            const current = document.activeElement;
            const currentIndex = items.indexOf(current);

            // All navigation focus calls use preventScroll for the same reason
            // as _focusFirstItem(): the panel is `position: fixed` at viewport
            // coordinates, so letting the browser scroll the page on focus
            // change causes a jarring jump when the trigger sits mid-page.
            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    // Move to next item, wrap to first
                    items[(currentIndex + 1) % items.length]?.focus({ preventScroll: true });
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    // Move to previous item, wrap to last
                    items[(currentIndex - 1 + items.length) % items.length]?.focus({ preventScroll: true });
                    break;

                case 'Home':
                    e.preventDefault();
                    items[0]?.focus({ preventScroll: true });
                    break;

                case 'End':
                    e.preventDefault();
                    items[items.length - 1]?.focus({ preventScroll: true });
                    break;

                case 'Escape':
                    e.preventDefault();
                    this.close();
                    break;

                case 'Tab':
                    // Let tab leave the dropdown naturally, but close it
                    this.close();
                    break;
            }
        },

        /**
         * Focus the first enabled menu item.
         *
         * Uses `preventScroll: true` because the panel uses `position: fixed`
         * and there is a brief window between `x-show` making the panel visible
         * and Floating UI applying its computed left/top coordinates where the
         * panel is rendered at the viewport origin (0, 0). Without preventScroll
         * the browser scrolls the page to bring the focused item into view at
         * that origin — manifesting as an unexpected jump when the trigger sits
         * mid-page. The panel is already visible once Floating UI finishes, so
         * we don't need (or want) the browser to scroll anything.
         */
        _focusFirstItem() {
            const items = this._getItems();
            items[0]?.focus({ preventScroll: true });
        },

        /**
         * Get all focusable menu items (not disabled).
         *
         * @returns {HTMLElement[]}
         */
        _getItems() {
            const panel = this.$refs.panel;
            if (!panel) return [];

            return Array.from(
                panel.querySelectorAll('[role="menuitem"]:not([aria-disabled="true"])')
            );
        },
    };
}
