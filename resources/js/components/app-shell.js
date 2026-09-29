import { createFocusTrap } from '../utils/focus-trap.js';
import { holdPageInert, releasePageInert } from '../utils/overlay.js';

/**
 * App shell — the off-canvas navigation drawer, and only below the breakpoint.
 *
 * The shell's navigation is two columns standing beside the content on a wide screen and
 * ONE sliding panel below `lg`. Those are not two presentations of one thing; they are two
 * different objects, and the accessibility contract differs between them. Beside the
 * content the columns are layout — a `role` there would announce a dialog that nobody
 * opened. Sliding over the page, the same element is modal: a backdrop covers everything
 * behind it, and a reader has to be able to get out.
 *
 * Below the breakpoint the factory gives the drawer `role="dialog"` and `aria-modal`, points
 * the toggle at it with `aria-controls`, and closes it on Escape.
 *
 * The failure that makes it worth a factory rather than a few attributes is asymmetric,
 * which is why it looks fine with a mouse. The scrim backdrop blocks pointers from the page
 * behind it. It does not block the keyboard, so without a trap focus would walk on through
 * controls the backdrop covers — visible to nobody, operable by exactly the people who
 * cannot see where focus went.
 *
 * The width test is asked of `matchMedia`, not of a class, because ARIA cannot be set from
 * a media query: `role` and `aria-modal` are attributes, and an attribute has one value at
 * a time regardless of how wide the window is.
 *
 * @param {Object} config
 * @param {string} [config.drawerId]  id the drawer carries and the toggle points at
 */
