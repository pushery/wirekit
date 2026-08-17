import { observeServerValue, WK_SERVER_VALUE_ATTRIBUTE } from '../utils/server-value.js';

/**
 * Pricing table — which billing interval the whole table is priced at.
 *
 * The interval used to live in an inline `x-data="{ interval: … }"`, which is
 * correct for a reader and useless to an application whose checkout is decided
 * on the server: the choice existed only in the browser and told nobody. A
 * server-authoritative checkout could therefore not use the component at all —
 * it had no way to learn that "annual" had been picked, and no way to say so
 * either when it already knew.
 *
 * A factory rather than an inline scope for two reasons, and only the first is
 * about this feature: a MutationObserver cannot be written inline under Alpine's
 * CSP build (the expression would not parse, and the whole element would end up
 * with an empty scope), and a factory is where the other components in this
 * library keep this exact pattern.
 *
 * Lifecycle resources held on `this`: the server-value observer. Disconnected in
 * destroy() — it outlives the scope otherwise and fires into a dead one.
 *
 * @param {Object} config
 * @param {string} config.interval  the interval selected at render time
 */
export default function wirekitPricingTable(config = {}) {
    return {
        interval: config.interval != null ? String(config.interval) : '',

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
            // The observer attribute is CONDITIONAL here — it is only rendered
            // when the server actually drives the interval. So the fallback is
            // the `default` prop rather than nothing: without it a table the
            // server does not drive would start on no interval at all, which is
            // a regression this change would otherwise have introduced silently.
            // `$root` is capability-checked, not assumed. Alpine hands a real element
            // here, but the ESM harness constructs each factory with a deliberately
            // barren stub — `test-tabs.mjs` passes `{ querySelectorAll }` and nothing
            // else, on purpose — and a factory that requires more than it uses turns
            // that into a TypeError at init. Measured: one of 63 ESM scripts, red in
            // CI and invisible to the PHP suite, which does not run them.
            if (config.interval == null) {
                const seed = typeof this.$root?.getAttribute === 'function'
                    ? this.$root.getAttribute(WK_SERVER_VALUE_ATTRIBUTE)
                    : null;

                this.interval = String(
                    seed != null && seed !== '' ? seed : (config.default ?? '')
                );
            }

            // Outward: the form has to see the choice. Assigning `.value` fires
            // nothing, so the event is dispatched by hand — without it a
            // `wire:model` on the hidden input would never observe a change,
            // which is the only reason the input exists.
            this.$watch('interval', (value) => {
                const input = this.$refs.hiddenInput;

                if (! input || input.value === value) {
                    return;
                }

                input.value = value;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });

            // Inward: the server's answer has to reach the toggle. Alpine reads
            // the seed once, so a value the server sends on a later round trip
            // changed the attribute text and nothing else.
            //
            // Guarded on a real change, so an unrelated round trip cannot undo a
            // choice the reader just made — every morph rewrites the attribute,
            // including the ones carrying the same value back.
            this._stopServerSync = observeServerValue(this.$root, (value) => {
                if (value === this.interval) {
                    return;
                }

                this.interval = value;
            });
        },

        destroy() {
            this._stopServerSync?.();
        },
    };
}
