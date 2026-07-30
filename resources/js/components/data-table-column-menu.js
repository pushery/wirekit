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
                    this.$nextTick(() => this.place());
                }
            });
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
