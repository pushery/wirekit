/**
 * Data table — the column-visibility menu's disclosure and its anchoring.
 *
 * A small component nested inside the table's own scope, and the last inline
 * `x-data` in the file. It did not parse under Alpine's CSP build (method
 * shorthand, multiple statements, an arrow function in the accompanying
 * `x-init`), so under a strict Content-Security-Policy the whole nested scope
 * failed to build: the menu button toggled nothing.
 *
 * Anchoring goes through `window.wirekitPosition`, which ships in the full
 * bundle but not the core one. Absence is a supported configuration rather than
 * an error — the menu still opens and closes, it just falls back to the CSS
 * placement — so the check stays a silent guard.
 *
 * ⚠️ Lifecycle resources held on `this`: ONE, and this line said NONE until the
 * panel started repairing its own placement. `place()` asks the positioner to put
 * the placement back when something removes it, which registers a MutationObserver
 * — so there is a handle to release, and it is released on close, before every
 * reopen and on destroy.
 *
 * WHY. Everything the positioner writes is inline style, and a framework update
 * patches this panel against its own template, whose `style` attribute carries
 * none of it. The whole attribute is replaced, the placement is gone, and `open`
 * never changed — so nothing asks for a new one. Measured here, menu open, one
 * refresh: `top` went from `158.5px` to empty and stayed empty.
 *
 * ⚠️ `autoReposition` does NOT cover this case, which is worth knowing because it
 * looks like it should. It recomputes on a size change, and this erasure does not
 * resize the panel: the `max-height` it drops was never binding on a 77px menu, so
 * the box stayed 192x77 and no observer fired. That is why the option here is
 * `repairErasure`, which watches the attribute rather than the box.
 */
export default function wirekitDataTableColumnMenu() {
    return {
        open: false,

        // The positioner's teardown handle, held only while the panel is open. Its own docblock
        // puts this duty on the caller: an observer left behind outlives every opening, and this
        // menu is opened and closed all day.
        _stopRepair: null,

        init() {
            // Anchor AFTER the menu has been rendered: it is x-show'd, so at the
            // moment `open` flips it still has no box to measure.
            this.$watch('open', (isOpen) => {
                if (isOpen) {
                    this.$nextTick(() => {
                        this.place();
                        this.focusFirstCheckbox();
                    });

                    return;
                }

                this._stopRepair?.();
                this._stopRepair = null;
                this.restoreFocus();
            });
        },

        destroy() {
            this._stopRepair?.();
            this._stopRepair = null;
        },

        /**
         * Move focus into the panel on open.
         *
         * The panel is a disclosed GROUP of checkboxes, not a menu, so it owns no
         * roving-focus model of its own — the checkboxes are ordinary tab stops and
         * the browser scrolls them into view. What a group cannot do is put the
         * reader anywhere near them: without this, focus stayed on the trigger and
         * the only way into a panel that had just appeared was to tab past
         * everything the trigger sits in front of.
         *
         * Focus is placed here rather than in the template because the CSP build of
         * Alpine parses only a narrow expression grammar, and this whole factory
         * exists because inline statements in this spot silently failed to build.
         */
        focusFirstCheckbox() {
            const panel = this.$refs.colMenu;

            if (! panel) {
                return;
            }

            const first = panel.querySelector('input[type="checkbox"]');

            if (first) {
                first.focus();
            }
        },

        /**
         * Hand focus back to the trigger when the panel closes — but ONLY if it is
         * still inside the panel.
         *
         * Escape and a second click on the trigger both close from within, and
         * leaving focus on a hidden checkbox drops the reader at the top of the
         * document. A click OUTSIDE also closes the panel, and there the user has
         * already chosen where to go; pulling focus back to the trigger would undo
         * their own click.
         */
        restoreFocus() {
            const panel = this.$refs.colMenu;
            const button = this.$refs.colBtn;

            if (! panel || ! button || ! panel.contains(document.activeElement)) {
                return;
            }

            button.focus();
        },

        place() {
            if (typeof window.wirekitPosition !== 'function') {
                return;
            }

            const button = this.$refs.colBtn;
            const menu = this.$refs.colMenu;

            if (! button || ! menu) {
                return;
            }

            // Drop the previous opening's observer before making another one.
            this._stopRepair?.();
            this._stopRepair = null;

            const placement = window.wirekitPosition(button, menu, {
                placement: 'bottom-end',
                offset: 4,
                fitViewport: true,
                repairErasure: true,
            });

            // ⚠️ The global is documented as something a component asks for WITHOUT depending
            // on it, so it may be absent — and by the same reasoning it may be something other
            // than this package's own helper. A stub that returns a non-thenable makes `.then`
            // throw, which is a worse failure than the missing placement it replaces.

            if (! placement || typeof placement.then !== 'function') {
                return;
            }

            placement.then((result) => {
                if (typeof result?.stop !== 'function') {
                    return;
                }

                // Closed while the placement was in flight: the helper awaits frames and a promise,
                // so the panel can be shut before this resolves and the observer would outlive it.
                if (! this.open) {
                    result.stop();

                    return;
                }

                this._stopRepair = result.stop;
            });
        },
    };
}
