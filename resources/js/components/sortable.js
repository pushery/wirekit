/**
 * Reorder a list — by pointer, and by keyboard, because one of those is not
 * optional.
 *
 * `data-sortable`, `data-sortable-column` and `data-sortable-items` shipped as
 * markers with nothing behind them. That is worse than an absent feature: a
 * missing capability costs a decision, an announced one that does nothing costs
 * a diagnosis, and the diagnosis starts in the developer's own code because the
 * component is visibly offering the thing.
 *
 * TWO WAYS IN, and the second is the reason this is not thirty lines. A pure
 * drag interaction cannot be operated without a pointer, so shipping only that
 * would turn every `sortable` into a barrier — the component would go from
 * doing nothing to excluding people, which is not an improvement. The keyboard
 * path is therefore part of the feature rather than an enhancement of it:
 * Space or Enter lifts an item, the arrows move it, Space or Enter drops it,
 * Escape puts it back.
 *
 * ONE EVENT, THE WHOLE ORDER. The new sequence is emitted once, when the move
 * finishes — not a swap per crossing. A stream of swaps makes the final state
 * depend on the order the responses arrive in, which is network timing: wrong
 * whenever two are in flight, and impossible to test deterministically. A drag
 * cannot promise the order of its own intermediate steps, so it does not report
 * them.
 *
 * IDENTITY COMES FROM THE DOM. Each item names itself with `data-sortable-id`;
 * without one it falls back to its index at mount, which is honest but only
 * usable for a list the server can address positionally. The event carries ids,
 * never elements.
 *
 * @param {Object} config
 * @param {string} config.itemSelector  which children are sortable
 */
