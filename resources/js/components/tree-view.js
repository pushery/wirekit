/**
 * WireKit Tree View Alpine Component.
 *
 * Implements WAI-ARIA Tree View keyboard navigation pattern:
 * - Arrow Down/Up: move focus between visible nodes
 * - Arrow Right: expand collapsed node, or move to first child
 * - Arrow Left: collapse expanded node, or move to parent
 * - Home/End: jump to first/last visible node
 * - Enter/Space: select focused node
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/treeview/
 */
export default function wirekitTreeView() {
    return {
        /**
         * Get all visible tree node elements in DOM order.
         * Nodes inside collapsed groups are excluded.
         */
        _getVisibleNodes() {
            return Array.from(this.$el.querySelectorAll('[data-wk-tree-node]')).filter(
                (node) => {
                    // Check if any ancestor <ul role="group"> is hidden (collapsed)
                    let parent = node.closest('ul[role="group"]');
                    while (parent && parent !== this.$el) {
                        if (parent.style.display === 'none' || parent.offsetParent === null) {
                            return false;
                        }
                        parent = parent.parentElement?.closest('ul[role="group"]');
                    }
                    return true;
                }
            );
        },

        /**
         * Get the currently focused node index.
         */
        _getFocusedIndex(nodes) {
            return nodes.indexOf(document.activeElement);
        },

        /**
         * Move focus to the next visible node.
         */
        focusNext() {
            const nodes = this._getVisibleNodes();
            const idx = this._getFocusedIndex(nodes);
            if (idx < nodes.length - 1) {
                nodes[idx + 1].focus();
            }
        },

        /**
         * Move focus to the previous visible node.
         */
        focusPrev() {
            const nodes = this._getVisibleNodes();
            const idx = this._getFocusedIndex(nodes);
            if (idx > 0) {
                nodes[idx - 1].focus();
            }
        },

        /**
         * Arrow Right: expand collapsed branch, or move to first child if already expanded.
         */
        expandOrChild() {
            const focused = document.activeElement;
            if (!focused) return;

            const treeitem = focused.closest('[role="treeitem"]');
            if (!treeitem) return;

            const isExpanded = treeitem.getAttribute('aria-expanded');
            if (isExpanded === 'false') {
                // Expand the node by clicking the label
                focused.click();
            } else if (isExpanded === 'true') {
                // Move focus to first child node
                const childNode = treeitem.querySelector('ul[role="group"] [data-wk-tree-node]');
                if (childNode) childNode.focus();
            }
        },

        /**
         * Arrow Left: collapse expanded branch, or move to parent node.
         */
        collapseOrParent() {
            const focused = document.activeElement;
            if (!focused) return;

            const treeitem = focused.closest('[role="treeitem"]');
            if (!treeitem) return;

            const isExpanded = treeitem.getAttribute('aria-expanded');
            if (isExpanded === 'true') {
                // Collapse the node by clicking the label
                focused.click();
            } else {
                // Move focus to parent treeitem's label
                const parentGroup = treeitem.closest('ul[role="group"]');
                if (parentGroup) {
                    const parentItem = parentGroup.closest('[role="treeitem"]');
                    if (parentItem) {
                        const parentLabel = parentItem.querySelector('[data-wk-tree-node]');
                        if (parentLabel) parentLabel.focus();
                    }
                }
            }
        },

        /**
         * Focus the first visible node.
         */
        focusFirst() {
            const nodes = this._getVisibleNodes();
            if (nodes.length > 0) nodes[0].focus();
        },

        /**
         * Focus the last visible node.
         */
        focusLast() {
            const nodes = this._getVisibleNodes();
            if (nodes.length > 0) nodes[nodes.length - 1].focus();
        },

        /**
         * Select the currently focused node (dispatch custom event).
         */
        selectFocused() {
            const focused = document.activeElement;
            if (!focused) return;

            const treeitem = focused.closest('[role="treeitem"]');
            if (!treeitem) return;

            // If it has children, toggle expansion
            if (treeitem.hasAttribute('aria-expanded')) {
                focused.click();
            }

            // Dispatch selection event for developers
            this.$dispatch('tree-node-select', {
                label: focused.querySelector('span.truncate')?.textContent?.trim(),
            });
        },
    };
}
