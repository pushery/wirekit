/**
 * The pointer drag in progress, if any.
 *
 * ⚠️ MODULE STATE, NOT INSTANCE STATE, because a drag can end in a list that never
 * started it. `dragend` fires on the dragged node and bubbles through the list the
 * node sits in AT THAT MOMENT — after a move between columns that is the target
 * column, whose instance knew nothing about the drag — and the column it left would
 * keep pointing at a card it no longer holds. One pointer drags one thing at a time,
 * so one slot per page is enough.
 *
 * @type {{ item: Element, list: Element, index: number } | null}
 */
let activeDrag = null;

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
 * THE KEYBOARD PATH HAS TO SPEAK. Lifting an item, moving it and dropping it
 * are three actions whose whole outcome is a position, and a position that only
 * exists as pixels reaches nobody using a screen reader: the item was grabbed,
 * three arrow presses happened, and every one of them was silent. WCAG 4.1.3
 * asks for the outcome of an action to be reported without focus moving, which
 * is what the live region below is. It says the position rather than naming the
 * item, because focus is already on the item and the reader has just heard it —
 * repeating a whole card back on every arrow press is noise, not information.
 *
 * THE SENTENCES ARE TEMPLATES FROM THE SERVER. ":position of :total" is not the
 * word order every language uses, so the numbers are substituted after the
 * translation rather than before it — the same shape carousel uses for its slide
 * announcement. The English fallbacks here are for a developer who mounts the
 * factory by hand; the component passes the catalog string.
 *
 * BETWEEN LISTS, ONLY WHERE A BOARD ASKS FOR IT. Inside an element marked
 * `data-sortable-connected` — `<x-wirekit::kanban sortable cross-column>` — a card
 * can leave its list for another one on the same board: dragged onto it, or moved
 * with ArrowLeft/ArrowRight while lifted, Up/Down still moving it within. Outside
 * such a board the arrows keep the meaning they always had, so a list that did not
 * ask for this behaves exactly as before. A move between lists reports once, as
 * `wirekit:sortable:moved` with both lists' new order; a move within one list still
 * reports `wirekit:sortable:reordered`.
 *
 * @param {Object} config
 * @param {string} config.itemSelector  which children are sortable
 * @param {string} [config.roleDescription]  what one item is called, translated
 * @param {Object} [config.messages]  { grabbed, grabbedAcross, moved, movedToColumn,
 *                                    dropped, canceled } — already translated, with
 *                                    `:position`, `:total` and `:column` placeholders
 */
