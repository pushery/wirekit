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
export default function wirekitFab(config = {}) {
    return {
        open: false,
        _onDocumentClick: null,

        init() {
            // Close when the reader moves on. Bound on document because the click
            // that dismisses a menu is by definition outside it.
            //
            // $root, not $el — see _actions() below for why that distinction is
            // not pedantry.
            this._onDocumentClick = (event) => {
                if (this.open && !this.$root.contains(event.target)) {
                    this.open = false;
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
            this.open ? this.close() : this.show();
        },

        show() {
            this.open = true;

            // Focus the first action once the browser will actually accept it.
            //
            // Neither $nextTick nor a single frame is reliable here, and the
            // reason is worth writing down. focus() on an element whose ancestor
            // is still display:none does nothing at all — silently, no error. The
            // panel becomes visible when Alpine's x-show effect flushes, and that
            // is on Alpine's schedule, not ours: measured, the focus call fails at
            // the microtask, and whether it succeeds one frame later depends on
            // what else happened to trigger a flush first. A fix that wins that
            // race most of the time is not a fix — it is a component that
            // sometimes traps keyboard users on the trigger.
            //
            // So ask the only question that actually matters — does this element
            // have a layout box yet — and wait until it does.
            //
            // Driven by requestAnimationFrame rather than $nextTick, deliberately.
            // Measured: on the click path the $nextTick callback did not run at
            // all, while the panel became visible at ~16ms — the menu opened and
            // focus simply never moved. rAF answers to the browser's frame clock
            // rather than to Alpine's flush schedule, so it cannot be skipped by
            // one.
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

        close({ restoreFocus = true } = {}) {
            const wasOpen = this.open;
            this.open = false;

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
            // $root, NOT $el. This is the whole bug that made the menu unreachable
            // by keyboard, and it is worth being precise about: Alpine's $el means
            // "the element the current expression is running on", not "the
            // component root". The trigger's own x-on:click is what starts this
            // chain, so inside it $el is the BUTTON — and searching a button for
            // the menu's actions finds nothing, every time, silently.
            //
            // Measured, not deduced: $el.tagName came back "BUTTON" while the same
            // query against the document found all three actions. It also explains
            // why calling show() from outside the component appeared to work —
            // there is no expression element there, so $el falls back to the root.
            return Array.from(this.$root.querySelectorAll('[data-wk-fab-action]'));
        },

        /**
         * Walk the actions. The arrow keys are what make this a menu rather than
         * a pile of buttons that happen to be stacked.
         */
        move(direction) {
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
    };
}
