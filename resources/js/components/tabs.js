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

export default function wirekitTabs(config = {}) {
    return {
        active: config.active != null ? String(config.active) : '',
        labels: config.labels || {},

        init() {
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
            const buttons = Array.from(this.$root.querySelectorAll('[role=tab]:not([disabled])'));

            if (buttons.length === 0) {
                return;
            }

            const current = buttons.indexOf(document.activeElement);

            if (current === -1) {
                return;
            }

            const index = {
                next: current + 1,
                prev: current - 1,
                first: 0,
                last: buttons.length - 1,
            }[direction];

            if (index === undefined) {
                return;
            }

            // Modulo twice: JS keeps the sign of the dividend, so -1 % 3 is -1
            // rather than 2 and the backwards wrap would land nowhere.
            buttons[((index % buttons.length) + buttons.length) % buttons.length].focus();
        },
    };
}