export default function wirekitSortable(config = {}) {
    return {
        /**
         * The live-region sentence. Exposed as state so a host that renders its
         * own `x-text` region gets it too, and written into the region below
         * either way.
         */
        announcement: '',

        /**
         * Fallback wording, used verbatim when the call site passes none.
         *
         * A blank catalog entry — which happens while a language is being
         * translated — falls back here rather than announcing nothing, because
         * an empty live region is indistinguishable from a list that never moved.
         */
        _messages: {
            grabbed: config.messages?.grabbed || 'Grabbed. Position :position of :total. Use the arrow keys to move it.',
            grabbedAcross: config.messages?.grabbedAcross || 'Grabbed. Position :position of :total. Use up and down to move it, left and right to change the column.',
            moved: config.messages?.moved || 'Position :position of :total.',
            movedToColumn: config.messages?.movedToColumn || 'Moved to :column. Position :position of :total.',
            dropped: config.messages?.dropped || 'Dropped at position :position of :total.',
            canceled: config.messages?.canceled || 'Reorder canceled. Back at position :position of :total.',
        },

        /** What one item is called, translated by the call site. */
        _roleDescription: config.roleDescription || 'Sortable item',

        /** The node the sentences are written into. Resolved once, at init. */
        _announcer: null,

        /** The item currently lifted by keyboard, if any. */
        _lifted: null,

        /** Where the lifted item started, so Escape can put it back exactly. */
        _liftedFrom: null,

        /**
         * The list and index a lifted card started from, which survives a move into another
         * list: Escape returns the card THERE, and the drop reports a move from THERE. Without
         * it the list a card was handed to would know only where the card is, and would put
         * it back at a position in the wrong column.
         *
         * @type {{ list: Element, index: number } | null}
         */
        _liftOrigin: null,

        /** The `receive` listener, kept so `destroy()` can take it off again. */
        _receiver: null,

        // The pointer drag is not here: it lives in the module-level `activeDrag`, because a
        // drag can end in a list that never started it.

        // The DIRECT CHILDREN of the list, unless the call site says otherwise.
        //
        // A marker on each item would be the tidier API and it would make the
        // feature opt-in twice: the developer already said `sortable`, and the
        // things inside a column are the cards. `:scope > *` is used for
        // SELECTION only — it cannot be handed to `closest()`, which is why the
        // event path walks up to the root instead of matching a selector.
        _itemSelector: config.itemSelector || ':scope > *',

        init() {
            this._announcer = this._resolveAnnouncer();
            this._wire();

            // Items arrive and leave — a card is added, a filter is applied, a
            // Livewire morph replaces the lot. Re-wiring on mutation is what
            // keeps a list sortable after its first render; without it the
            // feature works exactly once, which is the kind of defect that gets
            // reported as "sometimes".
            this._observer = new MutationObserver(() => this._wire());
            this._observer.observe(this.$root, { childList: true, subtree: true });

            // A card handed over by another list on a connected board arrives through this
            // event, see `_handOver()`. Registered on every list: a list cannot tell whether a
            // board around it connects, and a listener nobody triggers costs nothing.
            this._receiver = (event) => this._receive(event.detail);
            this.$root.addEventListener?.('wirekit:sortable:receive', this._receiver);
        },

        destroy() {
            this._observer?.disconnect();
            this.$root.removeEventListener?.('wirekit:sortable:receive', this._receiver);

            // Only the one this factory made: a region the call site rendered is
            // the call site's to remove, and taking it away here would delete
            // markup that Alpine did not create.
            if (this._announcer && this._announcer.hasAttribute('data-wk-sortable-owned')) {
                this._announcer.remove();
            }

            this._announcer = null;
        },

        /**
         * The sortable children, in document order.
         *
         * The live region is a child of the same root, and the default selector
         * is "every direct child" — so without this filter the region would be
         * offered as something to reorder, tab to and drop cards onto.
         */
        _items() {
            return this._itemsOf(this.$root);
        },

        /** The sortable children of ANY list on the board — this one, or one a card came from. */
        _itemsOf(list) {
            return Array.from(list.querySelectorAll(this._itemSelector))
                .filter((el) => ! el.hasAttribute('data-wk-sortable-announcer'));
        },

        /**
         * The connected board this list sits on, or null.
         *
         * `data-sortable-connected` is opt-in — `<x-wirekit::kanban sortable cross-column>` —
         * and everything that lets a card leave its list asks here first. Without the marker a
         * list behaves exactly as it did before cards could leave at all.
         */
        _board() {
            return this.$root.closest?.('[data-sortable-connected]') ?? null;
        },

        /** Every sortable list on the board, in reading order — the order ArrowLeft/Right walk. */
        _lists() {
            const board = this._board();

            return board ? Array.from(board.querySelectorAll('[data-sortable-items]')) : [this.$root];
        },

        /**
         * What the APPLICATION calls a list: its column's `column-id`, or its position among the
         * board's lists when it has none — the same honesty rule as a card without an id.
         */
        _column(list = this.$root) {
            const named = list.closest?.('[data-sortable-column]')?.getAttribute('data-sortable-column');

            return named || String(this._lists().indexOf(list));
        },

        /**
         * What a READER calls a list: its accessible name. The column body carries its column's
         * label as `aria-label`; an unnamed one falls back to the column id, which beats
         * announcing a move to nowhere.
         */
        _columnName(list = this.$root) {
            return list.getAttribute?.('aria-label') || this._column(list);
        },

        /**
         * The node the announcements are written into.
         *
         * A live region has to EXIST before the text arrives — one that appears
         * together with its first sentence is a new node, and nothing is spoken
         * at all. So it is created at init, empty, and only ever written to.
         *
         * The call site may supply its own by marking it, which is how a host
         * that wants the region somewhere specific keeps ONE of them; otherwise
         * this factory owns it, because a list mounted with `x-data` alone has
         * no markup of its own to carry it.
         *
         * The hiding is inline rather than the `sr-only` utility class: this node
         * is created in JavaScript, so the class never passes under the CSS
         * build's scanner and may simply not exist in the application's
         * stylesheet — which would put the sentence on screen.
         */
        _resolveAnnouncer() {
            const existing = this.$root.querySelector('[data-wk-sortable-announcer]');

            if (existing) {
                return existing;
            }

            const node = document.createElement('div');

            node.setAttribute('data-wk-sortable-announcer', '');
            node.setAttribute('data-wk-sortable-owned', '');
            node.setAttribute('aria-live', 'polite');
            node.setAttribute('aria-atomic', 'true');
            node.style.cssText = 'position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip-path:inset(50%);white-space:nowrap;border:0';

            this.$root.appendChild(node);

            return node;
        },

        /**
         * Say where the item is now.
         *
         * `position` is 1-based, because it is read by a person rather than used
         * as an index.
         */
        _say(template, position, total, column = '') {
            // The column name goes in LAST: it is the one value that comes from the page, and a
            // label that happened to contain ":total" would otherwise be rewritten by the step
            // meant for the number.
            this.announcement = template
                .replace(':position', String(position))
                .replace(':total', String(total))
                .replace(':column', column);

            // A morph can replace the subtree this region sits in, and a
            // detached node speaks to nobody — so the reference is checked
            // rather than trusted, and re-made when it has gone.
            if (! this._announcer || ! this._announcer.isConnected) {
                this._announcer = this._resolveAnnouncer();
            }

            this._announcer.textContent = this.announcement;
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
                //
                // The wording comes from the call site: it is read aloud, so an
                // English literal here would be the one thing about this item a
                // German page could not translate.
                // ⚠️ THE ROLE COMES FIRST, AND WITHOUT IT THE LINE BELOW IS A
                // VIOLATION RATHER THAN AN ANNOUNCEMENT. `aria-roledescription`
                // renames a role; on an element that has none — a plain `<div>`,
                // which is what `<x-wirekit::card>` renders and what the kanban
                // blueprint drops in here — the implicit role is `generic`, where
                // ARIA prohibits the attribute outright. Measured on the shipped
                // kanban preview before this line existed: axe reported
                // `aria-roledescription` on 16 nodes, and a bare control div took
                // it to 17, so the sixteen were ours.
                //
                // `group` rather than `listitem`: the container this runs inside is
                // already a labeled `region` (the column body, whose landmark name
                // is what keeps six columns apart in the rotor), so `listitem`
                // children would trade this violation for `aria-required-parent`.
                // `group` is non-generic, needs no particular parent, and is a fair
                // description of a card that holds several related fields.
                //
                // Only when the element brought none: a caller who set their own
                // role meant it, and it is a better description than ours.
                if (! item.hasAttribute('role')) {
                    item.setAttribute('role', 'group');
                }

                if (! item.hasAttribute('aria-roledescription')) {
                    item.setAttribute('aria-roledescription', this._roleDescription);
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

        /**
         * Say what happened across two lists, once: which card, from where, to where, and the
         * new order of BOTH, so an application can persist the whole move in one statement.
         *
         * Dispatched from the list the card ended in, and it bubbles, so a listener anywhere
         * above the board hears it: `wire:wirekit:sortable:moved="moveCard($event.detail)"`.
         */
        _announceMove(item, origin, to) {
            this.$root.dispatchEvent(new CustomEvent('wirekit:sortable:moved', {
                detail: {
                    id: item.getAttribute('data-sortable-id'),
                    from: {
                        column: this._column(origin.list),
                        index: origin.index,
                        order: this._itemsOf(origin.list).map((el) => el.getAttribute('data-sortable-id')),
                    },
                    to: { column: this._column(), index: to, order: this._order() },
                },
                bubbles: true,
            }));
        },

        /**
         * Give a card to another list on the board, which inserts, announces and focuses it.
         *
         * An event rather than a call into the other instance: the two lists share a board and
         * nothing else, and only the receiving factory knows its own items, its own live region
         * and its own column name. Not bubbling — it is addressed to that one list.
         */
        _handOver(list, item, index, origin, mode) {
            list.dispatchEvent(new CustomEvent('wirekit:sortable:receive', {
                detail: { item, index, origin, mode },
            }));
        },

        /**
         * Take a card another list handed over: as a LIFTED card that keeps moving
         * (`mode: 'lift'`), or as one Escape sent back to the list it started in
         * (`mode: 'cancel'`).
         */
        _receive({ item, index, origin, mode }) {
            const items = this._items();
            const at = Math.max(0, Math.min(index, items.length));

            // Before the card now at that index, or — past the last card — before this list's
            // own live region, so a card is never reached only after a hidden node.
            const reference = items[at] ?? (this._announcer?.parentNode === this.$root ? this._announcer : null);

            this.$root.insertBefore(item, reference);

            const now = this._items();
            const position = now.indexOf(item) + 1;

            if (mode === 'cancel') {
                this._say(this._messages.canceled, position, now.length);
            } else {
                this._lifted = item;
                this._liftOrigin = origin;
                item.setAttribute('data-sortable-lifted', 'true');
                this._say(this._messages.movedToColumn, position, now.length, this._columnName());
            }

            // A focused node that is re-inserted can lose focus, and this one just changed
            // lists — the reason the arrow branch re-focuses after a move within one list.
            item.focus();
        },

        /**
         * Carry a lifted card into the previous or next list on the board.
         *
         * At the first or last list there is nowhere to go, and the answer to "did that work"
         * is where the card still is — the rule an arrow press at the end of a list follows.
         */
        _moveAcross(item, direction) {
            const lists = this._lists();
            const target = lists[lists.indexOf(this.$root) + direction];

            if (! target) {
                const items = this._items();

                this._say(this._messages.moved, items.indexOf(item) + 1, items.length);
                item.focus();

                return;
            }

            const index = this._items().indexOf(item);
            const origin = this._liftOrigin ?? { list: this.$root, index: this._liftedFrom };

            this._release(item);
            this._handOver(target, item, index, origin, 'lift');
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

            activeDrag = { item, list: this.$root, index: this._items().indexOf(item) };
            item.setAttribute('data-sortable-dragging', 'true');

            // Firefox refuses to start a drag at all without data on the
            // transfer object, and says nothing about why.
            event.dataTransfer?.setData('text/plain', item.getAttribute('data-sortable-id') ?? '');
        },

        /**
         * The drag this list may take part in: its own, or — on a connected board — one that
         * started in another list of the same board. A drag whose card has left the page, a
         * morph having replaced it mid-drag, is nobody's: accepting it would move a card that
         * no longer exists.
         */
        _acceptedDrag() {
            if (! activeDrag || ! activeDrag.item.isConnected) {
                return null;
            }

            if (activeDrag.list === this.$root) {
                return activeDrag;
            }

            const board = this._board();

            return board && board.contains(activeDrag.list) ? activeDrag : null;
        },

        dragover(event) {
            const drag = this._acceptedDrag();

            if (! drag) {
                return;
            }

            // Without this the drop is refused by the browser and the whole
            // interaction ends in a snap-back with no event.
            event.preventDefault();

            const over = this._itemFrom(event.target);

            if (over === drag.item) {
                return;
            }

            // Over the list's own empty space, which is the only way into an EMPTY column at
            // all. Only for a card from another list: a card of this list already sits in it,
            // and the gap below the last card is not a position.
            if (! over) {
                if (drag.item.parentNode !== this.$root) {
                    this.$root.insertBefore(drag.item, this._announcer?.parentNode === this.$root ? this._announcer : null);
                }

                return;
            }

            // Halfway is the commit point — using the boundary instead makes the
            // list flicker between two orders while the pointer sits still.
            const box = over.getBoundingClientRect();
            const after = event.clientY > box.top + box.height / 2;

            over.parentNode.insertBefore(drag.item, after ? over.nextSibling : over);
        },

        dragend() {
            const drag = activeDrag;

            activeDrag = null;

            if (! drag) {
                return;
            }

            const { item } = drag;
            const to = this._items().indexOf(item);

            item.removeAttribute('data-sortable-dragging');

            // Reached through the list the card ENDED in, which is the one that reports: a card
            // from another list is a move, a card of this one a reorder, and no change at all
            // says nothing.
            if (to === -1) {
                return;
            }

            if (drag.list !== this.$root) {
                this._announceMove(item, { list: drag.list, index: drag.index }, to);
            } else if (to !== drag.index) {
                this._announceOrder(item.getAttribute('data-sortable-id'), drag.index, to);
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

                const origin = this._liftOrigin ?? { list: this.$root, index: this._liftedFrom };

                // A card carried in from another column goes home, and the column it started in
                // takes it back and says so: only that list knows its own positions and owns the
                // live region a reader of that column is listening to. Focus follows the card
                // inside `_receive()`.
                if (origin.list !== this.$root) {
                    this._release(item);
                    this._handOver(origin.list, item, origin.index, null, 'cancel');

                    return;
                }

                // The ORIGIN's index rather than `_liftedFrom`: a card that went to another column
                // and came back was released on the way out, and its start survives only there.
                const back = origin.index;

                this._moveTo(item, back);
                this._release(item);
                this._say(this._messages.canceled, back + 1, this._items().length);

                // The same re-insertion as the arrow branch below, so the same
                // reason: a browser can drop focus when a focused node is
                // re-inserted. Escape is the ONLY way out of a keyboard reorder,
                // which makes the loss here the more punishing of the two — a
                // reader who backs out of a move lands on `<body>` and has to tab
                // the whole list again to reach the card they decided NOT to
                // move, so abandoning costs more than finishing. WCAG 2.4.3.
                // A no-op wherever the browser kept focus on the node.
                item.focus();

                return;
            }

            if (this._lifted !== item) {
                return;
            }

            // On a connected board the horizontal arrows change the column — and only there, so a
            // list that did not ask for this keeps the meaning those two keys always had.
            if ((key === 'ArrowLeft' || key === 'ArrowRight') && this._board()) {
                event.preventDefault();
                this._moveAcross(item, key === 'ArrowRight' ? 1 : -1);

                return;
            }

            const delta =(key === 'ArrowDown' || key === 'ArrowRight') ? 1
                : (key === 'ArrowUp' || key === 'ArrowLeft') ? -1
                    : 0;

            if (delta === 0) {
                return;
            }

            event.preventDefault();
            this._moveTo(item, this._items().indexOf(item) + delta);

            // Said on every arrow press, including the one that hit the end of
            // the list and moved nothing: "position 3 of 3" is the answer to
            // "did that work", and silence is not.
            const items = this._items();

            this._say(this._messages.moved, items.indexOf(item) + 1, items.length);

            // Focus follows the element, which the move did not disturb — but a
            // browser can drop focus when a focused node is re-inserted, and a
            // list that loses focus mid-reorder is unusable by keyboard.
            item.focus();
        },

        _lift(item) {
            const items = this._items();

            this._lifted = item;
            this._liftedFrom = items.indexOf(item);
            this._liftOrigin = null;
            item.setAttribute('data-sortable-lifted', 'true');

            // On a connected board the instructions name the second pair of arrows, because it
            // now does something a reader could not guess from the first.
            this._say(this._board() ? this._messages.grabbedAcross : this._messages.grabbed, this._liftedFrom + 1, items.length);
        },

        _drop(item) {
            const items = this._items();
            const to = items.indexOf(item);
            const origin = this._liftOrigin ?? { list: this.$root, index: this._liftedFrom };

            this._release(item);
            this._say(this._messages.dropped, to + 1, items.length);

            // A card that started in another column is a MOVE, reported once with both columns'
            // order; one that stayed in this column is the reorder it always was.
            if (origin.list !== this.$root) {
                this._announceMove(item, origin, to);
            } else if (to !== origin.index) {
                this._announceOrder(item.getAttribute('data-sortable-id'), origin.index, to);
            }
        },

        _release(item) {
            item.removeAttribute('data-sortable-lifted');
            this._lifted = null;
            this._liftedFrom = null;
            this._liftOrigin = null;
        },
    };
}
