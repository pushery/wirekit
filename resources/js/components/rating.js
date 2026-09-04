import { observeServerValue, WK_SERVER_VALUE_ATTRIBUTE } from '../utils/server-value.js';

/**
 * Rating — an interactive star row implementing the WAI-ARIA radio-group
 * keyboard model.
 *
 * The state is two numbers and the interactions are short, so this lived
 * inline in the template. It cannot: Alpine's CSP build parses expressions
 * rather than compiling them, and every one of these handlers used something
 * outside that grammar — several statements in a row, an `if` block, an arrow
 * function, optional chaining. Under a strict Content-Security-Policy the stars
 * rendered and did nothing at all.
 *
 * Arrow-key navigation MOVES FOCUS as well as changing the value. That is not
 * decoration: in a radio-group the selected option is the only tab stop, so a
 * value change that leaves focus behind strands the user on an element that is
 * no longer the one they selected.
 *
 * ── Clearing, and why it is opt-in ──
 *
 * A radiogroup has no way back to "nothing chosen": a native radio cannot be
 * deselected from the keyboard, and this component follows that model on
 * purpose. But a rating is used as a filter facet and as an optional form field
 * far more often than a radiogroup is, and in both of those "no opinion" has to
 * survive a mis-click — otherwise the server receives a score nobody meant to
 * give, and the reader has no way to take it back.
 *
 * `clearable` reconciles the two by staying off. With it off, every path below
 * behaves exactly as it always has. With it on, the zero state the template
 * already renders becomes reachable again by the two routes a reader would try:
 * clicking the star that is already chosen, and stepping down past the first
 * one.
 *
 * @param {Object} config
 * @param {number} config.value      the initial rating
 * @param {number} config.max        the highest selectable rating
 * @param {boolean} config.clearable whether picking the current score again returns to 0
 */
export default function wirekitRating(config = {}) {
    return {
        rating: Number(config.value) || 0,
        hovered: 0,
        _max: Number(config.max) || 5,
        clearable: Boolean(config.clearable),

        init() {
            // Seed from the server attribute when the caller passed none.
            //
            // The seed used to be interpolated into `x-data`. That looked free —
            // Alpine reads the attribute once — but a Livewire morph REWRITES it,
            // and Alpine re-initializes on the change. Measured on the sibling
            // component by object identity: the scope is replaced on every round
            // trip, and an effect queued against the pre-morph scope flushes
            // afterwards and writes the pre-morph value LAST. It is invisible on
            // an outward change (old and new agree) and shows only when the value
            // returns to one it already held.
            //
            // Reading it here keeps the attribute byte-identical across renders,
            // so the scope survives and the observer below is the single path a
            // server-side change travels — which is what its own comment claims.
            // `$root` is capability-checked, not assumed. Alpine hands a real element
            // here, but the ESM harness constructs each factory with a deliberately
            // barren stub — `test-tabs.mjs` passes `{ querySelectorAll }` and nothing
            // else, on purpose — and a factory that requires more than it uses turns
            // that into a TypeError at init. Measured: one of 63 ESM scripts, red in
            // CI and invisible to the PHP suite, which does not run them.
            if (config.value == null) {
                const seed = typeof this.$root?.getAttribute === 'function'
                    ? this.$root.getAttribute(WK_SERVER_VALUE_ATTRIBUTE)
                    : null;

                if (seed != null && seed !== '') {
                    this.rating = Number(seed) || 0;
                }
            }

            // A value the server changed has to reach the stars. Alpine read the
            // seed once and will not read it again, and this component binds its
            // hidden input with `:value` — so Alpine writes its own stale number
            // back over whatever Livewire's morph put there. Watching the input
            // would be racing that binding; the server gets its own attribute,
            // which nothing else writes.
            this._stopServerSync = observeServerValue(this.$root, (value) => {
                const next = Number(value);

                // Guarded twice: a non-number would blank the row, and an
                // unchanged value arrives on every morph, including the ones
                // that must not disturb a choice just made.
                if (Number.isNaN(next) || next === this.rating) {
                    return;
                }

                this.rating = next;
            });
        },

        destroy() {
            this._stopServerSync?.();
        },

        /**
         * Pick a value, and tell a plain HTML form about it.
         *
         * The hidden input is what a non-Livewire form submits, and assigning
         * `rating` alone does not fire an input event on it — so without this
         * dispatch a plain form silently submits the value the page loaded with.
         */
        select(value) {
            this.rating = this.clearTarget(value);
            this._notify();
        },

        /**
         * What picking `value` should set the rating to.
         *
         * Split out of `select()` because the optimistic path cannot use
         * `select()`: it writes through the optimistic layer's `run()`, which
         * takes the value it should show while the request is in flight. That
         * layer lives in a nested Alpine scope and knows nothing about
         * clearing, so the template calls `run(clearTarget(N))` and the decision
         * stays here — one implementation, two entry points, rather than a
         * toggle spelled out once per star in the markup.
         *
         * Picking the star that is already chosen is the clear gesture. It is
         * the one a reader tries unprompted, it needs no extra control on the
         * page, and it costs nothing when `clearable` is off: the assignment is
         * a no-op that was already happening.
         */
        clearTarget(value) {
            return this.clearable && this.rating === value ? 0 : value;
        },

        /** Raise by one and follow with focus, unless already at the top. */
        stepUp() {
            if (this.rating >= this._max) {
                return;
            }

            this.rating++;
            this._notify();
            this._focus(this.$el.nextElementSibling);
        },

        /**
         * Lower by one and follow with focus, unless already at the bottom.
         *
         * Where the bottom is depends on `clearable`. Off, it is 1 and the
         * radiogroup model holds. On, it is 0 — the state the control renders
         * before anyone has touched it, so stepping down from one star lands
         * back in a state the markup already had rather than in a new one.
         *
         * Focus does not move on that last step: star 1 has no previous
         * sibling, so `_focus` declines, and the roving tabindex puts the tab
         * stop back on star 1 at a rating of 0. The reader keeps the element
         * they were on, which is the only outcome that does not strand them.
         */
        stepDown() {
            if (this.rating <= (this.clearable ? 0 : 1)) {
                return;
            }

            this.rating--;
            this._notify();
            this._focus(this.$el.previousElementSibling);
        },

        /** Home / End — jump to either end of the scale. */
        selectFirst() {
            this.rating = 1;
            this._notify();
            this._focus(this.$el.parentElement && this.$el.parentElement.firstElementChild);
        },

        selectLast() {
            this.rating = this._max;
            this._notify();
            this._focus(this.$el.parentElement && this.$el.parentElement.lastElementChild);
        },

        /**
         * Focus after the DOM settles.
         *
         * The star that should receive focus may only become focusable once the
         * new rating has rendered, so this waits a tick rather than reaching for
         * an element that is still the old one.
         */
        _focus(element) {
            if (! element || typeof element.focus !== 'function') {
                return;
            }

            this.$nextTick(() => element.focus());
        },

        /** Fire `input` on the hidden field so plain-HTML forms see the change. */
        _notify() {
            const root = this.$el.closest('[x-data]');
            const hidden = root ? root.querySelector('input[type=hidden]') : null;

            if (hidden) {
                hidden.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },
    };
}
