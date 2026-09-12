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

        /*
         * A REACTIVE mirror of the current step's `data-wk-step-complete`, and the observer
         * that keeps it true.
         *
         * `canAdvance` read the attribute straight off the DOM. Alpine tracks its own
         * reactive state and nothing else, so `x-bind:aria-disabled="canAdvance ? null :
         * 'true'"` was evaluated once, at bind time, and never again — a step that BECAME
         * complete kept `aria-disabled="true"` on its Next button, and a step that became
         * incomplete kept the button announcing as available. The gate itself worked; only
         * its announcement was frozen, which is the worse half: the button says one thing
         * and does another.
         *
         * `null` means "not measured yet", which is distinct from `true` — the observer only
         * runs where there is a DOM, and `canAdvance` still has to answer in the bare
         * construction the plugin is unit-tested in.
         */
        _stepComplete: null,
        _stepObserver: null,

        /**
         * May the flow leave the step it is on?
         *
         * `true` when the step says nothing, deliberately. A wizard whose steps carry no
         * condition is an ordinary next/back flow, and defaulting to "blocked" would make
         * the simplest use of this component the one that does not work.
         */
        get canAdvance() {
            // The mirror when the observer has measured, the DOM when it has not — the
            // second path is what answers in a unit harness and before the first mutation.
            if (this._stepComplete !== null) {
                return this._stepComplete;
            }

            const step = this.stepElement(this.current);
            if (!step) return true;

            return step.getAttribute('data-wk-step-complete') !== 'false';
        },

        /**
         * Re-read the current step's completeness into reactive state.
         *
         * Called on init, on every step change, and from the observer. Assigning even the
         * same value is harmless — Alpine compares before it notifies.
         */
        _syncStepComplete() {
            const step = this.stepElement(this.current);

            this._stepComplete = step
                ? step.getAttribute('data-wk-step-complete') !== 'false'
                : true;
        },

        /**
         * Watch the steps for the one attribute this component reads off the DOM.
         *
         * Scoped to `data-wk-step-complete` on the component's own subtree, not to the
         * document: a filter this narrow costs a callback only when the thing it is about
         * actually changes, where a document-wide observer is a cost every page pays
         * forever.
         */
        initWizard() {
            this._syncStepComplete();

            const root = this.$root || this.$el;

            if (typeof MutationObserver === 'undefined' || ! root || typeof root.querySelector !== 'function') {
                return;
            }

            this._stepObserver = new MutationObserver(() => this._syncStepComplete());
            this._stepObserver.observe(root, {
                subtree: true,
                attributes: true,
                attributeFilter: ['data-wk-step-complete'],
            });
        },

        /**
         * An observer that outlives its component keeps the whole scope alive and writes
         * into it on every mutation of a subtree nobody owns any more.
         */
        destroy() {
            if (this._stepObserver) {
                this._stepObserver.disconnect();
                this._stepObserver = null;
            }
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
            if (this.isLast) return;

            /*
             * A refusal SAYS SO. It was completely silent.
             *
             * The button carries `aria-disabled` rather than `disabled`, deliberately, so it
             * stays focusable and a reader can press it and find out why — that is the whole
             * point of choosing the ARIA attribute over the HTML one. Pressing it did
             * nothing at all: no movement, no message, no change of any kind. A reader using
             * a screen reader had no way to distinguish "this step is not finished" from "the
             * button is broken".
             *
             * The live region already exists for the step announcement, so the sentence goes
             * there and is heard on the spot.
             */
            if (! this.canAdvance) {
                this.announceRefusal();

                return;
            }

            this.goTo(this.current + 1);
        },

        /**
         * Say why the flow did not move.
         *
         * A separate method from `announce()` because the two are different sentences with
         * different triggers, and because re-announcing an unchanged string is a no-op in a
         * live region — pressing Next twice has to speak twice. The counter appended to the
         * message is what makes the second press a change; it is not read aloud as a number
         * because a trailing zero-width space carries no glyph and no announcement.
         */
        announceRefusal() {
            const template = typeof config.incompleteAnnouncement === 'string' && config.incompleteAnnouncement !== ''
                ? config.incompleteAnnouncement
                : 'This step is not complete yet.';

            const label = this.labels[this.current - 1] || '';

            const message = template
                .replace(':current', String(this.current))
                .replace(':total', String(this.total))
                .replace(':label', label)
                .trim();

            this._refusals = (this._refusals || 0) + 1;

            // A live region announces a CHANGE. The same string assigned again is not one,
            // so a second refused press would be silent — which is exactly the moment a
            // reader is most likely to try again.
            this.announcement = message + '\u200b'.repeat(this._refusals % 2);
        },

        _refusals: 0,

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
            this._syncStepComplete();
            this.announce();

            // The control that asked for the step is deliberately NOT read here and handed
            // on. Whether it survived the change is only knowable after the bindings have
            // settled, which is a tick later — so the rescue asks then, and asks the one
            // question that answers it: is focus on `<body>`?
            this.rescueFocus();
        },

        /**
         * Put focus back on something real when the control that moved the flow hid itself.
         *
         * The endpoint controls carry `hidden` — Back on the first step, Next on the last —
         * and so does the shape the documentation teaches for a `controls` slot, where a
         * submit button takes Next's place on the final step. So the button that just took
         * the click or the Enter is regularly the button the new state removes, and an
         * element hidden while it holds focus drops focus onto `<body>`: the next Tab
         * restarts at the top of the document, and the sentence the live region has just
         * written describes a step the reader was thrown out of. It is the same loss
         * `aria-disabled` is used instead of `disabled` to avoid, one state later.
         *
         * Only the loss is repaired. Focus that survived the change belongs to whatever is
         * holding it — moving it on every step would take it off a control that stayed, and
         * off a field the reader had reached on their own.
         */
        rescueFocus() {
            // Neither exists in the bare construction the plugin is unit-tested in, and a
            // page without Alpine's tick helper has nowhere to wait for the binding to
            // settle — the button is still focused at the moment `current` changes.
            if (typeof document === 'undefined' || typeof this.$nextTick !== 'function') return;

            this.$nextTick(() => {
                // `<body>` is where a browser leaves focus when the focused element is
                // hidden. Anything else means the control survived, and it keeps focus.
                const active = document.activeElement;
                if (active && active !== document.body) return;

                const panel = this.stepElement(this.current);
                if (!panel || typeof panel.focus !== 'function') return;

                // The panel the reader was moved to, rather than the opposite control: it
                // is the top of what changed, and it is the one target that exists whatever
                // the controls slot was replaced with.
                panel.focus();
            });
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
