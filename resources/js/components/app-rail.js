import { readPersistedFlag, writePersistedFlag } from '../utils/persisted-flag.js';
import { namesSomething, sameName } from '../utils/same-name.js';
import { timerDelay } from '../utils/timer-delay.js';

/**
 * App rail — the expanded/collapsed state of the module rail.
 *
 * Deliberately a separate factory from `wirekitSidebarRail` rather than a shared
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
 * @param {boolean}      [config.pinned]    true when the page set `expanded`; storage then does not answer it
 * @param {string|null}  [config.persist]   storage key; null keeps it ephemeral
 * @param {string}       [config.persistDriver] 'local' (default) or 'cookie'. The cookie
 *   driver exists so the server can seed the first render — see persisted-flag.js.
 * @param {boolean}      [config.expandable] whether a reader can widen it; absent means true
 */
export default function wirekitAppRail(config = {}) {
    // The rail's own element, kept from init(). Alpine binds `$el` to the element an expression
    // runs on, so `toggle()` from the button inside the rail would read that button: its id
    // in the announcement and its transition in the timer below. Before init(), as in a harness
    // that calls a method directly, `$el` answers.
    let railElement = null;

    return {
        // Handles set while the component runs, declared so that they are its own: Alpine stores a
        // property no scope declares on the outermost scope around the component.
        _onViewportChange: null,

        expanded: config.expanded === true,

        /**
         * Whether the names are laid out. Deliberately not the same thing as `expanded`.
         *
         * A label leaving is invisible; a label arriving is not. A name laid out while the
         * column is still widening is set at a width that is not yet the final one, wraps
         * there, and unwraps as the column catches up. That is the shift.
         *
         * Collapsing reads clean for the same reason: the label is already out of the layout
         * when the column starts to narrow, so it narrows over nothing.
         *
         * So the names leave with the toggle and arrive only once the column has stopped
         * moving. Wrapping is untouched — a navigation entry never truncates, and this is
         * precisely what lets that hold: the text is only ever laid out at a width it will
         * keep.
         */
        wide: false,

        /**
         * Whether this column may animate its width yet. False for the first frame.
         *
         * The transition is declared on the element, so on a cold load — no cache, stylesheet
         * still in flight — the column lays out at its unstyled width, and the moment the CSS
         * lands the browser animates the arrival of the styled width. The reader sees a column
         * unfold on a page where nothing was toggled. With a warm cache the styles are there
         * before first paint and nothing shows, which is why this only ever surfaced on
         * cmd+shift+R.
         *
         * Set after a frame rather than in `init()` itself: setting it synchronously lands in
         * the same style recalculation as the first paint, which is the thing being avoided.
         */
        ready: false,

        /**
         * Whether a reader can widen this rail at all: its `expandable` prop.
         *
         * Every rail runs this component, because one of its widths is decided in the page
         * rather than on the server (see `_presented()`). Only an expandable rail has a toggle,
         * a stored preference and a state worth announcing; a rail that cannot expand gets none
         * of those. Absent means true, because the factory predates the flag and every call
         * made without it is an expandable rail.
         */
        _canToggle: config.expandable !== false,

        _persistKey: config.persist || null,
        /** The page set `expanded`, so the store is not asked. Writes still go to it. */
        _pinned: config.pinned === true,
        _persistDriver: config.persistDriver === 'cookie' ? 'cookie' : 'local',
        _readyFrame: null,
        _onExternalToggle: null,
        _onWidthSettled: null,
        _wideFallback: null,
        // Watches the rail's own list come into view, on a wide screen at load and below the
        // breakpoint each time the drawer opens, to bring the current module into it.
        _seen: null,

        /**
         * The stored preference, kept apart from `expanded` because below the breakpoint
         * they are not the same thing.
         *
         * Below `lg` this rail is not a column — it is the left strip of an off-canvas
         * drawer, and the drawer's width is the sum of its columns. Expanded, the drawer can
         * be wider than a phone, and the page does not scroll sideways, so the entries and the
         * column edge would be off the device with no way to reach them.
         *
         * The preference is stored, is not tied to a breakpoint, and the control that sets
         * it lives inside the drawer. So a phone can reach that state without a desktop ever
         * having been involved.
         *
         * Forced collapsed below the breakpoint, and the stored value left untouched, so a
         * reader who expanded the rail on a laptop still finds it expanded there. The one
         * exception is a drawer the rail has to itself, which `_presented()` explains.
         */
        _persisted: false,

        /** The `lg` breakpoint, or null where `matchMedia` does not exist. */
        _viewport: null,

        /**
         * Whether an expanded rail means anything at this width.
         *
         * True when there is no `matchMedia` to ask — a unit harness, an unusual host —
         * because that is what this component did before the breakpoint existed, and a
         * guard that changes behavior where it cannot measure is worse than no guard.
         */
        _expandable() {
            return this._viewport ? this._viewport.matches === true : true;
        },

        /**
         * Whether this rail is the only column of an off-canvas drawer.
         *
         * The shell says so on the drawer itself, because it is the one that knows what else went
         * into it. Beside a module column the drawer is already as wide as a phone allows, and
         * widening the rail there pushed the column off the device (the measurement on
         * `_persisted` above). Alone, the expanded rail is narrower than any phone, while the
         * narrow one is a strip of unnamed icons on a screen with no hover: a tap navigates long
         * before a tooltip could appear.
         */
        _aloneInDrawer() {
            return !! this.$el?.closest?.('[data-wk-rail-only]');
        },

        /**
         * The width this rail presents right now.
         *
         * At or above the breakpoint it is the reader's: the stored preference, for a rail that
         * has one. Below it the rail is part of a drawer, the preference stays stored and unused,
         * and the drawer decides, from whether the rail has it to itself.
         */
        _presented() {
            if (this._expandable()) {
                return this._canToggle && this._persisted;
            }

            return this._aloneInDrawer();
        },

        init() {
            railElement = this.$el;
            // A rail that cannot expand has no preference to read. Its resting width is the one
            // `labels` names, and the only other width it ever takes is a drawer's.
            this._persisted = this._canToggle
                ? (this._pinned ? this.expanded : readPersistedFlag(this._persistKey, this.expanded, this._persistDriver))
                : false;

            // `64rem` rather than `1024px`: the shell's own `lg:` utilities are rem-based,
            // so a reader with a larger root font size crosses both at the same moment.
            this._viewport = typeof window !== 'undefined' && typeof window.matchMedia === 'function'
                ? window.matchMedia('(min-width: 64rem)')
                : null;

            this.expanded = this._presented();

            // Re-decide on a rotation or a resize across the breakpoint. Without this, a
            // phone held sideways at 812px keeps whichever state it entered with.
            this._onViewportChange = () => {
                this.expanded = this._presented();
                this.wide = this.expanded;
                this._announce();
            };

            this._viewport?.addEventListener?.('change', this._onViewportChange);

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
                if (namesSomething(target) && ! sameName(target, this.$el?.id)) {
                    return;
                }

                this.toggle();
            };

            // Only a rail a reader can widen listens. Any other would answer an outside trigger
            // by refusing it, and a listener that can only refuse is one more thing to tear down.
            if (this._canToggle) {
                window.addEventListener('wirekit:rail:toggle', this._onExternalToggle);
            }

            // Announce the state on arrival so a trigger rendered outside the rail can
            // paint the right aria-expanded before its first click instead of guessing.
            //
            // Deferred, for the reason sidebar-rail.js records at length: Alpine
            // initializes in document order, and the rail is the first column of the
            // shell, so every plausible external trigger — a topbar button, a mobile
            // menu control — registers its listener after this component. Announcing
            // synchronously lands on nobody, and a persisted expanded state then shows
            // the wrong arrow until someone clicks it.
            this.$nextTick(() => this._announce());

            // A list that scrolls can hold the current module below what it shows, and in a
            // phone's drawer that is where the foot's rule cuts it in half. Guarded because a
            // plain unit harness has no observer.
            //
            // Two thresholds. The first reports the list touching the window; the second reports
            // the whole list in view, which is the moment a drawer has finished opening. A drawer
            // told to open while it is still sliding out, after a phone turns or on a second tap,
            // never leaves the window, so the first is not crossed again and only the second
            // brings the module in. A list that fills a wide screen and does not scroll calls
            // `revealCurrent()` for nothing, which returns at once.
            const list = this.$el?.querySelector?.('[data-wk-rail-scroller]');

            if (list && typeof IntersectionObserver === 'function') {
                this._seen = new IntersectionObserver((entries) => {
                    if (this._seen && entries.some((entry) => entry.isIntersecting)) {
                        this.revealCurrent();
                    }
                }, { threshold: [0, 1] });
                this._seen.observe(list);
            }
        },

        destroy() {
            if (this._onViewportChange) {
                this._viewport?.removeEventListener?.('change', this._onViewportChange);
                this._onViewportChange = null;
            }

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

            this._seen?.disconnect();
            this._seen = null;
        },

        /**
         * Bring the current module whole into the rail's own list, by as little as it takes: up
         * when it stands above what the list shows, down when it stands below, not at all when
         * it is in view. The list's padding counts as the edge, so the module never stops under
         * the foot's rule. On the list itself, through `scrollTop`, never `scrollIntoView()`,
         * which scrolls every scrolling ancestor too, the page included.
         */
        revealCurrent() {
            const list = (railElement ?? this.$el)?.querySelector?.('[data-wk-rail-scroller]');
            const current = list?.querySelector?.('[aria-current]:not([aria-current="false"])');

            if (! current || list.scrollHeight <= list.clientHeight) {
                return;
            }

            const style = typeof getComputedStyle === 'function' ? getComputedStyle(list) : null;
            const box = list.getBoundingClientRect();
            const entry = current.getBoundingClientRect();
            const top = box.top + (parseFloat(style?.paddingTop) || 0);
            const bottom = box.bottom - (parseFloat(style?.paddingBottom) || 0);

            if (entry.top < top) {
                list.scrollTop -= top - entry.top;
            } else if (entry.bottom > bottom) {
                list.scrollTop += entry.bottom - bottom;
            }
        },

        toggle() {
            // The control is not rendered below the breakpoint, so this is the guard behind
            // it rather than the one in front: an external `wirekit:rail:toggle` event, or a
            // developer's own button, reaches this method the same way and must not be able
            // to widen the drawer past the device.
            if (! this._canToggle || ! this._expandable()) {
                return;
            }

            this.expanded = ! this.expanded;
            this._persisted = this.expanded;

            if (this.expanded) {
                // A belt for the case where `transitionend` never comes: a zero duration,
                // `prefers-reduced-motion`, a rail that is display:none while it changes.
                // Without it the names would simply never arrive, which is worse than the
                // shift this whole mechanism exists to remove. Read from the element rather
                // than assumed, because the duration is a token a project can retune.
                clearTimeout(this._wideFallback);

                const rail = railElement ?? this.$el;
                const declared = typeof getComputedStyle === 'function' && rail
                    ? getComputedStyle(rail).transitionDuration || '0s'
                    : '0s';
                const longest = Math.max(
                    0,
                    ...declared.split(',').map((d) => parseFloat(d) * (d.includes('ms') ? 1 : 1000) || 0),
                );

                this._wideFallback = setTimeout(() => {
                    this.wide = this.expanded;
                }, timerDelay(longest + 80));
            } else {
                // Leaving is instant, and that is the half that was always right.
                clearTimeout(this._wideFallback);
                this.wide = false;
            }

            writePersistedFlag(this._persistKey, this.expanded, this._persistDriver);
            this._announce();
        },

        _announce() {
            // A rail that cannot expand has no state for an outside trigger to mirror, and an
            // announcement from it would repaint that trigger for a rail it does not control.
            if (! this._canToggle) {
                return;
            }

            window.dispatchEvent(
                new CustomEvent('wirekit:rail:toggled', {
                    // Optional chaining because the id is optional and because `$el` is an
                    // Alpine magic a plain unit harness does not provide — reading it
                    // unguarded made the sibling component untestable outside a browser,
                    // which is a mistake worth not repeating.
                    detail: { id: (railElement ?? this.$el)?.id || null, expanded: this.expanded },
                }),
            );
        },
    };
}
