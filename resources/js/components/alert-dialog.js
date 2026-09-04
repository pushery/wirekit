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
 * @param {string} [config.confirmationPhrase] - Exact string the developer must type
 *   before `alert-dialog.confirm` will fire. Absent, nothing is held back.
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

        /**
         * The phrase a developer must type before the confirm control will fire,
         * and what they have typed so far.
         *
         * Null when the caller did not ask for a brake, which is the default and
         * leaves every other behavior of this component untouched.
         */
        confirmationPhrase: typeof config.confirmationPhrase === 'string' && config.confirmationPhrase !== ''
            ? config.confirmationPhrase
            : null,
        typed: '',

        /**
         * May the destructive action fire?
         *
         * True whenever no phrase was asked for — a dialog without a brake must
         * behave exactly as it did before this existed.
         *
         * The comparison trims the ends and is otherwise EXACT. Trimming is the one
         * concession, and it is there because a trailing space arrives from a
         * copy-paste rather than from a decision; case-folding or collapsing inner
         * whitespace would quietly widen what counts as "typed it", which is the
         * opposite of what a brake is for.
         */
        get confirmAllowed() {
            if (this.confirmationPhrase === null) return true;

            return this.typed.trim() === this.confirmationPhrase.trim();
        },

        /**
         * Refuse an activation of the confirm control while the phrase is unmet.
         *
         * This BLOCKS rather than merely describing itself as blocked. A control held
         * back only by `aria-disabled` stays clickable, so a stray Enter — or an Alpine
         * boot that never ran — fires the irreversible action while the dialog still
         * looks guarded. `disabled` alone is not the answer either: a disabled button is
         * not focusable, so the reason it is held back is never announced to anybody who
         * would need to hear it. Both halves are needed, and this is the half that bites.
         */
        blockUnlessConfirmed(event) {
            if (this.confirmAllowed) return;

            event.preventDefault();
            event.stopPropagation();
        },

        /**
         * Put the blocked state on the CONTROL, not on the wrapper around it.
         *
         * `aria-disabled` and `aria-describedby` were bound on the wrapper element
         * first, and neither reaches the button inside it: a generic element passes
         * no state down, and a description is only read out when the element carrying
         * it is the one with focus. So a screen-reader user focused "Delete forever",
         * heard an ordinary enabled button, pressed Enter and got nothing and no
         * reason — precisely the failure `blockUnlessConfirmed` exists to explain.
         *
         * The control is looked up rather than passed in because the caller may have
         * slotted their own; the same lookup covers the default button, a caller's
         * button and a link styled as one.
         *
         * @param {Element} wrapper the element carrying `data-wk-alert-confirm`
         * @param {string}  reasonId id of the region holding the reason text
         */
        syncConfirmControlState(wrapper, reasonId) {
            const control = wrapper?.querySelector?.('button, a[href], [role="button"]');

            if (!control) return;

            // A caller's control may already be described by something of their own, so
            // the reason is ADDED to that rather than replacing it. The baseline is read
            // once and kept on the element — this runs again on every keystroke, and
            // reading the attribute back after the first write would capture our own id
            // as though the caller had put it there. It is a plain expando rather than a
            // data attribute so nothing of ours shows up in the caller's markup, and
            // rather than component state so writing it cannot re-trigger the effect
            // that called us.
            if (control._wkConfirmDescribedBy === undefined) {
                control._wkConfirmDescribedBy = control.getAttribute('aria-describedby') ?? '';
            }

            const base = control._wkConfirmDescribedBy;

            if (this.confirmAllowed) {
                control.removeAttribute('aria-disabled');

                if (base === '') {
                    control.removeAttribute('aria-describedby');
                } else {
                    control.setAttribute('aria-describedby', base);
                }

                return;
            }

            control.setAttribute('aria-disabled', 'true');
            control.setAttribute('aria-describedby', base === '' ? reasonId : `${base} ${reasonId}`);
        },

        init() {
            this.initOverlay();
        },

        destroy() {
            this.destroyOverlay();
        },
    };
}
