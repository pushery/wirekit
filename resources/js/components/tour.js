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
            });
        },

        /**
         * Advance to next step or finish tour.
         */
        next() {
            if (this.currentStep < this.totalSteps - 1) {
                this.currentStep++;
                // setTimeout(0) gives Alpine a full macrotask to flush x-show
                // changes before we query element rects for positioning.
                setTimeout(() => this._positionStep(), 0);
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
                setTimeout(() => this._positionStep(), 0);
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
