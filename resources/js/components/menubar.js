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
        _onPointerDown: null,

        init() {
            this._navCleanup = () => { this.activeMenu = null; };
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });

            // Outside-click close. The dropdown panels are teleported to
            // <body> (to escape transformed ancestors), so they are no longer
            // DOM descendants of `this.$el` — a Blade `x-on:click.outside` on
            // the menubar root would treat a click INSIDE an open panel as
            // "outside" and close the menu before the item's own click handler
            // ran. This document-level handler instead closes only when the
            // pointer lands outside BOTH the menubar bar AND the active
            // teleported panel (looked up via the teleport-safe $refs).
            this._onPointerDown = (event) => {
                if (!this.activeMenu) return;
                const target = event.target;
                if (!(target instanceof Node)) return;
                if (this.$root.contains(target)) return;
                const panel = this.$refs[`panel-${this.activeMenu}`];
                if (panel && panel.contains(target)) return;
                this.closeAll();
            };
            document.addEventListener('pointerdown', this._onPointerDown, { capture: true });
        },

        destroy() {
            if (this._navCleanup) {
                document.removeEventListener('livewire:navigating', this._navCleanup);
            }
            if (this._onPointerDown) {
                document.removeEventListener('pointerdown', this._onPointerDown, { capture: true });
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

            // Trigger stays in the bar (not teleported). Query from $root (the
            // menubar element), NOT $el — when this runs off a trigger's
            // x-on:click, Alpine binds $el to the clicked menuitem button
            // (no trigger descendants), so $el.querySelector would miss and
            // positioning would silently never run. Panel is teleported to
            // <body> → resolve via the teleport-safe ref.
            const trigger = this.$root.querySelector(`[data-wk-menubar-trigger="${name}"]`);
            const panel = this.$refs[`panel-${name}`];

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
            // $root, not $el — see _positionActiveMenu for why ($el can be a
            // clicked menuitem button rather than the menubar root).
            return [...this.$root.querySelectorAll('[data-wk-menubar-trigger]')];
        },

        /**
         * Get menu items at the TOP level of the active panel.
         *
         * Items nested inside a submenu's child panel (`[data-wk-submenu-panel]`)
         * are excluded so top-level roving focus stays flat — the submenu owns
         * its own level via wirekitSubmenu. The submenu PARENT item is itself a
         * `[role="menuitem"]` NOT inside a submenu panel, so it stays included.
         */
        _getActiveItems() {
            if (!this.activeMenu) return [];
            // Panel is teleported to <body>; resolve via the teleport-safe ref.
            const panel = this.$refs[`panel-${this.activeMenu}`];
            if (!panel) return [];
            return [...panel.querySelectorAll('[role="menuitem"]:not([aria-disabled="true"])')]
                .filter((el) => !el.closest('[data-wk-submenu-panel]'));
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
