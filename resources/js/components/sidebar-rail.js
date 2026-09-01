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

        /**
         * True while the column is on its way back to full width and the names have not been
         * let in yet. It exists because a label LEAVING is invisible and a label ARRIVING is
         * not.
         *
         * Measured on the collapsible-sidebar example, with an entry named long enough to
         * wrap: at rest the first row is 51px tall and the second starts at 90px. Sixty
         * milliseconds into the expand, at a column 174px of its final 256px, the first row
         * was 70.5px — three lines — and the second sat at 109.5px. It then settled back.
         * That 19.5px round trip is the shift; with short names it is the one or two pixels
         * somebody notices without being able to say what moved.
         *
         * A SEPARATE marker rather than delaying `data-collapsed`, and that is not caution:
         * twenty-five rules key off that attribute — centering, badge shape, headings, the
         * column width itself — and holding it back would hold all of them back. This one is
         * read by exactly the rules that hide TEXT, so the geometry moves on time and only
         * the words wait.
         */
        settling: false,

        /**
         * Whether this column may ANIMATE its width yet. False for the first frame.
         *
         * The transition is declared on the element, so on a cold load — no cache, stylesheet
         * still in flight — the column lays out at its unstyled width, and the moment the CSS
         * lands the browser animates the ARRIVAL of the styled width. The reader sees a column
         * unfold on a page where nothing was toggled. With a warm cache the styles are there
         * before first paint and nothing shows, which is why this only ever surfaced on
         * cmd+shift+R.
         *
         * Set after a frame rather than in `init()` itself: setting it synchronously lands in
         * the same style recalculation as the first paint, which is the thing being avoided.
         */
        ready: false,

        _persistKey: config.persist || null,
        // 'local' or 'cookie'. The helper defaults the same way, but naming it here keeps a
        // factory built by a test — which passes no config at all — on the documented store
        // rather than on whatever the helper's signature happens to say today.
        _persistDriver: config.persistDriver || 'local',
        _readyFrame: null,
        _onExternalToggle: null,
        _onWidthSettled: null,
        _settleFallback: null,

        init() {
            this.collapsed = readPersistedFlag(this._persistKey, this.collapsed, this._persistDriver);

            // One frame, then the width may animate. `requestAnimationFrame` is guarded
            // because a plain unit harness has no browser globals — the same reason `$el` is
            // optional throughout this file.
            if (typeof requestAnimationFrame === 'function') {
                this._readyFrame = requestAnimationFrame(() => {
                    this.ready = true;
                });
            } else {
                this.ready = true;
            }

            // `width` only: this element transitions colors too, and any of those would
            // otherwise let the names in early.
            this._onWidthSettled = (event) => {
                if (event.propertyName === 'width' && event.target === this.$el) {
                    this.settling = false;
                }
            };

            this.$el?.addEventListener?.('transitionend', this._onWidthSettled);

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

            // Both outlive the component otherwise: a listener holds the node it watches, and
            // a pending timeout fires into a torn-down scope.
            if (this._onWidthSettled) {
                this.$el?.removeEventListener?.('transitionend', this._onWidthSettled);
                this._onWidthSettled = null;
            }

            if (this._settleFallback) {
                clearTimeout(this._settleFallback);
                this._settleFallback = null;
            }

            if (this._readyFrame && typeof cancelAnimationFrame === 'function') {
                cancelAnimationFrame(this._readyFrame);
                this._readyFrame = null;
            }

        },

        toggle() {
            this.collapsed = ! this.collapsed;

            if (this.collapsed) {
                // Narrowing: the names go at once, which is the half that always looked right.
                clearTimeout(this._settleFallback);
                this.settling = false;
            } else {
                this.settling = true;

                // A belt for the case where `transitionend` never arrives — a zero duration,
                // `prefers-reduced-motion`, a column that is display:none while it changes.
                // Without it the names would never come back, which is worse than the shift
                // this exists to remove. Read off the element, because the duration is a
                // token a project can retune.
                clearTimeout(this._settleFallback);

                const declared = typeof getComputedStyle === 'function' && this.$el
                    ? getComputedStyle(this.$el).transitionDuration || '0s'
                    : '0s';
                const longest = Math.max(
                    0,
                    ...declared.split(',').map((d) => parseFloat(d) * (d.includes('ms') ? 1 : 1000) || 0),
                );

                this._settleFallback = setTimeout(() => {
                    this.settling = false;
                }, longest + 80);
            }

            writePersistedFlag(this._persistKey, this.collapsed, this._persistDriver);
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
