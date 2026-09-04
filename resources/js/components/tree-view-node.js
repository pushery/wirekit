/**
 * Tree-view node — one branch's expanded state.
 *
 * The click handler was two statements: flip the flag, then write
 * `aria-expanded` onto the treeitem by hand. Alpine's CSP build parses one
 * expression, so under a strict Content-Security-Policy a branch could not be
 * opened at all.
 *
 * The ARIA write is the part worth keeping in mind. `aria-expanded` belongs on
 * the element carrying `role="treeitem"`, which is the label ROW — the element a
 * reader actually focuses — while this component's scope is the `<li>` wrapping
 * it. So the handler reaches DOWN to its own row rather than up: the row is the
 * `<li>`'s first `[data-wk-tree-node]` child, and the children's rows sit deeper.
 * It is written imperatively rather than bound because the row is rendered by
 * Blade with a static attribute for the pre-Alpine state, and this keeps the two
 * from fighting over it.
 *
 * ⚠️ The scope is `$root`, not `$el`. The click handler sits ON the row, so inside
 * `toggle()` Alpine binds `$el` to the row itself — and a row is not its own child,
 * so `:scope > [data-wk-tree-node]` matched nothing and the attribute was never
 * written. Measured in chromium: the branch opened on screen while `aria-expanded`
 * stayed `false`, which is the one state a reader goes by. `$root` is the `<li>`
 * regardless of which descendant dispatched the event.
 *
 * @param {Object}  config
 * @param {boolean} [config.expanded]  the branch's initial state
 */
export default function wirekitTreeViewNode(config = {}) {
    return {
        nodeExpanded: config.expanded === true,

        toggle() {
            this.nodeExpanded = ! this.nodeExpanded;

            const item = this.$root.querySelector(':scope > [data-wk-tree-node]');

            // A node rendered without a treeitem row is a composition mistake rather
            // than a runtime condition; the branch still opens, it just does not
            // announce itself.
            if (item) {
                item.setAttribute('aria-expanded', this.nodeExpanded ? 'true' : 'false');
            }
        },
    };
}
