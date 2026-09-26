/**
 * WireKit Modal Alpine Component.
 *
 * Uses focus-trap for keyboard navigation, scroll lock for body,
 * and event-based show/close via 'wirekit-modal-show' / 'wirekit-modal-close'.
 */
import { createOverlay } from '../utils/overlay.js';
import { firstControl } from '../utils/first-control.js';

/**
 * @param {Object} config - Modal configuration from Blade
 * @param {string} config.name - Unique modal identifier
 * @param {boolean} config.dismissible - Whether ESC/backdrop closes the modal
 */
export default function wirekitModal(config = {}) {
    const overlay = createOverlay({
        name: config.name || '',
        dismissible: config.dismissible !== false,
        showEvent: 'wirekit-modal-show',
        closeEvent: 'wirekit-modal-close',
        // Sent when the reader dismisses it, so a page can clean up state it did not close itself.
        dismissedEvent: 'wirekit:modal-dismissed',
        // Start on the first CONTROL, not on the scrolling body — utils/first-control.js.
        initialFocus: (panelEl) => firstControl(panelEl, 'data-wk-modal-body'),
    });

    return {
        ...overlay,

        init() {
            this.initOverlay();
        },

        destroy() {
            this.destroyOverlay();
        },
    };
}
