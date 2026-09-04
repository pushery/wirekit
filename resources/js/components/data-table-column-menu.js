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
 * Lifecycle resources held on `this`: NONE. The positioner is called once per
 * open and registers nothing that outlives it.
 */
export default function wirekitDataTableColumnMenu() {
    return {
        open: false,

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

                this.restoreFocus();
            });
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

            if (button && menu) {
                window.wirekitPosition(button, menu, {
                    placement: 'bottom-end',
                    offset: 4,
                    fitViewport: true,
                });
            }
        },
    };
}
