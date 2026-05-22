/**
 * WireKit Alert Dialog Alpine Component.
 *
 * Specialized modal for destructive confirmation dialogs.
 * Uses role="alertdialog" and focuses the CANCEL button by default (safety).
 * Non-dismissible by default — user must explicitly choose an action.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/alertdialog/
 */
import { createOverlay } from '../utils/overlay.js';

/**
 * @param {Object} config - Alert dialog configuration from Blade
 * @param {string} config.name - Unique dialog identifier
 * @param {boolean} config.dismissible - Whether ESC/backdrop closes (default: false)
 */
export default function wirekitAlertDialog(config = {}) {
    const overlay = createOverlay({
        name: config.name || '',
        // Alert dialogs are non-dismissible by default for safety —
        // a stray backdrop click should NOT approve a destructive action.
        dismissible: config.dismissible === true,
        showEvent: 'wirekit-alert-dialog-show',
        closeEvent: 'wirekit-alert-dialog-close',
        // ESC is a deliberate user action — even non-dismissible dialogs
        // need a keyboard escape hatch so users aren't trapped. Backdrop
        // click stays gated by `dismissible` (the safety-strict half).
        escapeAlwaysCloses: true,
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
