/**
 * WireKit Drawer Alpine Component.
 *
 * Shares base overlay behavior with Modal (focus-trap, scroll lock, events).
 * Differs in transitions (slide vs scale) and sizing (position-dependent).
 */
import { createOverlay } from '../utils/overlay.js';
import { firstControl } from '../utils/first-control.js';

/**
 * @param {Object} config - Drawer configuration from Blade
 * @param {string} config.name - Unique drawer identifier
 * @param {boolean} config.dismissible - Whether ESC/backdrop closes the drawer
 */
export default function wirekitDrawer(config = {}) {
    const overlay = createOverlay({
        name: config.name || '',
        dismissible: config.dismissible !== false,
        showEvent: 'wirekit-drawer-show',
        closeEvent: 'wirekit-drawer-close',
        // Sent when the reader dismisses it, so a page can clean up state it did not close itself.
        dismissedEvent: 'wirekit:drawer-dismissed',
        // drawer.body is a tab stop so a drawer of plain text can be scrolled from the keyboard,
        // and it wraps everything inside the drawer — start on the first CONTROL instead, and
        // on the body only when there is none. utils/first-control.js.
        initialFocus: (panelEl) => firstControl(panelEl, 'data-wk-drawer-body'),
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
