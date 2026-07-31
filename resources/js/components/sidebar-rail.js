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
 * A trigger OUTSIDE this component cannot call `toggle()` — Alpine merges scope
 * downwards only, so a button in a topbar is not in the sidebar's tree and never
 * sees it. That is the whole reason the built-in toggle used to be the only option:
 * moving it meant switching the collapsible mode off and rebuilding this state, the
 * persisted flag and the marker the items read, in the app. So the state stays here
 * and the outside reaches it through a window event instead.
 *
 * @param {Object}       config
 * @param {boolean}      [config.collapsed]  state on a first visit, before storage
 * @param {string|null}  [config.persist]    localStorage key; null keeps it ephemeral
 */
export default function wirekitSidebarRail(config = {}) {
    return {
        collapsed: config.collapsed === true,
        _persistKey: config.persist || null,
        _onExternalToggle: null,

        init() {
            this.collapsed = readPersistedFlag(this._persistKey, this.collapsed);

            this._onExternalToggle = (event) => {
                // An event with no id addresses every sidebar on the page, which is
                // right for the one-sidebar case that is nearly all of them. With an
                // id it addresses only that nav, so a page with two of them can drive
                // each separately instead of flipping both.
                const target = event?.detail?.id;
                if (target && target !== this.$el?.id) {
                    return;
                }

                this.toggle();
            };

            window.addEventListener('wirekit:sidebar:toggle', this._onExternalToggle);

            // The state on arrival, so an outside trigger can render the correct
            // aria-expanded from its first paint rather than guessing. Without this
            // an external button either lies until the first click or has to read a
            // DOM attribute the component owns.
            //
            // DEFERRED, and that is the whole correctness of it. Announcing synchronously
            // inside init() fires before a trigger that sits AFTER the sidebar in the
            // document has registered its `wirekit:sidebar:toggled.window` listener — Alpine
            // initializes in document order, so the listener does not exist yet and the
            // announcement lands on nobody. That order is not an edge case: it is the order
            // the documented external-trigger snippet uses, and the one
            // `header-placement="content"` produces, because a sidebar column comes before
            // the topbar that drives it. A persisted collapsed state therefore rendered
            // `aria-expanded="true"` on a collapsed sidebar until the first click.
            //
            // `$nextTick` and not a bare `setTimeout`: it is Alpine's own "after the current
            // work is finished", which is exactly the boundary that matters here, and it is
            // the idiom the rest of this directory already uses.
            this.$nextTick(() => this._announce());
        },

        destroy() {
            // Removed on teardown, not left behind: a Livewire navigation replaces the
            // sidebar, and a listener still holding the old component's `this` toggles
            // a node that is no longer in the document — the state then disagrees with
            // what is on screen and nothing looks broken until the next click.
            if (this._onExternalToggle) {
                window.removeEventListener('wirekit:sidebar:toggle', this._onExternalToggle);
                this._onExternalToggle = null;
            }
        },

        toggle() {
            this.collapsed = ! this.collapsed;
            writePersistedFlag(this._persistKey, this.collapsed);
            this._announce();
        },

        _announce() {
            window.dispatchEvent(
                new CustomEvent('wirekit:sidebar:toggled', {
                    // Optional chaining because the id is optional AND because $el is an
                    // Alpine magic that a plain unit harness does not provide -- reading it
                    // unguarded made the component untestable outside a browser.
                    detail: { id: this.$el?.id || null, collapsed: this.collapsed },
                }),
            );
        },
    };
}