export default function wirekitAppShell(config = {}) {
    // The drawer's elements that stay reachable while the page beside it is inert, held while
    // this shell holds the page (see `holdPageInert` in utils/overlay.js). A closure variable
    // rather than a property: a DOM node stored on the reactive object comes back out as a
    // Proxy, and the release has to hand back the very elements the hold was given.
    let pageInertKept = null;

    return {
        // Handles set while the component runs, declared so that they are its own: Alpine stores a
        // property no scope declares on the outermost scope around the component.
        _onViewportChange: null,
        _armTimer: null,
        _arm: null,
        _onInterimEscape: null,

        sidebarOpen: false,

        /** The id the toggle's `aria-controls` names. Empty when the shell has no drawer. */
        drawerId: config.drawerId || '',

        /** The `lg` query, or null on a host without media queries. */
        _viewport: null,

        _trap: null,

        /**
         * The element focus was taken FROM, captured the instant `sidebarOpen` turns true.
         *
         * Not the same moment `focus-trap` captures its own, and that difference is the
         * whole reason this property exists. The library records
         * `nodeFocusedBeforeActivation` inside `activate()`, and this component
         * deliberately defers `activate()` to the drawer's `transitionend` (or 350 ms) —
         * see `_armTrap`. Whatever is focused a third of a second after the drawer opened
         * is not "the element focus was taken from"; it is only what happened to survive
         * the wait.
         */
        _opener: null,

        /*
         * The drawer's settle wait, both halves.
         *
         * `_armTimer` was already reachable from `destroy()` and was the only half being
         * cleaned up; the `transitionend` handler lived in a closure, so nothing outside the
         * settle could release it. Declared here with the other handles because a handle
         * that only appears inside a method is one nobody reading the teardown knows to
         * look for.
         */
        _settlePanel: null,
        _onSettle: null,

        /**
         * Whether the navigation is CURRENTLY a drawer.
         *
         * A reactive property and NOT a method, which matters more than it looks. The
         * template binds `role` and `aria-modal` through this, and `matchMedia.matches` is
         * not a property Alpine tracks — a method reading it would be evaluated once and
         * never again, so a device turned sideways past the breakpoint would keep whichever
         * semantics it happened to load with, and nothing about the page would look wrong.
         *
         * False where `matchMedia` does not exist. That is the reading with no measurement
         * behind it, and the shell behaved exactly this way — as layout, with no dialog
         * semantics — before this factory. A guard that invents modal behavior on a host it
         * cannot measure would trap focus in a page that has no backdrop to escape from.
         */
        isDrawer: false,

        _syncViewport() {
            this.isDrawer = this._viewport ? this._viewport.matches !== true : false;
        },

        init() {
            this._viewport = typeof window !== 'undefined' && typeof window.matchMedia === 'function'
                ? window.matchMedia('(min-width: 64rem)')
                : null;

            this._syncViewport();

            // Crossing to a wide viewport while the drawer is open has to RELEASE it. The
            // same element becomes `display: contents` there and the backdrop is
            // `lg:hidden`, so a trap left armed would hold focus inside two columns that
            // are no longer covering anything — the mirror image of the bug above, and
            // harder to notice because the screen looks entirely normal.
            this._onViewportChange = () => {
                this._syncViewport();

                if (! this.isDrawer) {
                    // Released WITHOUT returning focus. Nobody closed anything here — the
                    // window got wider and the same element stopped being a dialog. The
                    // toggle that opened it is `lg:hidden` at this width, and `.focus()`
                    // on a `display: none` element is a silent no-op that leaves the
                    // reader on `<body>`: the exact landing this whole path exists to
                    // avoid. Focus stays where the reader put it.
                    this._releaseTrap({ returnFocus: false });
                    this.sidebarOpen = false;
                }
            };

            this._viewport?.addEventListener?.('change', this._onViewportChange);

            // Watched rather than driven from a method, and that is the whole reason this
            // works at all. The shell's documented contract is that SOMETHING writes to
            // `sidebarOpen` — the validation in the template says so in as many words, and
            // the toggle it ships is one of several plausible writers. A `toggleSidebar()`
            // that armed the trap would therefore be armed for the button we ship and
            // silently absent for every control a developer wires themselves, which is the
            // failure mode this component already had.
            this.$watch?.('sidebarOpen', (open) => {
                open ? this._armTrap() : this._releaseTrap();
            });
        },

        /**
         * Drop the drawer's `transitionend` handler, from wherever the wait ended.
         *
         * Idempotent: called by the settle itself, and by `destroy()` when the settle never
         * came. Only one of those is guaranteed to happen.
         */
        _releaseSettleListener() {
            if (this._onSettle) {
                this._settlePanel?.removeEventListener?.('transitionend', this._onSettle);
                this._onSettle = null;
                this._settlePanel = null;
            }
        },

        destroy() {
            // Release before dropping the reference. A Livewire navigation replaces the
            // shell, and a trap still armed over a detached node keeps its document-level
            // keydown listener: every Tab on the NEXT page would be pulled back toward an
            // element that is no longer in it.
            //
            // No return focus, for the same reason as the viewport crossing above: the
            // toggle this shell owns is being torn down with it, and the incoming page
            // decides where focus belongs. Aiming at a detached node lands on `<body>`.
            this._releaseTrap({ returnFocus: false });

            this._opener = null;

            if (this._armTimer) {
                clearTimeout(this._armTimer);
                this._armTimer = null;
            }

            this._releaseSettleListener();

            if (this._onViewportChange) {
                this._viewport?.removeEventListener?.('change', this._onViewportChange);
                this._onViewportChange = null;
            }
        },

        _armTrap() {
            // Only where it is a drawer. Beside the content there is nothing to trap focus
            // against and nothing covering the page to escape from.
            if (! this.isDrawer || this._trap) {
                return;
            }

            const panel = this.$refs?.drawer;

            if (! panel) {
                return;
            }

            // Captured HERE, synchronously on the state flip, for the reason spelled out on
            // `_opener` above: by the time the trap activates the drawer has finished
            // sliding in and the question "who opened this" can no longer be asked.
            this._opener = typeof document !== 'undefined' ? document.activeElement : null;

            // Escape has to work before the trap does, and the gap is the whole arming
            // wait below: focus arrives in the drawer a little after the panel transition
            // ends, not with the click.
            //
            // In that window the drawer is already over the page and already visible, focus
            // is still on the toggle, and `escapeDeactivates` belongs to a trap that is not
            // armed yet — so the key press reaches nothing at all. A reader who opens the
            // drawer by accident and presses Escape straight away sees nothing happen, and
            // has to press it a second time. Everything the arming wait buys is about WHERE
            // FOCUS GOES; closing needs none of it.
            this._armInterimEscape(panel);

            const trap = this._createTrap(panel, {
                // Escape is the drawer's own close, so the trap's deactivation and the
                // state have to agree — otherwise the panel slides away with `sidebarOpen`
                // still true and the next click on the toggle closes an already-closed
                // drawer.
                // Named explicitly rather than left to the library's own tabbable scan.
                //
                // The library picks the initial focus once, at `activate()`, and its scan
                // can come up empty on a panel that is still arriving, leaving the trap
                // active with focus outside it. A function resolves at activation time and
                // asks the DOM directly, which is the same question with a reliable answer.
                //
                // Falling back to the panel itself is why it carries `tabindex="-1"`: a
                // drawer whose links have not rendered yet still has to take focus, or the
                // reader is left outside a dialog that covers the page.
                initialFocus: () => panel.querySelector?.(
                    'a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])'
                ) || panel,
                escapeDeactivates: true,
                // The backdrop's own click handler closes the drawer, so outside clicks
                // must reach it rather than tearing the trap down first.
                allowOutsideClick: true,
                // WHERE focus goes on the way OUT, named rather than inferred.
                //
                // `returnFocusOnDeactivate` is on in the wrapper, and on its own it sends
                // focus to whatever `document.activeElement` was at ACTIVATION — which for
                // this component is a third of a second after the drawer opened, and is
                // `<body>` outright whenever the opening click left focus nowhere. WebKit
                // is the ordinary case of that, not an exotic one: clicking a `<button>`
                // there does not focus it, by platform convention. `focus-trap` then calls
                // `document.body.focus()`, which is a no-op, and the reader is returned to
                // the top of the document — every stop they had already tabbed past has to
                // be walked again.
                //
                // The sibling overlays name a target the same way (`overlay.js`,
                // `popover.js`, `color-picker.js`, `tour.js`).
                setReturnFocus: (previouslyFocused) => this._resolveReturnFocus(panel, previouslyFocused),
                onDeactivate: () => {
                    // Before focus returns: the toggle it goes back to sits on the page beside
                    // the drawer, and an inert element cannot take focus.
                    this._releasePageInert();
                    this._trap = null;
                    this.sidebarOpen = false;
                },
            });

            this._trap = trap;

            // Activated later, not here. The watcher fires the moment `sidebarOpen`
            // changes, before Alpine has applied the class that makes the panel visible.
            // `focus-trap` looks for something focusable at `activate()` time, finds
            // nothing in a subtree that is still `visibility: hidden`, and falls back to
            // the container, which cannot take focus either: the trap would exist, report
            // itself active, and leave focus on `<body>`.
            //
            // A counter, not an object comparison, says whether an activation is still
            // the current one. Alpine keeps component data behind a reactive Proxy, so
            // reading `this._trap` hands back a proxied wrapper that is never identical to
            // the raw trap just stored, and a guard reading `this._trap !== trap` would
            // reject every activation.
            const arming = (this._arm = (this._arm ?? 0) + 1);

            const activate = () => {
                // A newer arm, or a drawer that closed inside the wait. Either way this one
                // is stale, and activating it would trap focus in a panel on its way out.
                if (this._arm !== arming || ! this.sidebarOpen) {
                    return;
                }

                // Handed over rather than left running beside the trap: from here
                // `escapeDeactivates` is the close path, and two handlers for one key
                // would close the drawer twice — the second one against a trap that has
                // already returned focus.
                this._releaseInterimEscape();

                trap.activate();

                // The page beside the drawer is inert while it is a dialog, once the trap has
                // recorded the opener. The drawer and its backdrop stay reachable: they render
                // in place, and a click on the backdrop is how a pointer closes the drawer.
                if (! pageInertKept) {
                    const keep = [panel, this.$refs?.drawerBackdrop].filter(Boolean);

                    if (holdPageInert(keep)) {
                        pageInertKept = keep;
                    }
                }
            };

            // When to activate: once the panel has finished arriving.
            //
            // `focus-trap` places the initial focus once, at `activate()`; it re-reads its
            // tabbables later (on each Tab, when the focused node goes away), but it does
            // not come back to the initial focus. Called while the panel is still
            // translated out and mid-transition, it finds nothing to focus, and the trap
            // reports `active: true` without ever having moved focus. A tick or two
            // animation frames after the flip, the panel is still on its way in.
            //
            // So: the drawer's own `transitionend`, with a timer behind it because
            // `transitionend` does not fire for a zero-duration transition, for
            // `prefers-reduced-motion`, or when the browser coalesces the frame. Whichever
            // comes first wins; `activate` is idempotent against its own guard.
            // Both halves are held on `this`, so `destroy()` can release both. A shell torn
            // down while the drawer animates (a Livewire navigation is the ordinary case; the
            // window is the whole 350 ms) would otherwise keep a listener on a detached node
            // that then runs `activate()`, arming a focus trap over markup nobody owns; a
            // handle that lives only in a closure cannot be released from anywhere else.
            this._settlePanel = panel;
            this._onSettle = (event) => {
                // `transitionend` bubbles, and this panel is full of things that transition:
                // every link inside it animates its color on hover, the toggle animates its
                // own transform. Each of those would arm the trap early, in the middle of the
                // drawer sliding in, the moment the comment above shows focus lands nowhere.
                // `app-rail` filters its own `transitionend` on property and target for the
                // same reason.
                //
                // And the property list is the panel's own, not just `transform`: under
                // `transition-[transform,visibility]` Blink emits `visibility` as the panel's
                // `transitionend` and no `transform` event at all, so a filter on `transform`
                // alone would discard the one event there is and leave the 350 ms fallback as
                // the whole arming path.
                //
                // Focus would then sit on `<body>` for 350 ms after the drawer opens, and an
                // Escape aimed at the focused element would never reach the drawer.
                //
                // `target === panel` does the anti-bubbling work: the backdrop transitions its
                // own `opacity`, and a link's color transition bubbles from a child. Neither is
                // the panel.
                //
                // And the timer path must survive the filter. `setTimeout(this._onSettle, …)`
                // calls this with NO event, and that call is the whole fallback for a
                // zero-duration transition, for `prefers-reduced-motion`, and for a coalesced
                // frame. Filtering an absent event would leave exactly those readers with a
                // drawer that never traps focus.
                // `translate` is on the list because Tailwind 4 writes `translate-x-*` as the
                // standalone `translate` property: the panel's transition names it, the drawer
                // slides, and its `translate` end is as much the settle as `visibility`'s.
                if (event && (! ['translate', 'transform', 'visibility'].includes(event.propertyName) || event.target !== panel)) {
                    return;
                }

                this._releaseSettleListener();
                clearTimeout(this._armTimer);
                this._armTimer = null;
                activate();
            };

            panel.addEventListener?.('transitionend', this._onSettle);
            this._armTimer = setTimeout(this._onSettle, 350);
        },

        /**
         * The seam. One line, and it is the difference between a component that can be
         * reasoned about outside a browser and one that cannot: `focus-trap` reaches for a
         * global `document` the moment it is constructed, so a unit harness cannot get as
         * far as asking WHETHER a trap should have been armed — which is the only decision
         * this component actually makes. The trapping itself is the library's business and
         * is tested there.
         *
         * The sibling rail carries the same lesson about `$el`, learned the same way.
         */
        _createTrap(panel, options) {
            return createFocusTrap(panel, options);
        },

        /**
         * The element the drawer hands focus back to when it closes.
         *
         * Three candidates, in this order, and the order is the point:
         *
         * 1. The opener, when it is still in the document and still outside the panel. It
         *    is the only candidate that is right for a developer who wires their own
         *    control — the shell's documented contract is that SOMETHING writes
         *    `sidebarOpen`, not that our button did.
         * 2. The shell's own sidebar toggle, preferring the one whose `aria-controls`
         *    names THIS drawer. This is what carries the WebKit case, where the opening
         *    click left focus on `<body>` and candidate 1 is therefore worthless.
         * 3. `undefined`, which hands the decision back to `focus-trap` unchanged. A shell
         *    with no toggle and no usable opener is no worse off than before.
         *
         * `<body>` is rejected explicitly at step 1 rather than left to fall through:
         * `body.focus()` succeeds as a no-op, so returning it would look like a decision
         * and behave like the bug.
         *
         * Deliberately does NOT force a `tabindex` onto the candidate the way
         * `overlay.js` does for a surviving container. A toggle is a control and is
         * focusable on its own; writing `tabindex="-1"` onto a `<button>` would take it
         * OUT of the tab order — repairing the return path by breaking the forward one.
         *
         * @param {Element} panel      the drawer, so an opener inside it can be rejected
         * @param {Element} [previouslyFocused]  what `focus-trap` saw at activation
         * @returns {Element|undefined}
         */
        _resolveReturnFocus(panel, previouslyFocused) {
            const opener = this._opener ?? previouslyFocused;
            const body = typeof document !== 'undefined' ? document.body : null;

            if (opener && opener !== body && opener.isConnected && ! panel?.contains?.(opener)) {
                return opener;
            }

            const toggles = this.$el?.querySelectorAll?.('[data-wk-sidebar-toggle]') ?? [];

            let fallback;

            for (const toggle of toggles) {
                if (! toggle.isConnected) {
                    continue;
                }

                if (this.drawerId && toggle.getAttribute?.('aria-controls') === this.drawerId) {
                    return toggle;
                }

                fallback = fallback ?? toggle;
            }

            return fallback;
        },

        /**
         * Escape closes the drawer for as long as the trap is still arming.
         *
         * Held on `this` rather than in a closure for the reason the settle listener
         * records one screen up: a handle that lives only in a closure cannot be released
         * from anywhere else, and this one has three exits — the trap arming, the drawer
         * closing by any other means, and the shell being torn down mid-animation.
         *
         * Calls `preventDefault()`, as the armed trap does with the same key: focus-trap's
         * Escape handler prevents the default before it deactivates. A page listener that
         * checks `defaultPrevented` then sees the same flag whether the reader pressed Escape
         * before the trap armed or after, and the drawer does not behave differently
         * depending on how fast the reader is.
         *
         * @param {Element} panel the drawer, so focus can be returned the way the trap does
         */
        _armInterimEscape(panel) {
            if (typeof document === 'undefined' || this._onInterimEscape) {
                return;
            }

            this._onInterimEscape = (event) => {
                if (event?.key !== 'Escape' || ! this.sidebarOpen) {
                    return;
                }

                event.preventDefault?.();

                this._releaseInterimEscape();

                // The trap never activated, so `focus-trap` will not return focus for us —
                // `deactivate()` on an inactive trap is a no-op, by its own contract. The
                // drawer therefore returns focus here, through the SAME resolver the trap
                // is given, so both paths land on the same control.
                const target = this._resolveReturnFocus(panel, this._opener);

                this.sidebarOpen = false;
                target?.focus?.();
            };

            // Optional call, like every other listener in this file: a unit harness hands
            // the factory a `document` with only the fields a case needs, and a hard call
            // here would throw before the decision under test is reached.
            document.addEventListener?.('keydown', this._onInterimEscape);
        },

        /** Give the page beside the drawer back, once. Idempotent, like the releases around it. */
        _releasePageInert() {
            if (pageInertKept) {
                const keep = pageInertKept;

                pageInertKept = null;
                releasePageInert(keep);
            }
        },

        /** Idempotent: called by the arming handover, by every close, and by `destroy()`. */
        _releaseInterimEscape() {
            if (! this._onInterimEscape) {
                return;
            }

            if (typeof document !== 'undefined') {
                document.removeEventListener?.('keydown', this._onInterimEscape);
            }

            this._onInterimEscape = null;
        },

        _releaseTrap(deactivateOptions = {}) {
            // Before the early return, because the interim listener outlives the trap in
            // exactly one case: a drawer that closes while the trap is still arming has no
            // `_trap` to release and would otherwise keep a document-level keydown handler
            // for the rest of the page's life.
            this._releaseInterimEscape();
            this._releasePageInert();

            if (! this._trap) {
                return;
            }

            const trap = this._trap;

            // Cleared FIRST. `deactivate()` calls `onDeactivate`, which sets `sidebarOpen`
            // to false and would arrive back here — and a second `deactivate()` on a torn
            // down trap is where this kind of code throws.
            this._trap = null;
            trap.deactivate(deactivateOptions);

            // `_opener` is deliberately NOT cleared here, and that is load-bearing rather
            // than an omission. `focus-trap` runs its whole return-focus step — resolving
            // the node through `setReturnFocus` included — inside a `setTimeout(…, 0)`
            // (`delayReturnFocus` defaults to true, verified in the installed 8.2.2
            // source). Clearing the reference on this line would therefore delete the
            // answer before the question is asked, and the resolver would fall through to
            // the toggle in cases where it knew a better target. It is overwritten on the
            // next open and dropped in `destroy()`.
        },
    };
}
