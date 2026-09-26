/**
 * Strength meter — a row of bars for how hard something is to guess, without a field attached.
 *
 * `password-input` has always had one, bound to its own field. A code generator, a passphrase
 * built from settings, or a key whose strength the server computes needs the same display with
 * the value coming from somewhere else, so the value arrives three ways:
 *
 *   - rendered by the server, and on every Livewire render after that through the
 *     `data-wk-server-value` attribute, which Alpine would not read again from `x-data`;
 *   - bound from a parent Alpine scope through `x-modelable`, for a value computed in the
 *     browser;
 *   - set once, for a static display.
 *
 * What a step is called comes from the server, translated, and a changed step is spoken
 * through a polite status region, because four bars that differ only in tint and length say
 * nothing to a screen reader.
 *
 * @param {Object}   config
 * @param {number}   [config.value]    the lit steps, 0 to `max`; left out, the component reads
 *                                     them from its `data-wk-server-value` attribute
 * @param {number}   config.max        how many bars
 * @param {string[]} [config.levels]   what each step is called, weakest first
 * @param {string}   [config.template] the status sentence, with `:value` for the step's name
 */
import { observeServerValue, WK_SERVER_VALUE_ATTRIBUTE } from '../utils/server-value.js';
import { clampStrength, strengthBarColor } from '../utils/strength.js';

export default function wirekitStrengthMeter(config = {}) {
    const max = Math.max(1, Math.round(Number(config.max) || 4));

    return {
        max,
        value: clampStrength(config.value, max),
        levels: Array.isArray(config.levels) ? config.levels.map(String) : [],
        _template: typeof config.template === 'string' ? config.template : ':value',
        _stopServerSync: null,
        // What the status region holds. Empty until the step CHANGES: bound straight to the
        // announcement, hydration would write the starting step into the region, and a region
        // whose text changes is spoken, so every page would announce its meters on load.
        spoken: '',
        // Set once the meter has settled on the step the page shows. A meter bound with
        // `x-model` takes its parent's value in the same pass that starts it, after `init()`,
        // and that first sync is the page loading rather than the step changing.
        _settled: false,

        init() {
            // The step the server rendered comes from its attribute, not from `x-data`: a value
            // written into the seed changes the seed on every render, a Livewire morph rewrites
            // it, and Alpine re-initializes the meter on that, forgetting that it had settled.
            // Capability-checked, since a harness may hand a `$root` that answers nothing.
            if (config.value == null) {
                const seed = typeof this.$root?.getAttribute === 'function'
                    ? this.$root.getAttribute(WK_SERVER_VALUE_ATTRIBUTE)
                    : null;

                if (seed != null && seed !== '') {
                    this.value = clampStrength(seed, this.max);
                }
            }

            this.$watch('steps', () => {
                if (this._settled) {
                    this.spoken = this.announcement;
                }
            });

            // A task, not a microtask: the binding's first sync and the watcher it wakes both run
            // as microtasks, and this has to come after them.
            setTimeout(() => {
                this._settled = true;
            }, 0);

            this._stopServerSync = observeServerValue(this.$root, (raw) => {
                const next = clampStrength(raw, this.max);

                // A morph rewrites an unchanged value too, and the step a parent scope just
                // bound must not be put back to the server's older one by it.
                if (next !== this.steps) {
                    this.value = next;
                }
            });
        },

        destroy() {
            this._stopServerSync?.();
            this._stopServerSync = null;
        },

        /** The lit steps as a whole number in range, whatever was bound. */
        get steps() {
            return clampStrength(this.value, this.max);
        },

        /** What the current step is called, or '' with nothing lit. */
        get level() {
            if (this.steps === 0) {
                return '';
            }

            return this.levels[Math.min(this.steps, this.levels.length) - 1] ?? '';
        },

        /**
         * `aria-valuetext`, or false so Alpine removes the attribute: an empty string would be
         * SET, and a meter announcing an empty value text reads as one somebody thought about.
         */
        get valueText() {
            return this.level || false;
        },

        /** What the status region says: the meter's name with the step, never the step alone. */
        get announcement() {
            return this.level === '' ? '' : this._template.replace(':value', this.level);
        },

        barColor(index) {
            return strengthBarColor(index, this.steps, this.max);
        },
    };
}
