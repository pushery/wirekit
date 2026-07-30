import { readPersistedFlag, writePersistedFlag } from '../utils/persisted-flag.js';

/**
 * Sidebar disclosure — the folding section behind both `sidebar.group` and
 * `sidebar.collapsible`.
 *
 * The two differ only in chrome: a group is a section heading, a collapsible
 * looks like a nav row with an icon. Their state was already one
 * implementation, emitted into `x-data` by a PHP helper. Alpine's CSP build
 * rejects the method shorthand that helper produced, so under a strict
 * Content-Security-Policy neither section could be folded — the chevron sat
 * there and the click did nothing.
 *
 * @param {Object}       config
 * @param {boolean}      [config.open]     state on a first visit, before storage
 * @param {string|null}  [config.persist]  localStorage key; null keeps it ephemeral
 */
export default function wirekitSidebarDisclosure(config = {}) {
    return {
        open: config.open === true,
        _persistKey: config.persist || null,

        init() {
            this.open = readPersistedFlag(this._persistKey, this.open);
        },

        toggle() {
            this.open = ! this.open;
            writePersistedFlag(this._persistKey, this.open);
        },

        /**
         * Whether the child container shows.
         *
         * Inside a COLLAPSED icon rail the children are force-shown as a flat
         * icon list: the trigger is unreadable at rail width, and hiding the
         * section outright would strand its items. But `collapsed` lives on an
         * ANCESTOR, and this same component is valid inside a plain sidebar
         * where no such ancestor exists.
         *
         * The template guarded that with `typeof collapsed !== 'undefined'`,
         * which the CSP parser does not accept — its unary operators are `!`,
         * `-` and `+`, and `typeof` is not in the grammar at all. An `in` check
         * against the scope Alpine merges for this element answers the same
         * question: it reaches the rail when there is one, and is false when
         * there is not.
         *
         * Both operands are read before the decision rather than
         * short-circuited. Alpine's effect only tracks what an evaluation
         * actually touches, so an early return on `open` would leave the rail
         * flag untracked on the first pass — and folding the rail would then not
         * re-run this.
         */
        childrenVisible() {
            const isOpen = this.open === true;
            const railFolded = 'collapsed' in this && this.collapsed === true;

            return isOpen || railFolded;
        },
    };
}
