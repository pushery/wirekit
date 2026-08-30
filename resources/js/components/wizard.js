/**
 * WireKit Wizard — the container a multi-step flow was missing.
 *
 * `<x-wirekit::stepper>` draws where you are and does not know it: it is an `<ol>` driven
 * by a `current` prop, with no state and no controls. That is correct for what it is — an
 * indicator — but it left every application to rebuild the same three things around it:
 * which step is showing, whether you may leave it, and how the change is announced.
 *
 * The third is the one that gets skipped. A step change is a whole-panel replacement with
 * no page load behind it, so a screen reader is given nothing to notice: focus has not
 * moved, no landmark changed, and the new panel simply exists. A flow that advances
 * silently has not advanced for the person who cannot see it.
 *
 * WHY THE RELEASE CONDITION IS READ FROM THE DOM RATHER THAN HELD HERE. A step's
 * completeness belongs to the form in it, which in a Livewire application lives on the
 * server and arrives as a re-render. Mirroring it into Alpine state would create a second
 * copy that is correct until the first morph. So `next()` asks the step element what it
 * says right now — the same reason the sidebar listbox re-reads its options instead of
 * caching them.
 */

/**
 * @param {Object} config
 * @param {number} [config.current] - The step showing on first render, 1-based.
 * @param {number} [config.total] - How many steps the flow has.
 * @param {string[]} [config.labels] - Step names, used in the announcement.
 * @param {string} [config.announcement] - Template with :current, :total and :label.
 */
export default function wirekitWizard(config = {}) {
    return {
        current: Number.isInteger(config.current) && config.current > 0 ? config.current : 1,
        total: Number.isInteger(config.total) && config.total > 0 ? config.total : 1,
        labels: Array.isArray(config.labels) ? config.labels : [],

        /** What a screen reader is told after a step change. Empty until one happens. */
        announcement: '',

        /**
         * May the flow leave the step it is on?
         *
         * `true` when the step says nothing, deliberately. A wizard whose steps carry no
         * condition is an ordinary next/back flow, and defaulting to "blocked" would make
         * the simplest use of this component the one that does not work.
         */
        get canAdvance() {
            const step = this.stepElement(this.current);
            if (!step) return true;

            return step.getAttribute('data-wk-step-complete') !== 'false';
        },

        get isFirst() {
            return this.current <= 1;
        },

        get isLast() {
            return this.current >= this.total;
        },

        /**
         * ⚠️ `$root`, NOT `$el`, and the difference is what made the gate inert.
         *
         * `$el` is contextual in Alpine: inside an expression evaluated from a directive on
         * the Next button it is the BUTTON, not the component. So `$el.querySelector` looked
         * for the step inside the button, found nothing, and `canAdvance` fell through to its
         * "nothing said, so yes" default — a gate that reported itself as applied and let
         * every incomplete step through. `$root` is the component root wherever it is read
         * from. Caught by the browser case; every render assertion was green throughout.
         */
        stepElement(index) {
            const root = this.$root || this.$el;
            if (!root || typeof root.querySelector !== 'function') return null;

            return root.querySelector(`[data-wk-wizard-step="${index}"]`);
        },

        next() {
            if (this.isLast || !this.canAdvance) return;

            this.goTo(this.current + 1);
        },

        prev() {
            // Going BACK is never gated. The condition guards leaving a step forward with
            // it unfinished; refusing to return to a step someone already completed would
            // trap them on the one they cannot finish.
            if (this.isFirst) return;

            this.goTo(this.current - 1);
        },

        goTo(index) {
            if (!Number.isInteger(index) || index < 1 || index > this.total) return;
            if (index === this.current) return;

            this.current = index;
            this.announce();
        },

        /**
         * Say what happened, in words rather than in a number.
         *
         * "Step 2 of 4" alone tells a reader they moved and not where to. The label is
         * included when the flow has one, which is why the template is a string with
         * placeholders rather than concatenation here — it has to be translatable, and a
         * sentence assembled from fragments cannot be.
         */
        announce() {
            const label = this.labels[this.current - 1] || '';
            const template = typeof config.announcement === 'string' && config.announcement !== ''
                ? config.announcement
                : 'Step :current of :total';

            this.announcement = template
                .replace(':current', String(this.current))
                .replace(':total', String(this.total))
                .replace(':label', label)
                .trim();
        },
    };
}
