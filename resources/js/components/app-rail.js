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

        /**
         * Whether the NAMES are laid out. Deliberately not the same thing as `expanded`.
         *
         * A label leaving is invisible; a label arriving is not. Measured mid-flight, at a
         * column already 164.8px of its final 240px: the name was in the layout at 101.8px
         * and grew to 177px as the column caught up — so it was set at a width that was not
         * yet the final one, wrapped there, and then unwrapped. That is the shift.
         *
         * The sidebar reads clean in the other direction for the same reason: collapsing, its
         * label is already out of the layout at 164.5px, so the column narrows over nothing.
         *
         * So the names LEAVE with the toggle and ARRIVE only once the column has stopped
         * moving. Wrapping is untouched — the house rule that a navigation entry never
         * truncates stands, and this is precisely what lets it stand: the text is only ever
         * laid out at a width it will keep.
         */
        wide: false,

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
        _readyFrame: null,
        _onExternalToggle: null,
        _onWidthSettled: null,
        _wideFallback: null,

        init() {
            this.expanded = readPersistedFlag(this._persistKey, this.expanded);

            // First paint has no animation to wait for, so the names are simply there.
            this.wide = this.expanded;

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

            // `width` and not any property: this element also transitions colors, and every
            // one of those would otherwise publish the names early.
            this._onWidthSettled = (event) => {
                if (event.propertyName === 'width' && event.target === this.$el) {
                    this.wide = this.expanded;
                }
            };

            // Optional, and not defensively: `$el` is an Alpine magic that a plain unit
            // harness does not provide, and reading it unguarded is what made the sibling
            // component untestable outside a browser. Same mistake, same file, once already.
            this.$el?.addEventListener?.('transitionend', this._onWidthSettled);

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

            // Both of these outlive the component otherwise — the listener holds the node it
            // watches, and a pending timeout fires into a torn-down scope. That is the
            // null-callback class this repo's Alpine rules exist for.
            if (this._onWidthSettled) {
                this.$el?.removeEventListener?.('transitionend', this._onWidthSettled);
                this._onWidthSettled = null;
            }

            if (this._wideFallback) {
                clearTimeout(this._wideFallback);
                this._wideFallback = null;
            }

            if (this._readyFrame && typeof cancelAnimationFrame === 'function') {
                cancelAnimationFrame(this._readyFrame);
                this._readyFrame = null;
            }

        },

        toggle() {
            this.expanded = ! this.expanded;

            if (this.expanded) {
                // A belt for the case where `transitionend` never comes: a zero duration,
                // `prefers-reduced-motion`, a rail that is display:none while it changes.
                // Without it the names would simply never arrive, which is worse than the
                // shift this whole mechanism exists to remove. Read from the element rather
                // than assumed, because the duration is a token a project can retune.
                clearTimeout(this._wideFallback);

                const declared = typeof getComputedStyle === 'function' && this.$el
                    ? getComputedStyle(this.$el).transitionDuration || '0s'
                    : '0s';
                const longest = Math.max(
                    0,
                    ...declared.split(',').map((d) => parseFloat(d) * (d.includes('ms') ? 1 : 1000) || 0),
                );

                this._wideFallback = setTimeout(() => {
                    this.wide = this.expanded;
                }, longest + 80);
            } else {
                // Leaving is instant, and that is the half that was always right.
                clearTimeout(this._wideFallback);
                this.wide = false;
            }

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
