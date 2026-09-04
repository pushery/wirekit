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
import { prefersReducedMotion } from '../utils/motion.js';

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
            this._releaseFocus({ returnFocus: false });
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
            // sitting on that hidden element in the meantime.
            this._releaseFocus();

            this.active = false;
            this.currentStep = 0;
            this.totalSteps = 0;
        },

        /**
         * Dismiss the tour (via ESC or skip button).
         */
        dismiss() {
            this.finish();
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
                await position(targetEl, stepEl, {
                    placement,
                    offset: 12,
                });

                // Same reason as scroll-to-top: an explicit `behavior` argument wins
                // over the CSS reduced-motion rule, so it has to ask itself.
                targetEl.scrollIntoView({
                    behavior: prefersReducedMotion() ? 'auto' : 'smooth',
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
         */
        _focusStep() {
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

            this._trap = createFocusTrap(stepEl, {
                // The Blade template keeps a window-level Escape handler so a press
                // still dismisses when focus has fallen to the body; letting the
                // trap deactivate on Escape as well would run the teardown twice.
                escapeDeactivates: false,
                initialFocus: stepEl,
                // Every step after the first activates while the PREVIOUS panel
                // holds focus, so focus-trap's own record of "what was focused
                // before" points at a step that is hidden by the time the tour
                // ends. The opener is the only sensible destination.
                setReturnFocus: () => (
                    this._opener && this._opener.isConnected ? this._opener : document.body
                ),
            });

            this._trap.activate();
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
