import { onFormReset } from '../utils/form-reset.js';
import { requiredCheckState } from '../utils/required-check.js';
import { watchModelEvents } from '../utils/model-events.js';
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
 * Lifecycle resources held on `this`, released in destroy():
 *   - _stopServerSync — the observer of the server's value attribute.
 *   - _modelEvents — `change` and `blur` on the hidden input for `wire:model.change` and
 *     `wire:model.blur`, which listen on that input alone (utils/model-events.js): `change` with
 *     every score chosen, as a radio button fires it, and `blur` when the reader leaves the row.
 *   - _stopFormReset — the listener that puts the starting score back when the form is reset
 *     (utils/form-reset.js).
 *
 * @param {Object} config
 * @param {number} config.value      the initial rating
 * @param {number} config.max        the highest selectable rating
 * @param {boolean} config.clearable whether picking the current score again returns to 0
 * @param {boolean} config.disabled  whether the rating is out of use: no pick and no step changes it
 */
export default function wirekitRating(config = {}) {
    return {
        // Handles set while the component runs, declared so that they are its own: Alpine stores a
        // property no scope declares on the outermost scope around the component.
        _stopServerSync: null,
        _modelEvents: null,
        _stopFormReset: null,

        // `requiredMessage` and `onRequiredInvalid()`: a required rating stops an empty submit
        // (utils/required-check.js).
        ...requiredCheckState(),

        // The score a form reset returns to: the one the page started with, or the one the server
        // sent last, as a native field returns to the value the server rendered.
        _resetValue: Number(config.value) || 0,

        rating: Number(config.value) || 0,
        hovered: 0,
        _max: Number(config.max) || 5,
        clearable: Boolean(config.clearable),
        // Mirrors the component's `disabled` prop. The view binds no handler then; the methods
        // refuse as well, so a call that reaches them another way changes nothing either.
        disabled: config.disabled === true,

        init() {
            // Seed from the server attribute when the caller passed none.
            //
            // The value is not interpolated into `x-data`, so that attribute stays
            // byte-identical across renders. A morph that changed it would make
            // Alpine initialize the component again (a new scope before Alpine 3.16,
            // the same one reset to the seed from 3.16), and an effect queued before
            // the morph would then write the old value last. That shows only when
            // the value returns to one it already held. With `x-data` unchanged the
            // scope survives, and the observer below is the one path a server-side
            // change travels.
            //
            // `$root` is capability-checked, not assumed. Alpine hands a real element
            // here, but `test-server-value-seed.mjs` constructs this factory with a
            // barren stub that answers `getAttribute` and nothing else, or nothing at
            // all, and a factory needing more than it uses turns that into a TypeError
            // at init.
            if (config.value == null) {
                const seed = typeof this.$root?.getAttribute === 'function'
                    ? this.$root.getAttribute(WK_SERVER_VALUE_ATTRIBUTE)
                    : null;

                if (seed != null && seed !== '') {
                    this.rating = Number(seed) || 0;
                }
            }

            this._resetValue = this.rating;

            // A value the server changed has to reach the stars. Alpine read the
            // seed once and will not read it again, and this component binds its
            // hidden input with `:value` — so Alpine writes its own stale number
            // back over whatever Livewire's morph put there. Watching the input
            // would be racing that binding; the server gets its own attribute,
            // which nothing else writes.
            this._stopServerSync = observeServerValue(this.$root, (value) => {
                const next = Number(value);

                // Guarded twice: a non-number would blank the row, and a server
                // value equal to the rating on screen (the reader's own pick coming
                // back from the server) needs nothing done beyond becoming what a
                // reset returns to.
                if (Number.isNaN(next)) {
                    return;
                }

                this._resetValue = next;

                if (next === this.rating) {
                    return;
                }

                this.rating = next;
            });

            this._modelEvents = watchModelEvents(this.$root, () => this._hiddenInput());
            this._stopFormReset = onFormReset(this.$root, () => this._hiddenInput(), () => this._restore());
        },

        destroy() {
            this._stopServerSync?.();
            this._modelEvents?.dispose();
            this._modelEvents = null;
            this._stopFormReset?.();
            this._stopFormReset = null;
        },

        /** Back to the starting score after a form reset; the hidden input follows its binding. */
        _restore() {
            this.hovered = 0;
            this.rating = this._resetValue;
            this.requiredMessage = '';
        },

        /** What the required check reads: empty exactly while no score is chosen. */
        get requiredValue() {
            return this.rating > 0 ? String(this.rating) : '';
        },

        /** The star a reader tabs to, which is where an empty required rating sends the focus. */
        _focusRequiredControl() {
            const root = this.$root;
            const star = typeof root?.querySelector === 'function'
                ? (root.querySelector('[role="radio"][tabindex="0"]') ?? root.querySelector('[role="radio"]'))
                : null;

            star?.focus();
        },

        /** The hidden input `wire:model` is bound to, or null where there is none to find. */
        _hiddenInput() {
            return typeof this.$root?.querySelector === 'function' ? this.$root.querySelector('input[type=hidden]') : null;
        },

        /**
         * Pick a value, and tell a plain HTML form about it.
         *
         * The hidden input is what a non-Livewire form submits, and assigning
         * `rating` alone does not fire an input event on it — so without this
         * dispatch a plain form silently submits the value the page loaded with.
         */
        select(value) {
            if (this.disabled) {
                return;
            }

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
            if (this.disabled || this.rating >= this._max) {
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
            if (this.disabled || this.rating <= (this.clearable ? 0 : 1)) {
                return;
            }

            this.rating--;
            this._notify();
            this._focus(this.$el.previousElementSibling);
        },

        /** Home / End — jump to either end of the scale. */
        selectFirst() {
            if (this.disabled) {
                return;
            }

            this.rating = 1;
            this._notify();
            this._focus(this.$el.parentElement && this.$el.parentElement.firstElementChild);
        },

        selectLast() {
            if (this.disabled) {
                return;
            }

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

        /**
         * Write the score into the hidden field and fire `input` there, for `wire:model`.
         *
         * The field is bound with `:value`, and Alpine writes a binding a microtask later. Fired
         * right after the assignment, the event found the field still holding the previous score,
         * which is what `wire:model` reads, so the server always received the star before the one
         * chosen. So the value is written here as well, before the event.
         */
        _notify() {
            const root = this.$el.closest('[x-data]');
            const hidden = root ? root.querySelector('input[type=hidden]') : null;

            if (hidden) {
                hidden.value = String(this.rating);
                hidden.dispatchEvent(new Event('input', { bubbles: true }));
            }

            // A chosen score is committed at once, as a radio button commits its choice.
            this._modelEvents?.commit();
        },
    };
}
