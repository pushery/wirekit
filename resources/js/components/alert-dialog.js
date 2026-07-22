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
 * Focusable descendants, in the order the browser would tab through them.
 * Kept local: the only thing this file needs it for is finding the control
 * inside the Cancel wrapper.
 */
const FOCUSABLE = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

/**
 * @param {Object} config - Alert dialog configuration from Blade
 * @param {string} config.name - Unique dialog identifier
 * @param {boolean} config.dismissible - Whether ESC/backdrop closes (default: false)
 * @param {string} [config.initialFocus] - CSS selector for the control that should
 *   receive focus instead of Cancel
 * @param {string} [config.focusReturnTo] - CSS selector for where focus should land
 *   when the dialog closes and its own trigger no longer exists
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
        /**
         * Focus Cancel, not whatever happens to come first in the DOM.
         *
         * This is the safety promise this component is recommended FOR, and for
         * a long time it was only a docblock: without an explicit initialFocus,
         * focus-trap falls back to the first focusable element in the panel —
         * which, depending on how the caller composed the dialog, can be the
         * destructive button itself. A stray Enter would then confirm the very
         * action the pattern exists to guard. The APG alertdialog pattern is
         * explicit that initial focus belongs on the LEAST destructive control.
         *
         * A caller can name a different control; falling back to the panel keeps
         * focus inside the dialog when no Cancel is rendered at all.
         */
        initialFocus: (panelEl) => {
            if (!panelEl) return undefined;

            if (config.initialFocus) {
                const named = panelEl.querySelector(config.initialFocus);
                if (named) return named;
            }

            const cancel = panelEl.querySelector('[data-wk-alert-cancel]');
            if (cancel) {
                // The marker sits on the wrapper; the focusable control is the
                // button inside it (the wrapper carries the click handler).
                const control = cancel.matches(FOCUSABLE) ? cancel : cancel.querySelector(FOCUSABLE);
                if (control) return control;
            }

            // Nothing identified itself as the safe action: return undefined so
            // focus-trap keeps its own default. Deliberately NOT the panel — an
            // element that is not focusable would make focus-trap throw, and
            // guessing which of several bare buttons is the harmless one would be
            // worse than saying so. The guarantee holds when the dialog is built
            // with alert-dialog.cancel, or when the caller names initial-focus.
            return undefined;
        },
        focusReturnTo: config.focusReturnTo || undefined,
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
