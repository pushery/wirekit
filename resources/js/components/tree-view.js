/**
 * WireKit Tree View Alpine Component.
 *
 * Implements WAI-ARIA Tree View keyboard navigation pattern:
 * - Arrow Down/Up: move focus between visible nodes
 * - Arrow Right: expand collapsed node, or move to first child
 * - Arrow Left: collapse expanded node, or move to parent
 * - Home/End: jump to first/last visible node
 * - Enter/Space: select focused node
 * - A printable character: type-ahead to the next visible node starting with it
 *
 * Focus management is the APG roving-tabindex model: exactly ONE node in the tree is a
 * tab stop, and it follows focus. Every node renders `tabindex="-1"`, so without the seed
 * below the tree has no tab stop at all — Tab walks straight past it and everything in
 * this file is reachable only by clicking a row first.
 *
 * Lifecycle resources held on `this`:
 *   - _typeAheadTimer (setTimeout) — the handle that forgets what was typed. Cleared in
 *     destroy(), and null-guarded where it is cleared, because a timeout that fires into
 *     a torn-down component is the null-callback class this codebase has been bitten by.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/treeview/
 */
import { typeAheadIndex } from '../utils/roving-focus.js';

export default function wirekitTreeView() {
    return {
        // Type-ahead state. The buffer is what the reader has typed so far; the timer is
        // what forgets it.
        _typeAheadBuffer: '',
        _typeAheadTimer: null,
        /**
         * Give the tree its single tab stop.
         *
         * Deferred by a tick on purpose: a branch that ships collapsed is hidden by the
         * child node's own `x-show`, and children initialize AFTER this container. Reading
         * visibility before that tick would hand the tab stop to a row that is about to
         * disappear, which is a tab stop the reader can neither see nor leave sensibly.
         */
        init() {
            this.$nextTick(() => this.seedTabStop());
        },

        /**
         * Release the forget-timer.
         *
         * Alpine tears a component down on a Livewire morph that removes the host, on an
         * `x-if` flipping, and on SPA navigation. A pending timeout would then fire into a
         * scope nobody is reading any more.
         */
        destroy() {
            if (this._typeAheadTimer) {
                clearTimeout(this._typeAheadTimer);
                this._typeAheadTimer = null;
            }
        },

        /**
         * Pick the entry point and make it the only tab stop.
         *
         * A node the caller marked `selected` renders `tabindex="0"` and is preferred —
         * that is where a reader expects to land. It is honored only while it is visible:
         * a selected node inside a collapsed branch would be a tab stop pointing at
         * nothing on screen. Several selected nodes collapse to the first of them, since
         * a second tab stop puts the tree back in the state this method exists to end.
         */
        seedTabStop() {
            const visible = this._getVisibleNodes();
            if (visible.length === 0) return;

            const marked = visible.find((node) => node.getAttribute('tabindex') === '0');

            this._setTabStop(marked || visible[0]);
        },

        /**
         * Move the tab stop to `node`, taking it off every other row.
         */
        _setTabStop(node) {
            this.$el.querySelectorAll('[data-wk-tree-node]').forEach((other) => {
                other.setAttribute('tabindex', other === node ? '0' : '-1');
            });
        },

        /**
         * Follow focus with the tab stop.
         *
         * Bound to `focusin` on the tree so it covers every route focus can take — the
         * arrow methods below, a mouse click on a row, and a developer's own `.focus()`.
         * Leaving the 0 on the row the reader started at means the next Tab back into the
         * tree lands somewhere other than where they left it.
         */
        rove() {
            const node = document.activeElement?.closest('[data-wk-tree-node]');

            if (node && this.$el.contains(node)) {
                this._setTabStop(node);
            }
        },

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
         * The child group a branch row owns.
         *
         * Resolved through the DOM rather than through the row's `aria-owns` id: the group
         * is a block beneath the row inside the same `<li>`, which is a relationship the
         * markup guarantees, while the id is only the a11y tree's way of saying the same
         * thing. Reading the DOM keeps this working even where two independently-morphing
         * regions hand out the same counted id.
         */
        _ownedGroup(item) {
            return item.parentElement?.querySelector(':scope > ul[role="group"]') || null;
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
                const childNode = this._ownedGroup(treeitem)?.querySelector('[data-wk-tree-node]');
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
                // Move focus to the parent branch's own row. The group holding this node
                // sits beside that row inside the parent `<li>`, so the step out is one
                // level up and then back down to the row — never `closest('[role=treeitem]')`,
                // which would find nothing now that the row rather than the `<li>` carries
                // the role.
                const parentGroup = treeitem.closest('ul[role="group"]');
                const parentRow = parentGroup?.parentElement?.querySelector(':scope > [data-wk-tree-node]');

                if (parentRow) parentRow.focus();
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
         * Move focus to the next visible node whose label starts with what has been typed.
         *
         * The tree pattern names type-ahead, and on a tree it does more than on a menu: the
         * reader cannot see how deep the structure runs, so stepping to a row by name is
         * often the only cheap way there. The docs page has listed the key row from the
         * start; nothing implemented it.
         *
         * Scoped to the VISIBLE rows, which is the same set the arrows walk — a row inside
         * a collapsed branch is not somewhere focus may land.
         *
         * The arithmetic lives in `typeAheadIndex` — pure, shared and unit-tested — so this
         * is only the buffer and its timer. Half a second to forget: long enough to finish
         * a short word, short enough that a pause reads as a fresh search.
         *
         * A space never gets here. The container claims it for activation, so the buffer
         * holds no spaces and a two-word label is reached by its first word — the same
         * trade the menu pattern names.
         */
        typeAhead(event) {
            // Single printable characters only. A modifier means the reader is reaching for
            // a browser or OS shortcut, and swallowing those is how a widget stops being a
            // good citizen of the page.
            if (!event || event.key === ' ' || event.key?.length !== 1
                || event.ctrlKey || event.metaKey || event.altKey) {
                return;
            }

            event.preventDefault();

            this._typeAheadBuffer += event.key;

            if (this._typeAheadTimer) {
                clearTimeout(this._typeAheadTimer);
            }

            this._typeAheadTimer = setTimeout(() => {
                this._typeAheadBuffer = '';
                this._typeAheadTimer = null;
            }, 500);

            const nodes = this._getVisibleNodes();
            const labels = nodes.map((node) => node.textContent || '');
            const index = typeAheadIndex(labels, this._typeAheadBuffer, this._getFocusedIndex(nodes));

            if (index >= 0) nodes[index]?.focus();
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
