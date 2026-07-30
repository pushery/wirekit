import { readPersistedFlag, writePersistedFlag } from '../utils/persisted-flag.js';

/**
 * Sidebar rail — the folded/expanded state of the sidebar itself.
 *
 * The same persisted-flag mechanic as the disclosures it contains, under a
 * different name because the templates bind different names: a section is
 * `open`, the rail is `collapsed`, and Alpine resolves a binding by the name it
 * finds in scope. One factory with a configurable property name would only move
 * that coupling somewhere harder to read.
 *
 * The disclosures reach this component's `collapsed` through the scope Alpine
 * merges down the tree — see `childrenVisible()` in sidebar-disclosure.js, which
 * is why the flag has to stay a plain property here rather than becoming a
 * getter.
 *
 * @param {Object}       config
 * @param {boolean}      [config.collapsed]  state on a first visit, before storage
 * @param {string|null}  [config.persist]    localStorage key; null keeps it ephemeral
 */
export default function wirekitSidebarRail(config = {}) {
    return {
        collapsed: config.collapsed === true,
        _persistKey: config.persist || null,

        init() {
            this.collapsed = readPersistedFlag(this._persistKey, this.collapsed);
        },

        toggle() {
            this.collapsed = ! this.collapsed;
            writePersistedFlag(this._persistKey, this.collapsed);
        },
    };
}
