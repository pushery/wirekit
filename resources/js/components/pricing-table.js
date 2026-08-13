import { observeServerValue } from '../utils/server-value.js';

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
