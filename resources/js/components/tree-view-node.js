/**
 * Tree-view node — one branch's expanded state.
 *
 * The click handler was two statements: flip the flag, then write
 * `aria-expanded` onto the treeitem by hand. Alpine's CSP build parses one
 * expression, so under a strict Content-Security-Policy a branch could not be
 * opened at all.
 *
 * The ARIA write is the part worth keeping in mind. `aria-expanded` belongs on
 * the element carrying `role="treeitem"`, but the click lands on the label row
 * INSIDE it — a `:aria-expanded` binding would therefore have to sit on an
 * element that does not own the event, so the handler walks up and sets it. That
 * is also why it is written imperatively rather than bound: the treeitem is
 * rendered by Blade with a static attribute for the pre-Alpine state, and this
 * keeps the two from fighting over it.
 *
 * @param {Object}  config
 * @param {boolean} [config.expanded]  the branch's initial state
 */
export default function wirekitTreeViewNode(config = {}) {
    return {
        nodeExpanded: config.expanded === true,

        toggle() {
            this.nodeExpanded = ! this.nodeExpanded;

            const item = this.$el.closest('[role=treeitem]');

            // A node rendered outside a treeitem is a composition mistake rather
            // than a runtime condition; the branch still opens, it just does not
            // announce itself.
            if (item) {
                item.setAttribute('aria-expanded', this.nodeExpanded ? 'true' : 'false');
            }
        },
    };
}
