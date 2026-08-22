import { readPersistedFlag, writePersistedFlag } from '../utils/persisted-flag.js';

/**
 * App rail — the expanded/collapsed state of the module rail.
 *
 * Deliberately a SEPARATE factory from `wirekitSidebarRail` rather than a shared
 * one with a configurable property name, and the reason is the same one recorded
 * there: Alpine resolves a binding by the name it finds in scope, and these two
 * templates bind different names on purpose. A sidebar is `collapsed`; a rail is
 * `expanded`. They also nest — a rail column and a sidebar column stand side by
 * side in the same shell, and on mobile they travel inside one drawer — so a
 * shared property name would let one read the other's state through the scope
 * Alpine merges downward. That is not a hypothetical: `sidebar-disclosure.js`
 * reaches the sidebar's `collapsed` through exactly that merge.
 *
 * The polarity is inverted from the sidebar's on purpose too. A rail's resting
 * state is narrow, so `expanded` is the deviation and the default is `false`;
 * writing it as `collapsed: true` would make every call site state the default.
 *
 * @param {Object}       config
 * @param {boolean}      [config.expanded]  state on a first visit, before storage
 * @param {string|null}  [config.persist]   localStorage key; null keeps it ephemeral
 */
export default function wirekitAppRail(config = {}) {
    return {
        expanded: config.expanded === true,
        _persistKey: config.persist || null,
        _onExternalToggle: null,

        init() {
            this.expanded = readPersistedFlag(this._persistKey, this.expanded);

            this._onExternalToggle = (event) => {
                // No id addresses every rail on the page, which is the one-rail case
                // that is nearly all of them. With an id it addresses only that rail,
                // so a shell that renders two can drive each separately.
                const target = event?.detail?.id;
                if (target && target !== this.$el?.id) {
                    return;
                }

                this.toggle();
            };

            window.addEventListener('wirekit:rail:toggle', this._onExternalToggle);

            // Announce the state on arrival so a trigger rendered OUTSIDE the rail can
            // paint the right aria-expanded before its first click instead of guessing.
            //
            // Deferred, for the reason sidebar-rail.js records at length: Alpine
            // initializes in document order, and the rail is the FIRST column of the
            // shell, so every plausible external trigger — a topbar button, a mobile
            // menu control — registers its listener after this component. Announcing
            // synchronously lands on nobody, and a persisted expanded state then shows
            // the wrong arrow until someone clicks it.
            this.$nextTick(() => this._announce());
        },

        destroy() {
            // A Livewire navigation replaces the rail. A listener still holding the old
            // component's `this` would toggle a node no longer in the document, and the
            // state would disagree with the screen without anything looking broken.
            if (this._onExternalToggle) {
                window.removeEventListener('wirekit:rail:toggle', this._onExternalToggle);
                this._onExternalToggle = null;
            }
        },

        toggle() {
            this.expanded = ! this.expanded;
            writePersistedFlag(this._persistKey, this.expanded);
            this._announce();
        },

        _announce() {
            window.dispatchEvent(
                new CustomEvent('wirekit:rail:toggled', {
                    // Optional chaining because the id is optional AND because `$el` is an
                    // Alpine magic a plain unit harness does not provide — reading it
                    // unguarded made the sibling component untestable outside a browser,
                    // which is a mistake worth not repeating.
                    detail: { id: this.$el?.id || null, expanded: this.expanded },
                }),
            );
        },
    };
}
