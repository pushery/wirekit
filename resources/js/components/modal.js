/**
 * WireKit Modal Alpine Component.
 *
 * Uses focus-trap for keyboard navigation, scroll lock for body,
 * and event-based show/close via 'wirekit-modal-show' / 'wirekit-modal-close'.
 */
import { createOverlay } from '../utils/overlay.js';
import { withOpenAlias } from '../utils/open-alias.js';
import { firstControl } from '../utils/first-control.js';
import { armDialogConfirm } from '../utils/dialog-confirm.js';
import { watchCurrent } from '../utils/watch-current.js';

/**
 * @param {Object} config - Modal configuration from Blade
 * @param {string} config.name - Unique modal identifier
 * @param {boolean} config.dismissible - Whether ESC/backdrop closes the modal
 * @param {boolean} [config.lockScroll=true] - Whether opening locks the page's scroll
 * @param {string|null} [config.discardQuestion=null] - Asked before the reader closes a dialog
 *   whose fields they changed (utils/discard-guard.js)
 * @param {boolean} [config.confirmShortcut=true] - Whether ⌘↵ / Ctrl+Enter presses the control
 *   marked `data-wk-dialog-primary` while the modal is open (utils/dialog-confirm.js)
 */
export default function wirekitModal(config = {}) {
    const overlay = createOverlay({
        name: config.name || '',
        dismissible: config.dismissible !== false,
        showEvent: 'wirekit-modal-show',
        closeEvent: 'wirekit-modal-close',
        // Sent when the reader dismisses it, so a page can clean up state it did not close itself.
        dismissedEvent: 'wirekit:modal-dismissed',
        // False for an overlay inside a page region, such as a preview: the page keeps scrolling.
        lockScroll: config.lockScroll !== false,
        // Start on the first CONTROL, not on the scrolling body — utils/first-control.js.
        initialFocus: (panelEl) => firstControl(panelEl, 'data-wk-modal-body'),
        // Asked before the reader closes a dialog whose fields they changed; null asks nothing.
        discardQuestion: typeof config.discardQuestion === 'string' && config.discardQuestion !== '' ? config.discardQuestion : null,
    });

    return withOpenAlias({
        ...overlay,

        /**
         * ⌘↵ / Ctrl+Enter presses the primary control while the modal is open. A modal has no
         * confirm of its own, so only a control the page marks with `data-wk-dialog-primary`
         * counts; without one the shortcut is not registered.
         */
        confirmShortcut: config.confirmShortcut !== false,
        _disarmConfirmShortcut: null,

        _syncConfirmShortcut(open) {
            if (this._disarmConfirmShortcut) {
                this._disarmConfirmShortcut();
                this._disarmConfirmShortcut = null;
            }

            if (open && this.confirmShortcut) {
                this._disarmConfirmShortcut = armDialogConfirm(this.$refs.panel);
            }
        },

        init() {
            this.initOverlay();
            watchCurrent(this, 'isOpen', (open) => this._syncConfirmShortcut(open));
        },

        destroy() {
            this._syncConfirmShortcut(false);
            this.destroyOverlay();
        },
    });
}
