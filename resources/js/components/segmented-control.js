import { observeServerValue, WK_SERVER_VALUE_ATTRIBUTE } from '../utils/server-value.js';

/**
 * Segmented control — a radiogroup that behaves like the native one it imitates.
 *
 * The handlers moved out of the template because none of the three parsed under
 * Alpine's CSP build: the click handler was three statements and a `new`, and
 * both arrow handlers used optional chaining. Under a strict
 * Content-Security-Policy the segments rendered, took focus, and did nothing.
 *
 * Moving them here also closed the gap between what the component did and what
 * its documentation promised, which was wider than the CSP problem:
 *
 *   - ArrowDown / ArrowUp / Home / End were documented and never bound at all.
 *   - Neither arrow wrapped. On the last segment ArrowRight reached for a
 *     nextElementSibling that does not exist and stopped; on the FIRST segment
 *     ArrowLeft reached backwards into the hidden input that carries the form
 *     value — not focusable, so the key simply died there. Native radio groups
 *     wrap in both directions, and so does the WAI-ARIA radio pattern.
 *
 * Navigation is written against the segments themselves rather than sibling
 * traversal, which is what made the hidden input reachable in the first place.
 *
 * @param {Object} config
 * @param {string} config.selected  the option value selected at render time
 */
export default function wirekitSegmentedControl(config = {}) {
    return {
        selected: config.selected != null ? String(config.selected) : '',

        init() {
            // Seed from the server attribute when the caller passed nothing.
            //
            // The seed used to be interpolated into `x-data`, which looked
            // harmless because Alpine reads that attribute once. A Livewire morph
            // REWRITES it, though, and Alpine re-initializes on the change — so
            // every round trip replaced the scope, and an effect queued against
            // the old one flushed afterwards and wrote the old value last. That
            // is invisible on an outward click (old and new agree) and shows up
            // only when a reader returns to a value they already had.
            //
            // Reading it here instead keeps the attribute byte-identical across
            // renders, so the scope survives and observeServerValue is the single
            // path a server-side change travels — which is what its own docblock
            // already claimed. The config argument still wins when given, so a
            // caller constructing this factory by hand is unaffected.
            // `$root` is capability-checked, not assumed. Alpine hands a real element
            // here, but the ESM harness constructs each factory with a deliberately
            // barren stub — `test-tabs.mjs` passes `{ querySelectorAll }` and nothing
            // else, on purpose — and a factory that requires more than it uses turns
            // that into a TypeError at init. Measured: one of 63 ESM scripts, red in
            // CI and invisible to the PHP suite, which does not run them.
            if (config.selected == null) {
                const seed = typeof this.$root?.getAttribute === 'function'
                    ? this.$root.getAttribute(WK_SERVER_VALUE_ATTRIBUTE)
                    : null;

                if (seed != null) {
                    this.selected = String(seed);
                }
            }

            // The hidden input is what a form (or wire:model) actually submits.
            // It starts empty in the markup, so the initial selection has to be
            // written into it before anything reads it.
            //
            // Silently: no `input` event. Announcing a change that the user did
            // not make would cost a Livewire round trip on every page load, for
            // a value the server already had.
            this._writeHiddenInput();

            // A value the server changed has to reach the segments. Alpine read
            // `selected` once, here, and will not look at the seed again — so
            // without this the control keeps showing whatever it was born with
            // while the form submits something else entirely. Measured: the
            // hidden input said `max`, the checked segment said Basic.
            //
            // Guarded on a real change so an unrelated round trip cannot undo a
            // choice the reader just made: every morph rewrites the attribute,
            // including the ones that carry the same value back.
            this._stopServerSync = observeServerValue(this.$root, (value) => {
                if (value === this.selected) {
                    return;
                }

                this.selected = value;
                this._writeHiddenInput();
            });
        },

        destroy() {
            // The observer outlives the scope otherwise, and fires into it.
            this._stopServerSync?.();
        },

        /**
         * Select a segment and tell the form about it.
         *
         * The `input` event is dispatched by hand because assigning `.value`
         * fires nothing — without it wire:model on the hidden input would never
         * see a change, which is the whole reason the input exists.
         */
        select(value) {
            this.selected = String(value);
            this._notify();
        },

        /**
         * Tell the form what `selected` now holds.
         *
         * Split out of select() because the optimistic layer writes `selected`
         * itself — on the flip AND on the rollback — and has to be able to run
         * the same sync afterwards. Without it a rolled-back control would show
         * the old segment while the form still submitted the new one.
         */
        _notify() {
            const input = this._writeHiddenInput();

            // Assigning `.value` fires nothing, so the event is dispatched by
            // hand — without it wire:model on the hidden input would never see
            // the change, which is the whole reason the input exists.
            input?.dispatchEvent(new Event('input', { bubbles: true }));
        },

        /**
         * Move focus AND selection, wrapping at both ends.
         *
         * Selection follows focus here because that is the radio pattern: an
         * arrow key on a radio group checks as it moves. `.click()` is used
         * rather than calling select() directly so a segment stays the single
         * place that knows its own value.
         */
        focusNext(current) {
            this._focusAt(this._indexOf(current) + 1);
        },

        focusPrevious(current) {
            this._focusAt(this._indexOf(current) - 1);
        },

        focusFirst() {
            this._focusAt(0);
        },

        focusLast() {
            this._focusAt(this._segments().length - 1);
        },

        /**
         * The segments, in document order. Never the hidden input.
         *
         * `$root`, not `$el`. Every caller is reached from a keydown handler on
         * a BUTTON, and Alpine binds `$el` to the element the handler sits on —
         * so `$el` here is one segment, which contains no segments, and the
         * lookup would come back empty with the navigation silently dead.
         * `$root` is the x-data element regardless of which child dispatched.
         */
        _segments() {
            return Array.from(this.$root.querySelectorAll('[role="radio"]:not([disabled])'));
        },

        _indexOf(el) {
            // A missing element resolves to -1, which _focusAt turns into the
            // last segment — the same place ArrowLeft from the first one goes.
            return this._segments().indexOf(el);
        },

        _focusAt(index) {
            const segments = this._segments();

            if (segments.length === 0) {
                return;
            }

            // Modulo twice: JS keeps the sign of the dividend, so -1 % 3 is -1
            // rather than 2, and the backwards wrap would land nowhere.
            const target = segments[((index % segments.length) + segments.length) % segments.length];

            target.focus();
            target.click();
        },

        /** Writes the value and returns the input, or null when there is none. */
        _writeHiddenInput() {
            const input = this.$refs.hiddenInput;

            if (input) {
                input.value = this.selected;
            }

            return input || null;
        },
    };
}
