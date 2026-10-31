/**
 * Tree-view node — one branch's expanded state.
 *
 * The click handler flips the flag, and nothing else: `aria-expanded` on the
 * treeitem row is bound to `nodeExpanded` in the node's view. Alpine's CSP
 * build parses one expression, which is why the toggle is a method at all.
 *
 * The attribute is bound rather than written by hand because a tree can stand
 * inside a Livewire component. The morph of each update compares the live row
 * with a clone of the server's markup and puts back every attribute the clone
 * holds differently. The server renders `aria-expanded` from the `expanded`
 * prop, which knows nothing of the reader's clicks, so a value a script had
 * written was replaced on the next update: a branch the reader closed was
 * announced as expanded over a hidden group. A binding is evaluated in the
 * clone against this node's live data, and the morph leaves it as it is.
 *
 * `aria-expanded` belongs on the element carrying `role="treeitem"`, the label
 * ROW a reader focuses, while this component's scope is the `<li>` around it;
 * the row is inside that scope, so the binding reads this node's flag.
 *
 * @param {Object}  config
 * @param {boolean} [config.expanded]  the branch's initial state
 */
export default function wirekitTreeViewNode(config = {}) {
    return {
        nodeExpanded: config.expanded === true,

        toggle() {
            this.nodeExpanded = ! this.nodeExpanded;
        },
    };
}
