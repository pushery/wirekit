import { hasModelBinding, observeServerValue, WK_SERVER_VALUE_ATTRIBUTE } from '../utils/server-value.js';

/**
 * The pressed state of a toggle button that flips itself: with a caller's model, which reaches it
 * through `x-modelable`, or with `self-toggle`.
 *
 * The state is read from `data-wk-server-value` rather than written into `x-data`, so that the
 * expression is the same either way. A Livewire morph that changed it would start the scope
 * again, and through `x-modelable` the answer to a `wire:model.live` click would send the state of
 * the request back: a second click before the answer arrived would be undone.
 *
 * A state the server renders later reaches the button. With a caller's model the binding carries
 * the server's changes and leaves alone what the server only returned; without one, the state is
 * the server's to set.
 *
 * Lifecycle: `_stopServerValue`, the observer on the root's attribute (utils/server-value.js),
 * released in destroy().
 */
export default function wirekitToggleButton() {
    return {
        pressed: false,
        _stopServerValue: null,

        init() {
            const root = this.$root;

            // `$root` is checked rather than assumed, as a unit harness hands a bare stub.
            if (typeof root?.getAttribute === 'function') {
                this.pressed = root.getAttribute(WK_SERVER_VALUE_ATTRIBUTE) === 'true';
            }

            this._stopServerValue = observeServerValue(root, (value) => {
                if (! hasModelBinding(root)) {
                    this.pressed = value === 'true';
                }
            });
        },

        destroy() {
            this._stopServerValue?.();
            this._stopServerValue = null;
        },
    };
}
