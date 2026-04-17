/**
 * Shared overlay utilities for WireKit Modal and Drawer.
 *
 * Provides scroll lock, event handling, wire:model sync, and SPA cleanup.
 * Both Modal and Drawer share identical logic for these concerns.
 */
import { createFocusTrap } from './focus-trap.js';

/**
 * Global scroll lock reference counter.
 * Tracks how many overlays currently hold a scroll lock so that
 * body overflow is only restored when ALL overlays are closed.
 */
let scrollLockCount = 0;

function lockScroll() {
    if (scrollLockCount === 0) {
        document.body.style.overflow = 'hidden';
    }
    scrollLockCount++;
}

function unlockScroll() {
    scrollLockCount = Math.max(0, scrollLockCount - 1);
    if (scrollLockCount === 0) {
        document.body.style.overflow = '';
    }
}

/**
 * Create shared overlay behavior for Modal and Drawer Alpine components.
 *
 * @param {Object} options - Overlay configuration
 * @param {string} options.name - Unique overlay identifier
 * @param {boolean} options.dismissible - Whether ESC/backdrop close is allowed
 * @param {string} options.showEvent - Event name for showing (e.g. 'wirekit-modal-show')
 * @param {string} options.closeEvent - Event name for closing (e.g. 'wirekit-modal-close')
 * @returns {Object} Alpine component data object with overlay methods
 */
export function createOverlay({ name, dismissible, showEvent, closeEvent }) {
    return {
        // Expose `dismissible` as a public Alpine property so descendant
        // scopes (e.g. modal.header's auto-rendered close button) can gate
        // their visibility with `x-show="dismissible"`. Non-reactive — the
        // value is set at init and never changes during the overlay lifetime.
        dismissible,
        open: false,
        _trap: null,
        _showHandler: null,
        _closeHandler: null,
        _navCleanup: null,

        /**
         * Initialize overlay event listeners and wire:model sync.
         * Called from the component's init() method.
         */
        initOverlay() {
            // Store named handler references so they can be removed on cleanup
            this._showHandler = (e) => {
                if (e.detail?.name === name) {
                    this.show();
                }
            };

            this._closeHandler = (e) => {
                if (e.detail?.name === name) {
                    this.close();
                }
            };

            window.addEventListener(showEvent, this._showHandler);
            window.addEventListener(closeEvent, this._closeHandler);

            // wire:model support — watch Livewire property if bound
            if (this.$wire) {
                const wireModelAttr = this.$el.getAttribute('wire:model')
                    || this.$el.getAttribute('wire:model.live');

                if (wireModelAttr) {
                    // Watch the Livewire property for changes
                    this.$watch('open', (value) => {
                        this.$wire.set(wireModelAttr, value);
                    });

                    // React to external Livewire property changes
                    const initialValue = this.$wire.get(wireModelAttr);
                    if (initialValue) {
                        this.$nextTick(() => this.show());
                    }
                }
            }

            // Cleanup on Livewire SPA navigation
            this._navCleanup = () => this._forceClose();
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });
        },

        /**
         * Alpine destroy() hook — cleanup event listeners and close overlay.
         * Called automatically when the component's DOM element is removed.
         */
        destroyOverlay() {
            if (this._navCleanup) {
                document.removeEventListener('livewire:navigating', this._navCleanup);
            }
            window.removeEventListener(showEvent, this._showHandler);
            window.removeEventListener(closeEvent, this._closeHandler);
            this._forceClose();
        },

        /**
         * Show the overlay — activate focus trap and scroll lock.
         * Resolves $refs.panel lazily to avoid stale element references.
         */
        show() {
            if (this.open) return;
            this.open = true;

            // Reference-counted scroll lock — safe with multiple overlays
            lockScroll();

            // Focus trap — activate after Alpine renders the panel
            this.$nextTick(() => {
                const panelEl = this.$refs.panel;
                if (panelEl) {
                    this._trap = createFocusTrap(panelEl, {
                        escapeDeactivates: dismissible,
                        // onDeactivate fires when ESC is pressed — close without
                        // calling deactivate() again (it's already deactivating)
                        onDeactivate: () => this._closeFromTrap(),
                        // Dismissible: allow outside clicks so backdrop click
                        // handlers (handleBackdropClick) can fire.
                        // Non-dismissible: block outside clicks entirely to
                        // prevent unintended page interaction behind the overlay.
                        allowOutsideClick: dismissible,
                    });
                    this._trap.activate();
                }
            });
        },

        /**
         * Close triggered by focus-trap deactivation (ESC key).
         * Skips deactivate() call since the trap is already deactivating.
         */
        _closeFromTrap() {
            if (!this.open) return;
            this.open = false;
            this._trap = null;
            unlockScroll();
        },

        /**
         * Close the overlay — deactivate focus trap and restore scroll.
         */
        close() {
            if (!this.open) return;
            this.open = false;

            // Deactivate focus trap (returns focus to trigger automatically)
            if (this._trap) {
                this._trap.deactivate();
                this._trap = null;
            }

            unlockScroll();
        },

        /**
         * Force close without transitions — used during SPA navigation cleanup.
         */
        _forceClose() {
            if (!this.open) return;
            this.open = false;

            if (this._trap) {
                this._trap.deactivate();
                this._trap = null;
            }

            unlockScroll();
        },

        /**
         * Handle backdrop click — close only if dismissible.
         */
        handleBackdropClick() {
            if (dismissible) {
                this.close();
            }
        },
    };
}
