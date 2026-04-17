/**
 * WireKit Menubar Alpine Component.
 *
 * Desktop-style horizontal menu bar with dropdown menus.
 * Follows WAI-ARIA menubar pattern with full keyboard navigation:
 * Arrow Left/Right between menus, Arrow Down opens/navigates items.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/menubar/
 */
import { position } from '../utils/floating.js';

export default function wirekitMenubar() {
    return {
        activeMenu: null,
        _focusIndex: -1,
        _navCleanup: null,

        init() {
            this._navCleanup = () => { this.activeMenu = null; };
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });
        },

        destroy() {
            if (this._navCleanup) {
                document.removeEventListener('livewire:navigating', this._navCleanup);
            }
        },

        /**
         * Toggle a specific menu open/close.
         */
        async toggleMenu(name) {
            if (this.activeMenu === name) {
                this.activeMenu = null;
                this._focusIndex = -1;
            } else {
                this.activeMenu = name;
                this._focusIndex = -1;
                await this._positionActiveMenu(name);
            }
        },

        /**
         * Open a menu (used for hover when another menu is already open).
         */
        async openMenu(name) {
            if (this.activeMenu && this.activeMenu !== name) {
                this.activeMenu = name;
                this._focusIndex = -1;
                await this._positionActiveMenu(name);
            }
        },

        /**
         * Close all menus.
         */
        closeAll() {
            this.activeMenu = null;
            this._focusIndex = -1;
        },

        /**
         * Position the active menu's dropdown panel.
         */
        async _positionActiveMenu(name) {
            await this.$nextTick();

            const trigger = this.$el.querySelector(`[data-wk-menubar-trigger="${name}"]`);
            const panel = this.$el.querySelector(`[data-wk-menubar-panel="${name}"]`);

            if (trigger && panel) {
                await position(trigger, panel, {
                    placement: 'bottom-start',
                    offset: 4,
                });
            }
        },

        /**
         * Get menu trigger buttons.
         */
        _getTriggers() {
            return [...this.$el.querySelectorAll('[data-wk-menubar-trigger]')];
        },

        /**
         * Get menu items in the active panel.
         */
        _getActiveItems() {
            if (!this.activeMenu) return [];
            const panel = this.$el.querySelector(`[data-wk-menubar-panel="${this.activeMenu}"]`);
            if (!panel) return [];
            return [...panel.querySelectorAll('[role="menuitem"]:not([aria-disabled="true"])')];
        },

        /**
         * Handle keyboard navigation for the menubar.
         */
        handleKeydown(event) {
            const triggers = this._getTriggers();

            switch (event.key) {
                case 'ArrowRight': {
                    event.preventDefault();
                    const currentIdx = triggers.findIndex(t => t.dataset.wkMenubarTrigger === this.activeMenu);
                    const nextIdx = (currentIdx + 1) % triggers.length;
                    const nextName = triggers[nextIdx]?.dataset.wkMenubarTrigger;
                    if (nextName) {
                        this.toggleMenu(nextName);
                        triggers[nextIdx]?.focus();
                    }
                    break;
                }

                case 'ArrowLeft': {
                    event.preventDefault();
                    const currentIdx = triggers.findIndex(t => t.dataset.wkMenubarTrigger === this.activeMenu);
                    const prevIdx = currentIdx <= 0 ? triggers.length - 1 : currentIdx - 1;
                    const prevName = triggers[prevIdx]?.dataset.wkMenubarTrigger;
                    if (prevName) {
                        this.toggleMenu(prevName);
                        triggers[prevIdx]?.focus();
                    }
                    break;
                }

                case 'ArrowDown': {
                    event.preventDefault();
                    const items = this._getActiveItems();
                    if (!items.length && this.activeMenu) return;
                    if (!this.activeMenu) {
                        // Open first menu
                        const firstName = triggers[0]?.dataset.wkMenubarTrigger;
                        if (firstName) this.toggleMenu(firstName);
                        return;
                    }
                    this._focusIndex = (this._focusIndex + 1) % items.length;
                    items[this._focusIndex]?.focus();
                    break;
                }

                case 'ArrowUp': {
                    event.preventDefault();
                    const items = this._getActiveItems();
                    if (!items.length) return;
                    this._focusIndex = this._focusIndex <= 0 ? items.length - 1 : this._focusIndex - 1;
                    items[this._focusIndex]?.focus();
                    break;
                }

                case 'Escape':
                    event.preventDefault();
                    this.closeAll();
                    break;

                case 'Home': {
                    event.preventDefault();
                    const items = this._getActiveItems();
                    if (items.length) {
                        this._focusIndex = 0;
                        items[0]?.focus();
                    }
                    break;
                }

                case 'End': {
                    event.preventDefault();
                    const items = this._getActiveItems();
                    if (items.length) {
                        this._focusIndex = items.length - 1;
                        items[items.length - 1]?.focus();
                    }
                    break;
                }
            }
        },
    };
}
