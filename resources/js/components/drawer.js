/**
 * WireKit Drawer Alpine Component.
 *
 * Shares base overlay behavior with Modal (focus-trap, scroll lock, events).
 * Differs in transitions (slide vs scale) and sizing (position-dependent).
 */
import { createOverlay } from '../utils/overlay.js';

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
