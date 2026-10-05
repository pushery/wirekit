import { onFormReset } from '../utils/form-reset.js';
import { observeServerValue, WK_SERVER_VALUE_ATTRIBUTE } from '../utils/server-value.js';
import { watchModelEvents } from '../utils/model-events.js';
import { watchCurrent } from '../utils/watch-current.js';

/**
 * Pricing table — which billing interval the whole table is priced at.
 *
 * An inline `x-data="{ interval: … }"` would be correct for a reader and useless
 * to an application whose checkout is decided on the server, because the choice
 * would exist only in the browser. The factory lets the server learn that
 * "annual" was picked, and set the interval when it already knows.
 *
 * A factory rather than an inline scope for two reasons, and only the first is
 * about this feature: a MutationObserver cannot be written inline under Alpine's
 * CSP build (the expression would not parse, and the whole element would end up
 * with an empty scope), and a factory is where the other components in this
 * library keep this exact pattern.
 *
 * Lifecycle resources held on `this`, released in destroy():
 *   - _stopServerSync — the server-value observer. It outlives the scope otherwise and fires
 *     into a dead one.
 *   - _modelEvents — `change` and `blur` on the hidden input for `wire:model.change` and
 *     `wire:model.blur`, which listen on that input alone (utils/model-events.js): `change` with
 *     every interval chosen, as before, and `blur` when the reader leaves the toggle.
 *   - _stopFormReset — the listener that puts the starting interval back when the form is reset
 *     (utils/form-reset.js).
 *
 * @param {Object} config
 * @param {string} config.interval  the interval selected at render time
 */
export default function wirekitPricingTable(config = {}) {
    return {
        // Handles set while the component runs, declared so that they are its own: Alpine stores a
        // property no scope declares on the outermost scope around the component.
        _stopServerSync: null,
        _modelEvents: null,
        _stopFormReset: null,

        interval: config.interval != null ? String(config.interval) : '',
        // The interval a form reset returns to: the one the page started with, or the one the
        // server sent last, as a native field returns to the value the server rendered.
        _resetValue: config.interval != null ? String(config.interval) : '',

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
            // The observer attribute is CONDITIONAL here — it is only rendered
            // when the server actually drives the interval. So the fallback is
            // the `default` prop rather than nothing: without it a table the
            // server does not drive would start on no interval at all.
            //
            // `$root` is capability-checked, not assumed. Alpine hands a real element
            // here, but `test-server-value-seed.mjs` constructs this factory with a
            // barren stub that answers `getAttribute` and nothing else, or nothing at
            // all, and a factory needing more than it uses turns that into a TypeError
            // at init.
            if (config.interval == null) {
                const seed = typeof this.$root?.getAttribute === 'function'
                    ? this.$root.getAttribute(WK_SERVER_VALUE_ATTRIBUTE)
                    : null;

                this.interval = String(
                    seed != null && seed !== '' ? seed : (config.default ?? '')
                );
            }

            this._resetValue = this.interval;

            // Outward: the form has to see the choice. Assigning `.value` fires
            // nothing, so the event is dispatched by hand — without it a
            // `wire:model` on the hidden input would never observe a change,
            // which is the only reason the input exists.
            watchCurrent(this, 'interval', (value) => {
                const input = this.$refs.hiddenInput;

                if (! input || input.value === value) {
                    return;
                }

                input.value = value;
                input.dispatchEvent(new Event('input', { bubbles: true }));

                // Through the model events once they are armed, so that leaving the toggle
                // afterwards does not fire `change` a second time.
                if (this._modelEvents) {
                    this._modelEvents.commit();
                } else {
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            // Inward: the server's answer has to reach the toggle. Alpine reads
            // the seed once, so a value the server sends on a later round trip
            // changed the attribute text and nothing else.
            //
            // Guarded on a real change, so an unrelated round trip cannot undo a
            // choice the reader just made — every morph rewrites the attribute,
            // including the ones carrying the same value back.
            this._stopServerSync = observeServerValue(this.$root, (value) => {
                this._resetValue = value;

                if (value === this.interval) {
                    return;
                }

                this.interval = value;
            });

            this._modelEvents = watchModelEvents(this.$root, () => this.$refs?.hiddenInput);
            this._stopFormReset = onFormReset(this.$root, () => this.$refs?.hiddenInput, () => this._restore());
        },

        destroy() {
            this._stopServerSync?.();
            this._modelEvents?.dispose();
            this._modelEvents = null;
            this._stopFormReset?.();
            this._stopFormReset = null;
        },

        /**
         * Back to the starting interval after a form reset. The hidden input is written first, so
         * the `interval` watcher finds it current and announces nothing: a reset changes a native
         * field without an event.
         */
        _restore() {
            const input = this.$refs?.hiddenInput;

            if (input) {
                input.value = this._resetValue;
            }

            this.interval = this._resetValue;
        },
    };
}