export default function wirekitSortable(config = {}) {
    return {
        /** The item currently lifted by keyboard, if any. */
        _lifted: null,

        /** Where the lifted item started, so Escape can put it back exactly. */
        _liftedFrom: null,

        /** The item being dragged by pointer. */
        _dragging: null,

        // The DIRECT CHILDREN of the list, unless the call site says otherwise.
        //
        // A marker on each item would be the tidier API and it would make the
        // feature opt-in twice: the developer already said `sortable`, and the
        // things inside a column are the cards. `:scope > *` is used for
        // SELECTION only — it cannot be handed to `closest()`, which is why the
        // event path walks up to the root instead of matching a selector.
        _itemSelector: config.itemSelector || ':scope > *',

        init() {
            this._wire();

            // Items arrive and leave — a card is added, a filter is applied, a
            // Livewire morph replaces the lot. Re-wiring on mutation is what
            // keeps a list sortable after its first render; without it the
            // feature works exactly once, which is the kind of defect that gets
            // reported as "sometimes".
            this._observer = new MutationObserver(() => this._wire());
            this._observer.observe(this.$root, { childList: true, subtree: true });
        },

        destroy() {
            this._observer?.disconnect();
        },

        /** The sortable children, in document order. */
        _items() {
            return Array.from(this.$root.querySelectorAll(this._itemSelector));
        },

        /**
         * The sortable item an event happened inside, or null.
         *
         * Walks up rather than matching, because the default selector is
         * `:scope > *` and `closest(':scope > *')` is not a thing — it throws on
         * some engines and matches nothing on others, which would have made this
         * silently inert exactly like the markers it replaces.
         */
        _itemFrom(target) {
            const items = this._items();
            let node = target;

            while (node && node !== this.$root) {
                if (items.includes(node)) {
                    return node;
                }

                node = node.parentElement;
            }

            return null;
        },

        /**
         * Make every item operable, idempotently.
         *
         * Re-run on every mutation, so it must not accumulate listeners or
         * re-announce anything. The `draggable` attribute and the tabindex are
         * both set rather than toggled, and the handlers are delegated to the
         * root — one listener for the list, however long it gets.
         */
        _wire() {
            for (const [index, item] of this._items().entries()) {
                item.setAttribute('draggable', 'true');

                // Reachable by keyboard at all. A list you can only reorder with
                // a pointer is one that some people simply cannot reorder.
                if (! item.hasAttribute('tabindex')) {
                    item.setAttribute('tabindex', '0');
                }

                if (! item.hasAttribute('data-sortable-id')) {
                    item.setAttribute('data-sortable-id', String(index));
                }

                // The state a screen reader needs: grabbed, or available to be.
                // `aria-grabbed` is deprecated in ARIA 1.1 with no replacement
                // that browsers implement, so the visible state is carried by a
                // data attribute the stylesheet can reach and the announcement
                // does the rest.
                if (! item.hasAttribute('aria-roledescription')) {
                    item.setAttribute('aria-roledescription', 'Sortable item');
                }
            }
        },

        /** The ids, in the order they now appear. */
        _order() {
            return this._items().map((el) => el.getAttribute('data-sortable-id'));
        },

        /**
         * Say what happened, once, to whoever is listening.
         *
         * Bubbles, so a Livewire component can listen on any ancestor:
         * `wire:wirekit:sortable:reordered="reorder($event.detail.order)"`.
         */
        _announceOrder(id, from, to) {
            this.$root.dispatchEvent(new CustomEvent('wirekit:sortable:reordered', {
                detail: { order: this._order(), id, from, to },
                bubbles: true,
            }));
        },

        /** Move an element to a new index among its siblings. */
        _moveTo(item, index) {
            const items = this._items();
            const bounded = Math.max(0, Math.min(index, items.length - 1));
            const target = items[bounded];

            if (! target || target === item) {
                return false;
            }

            const forward = items.indexOf(item) < bounded;

            target.parentNode.insertBefore(item, forward ? target.nextSibling : target);

            return true;
        },

        // ─── Pointer ──────────────────────────────────────────────────────

        dragstart(event) {
            const item = this._itemFrom(event.target);

            if (! item) {
                return;
            }

            this._dragging = item;
            this._dragFrom = this._items().indexOf(item);
            item.setAttribute('data-sortable-dragging', 'true');

            // Firefox refuses to start a drag at all without data on the
            // transfer object, and says nothing about why.
            event.dataTransfer?.setData('text/plain', item.getAttribute('data-sortable-id') ?? '');
        },

        dragover(event) {
            if (! this._dragging) {
                return;
            }

            // Without this the drop is refused by the browser and the whole
            // interaction ends in a snap-back with no event.
            event.preventDefault();

            const over = this._itemFrom(event.target);

            if (! over || over === this._dragging) {
                return;
            }

            // Halfway is the commit point — using the boundary instead makes the
            // list flicker between two orders while the pointer sits still.
            const box = over.getBoundingClientRect();
            const after = event.clientY > box.top + box.height / 2;

            over.parentNode.insertBefore(this._dragging, after ? over.nextSibling : over);
        },

        dragend() {
            if (! this._dragging) {
                return;
            }

            const item = this._dragging;
            const to = this._items().indexOf(item);

            item.removeAttribute('data-sortable-dragging');
            this._dragging = null;

            if (to !== this._dragFrom) {
                this._announceOrder(item.getAttribute('data-sortable-id'), this._dragFrom, to);
            }
        },

        // ─── Keyboard ─────────────────────────────────────────────────────

        keydown(event) {
            const item = this._itemFrom(event.target);

            if (! item) {
                return;
            }

            const key = event.key;

            if (key === ' ' || key === 'Enter') {
                event.preventDefault();
                this._lifted === item ? this._drop(item) : this._lift(item);

                return;
            }

            if (key === 'Escape' && this._lifted === item) {
                event.preventDefault();
                this._moveTo(item, this._liftedFrom);
                this._release(item);

                return;
            }

            if (this._lifted !== item) {
                return;
            }

            const delta = (key === 'ArrowDown' || key === 'ArrowRight') ? 1
                : (key === 'ArrowUp' || key === 'ArrowLeft') ? -1
                    : 0;

            if (delta === 0) {
                return;
            }

            event.preventDefault();
            this._moveTo(item, this._items().indexOf(item) + delta);

            // Focus follows the element, which the move did not disturb — but a
            // browser can drop focus when a focused node is re-inserted, and a
            // list that loses focus mid-reorder is unusable by keyboard.
            item.focus();
        },

        _lift(item) {
            this._lifted = item;
            this._liftedFrom = this._items().indexOf(item);
            item.setAttribute('data-sortable-lifted', 'true');
        },

        _drop(item) {
            const to = this._items().indexOf(item);
            const from = this._liftedFrom;

            this._release(item);

            if (to !== from) {
                this._announceOrder(item.getAttribute('data-sortable-id'), from, to);
            }
        },

        _release(item) {
            item.removeAttribute('data-sortable-lifted');
            this._lifted = null;
            this._liftedFrom = null;
        },
    };
}
