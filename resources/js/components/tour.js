/**
 * WireKit Tour Alpine Component.
 *
 * Step-by-step product tour overlay. Positions tooltip-like steps
 * next to target elements using Floating UI.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/
 */
import { createFocusTrap } from '../utils/focus-trap.js';
import { position } from '../utils/floating.js';
import { isComposing } from '../utils/ime.js';
import { holdPageInert, inOverlayRoot, releasePageInert } from '../utils/overlay.js';
import { prefersReducedMotion, scrollBehavior } from '../utils/motion.js';

/**
 * @param {Object} config - Tour configuration from Blade
 * @param {string} config.name - Unique tour identifier
 * @param {string} [config.announcement] - Progress template with :current and :total
 */
export default function wirekitTour(config = {}) {
    return {
        active: false,
        currentStep: 0,
        totalSteps: 0,
        _name: config.name || 'tour',
        _startHandler: null,
        _trap: null,
        _opener: null,
        // Whether this tour holds one of the counted holds that keep the page behind it inert
        // (see `holdPageInert` in utils/overlay.js). One hold for the whole tour, taken once the
        // first step's trap has recorded the opener, given back before focus returns to it.
        _holdsPageInert: false,
        // Ends both observers of the current step: the one that puts its placement back after a
        // framework update erases it, and the one that follows its target on scroll. See the
        // notes beside `repairErasure` and `autoReposition` in _positionStep().
        _stopRepair: null,

        init() {
            // Listen for programmatic start — store reference for cleanup
            this._startHandler = () => this.start();
            window.addEventListener(`wirekit-tour-start-${this._name}`, this._startHandler);
        },

        /**
         * Cleanup on Alpine component teardown. Removes the window event
         * listener to prevent accumulation on Livewire SPA navigation.
         */
        destroy() {
            if (this._startHandler) {
                window.removeEventListener(`wirekit-tour-start-${this._name}`, this._startHandler);
                this._startHandler = null;
            }

            // A tour torn down mid-flight (SPA navigation away from the page it
            // runs on) would otherwise leave an active focus trap behind, holding
            // its own document listeners and a reference to a detached panel.
            this._releasePageInert();
            this._releaseFocus({ returnFocus: false });

            this._stopRepair?.();
            this._stopRepair = null;
        },

        /**
         * Start the tour at step 0.
         */
        start() {
            this.active = true;
            this.currentStep = 0;

            // x-teleport + x-show keeps steps in the DOM at all times,
            // so $nextTick is sufficient (no setTimeout needed).
            this.$nextTick(() => {
                // Ended inside the tick: there is no step to show any more.
                if (! this.active) return;

                const overlay = this.$refs.overlay;
                if (!overlay) return;
                this.totalSteps = overlay.querySelectorAll('[data-wk-tour-step]').length;
                this._positionStep();
                this._focusStep();
            });
        },

        /**
         * Advance to next step or finish tour.
         */
        next() {
            if (this.currentStep < this.totalSteps - 1) {
                this.currentStep++;
                // setTimeout(0) gives Alpine a full macrotask to flush x-show
                // changes before we query element rects for positioning. Focus
                // moves in the same macrotask and for the same reason: a panel
                // that x-show has not yet revealed cannot take focus.
                setTimeout(() => {
                    if (! this.active) return;
                    this._positionStep();
                    this._focusStep();
                }, 0);
            } else {
                this.finish();
            }
        },

        /**
         * Go back one step.
         */
        prev() {
            if (this.currentStep > 0) {
                this.currentStep--;
                setTimeout(() => {
                    if (! this.active) return;
                    this._positionStep();
                    this._focusStep();
                }, 0);
            }
        },

        /**
         * End the tour.
         */
        finish() {
            // Before the panels go hidden: releasing afterwards would deactivate
            // a trap whose container is already display:none, and focus would be
            // sitting on that hidden element in the meantime. The page first, so
            // the opener focus returns to is no longer inert.
            this._releasePageInert();
            this._releaseFocus();

            this.active = false;
            this.currentStep = 0;
            this.totalSteps = 0;

            this._stopRepair?.();
            this._stopRepair = null;
        },

        /**
         * Dismiss the tour (via ESC or skip button).
         */
        dismiss() {
            this.finish();
        },

        /**
         * Escape anywhere while the tour runs ends it, and that press is spent.
         *
         * Heard on the window in the capture phase. A step sits above everything on the page,
         * an open modal or drawer included, and both of those read Escape later, on the
         * document and on the window: the tour holds a trap of its own, so theirs is paused,
         * but a modal's window listener would still take the same press and close as well.
         * Marked, the press ends the tour and nothing else. Heard wherever the focus is, so a
         * press still ends the tour when the focus has fallen to the body.
         *
         * @param {KeyboardEvent} event
         */
        escapeTour(event) {
            // An Escape that abandons an input method's conversion belongs to the input method.
            if (isComposing(event)) return;

            if (! this.active) return;

            event?.preventDefault();
            this.dismiss();
        },

        /**
         * Position the current step popup near its target element.
         */
        async _positionStep() {
            await this.$nextTick();

            const overlay = this.$refs.overlay;
            if (!overlay) return;

            const stepEl = overlay.querySelector(`[data-wk-tour-step="${this.currentStep}"]`);
            if (!stepEl) return;

            const targetSelector = stepEl.dataset.wkTarget;
            const placement = stepEl.dataset.wkPlacement || 'bottom';
            const targetEl = targetSelector ? document.querySelector(targetSelector) : null;

            if (targetEl && stepEl) {
                // Every step re-places a DIFFERENT element, so the previous step's observer is
                // dropped here rather than only on finish().
                this._stopRepair?.();
                this._stopRepair = null;

                const placed = await position(targetEl, stepEl, {
                    placement,
                    offset: 12,

                    // Everything this call writes is inline style, and a framework update patches
                    // the step against its own template, whose `style` attribute carries none of
                    // it: after a refresh the same node is still shown, with no `top` and the same
                    // box.
                    //
                    // The unchanged box is why `repairErasure` is needed beside `autoReposition`
                    // below: no resize means `autoUpdate` sees nothing, because it observes boxes
                    // rather than the style attribute.
                    //
                    // A tour is the longest-lived overlay in the catalog — it stands over the page
                    // for as many steps as it has, and the page underneath keeps working. The
                    // whole point of a step is that it POINTS AT something, and a step that has
                    // slid to the end of the document is telling the reader about an element they
                    // cannot see, while the focus trap still holds them inside it.
                    repairErasure: true,
                    // It also follows its target: the placement is viewport-relative (`fixed`),
                    // and the step scrolls its target into view right after this call, so without
                    // this the step would stay where the target was before that scroll, and after
                    // any scroll of the reader's own. The same `stop()` ends both observers.
                    autoReposition: true,
                });

                if (placed && typeof placed.stop === 'function') {
                    // Compared against the step rather than merely checking `active`: the reader
                    // may already have advanced while this placement was in flight, and that step
                    // owns the observer now.
                    if (this.active && this.currentStep === Number(stepEl.dataset.wkTourStep)) {
                        this._stopRepair = placed.stop;
                    } else {
                        placed.stop();
                    }
                }

                // Same reason as scroll-to-top: an explicit `behavior` argument wins
                // over the CSS reduced-motion rule, so it has to ask itself.
                targetEl.scrollIntoView({
                    behavior: scrollBehavior(!prefersReducedMotion()),
                    block: 'center',
                });
            }
        },

        /**
         * Move focus into the current step and hold it there.
         *
         * A step is a `role="dialog"` behind a full-viewport scrim, so the dialog
         * contract applies in full: focus starts inside it, Tab cycles within it,
         * and the element that opened the tour gets focus back at the end. Without
         * this the page's own tab order stays live UNDER a scrim that covers it —
         * and because the steps are teleported to the end of the document, a
         * keyboard reader would have to walk the entire covered page to reach the
         * Back and Next buttons of the step they are looking at.
         *
         * Focus lands on the panel itself rather than on Next, so the step's name
         * and body are announced ahead of its controls; the panel carries
         * `tabindex="-1"` for exactly that.
         *
         * The trap is rebuilt per step because a tour walks between SIBLING
         * dialogs. Leaving the previous step's trap active would keep pulling
         * focus back into a panel that is now hidden.
         *
         * Body scroll is deliberately NOT locked, which is the one place a tour
         * departs from the modal overlays. A tour exists to point at elements on
         * the page and scrolls each target into view itself, so pinning the
         * document would defeat the component. Focus-trap's `preventScroll` keeps
         * the two from fighting: the focus call moves no scroll position of its own.
         *
         * The page behind is inert all the same. `inert` takes the page out of reach,
         * not out of scrolling, so the tour still brings each target into view.
         */
        _focusStep() {
            // Every caller runs a tick or a task after the step changed, and the tour can end in
            // between. `finish()` found no trap and no hold then, so taking them now would leave
            // a trap on a hidden step and the page inert behind nothing.
            if (! this.active) return;

            const overlay = this.$refs.overlay;
            if (!overlay) return;

            const stepEl = overlay.querySelector(`[data-wk-tour-step="${this.currentStep}"]`);
            if (!stepEl) return;

            // Captured on the first step only. From the second step onward the
            // focused element IS the previous panel, which is about to be hidden,
            // so re-reading it would record a destination that cannot take focus.
            if (!this._opener) {
                this._opener = document.activeElement;
            }

            // Old trap first, and without restoring focus — the new one is about
            // to take it, and letting both act would move focus twice.
            this._releaseFocus({ returnFocus: false });

            // The destination is fixed when the trap is made. focus-trap asks for it a task
            // after `deactivate()`, and by then ending the tour has cleared `_opener`.
            const opener = this._opener;

            this._trap = createFocusTrap(stepEl, {
                // The Blade template keeps a window-level Escape handler so a press
                // still dismisses when focus has fallen to the body (`escapeTour()`);
                // letting the trap deactivate on Escape as well would run the teardown
                // twice.
                escapeDeactivates: false,
                initialFocus: stepEl,
                // Every step after the first activates while the PREVIOUS panel
                // holds focus, so focus-trap's own record of "what was focused
                // before" points at a step that is hidden by the time the tour
                // ends. The opener is the only sensible destination.
                setReturnFocus: () => (
                    opener && opener.isConnected ? opener : document.body
                ),
            });

            this._trap.activate();

            if (! this._holdsPageInert && inOverlayRoot(stepEl)) {
                this._holdsPageInert = holdPageInert();
            }
        },

        /** Give back the hold on the page behind the tour, once. */
        _releasePageInert() {
            if (this._holdsPageInert) {
                this._holdsPageInert = false;
                releasePageInert();
            }
        },

        /**
         * Release the current step's focus trap.
         *
         * @param {Object} [options]
         * @param {boolean} [options.returnFocus=true] - Whether focus goes back to
         *   whatever opened the tour. False on a step change, where the next step's
         *   trap takes focus immediately afterwards.
         */
        _releaseFocus({ returnFocus = true } = {}) {
            if (this._trap) {
                this._trap.deactivate({ returnFocus });
                this._trap = null;
            }

            if (returnFocus) {
                this._opener = null;
            }
        },

        /**
         * Get progress text for announcement.
         *
         * A TEMPLATE with placeholders rather than a sentence built here, because it has
         * to be translatable and a sentence assembled from fragments in JavaScript cannot
         * be — word order is not the same in every language. This read as a literal for a
         * while, and the cost was quiet: the sentence goes into a live region and onto the
         * visible progress line, so every reader of a German or Spanish application heard
         * and saw English on every step change, from a component whose Blade half was
         * fully translated. The fallback keeps a tour constructed without a template
         * working rather than announcing an empty string.
         */
        get progressText() {
            const template = typeof config.announcement === 'string' && config.announcement !== ''
                ? config.announcement
                : 'Step :current of :total';

            return template
                .replace(':current', String(this.currentStep + 1))
                .replace(':total', String(this.totalSteps));
        },
    };
}
