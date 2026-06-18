/**
 * WireKit Submenu Alpine Component.
 *
 * A nested flyout opened from a parent menu item inside a dropdown,
 * context-menu, or menubar menu. The parent item carries
 * `aria-haspopup="menu"` + `aria-expanded`; the child panel is a
 * `role="menu"` positioned beside the parent via Floating UI.
 *
 * This factory is purely additive — a flat menu without a
 * `<x-wirekit::*.submenu>` never instantiates it, so existing menus are
 * unchanged. It is nested inside its parent menu's x-data, so its
 * expressions can read the parent scope (e.g. `open` on dropdown /
 * context-menu) for the close-on-parent-close reset.
 *
 * WAI-ARIA submenu keyboard model (https://www.w3.org/WAI/ARIA/apg/patterns/menu/):
 *   - On the parent item: ArrowRight / Enter / Space open the submenu and
 *     focus its first item. ArrowUp / ArrowDown are NOT handled here — they
 *     bubble to the parent menu's own handler so parent-level roving focus
 *     keeps working.
 *   - Inside the submenu panel: ArrowUp / ArrowDown / Home / End move within
 *     the level; ArrowLeft and Escape close the submenu and return focus to
 *     the parent item. These keys stopPropagation so the parent menu's
 *     handler does not also act on them.
 *
 * Lifecycle resources held on `this` (released in destroy()):
 *   - `_closeTimer` — the hover-out close setTimeout. Cleared on teardown so a
 *     pending close can't fire against a destroyed scope (no listeners/observers
 *     are registered, so the timer is the only thing to release).
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/menu/
 */
import { position } from '../utils/floating.js';

// Hover-out close delay. A short grace period lets the pointer travel
// diagonally from the parent item onto the child panel without the submenu
// snapping shut mid-traverse (the classic "diagonal problem").
const HOVER_CLOSE_DELAY_MS = 140;

/**
 * @param {Object} config - Submenu configuration from Blade
 * @param {string} config.placement - Floating UI placement (default right-start)
 * @param {number} config.offset - Gap between parent item and child panel
 */
export default function wirekitSubmenu(config = {}) {
    return {
        subOpen: false,
        _subPlacement: config.placement || 'right-start',
        _subOffset: config.offset ?? 0,
        _closeTimer: null,

        /**
         * Release the only lifecycle resource: the pending hover-out close timer.
         * Alpine calls this on teardown (Livewire morph / SPA navigation), so a
         * scheduled close can never fire against a destroyed component scope.
         */
        destroy() {
            this._clearCloseTimer();
        },

        /**
         * Open the submenu and position the child panel beside the parent item.
         * @param {boolean} focusFirst - Move focus to the first child item.
         */
        async openSub(focusFirst = false) {
            this._clearCloseTimer();

            if (this.subOpen) {
                if (focusFirst) this._focusFirstSubItem();

                return;
            }

            this.subOpen = true;
            await this.$nextTick();

            const trigger = this.$refs.subTrigger;
            const panel = this.$refs.subPanel;
            if (trigger && panel) {
                await position(trigger, panel, {
                    placement: this._subPlacement,
                    offset: this._subOffset,
                });
                if (focusFirst) this._focusFirstSubItem();
            }
        },

        /**
         * Close the submenu. Optionally return focus to the parent item (the
         * ArrowLeft / Escape path); the parent-close reset path does not
         * refocus (the whole menu is going away).
         * @param {boolean} refocusParent
         */
        closeSub(refocusParent = false) {
            this._clearCloseTimer();

            if (!this.subOpen) return;

            this.subOpen = false;
            if (refocusParent) {
                this.$refs.subTrigger?.focus({ preventScroll: true });
            }
        },

        /**
         * Hover open (no focus move — pointer users don't need roving focus).
         */
        scheduleOpen() {
            this._clearCloseTimer();
            this.openSub(false);
        },

        /**
         * Hover-out close after a short grace period (the diagonal problem).
         *
         * Sets `subOpen = false` inline rather than routing through closeSub() on
         * purpose: a pointer-driven close must NOT refocus the parent item (that
         * would yank focus on a mouse interaction). closeSub(true) is reserved for
         * the keyboard paths (ArrowLeft / Escape) where refocusing IS correct.
         */
        scheduleClose() {
            this._clearCloseTimer();
            this._closeTimer = setTimeout(() => {
                this.subOpen = false;
                this._closeTimer = null;
            }, HOVER_CLOSE_DELAY_MS);
        },

        _clearCloseTimer() {
            if (this._closeTimer) {
                clearTimeout(this._closeTimer);
                this._closeTimer = null;
            }
        },

        /**
         * Keyboard on the parent item. Only opens-the-submenu keys are handled
         * (and stopPropagation'd); everything else bubbles to the parent menu's
         * handler so parent-level navigation is untouched.
         * @param {KeyboardEvent} e
         */
        onTriggerKey(e) {
            switch (e.key) {
                case 'ArrowRight':
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    e.stopPropagation();
                    this.openSub(true);
                    break;
            }
        },

        /**
         * Keyboard within the submenu panel. Handled keys stopPropagation so the
         * parent menu's roving-focus handler does not also fire.
         * @param {KeyboardEvent} e
         */
        onSubKey(e) {
            const items = this._subItems();
            if (!items.length) return;

            const idx = items.indexOf(document.activeElement);

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    e.stopPropagation();
                    items[(idx + 1) % items.length]?.focus({ preventScroll: true });
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    e.stopPropagation();
                    items[(idx - 1 + items.length) % items.length]?.focus({ preventScroll: true });
                    break;

                case 'Home':
                    e.preventDefault();
                    e.stopPropagation();
                    items[0]?.focus({ preventScroll: true });
                    break;

                case 'End':
                    e.preventDefault();
                    e.stopPropagation();
                    items[items.length - 1]?.focus({ preventScroll: true });
                    break;

                case 'ArrowLeft':
                case 'Escape':
                    e.preventDefault();
                    e.stopPropagation();
                    this.closeSub(true);
                    break;
            }
        },

        /**
         * Direct-level items of THIS submenu (a deeper nested submenu's items
         * are excluded — their closest submenu panel is the deeper one).
         * @returns {HTMLElement[]}
         */
        _subItems() {
            const panel = this.$refs.subPanel;
            if (!panel) return [];

            return [...panel.querySelectorAll('[role="menuitem"]:not([aria-disabled="true"])')]
                .filter((el) => el.closest('[data-wk-submenu-panel]') === panel);
        },

        _focusFirstSubItem() {
            this._subItems()[0]?.focus({ preventScroll: true });
        },
    };
}
