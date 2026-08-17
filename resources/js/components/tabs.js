/**
 * Tabs — which panel is showing, and roving focus across the tablist.
 *
 * The inline `x-data` did not parse under Alpine's CSP build: method shorthand,
 * an arrow-function `$watch` callback, and optional chaining in the label
 * lookup. Under a strict Content-Security-Policy the whole object failed to
 * build, so the element got an empty scope and the tablist stopped responding
 * to both clicks and arrow keys.
 *
 * One correctness note carried over from the inline version and worth keeping
 * visible: `focusTab` reads the tab buttons off `$root`, not `$el`. Every caller
 * is a keydown handler bound to a BUTTON, and Alpine binds `$el` to the element
 * the handler sits on — a tab, which contains no tabs. The inline version used
 * a bare `$el` that resolved to the x-data root by accident of where the
 * expression was evaluated; written as a factory method the distinction is real,
 * and `$root` is the one that holds regardless of which child dispatched.
 *
 * Lifecycle resources held on `this`: NONE. The $watch is torn down with the
 * component, and nothing else is registered.
 *
 * @param {Object} config
 * @param {string} config.active     key of the tab open at render time
 * @param {Object} config.labels     key -> label, for the change event's payload
 * @param {string} [config.warning]  a debug-only composition warning to emit at
 *   init (a wire:model on tabs is dropped — they are client-only state). It
 *   arrives here rather than in its own x-init because an element cannot carry
 *   two Alpine components, and because an inline console.warn breaks the whole
 *   scope under the CSP build — see utils/dev-warning.js.
 */
import { devWarn } from '../utils/dev-warning.js';
import { moveRovingFocus } from '../utils/roving-focus.js';
import { observeServerValue, WK_SERVER_VALUE_ATTRIBUTE } from '../utils/server-value.js';

export default function wirekitTabs(config = {}) {
    return {
        active: config.active != null ? String(config.active) : '',
        labels: config.labels || {},

        init() {
            // Seed from the server attribute when the template left `active` out,
            // which it does exactly when the server drives the tab. See the note
            // on the `x-data` attribute in tabs.blade.php: interpolating a value
            // that changes per render makes every morph re-initialize this scope.
            // `$root` is capability-checked, not assumed. Alpine hands a real element
            // here, but the ESM harness constructs each factory with a deliberately
            // barren stub — `test-tabs.mjs` passes `{ querySelectorAll }` and nothing
            // else, on purpose — and a factory that requires more than it uses turns
            // that into a TypeError at init. Measured: one of 63 ESM scripts, red in
            // CI and invisible to the PHP suite, which does not run them.
            // The server's tab, when there is one, beats the prop-derived seed.
            // It arrives as an attribute rather than in `x-data` for the reason
            // spelled out on that attribute in tabs.blade.php.
            const seed = typeof this.$root?.getAttribute === 'function'
                ? this.$root.getAttribute(WK_SERVER_VALUE_ATTRIBUTE)
                : null;

            if (seed != null && seed !== '') {
                this.active = String(seed);
            }

            devWarn(config.warning);

            // A namespaced, bubbling browser event on every change, so a Livewire
            // (or any) listener can observe the switch server-side without
            // rebuilding the tablist by hand. $watch fires on CHANGE only, and an
            // unobserved CustomEvent is a no-op — so this stays zero-config and
            // backward-compatible for every existing usage. Listen with
            // @wirekit:tab-changed on any ancestor, or on window.
            this.$watch('active', (value) => this.$dispatch('wirekit:tab-changed', {
                tab: value,
                label: this.labels[value] ?? value,
            }));

            // The other direction. The event above lets the server hear a switch;
            // without this it could not answer. Alpine read the seed once, so a
            // tab the server selects on a later round trip changed the attribute
            // text and nothing else — the tablist kept showing what it was born
            // with.
            //
            // Guarded on a real change so an unrelated round trip cannot undo a
            // choice the reader just made: every morph rewrites the attribute,
            // including the ones carrying the same value back.
            this._stopServerSync = observeServerValue(this.$root, (value) => {
                if (value === this.active) {
                    return;
                }

                this.active = value;
            });
        },

        destroy() {
            // The observer outlives the scope otherwise, and fires into it.
            this._stopServerSync?.();
        },

        /**
         * Move focus within the tablist. Wraps in both directions.
         *
         * Focus only — selection does NOT follow, which is the deliberate
         * difference from the radio pattern: a tab panel can be expensive to
         * render, so the manual-activation model (arrow to a tab, Enter or Space
         * to open it) is the one the WAI-ARIA tabs pattern recommends.
         *
         * @param {'next'|'prev'|'first'|'last'} direction
         */
        focusTab(direction) {
            // Shared with the server-driven tablist, which needs the identical
            // behavior for a reason this component also has: the item list is read
            // from the DOM on every keypress rather than held, so a Livewire morph
            // between one arrow key and the next cannot leave focus pointing into a
            // list that no longer exists.
            moveRovingFocus(this.$root, direction);
        },
    };
}
