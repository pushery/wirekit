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
import { withOpenAlias } from '../utils/open-alias.js';
import { armDialogConfirm } from '../utils/dialog-confirm.js';
import { watchCurrent } from '../utils/watch-current.js';

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
 * @param {boolean} [config.lockScroll=true] - Whether opening locks the page's scroll
 * @param {string} [config.initialFocus] - CSS selector for the control that should
 *   receive focus instead of Cancel
 * @param {string} [config.focusReturnTo] - CSS selector for where focus should land when the
 *   dialog closes after its action, typically because the re-render removed the trigger's row.
 *   A dismissal (Cancel, Escape, the backdrop) returns to a surviving trigger first.
 * @param {string} [config.confirmationPhrase] - Exact string the developer must type
 *   before `alert-dialog.confirm` will fire. Absent, nothing is held back.
 * @param {boolean} [config.confirmShortcut=true] - Whether ⌘↵ / Ctrl+Enter presses the confirm
 *   control while the dialog is open (utils/dialog-confirm.js)
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
        // False for a dialog inside a page region, such as a preview: the page keeps scrolling.
        lockScroll: config.lockScroll !== false,
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

    return withOpenAlias({
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
         * True whenever no phrase was asked for — a dialog without a brake does not
         * hold its action back.
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
         * A Livewire update compares the control with markup that carries neither value and
         * takes both off, while the effect that calls this waits for the next keystroke. The
         * wrapper is watched (_watchConfirmControl()), and this runs again on such a change.
         *
         * @param {Element} wrapper the element carrying `data-wk-alert-confirm`
         * @param {string}  reasonId id of the region holding the reason text
         */
        syncConfirmControlState(wrapper, reasonId) {
            const control = wrapper?.querySelector?.('button, a[href], [role="button"]');

            if (!control) return;

            this._watchConfirmControl(wrapper, reasonId);

            // A caller's control may already be described by something of their own, so the
            // reason is ADDED to that rather than replacing it. Their description is what the
            // control carries but the reason's id, read on every run, so a render that changes
            // it is followed and our own id is never taken for theirs.
            const base = String(control.getAttribute('aria-describedby') ?? '')
                .split(/\s+/)
                .filter((token) => token !== '' && token !== reasonId)
                .join(' ');
            const allowed = this.confirmAllowed;
            const held = base === '' ? reasonId : `${base} ${reasonId}`;
            const describedBy = allowed ? base : held;

            // Only what differs is written: every write is a change the watch hears.
            if (allowed) {
                if (control.hasAttribute('aria-disabled')) {
                    control.removeAttribute('aria-disabled');
                }
            } else if (control.getAttribute('aria-disabled') !== 'true') {
                control.setAttribute('aria-disabled', 'true');
            }

            if (describedBy === '') {
                if (control.hasAttribute('aria-describedby')) {
                    control.removeAttribute('aria-describedby');
                }
            } else if (control.getAttribute('aria-describedby') !== describedBy) {
                control.setAttribute('aria-describedby', describedBy);
            }
        },

        /**
         * Run the sync again when something else changes the control's state: a Livewire update,
         * or a render that put another control in the wrapper. Once per wrapper, kept on the
         * wrapper as a plain expando rather than in component state, so registering it cannot
         * re-trigger the effect that calls the sync.
         *
         * @param {Element} wrapper
         * @param {string}  reasonId
         */
        _watchConfirmControl(wrapper, reasonId) {
            if (wrapper._wkConfirmWatch || typeof MutationObserver !== 'function') return;

            const watch = new MutationObserver(() => this.syncConfirmControlState(wrapper, reasonId));
            watch.observe(wrapper, { subtree: true, childList: true, attributes: true, attributeFilter: ['aria-disabled', 'aria-describedby'] });
            wrapper._wkConfirmWatch = watch;
        },

        /**
         * Close the dialog once the destructive action has actually fired — opt-in.
         *
         * It is not cosmetic, and that is why the option exists. A dialog that stays up
         * after its action holds `aria-modal="true"`, so the `role="status"` the action
         * produces on the page BELOW is outside the modal and is never announced (WCAG
         * 4.1.3). The backdrop also keeps the page unreachable after the work is done.
         *
         * Default off, deliberately: closing is a behavior change for every dialog that
         * already ships, and some confirmations legitimately keep the panel up to show a
         * result inside it.
         *
         * And it closes on the next task rather than inside the event. `wire:click` on
         * the same control is another listener on the same event; closing synchronously
         * tears the panel out from under handlers that have not run yet, and which of them
         * runs first is registration order rather than anything a caller controls. Yielding
         * once lets every same-event handler finish before the DOM changes.
         */
        closeOnConfirm: config.closeOnConfirm === true,

        confirmed() {
            if (! this.closeOnConfirm) return;

            setTimeout(() => this.close(), 0);
        },

        /**
         * ⌘↵ / Ctrl+Enter presses the confirm control while the dialog is open: the control a page
         * marks with `data-wk-dialog-primary`, else the one inside `alert-dialog.confirm`. Focus
         * stays on Cancel, so a plain Enter still approves nothing. A control held back by an
         * unmet phrase is `aria-disabled`, and the shortcut leaves it alone.
         */
        confirmShortcut: config.confirmShortcut !== false,
        _disarmConfirmShortcut: null,

        _syncConfirmShortcut(open) {
            if (this._disarmConfirmShortcut) {
                this._disarmConfirmShortcut();
                this._disarmConfirmShortcut = null;
            }

            if (open && this.confirmShortcut) {
                this._disarmConfirmShortcut = armDialogConfirm(this.$refs.panel, { fallback: '[data-wk-alert-confirm]' });
            }
        },

        /**
         * `closeOnConfirm` with a control the page marks `data-wk-dialog-primary` rather than one
         * inside `alert-dialog.confirm`: its click is the confirmation, and it closes the dialog
         * the same way. A marked control inside the confirm wrapper is left to the wrapper, which
         * calls `confirmed()` itself. A disabled control is never clicked.
         *
         * The panel hears the click and asks then which control is marked, so a marked control a
         * Livewire update put in place of the one at the opening closes the dialog as well. It
         * hears it on the way down, before the control's own handlers, so one that stops the
         * click's way up still closes the dialog; the close waits a task either way.
         */
        _disarmPrimaryClose: null,

        _syncPrimaryClose(open) {
            if (this._disarmPrimaryClose) {
                this._disarmPrimaryClose();
                this._disarmPrimaryClose = null;
            }

            const panel = open && this.closeOnConfirm ? this.$refs.panel : null;

            if (typeof panel?.addEventListener !== 'function') {
                return;
            }

            const onClick = (event) => {
                const marked = panel.querySelector?.('[data-wk-dialog-primary]');

                if (marked && ! marked.closest('[data-wk-alert-confirm]') && marked.contains(event.target)) {
                    this.confirmed();
                }
            };

            panel.addEventListener('click', onClick, true);
            this._disarmPrimaryClose = () => panel.removeEventListener('click', onClick, true);
        },

        init() {
            this.initOverlay();
            watchCurrent(this, 'isOpen', (open) => {
                this._syncConfirmShortcut(open);
                this._syncPrimaryClose(open);
            });
        },

        destroy() {
            this._syncConfirmShortcut(false);
            this._syncPrimaryClose(false);

            const wrapper = this.$refs?.panel?.querySelector?.('[data-wk-alert-confirm]');

            if (wrapper?._wkConfirmWatch) {
                wrapper._wkConfirmWatch.disconnect();
                wrapper._wkConfirmWatch = undefined;
            }

            this.destroyOverlay();
        },
    });
}
