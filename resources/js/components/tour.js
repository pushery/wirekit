/**
 * WireKit Tour Alpine Component.
 *
 * Step-by-step product tour overlay. Positions tooltip-like steps
 * next to target elements using Floating UI.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/
 */
import { position } from '../utils/floating.js';

/**
 * @param {Object} config - Tour configuration from Blade
 * @param {string} config.name - Unique tour identifier
 */
export default function wirekitTour(config = {}) {
    return {
        active: false,
        currentStep: 0,
        totalSteps: 0,
        _name: config.name || 'tour',
        _startHandler: null,

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
        },

        /**
         * Start the tour at step 0. Step count is deferred to here
         * because steps live inside x-if="active" and are not in the
         * DOM until activation.
         */
        start() {
            this.active = true;
            this.currentStep = 0;

            // setTimeout(0) instead of $nextTick: Alpine's x-if defers
            // DOM insertion via microtask. $nextTick (also microtask) can
            // fire BEFORE x-if has inserted the step elements. setTimeout
            // runs after all microtasks complete, guaranteeing the steps
            // are in the DOM when we query them.
            setTimeout(() => {
                this.totalSteps = this.$el.querySelectorAll('[data-wk-tour-step]').length;
                this._positionStep();
            }, 0);
        },

        /**
         * Advance to next step or finish tour.
         */
        next() {
            if (this.currentStep < this.totalSteps - 1) {
                this.currentStep++;
                this._positionStep();
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
                this._positionStep();
            }
        },

        /**
         * End the tour.
         */
        finish() {
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

            const stepEl = this.$el.querySelector(`[data-wk-tour-step="${this.currentStep}"]`);
            if (!stepEl) return;

            const targetSelector = stepEl.dataset.wkTarget;
            const placement = stepEl.dataset.wkPlacement || 'bottom';
            const targetEl = targetSelector ? document.querySelector(targetSelector) : null;

            if (targetEl && stepEl) {
                await position(targetEl, stepEl, {
                    placement,
                    offset: 12,
                });

                // Scroll target into view
                targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        },

        /**
         * Get progress text for announcement.
         */
        get progressText() {
            return `Step ${this.currentStep + 1} of ${this.totalSteps}`;
        },
    };
}
