import { createFocusTrap } from '../utils/focus-trap.js';

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
 * Until this factory existed the shell carried `x-data="{ sidebarOpen: false }"` and
 * nothing else, so the drawer half of that contract was simply absent. Measured by an
 * adopting application at 375px with the drawer open: `role` null, `aria-modal` null, the
 * toggle's `aria-controls` null, and Escape did nothing — visibility stayed `visible`.
 *
 * ⚠️ The failure that makes it worth a factory rather than a few attributes is asymmetric,
 * and asymmetry is why it looked fine to everyone who tried it with a mouse. The
 * `bg-black/50` backdrop blocks POINTERS from the page behind it. It does not block the
 * keyboard. So focus walked on through controls the backdrop was covering — visible to
 * nobody, operable by exactly the people who cannot see where focus went.
 *
 * The width test is asked of `matchMedia`, not of a class, because ARIA cannot be set from
 * a media query: `role` and `aria-modal` are attributes, and an attribute has one value at
 * a time regardless of how wide the window is.
 *
 * @param {Object} config
 * @param {string} [config.drawerId]  id the drawer carries and the toggle points at
 */
export default function wirekitAppShell(config = {}) {
    return {
        sidebarOpen: false,

        /** The id the toggle's `aria-controls` names. Empty when the shell has no drawer. */
        drawerId: config.drawerId || '',

        /** The `lg` query, or null on a host without media queries. */
        _viewport: null,

        _trap: null,

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
                    this._releaseTrap();
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

        destroy() {
            // Release before dropping the reference. A Livewire navigation replaces the
            // shell, and a trap still armed over a detached node keeps its document-level
            // keydown listener: every Tab on the NEXT page would be pulled back toward an
            // element that is no longer in it.
            this._releaseTrap();

            if (this._armTimer) {
                clearTimeout(this._armTimer);
                this._armTimer = null;
            }

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

            const trap = this._createTrap(panel, {
                // Escape is the drawer's own close, so the trap's deactivation and the
                // state have to agree — otherwise the panel slides away with `sidebarOpen`
                // still true and the next click on the toggle closes an already-closed
                // drawer.
                // Named EXPLICITLY rather than left to the library's own tabbable scan.
                //
                // That scan runs once, at `activate()`, and on this panel it kept coming up
                // empty — the trap then reported `active: true` and never moved focus, at
                // any delay we tried. A function resolves at activation time and asks the
                // DOM directly, which is the same question with a reliable answer.
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
                onDeactivate: () => {
                    this._trap = null;
                    this.sidebarOpen = false;
                },
            });

            this._trap = trap;

            // Activated on the NEXT tick, not here, and that is the whole difference
            // between a trap that exists and a trap that works.
            //
            // The watcher fires the moment `sidebarOpen` changes — BEFORE Alpine has
            // applied the class that makes the panel visible. `focus-trap` looks for
            // something focusable at `activate()` time, finds nothing in a subtree that is
            // still `visibility: hidden`, and falls back to the container, which cannot
            // take focus either. The trap then exists, reports itself active, and focus
            // stays on `<body>`.
            //
            // Measured in a browser, and only there: `_trap` truthy, `isDrawer` true, two
            // focusable elements inside the panel, and `document.activeElement` still
            // `BODY`. Every ESM case passed throughout — they construct the state machine,
            // and this is a question about paint order.
            //
            // `$nextTick` is Alpine's; where it is absent (a unit harness) activation is
            // immediate, which is what those cases already assert.
            // A COUNTER, not an object comparison — and that distinction is the whole bug
            // this line used to be.
            //
            // The guard read `this._trap !== trap`, which looks obviously correct and is
            // never true: Alpine keeps component data behind a reactive Proxy, so reading
            // `this._trap` hands back a proxied wrapper that is not identical to the raw
            // trap that was just stored. The guard therefore rejected every activation, the
            // trap sat created-but-never-armed, and the symptom was a `role="dialog"` panel
            // with focus still on `<body>`.
            //
            // It took instrumenting `activate` itself to see: the log showed `trap-created`
            // and never `activate-called`. Everything before that read like a timing
            // problem, and two plausible timing fixes changed nothing — because the call
            // was not late, it was not happening.
            const arming = (this._arm = (this._arm ?? 0) + 1);

            const activate = () => {
                // A newer arm, or a drawer that closed inside the wait. Either way this one
                // is stale, and activating it would trap focus in a panel on its way out.
                if (this._arm !== arming || ! this.sidebarOpen) {
                    return;
                }

                trap.activate();
            };

            // WHEN to activate is the whole problem, and it took a browser to see it.
            //
            // `focus-trap` computes its tabbables once, at `activate()`. Called while the
            // panel is still translated out and mid-transition, it finds none, focuses
            // nothing, and then reports `active: true` forever after — a trap that exists,
            // claims to be armed, and never moved focus. Measured: `_trap` truthy,
            // `active: true`, two visible tabbable links in the panel, and
            // `document.activeElement` still `BODY` at 0, 50, 150, 400 and 900 ms.
            //
            // `$nextTick` is too early. Two animation frames are too early. What works —
            // proven by deactivating and re-activating the SAME trap once the panel is laid
            // out, which puts focus on the first link — is to wait for the panel to finish
            // arriving.
            //
            // So: the drawer's own `transitionend`, with a timer behind it because
            // `transitionend` does not fire for a zero-duration transition, for
            // `prefers-reduced-motion`, or when the browser coalesces the frame. Whichever
            // comes first wins; `activate` is idempotent against its own guard.
            const settle = () => {
                panel.removeEventListener?.('transitionend', settle);
                clearTimeout(this._armTimer);
                this._armTimer = null;
                activate();
            };

            panel.addEventListener?.('transitionend', settle);
            this._armTimer = setTimeout(settle, 350);
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

        _releaseTrap() {
            if (! this._trap) {
                return;
            }

            const trap = this._trap;

            // Cleared FIRST. `deactivate()` calls `onDeactivate`, which sets `sidebarOpen`
            // to false and would arrive back here — and a second `deactivate()` on a torn
            // down trap is where this kind of code throws.
            this._trap = null;
            trap.deactivate();
        },
    };
}
