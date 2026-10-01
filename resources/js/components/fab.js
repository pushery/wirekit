import { withOpenAlias } from '../utils/open-alias.js';

/**
 * WireKit FAB (speed dial) Alpine Component.
 *
 * A floating trigger that fans out into secondary actions.
 *
 * The interesting part is not the fan — it is what happens to focus. A menu that
 * opens and leaves focus on the trigger is a menu a keyboard user cannot reach;
 * one that traps focus is a menu they cannot leave. This does neither: it moves
 * focus to the first action on open, walks the actions with the arrow keys, and
 * hands focus back to the trigger on Escape, which is where the reader was.
 */
export default function wirekitFab() {
    return withOpenAlias({

        /**
         * Close the panel once focus has left it entirely.
         *
         * A keyboard user could tab past an open panel and leave it hanging over the page
         * behind them — the panel has no focus trap, deliberately, because its actions are
         * ordinary controls rather than a menu.
         *
         * `relatedTarget` is where focus is GOING. It is null when focus leaves the document
         * altogether (a window blur, or a click on browser chrome), and that must NOT close
         * the panel: coming back to the tab would find it gone.
         */
        closeIfFocusLeft(event) {
            const next = event?.relatedTarget;

            if (!next) {
                return;
            }

            const root = this.$refs?.actions ?? event?.currentTarget;

            if (root && typeof root.contains === 'function' && !root.contains(next)) {
                this.isOpen = false;
            }
        },
        isOpen: false,
        _onDocumentClick: null,

        init() {
            // Close when the reader moves on. Bound on document because the click
            // that dismisses a menu is by definition outside it.
            //
            // $root, not $el — see _actions() below for why that distinction is
            // not pedantry.
            this._onDocumentClick = (event) => {
                if (this.isOpen && !this.$root.contains(event.target)) {
                    this.isOpen = false;
                }
            };

            document.addEventListener('click', this._onDocumentClick);
        },

        destroy() {
            // The listener is on document, so it outlives this element by a very
            // long way. Left behind, it keeps testing `this.$el.contains()` on a
            // detached node for every click on the page, forever.
            if (this._onDocumentClick) {
                document.removeEventListener('click', this._onDocumentClick);
                this._onDocumentClick = null;
            }
        },

        toggle() {
            this.isOpen ? this.close() : this.show();
        },

        show() {
            this.isOpen = true;

            // Focus the first action once the browser will actually accept it.
            //
            // Neither $nextTick nor a single frame is reliable here. focus() on an
            // element whose ancestor is still display:none does nothing at all,
            // silently. The panel becomes visible when Alpine's x-show effect
            // flushes, and that is on Alpine's schedule, not ours: a focus call at
            // the microtask fails, and one a frame later succeeds only if something
            // else has triggered a flush first. Winning that race most of the time
            // would still leave keyboard users stranded on the trigger sometimes.
            //
            // So ask the only question that actually matters — does this element
            // have a layout box yet — and wait until it does.
            //
            // Driven by requestAnimationFrame rather than $nextTick, deliberately:
            // on the click path a $nextTick callback can be skipped altogether.
            // rAF answers to the browser's frame clock rather than to Alpine's
            // flush schedule, so it cannot be skipped by one.
            this._focusFirstAction();
        },

        /**
         * Focus the first action as soon as it is really rendered, retrying across
         * frames. Capped: if the menu never becomes visible (a FAB inside a hidden
         * container, say), give up rather than spin for the life of the page.
         */
        _focusFirstAction(attempt = 0) {
            const first = this._actions()[0];

            // The actions can be absent on the very first pass: x-show has not
            // rendered the panel yet. Keep waiting rather than bailing.
            if (!first) {
                if (attempt < 10) {
                    requestAnimationFrame(() => this._focusFirstAction(attempt + 1));
                }

                return;
            }

            // getClientRects() over offsetParent: offsetParent is also null for a
            // position:fixed element, so it would call a perfectly visible FAB
            // hidden and never focus it.
            if (first.getClientRects().length === 0) {
                if (attempt < 10) {
                    requestAnimationFrame(() => this._focusFirstAction(attempt + 1));
                }

                return;
            }

            first.focus();
        },

        /**
         * Escape closes the open menu, and that press is spent: it is marked so that a layer
         * further out leaves it alone. With the menu closed it is not this component's, and it
         * goes on unmarked.
         *
         * @param {KeyboardEvent} event
         */
        escapeMenu(event) {
            if (! this.isOpen) return;

            event?.preventDefault();
            this.close();
        },

        close({ restoreFocus = true } = {}) {
            const wasOpen = this.isOpen;
            this.isOpen = false;

            // Give focus back to the trigger, but only if it was inside the menu
            // we just closed. Yanking focus from wherever the reader happens to
            // be — because a stray Escape reached us — is worse than leaving it.
            //
            // $root again: this runs from the component's own keydown handler, so
            // $el would be whichever element the key landed on.
            if (wasOpen && restoreFocus && this.$root.contains(document.activeElement)) {
                this.$refs.trigger?.focus();
            }
        },

        _actions() {
            // $root, not $el. Alpine's $el means "the element the current
            // expression is running on", not "the component root". The trigger's
            // own x-on:click is what starts this chain, so inside it $el is the
            // button, and searching a button for the menu's actions finds nothing,
            // silently, which would leave the menu unreachable by keyboard. Called
            // from outside the component there is no expression element, and $el
            // falls back to the root; $root is right on both paths.
            return Array.from(this.$root.querySelectorAll('[data-wk-fab-action]'));
        },

        /**
         * Walk the actions. The arrow keys are what make this a menu rather than
         * a pile of buttons that happen to be stacked.
         */
        move(direction, event) {
            // The guard and the cancellation are the same decision, so they live in one
            // place. Split between `.prevent` on the binding and `open &&` in the expression,
            // the default would be canceled unconditionally while only the movement is gated,
            // and a closed FAB would eat ArrowUp and ArrowDown: it is `fixed z-40`, so a
            // keyboard reader tabs to it on any page that renders one and could not scroll.
            //
            // Reading `this.open` here rather than in the template also keeps the binding
            // inside Alpine's CSP grammar: the obvious one-line fix,
            // `open && (move(1), $event.preventDefault())`, is a sequence expression, which the
            // CSP build does not parse: the key would log `Alpine Expression Error` and do nothing.
            if (! this.isOpen) {
                return;
            }

            event?.preventDefault?.();

            const actions = this._actions();
            if (actions.length === 0) return;

            const current = actions.indexOf(document.activeElement);

            // From the trigger (index -1), Up enters at the end and Down at the
            // start — the fan grows upward, so "up" should land on the nearest
            // action, not wrap all the way around.
            const next = current === -1
                ? (direction === 1 ? 0 : actions.length - 1)
                : (current + direction + actions.length) % actions.length;

            actions[next].focus();
        },
    });
}
