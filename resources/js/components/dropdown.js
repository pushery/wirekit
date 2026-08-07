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
        // Read by the panel through the scope chain, which survives its teleport.
        panelId: config.panelId || '',
        _placement: config.placement || 'bottom-start',
        _offset: config.offset ?? 8,

        // Stored cleanup handler for destroy()
        _navCleanup: null,
        // Floating UI autoUpdate teardown handle (set in show(), called in close()
        // + destroy()). Keeping the panel pinned to its trigger on scroll/resize
        // attaches ancestor-scroll + resize listeners; so every teardown path must call stop() or they leak
        // they MUST be torn down on every close path or they leak.
        _stopAutoUpdate: null,

        init() {
            // The panel's id is written here rather than bound in the template.
            //
            // It used to be `x-bind:id="panelId"` on the panel — reading the value out of
            // this scope, which is correct across the TELEPORT (Alpine keeps the scope;
            // `closest()` would not) and wrong across a Livewire MORPH. On every morph
            // that binding was re-evaluated in a scope that no longer had `panelId`, and
            // threw `panelId is not defined`.
            //
            // A JavaScript error during a morph ends evaluation at that point, so whatever
            // the same pass would have done next does not happen — and nothing turns red,
            // because a console error is not a failed assertion. It sat in a consuming
            // application for weeks that way.
            //
            // Assigning it imperatively removes the last scope-dependent expression from
            // the teleported node: it runs on init, re-runs when the morph re-initializes
            // this component, and cannot be re-evaluated in a scope it does not belong to.
            this._applyPanelId();

            // Cleanup on Livewire SPA navigation
            this._navCleanup = () => { this.open = false; };
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });
        },

        /**
         * Put the panel id on the panel node.
         *
         * The value is preferred from the root's own attribute over this scope, because
         * the attribute is a fact about the DOM and survives anything the scope does not.
         */
        _applyPanelId() {
            const panel = this.$refs.panel;
            const id = (this.$el && this.$el.dataset && this.$el.dataset.wkPanelId) || this.panelId;

            if (panel && id) {
                panel.id = id;
            }
        },

        destroy() {
            if (this._navCleanup) {
                document.removeEventListener('livewire:navigating', this._navCleanup);
            }
            this._stopAutoUpdate?.();
            this._stopAutoUpdate = null;
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
                this._stopAutoUpdate?.();
                const { stop } = await position(trigger, panel, {
                    placement: this._placement,
                    offset: this._offset,
                    // Cap the panel to the viewport and let it scroll — a 12-item
                    // menu opening upward from the foot of a short window used to
                    // pin to the top edge and clip its first (often most
                    // important) item. See floating.js size middleware.
                    fitViewport: true,
                    // Follow the trigger while open; teardown handle stored for close().
                    autoReposition: true,
                });
                this._stopAutoUpdate = stop;

                // Focus first menu item for keyboard users
                this._focusFirstItem();
            }
        },

        /**
         * Close dropdown and return focus to trigger.
         *
         * The leading `if (!this.open) return;` guard is REQUIRED, not optional.
         * Every <x-wirekit::dropdown> wrapper carries an `x-on:click.outside="close()"`
         * listener that fires for EVERY dropdown on EVERY click anywhere on the page.
         * Without the guard, three dropdowns + one click on an unrelated input means
         * three close() invocations, three trigger.focus() calls, and the last one
         * wins — silently stealing focus from whatever element the user actually
         * clicked. Inputs lose focus mid-typing, dropdowns flicker open-then-shut,
         * any focusable element next to a dropdown is collateral damage.
         *
         * Matches the same guard pattern used in popover.js, command-palette.js,
         * and overlay.js (modal / drawer / alert-dialog).
         */
        close() {
            if (!this.open) return;
            this.open = false;
            this._stopAutoUpdate?.();
            this._stopAutoUpdate = null;
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
         * Get all focusable menu items (not disabled) at THIS level.
         *
         * Items nested inside a submenu's child panel (`[data-wk-submenu-panel]`)
         * are excluded so parent-level roving focus (ArrowUp/Down/Home/End) stays
         * flat — the submenu owns its own level via wirekitSubmenu. The submenu
         * PARENT item is itself a `[role="menuitem"]` that is NOT inside a submenu
         * panel, so it is correctly included at this level.
         *
         * @returns {HTMLElement[]}
         */
        _getItems() {
            const panel = this.$refs.panel;
            if (!panel) return [];

            return Array.from(
                panel.querySelectorAll('[role="menuitem"]:not([aria-disabled="true"])')
            ).filter((el) => !el.closest('[data-wk-submenu-panel]'));
        },
    };
}
