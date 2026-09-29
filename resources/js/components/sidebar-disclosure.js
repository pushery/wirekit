import { readPersistedFlag, writePersistedFlag } from '../utils/persisted-flag.js';
import { withOpenAlias } from '../utils/open-alias.js';

/**
 * The sections on the page that persist their state, grouped by key.
 *
 * Two sections with one key are one section drawn twice, such as the same navigation in a drawer
 * for phones and in a rail for wider screens, and a fold in one has to reach the other. The
 * `storage` event never fires in the tab that wrote, so the page tells its own sections. One
 * registry per page and no listener at all, so nothing accumulates across `wire:navigate`: a
 * section joins when Alpine starts it and leaves when Alpine destroys its element.
 *
 * @type {Map<string, Set<{ adopt: ((open: boolean) => void) | null }>>}
 */
const sectionsByKey = new Map();

function joinSections(key, entry) {
    if (! sectionsByKey.has(key)) {
        sectionsByKey.set(key, new Set());
    }

    sectionsByKey.get(key).add(entry);
}

function leaveSections(key, entry) {
    const entries = sectionsByKey.get(key);

    if (entries) {
        entries.delete(entry);

        if (entries.size === 0) {
            sectionsByKey.delete(key);
        }
    }
}

/** Hand a new state to every other section with the key. Each adopts it only when it differs. */
function tellSections(key, from, open) {
    for (const entry of sectionsByKey.get(key) ?? []) {
        if (entry !== from && entry.adopt) {
            entry.adopt(open);
        }
    }
}

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
 * @param {boolean}      [config.open]       state on a first visit, before storage
 * @param {string|null}  [config.persist]    localStorage key; null keeps it ephemeral
 * @param {boolean}      [config.forceOpen]  open on every load, whatever storage says; a fold
 *                                           still holds for the rest of the visit
 *
 * A fold reaches every other section on the page with the same `persist` key, through the
 * registry above.
 */
export default function wirekitSidebarDisclosure(config = {}) {
    // This instance's place in the registry above. Held in the closure rather than on the
    // component: Alpine hands every method call a fresh proxy as `this`, and reads of the
    // component's data come back wrapped, so neither is the same object twice.
    const entry = { adopt: null };

    return withOpenAlias({
        isOpen: config.open === true || config.forceOpen === true,
        _persistKey: config.persist || null,
        _forceOpen: config.forceOpen === true,

        init() {
            // A forced section opens on every load, so the group that holds the current page shows
            // where the reader is even after they folded it on an earlier visit. Otherwise a stored
            // state wins over the seed, as it always has. A fold is still written, and holds for
            // the rest of this visit.
            this.isOpen = this._forceOpen || readPersistedFlag(this._persistKey, this.isOpen);

            if (this._persistKey) {
                // Compared before it is set, so two sections never answer each other.
                entry.adopt = (open) => {
                    if (this.isOpen !== open) {
                        this.isOpen = open;
                    }
                };
                joinSections(this._persistKey, entry);
            }
        },

        destroy() {
            if (this._persistKey) {
                leaveSections(this._persistKey, entry);
            }

            entry.adopt = null;
        },

        toggle() {
            this.isOpen = ! this.isOpen;
            writePersistedFlag(this._persistKey, this.isOpen);

            if (this._persistKey) {
                tellSections(this._persistKey, entry, this.isOpen);
            }
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
            const isOpen = this.isOpen === true;
            const railFolded = 'collapsed' in this && this.collapsed === true;

            return isOpen || railFolded;
        },
    });
}
