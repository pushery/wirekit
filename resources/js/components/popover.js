import { applyTriggerAria } from '../utils/trigger-aria.js';

/**
 * WireKit Popover Alpine Component.
 *
 * Click-triggered floating panel with focus trap. Unlike Tooltip (hover)
 * and HoverCard (hover + rich content), Popover opens on click and traps
 * focus inside the panel. Uses Floating UI for positioning.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/
 */
import { position } from '../utils/floating.js';
import { createFocusTrap } from '../utils/focus-trap.js';

/**
 * @param {Object} config - Popover configuration from Blade
 * @param {string} config.placement - Floating UI placement (default: 'bottom')
 * @param {number} config.offset - Distance from trigger in px (default: 8)
 */
export default function wirekitPopover(config = {}) {
    return {
        /** Move the popup ARIA onto the trigger's focusable child. */
        initTriggerAria() {
            applyTriggerAria(this.$el, this.$watch.bind(this), { missingTriggerWarning: '[wirekit] popover: trigger slot has no focusable element (button/link). Keyboard users cannot open the popover. Wrap the trigger content in a <button>.' });
        },

        open: false,
        _placement: config.placement || 'bottom',
        _offset: config.offset ?? 8,
        _trap: null,
        _navCleanup: null,
        // Floating UI autoUpdate teardown handle — set in show(), called in EVERY
        // close path (close / _closeFromTrap / _forceClose) so the scroll+resize
        // listeners never outlive the panel (every teardown path must call stop()).
        _stopAutoUpdate: null,

        init() {
            // Cleanup on Livewire SPA navigation
            this._navCleanup = () => this._forceClose();
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });
        },

        destroy() {
            if (this._navCleanup) {
                document.removeEventListener('livewire:navigating', this._navCleanup);
            }
            this._forceClose();
        },

        /**
         * Toggle popover open/close.
         */
        toggle() {
            this.open ? this.close() : this.show();
        },

        /**
         * Show popover, position via Floating UI, activate focus trap.
         */
        async show() {
            if (this.open) return;
            this.open = true;

            await this.$nextTick();

            const trigger = this.$refs.trigger;
            const panel = this.$refs.panel;

            if (trigger && panel) {
                this._stopAutoUpdate?.();
                const { stop } = await position(trigger, panel, {
                    placement: this._placement,
                    offset: this._offset,
                    // Keep the panel inside the viewport on narrow screens for
                    // left/right placements — Floating UI's default main-axis
                    // shift can't pull a right-placed panel back from the right
                    // edge (main axis is vertical for left/right placements).
                    crossAxisShift: true,
                    // Follow the trigger on scroll/resize; torn down in every close path.
                    autoReposition: true,
                });
                this._stopAutoUpdate = stop;

                // Activate focus trap — ESC deactivates and closes
                this._trap = createFocusTrap(panel, {
                    escapeDeactivates: true,
                    onDeactivate: () => this._closeFromTrap(),
                    // Allow clicking trigger to close without trap interference
                    allowOutsideClick: true,
                });
                this._trap.activate();
            }
        },

        /**
         * Close triggered by focus-trap deactivation (ESC key).
         */
        _closeFromTrap() {
            if (!this.open) return;
            this.open = false;
            this._stopAutoUpdate?.();
            this._stopAutoUpdate = null;
            this._trap = null;
        },

        /**
         * Close popover and deactivate focus trap.
         */
        close() {
            if (!this.open) return;
            this.open = false;
            this._stopAutoUpdate?.();
            this._stopAutoUpdate = null;

            if (this._trap) {
                this._trap.deactivate();
                this._trap = null;
            }
        },

        /**
         * Force close without transitions — SPA navigation cleanup.
         */
        _forceClose() {
            if (!this.open) return;
            this.open = false;
            this._stopAutoUpdate?.();
            this._stopAutoUpdate = null;

            if (this._trap) {
                this._trap.deactivate();
                this._trap = null;
            }
        },
    };
}
