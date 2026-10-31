/**
 * Password input — visibility toggle and the optional strength meter.
 *
 * A factory rather than an inline `x-data` object literal, because Alpine's CSP
 * parser does not accept a literal carrying a getter and a method. Under a strict
 * Content-Security-Policy such an element ends up with an empty scope, and the
 * show/hide toggle and the whole meter go dead without an error on the element.
 *
 * @param {Object}    config
 * @param {boolean}   config.strengthMeter    whether the meter is rendered at all
 * @param {string[]}  [config.strengthLabels] the four rungs, weakest first, already
 *                                            translated — the template resolves them,
 *                                            because this file has no locale
 */
import { strengthBarColor, strengthBarStyle } from '../utils/strength.js';
import { onFormReset } from '../utils/form-reset.js';

export default function wirekitPasswordInput(config = {}) {
    return {
        showPassword: false,

        /**
         * The typed password, mirrored from the field by `x-model`.
         *
         * Declared even when the meter is off. The alternative — declaring it
         * conditionally, as the inline literal did — means the property is
         * absent from the scope, and a stray reference then reads as an
         * undefined variable rather than as an empty string. Costing one unused
         * property is cheaper than a scope whose shape depends on a prop.
         */
        password: '',

        strengthMeter: config.strengthMeter === true,

        // The listener that reads the field again after its form is reset (utils/form-reset.js),
        // released in destroy(). Only with the meter, the one thing that mirrors the field.
        _stopFormReset: null,

        /**
         * Follow a form reset. The browser empties the field to its default and fires no
         * `input`, so `x-model` never hears of it and the meter would go on rating the password
         * that was typed.
         */
        init() {
            if (this.strengthMeter) {
                this._stopFormReset = onFormReset(this.$root, () => this.$refs?.field, () => this._restore());
            }
        },

        destroy() {
            this._stopFormReset?.();
            this._stopFormReset = null;
        },

        /** What the field holds after a form reset, read back into the meter's copy. */
        _restore() {
            const field = this.$refs?.field;

            this.password = field && typeof field.value === 'string' ? field.value : '';
        },

        /**
         * The four rung names, weakest first.
         *
         * Always declared, even with the meter off, for the same reason
         * `password` is: a property that appears and disappears with a prop makes
         * a stray reference read as an undefined variable instead of as an empty
         * value. An empty array simply yields an empty label.
         */
        strengthLabels: Array.isArray(config.strengthLabels) ? config.strengthLabels : [],

        /**
         * 0–4, by count of satisfied criteria.
         *
         * A getter rather than a watcher: it is derived state with no cost to
         * recompute, and a watcher would need to be kept in step with every
         * path that writes `password`.
         */
        get strength() {
            const pw = this.password;

            if (! pw) {
                return 0;
            }

            let score = 0;

            if (pw.length >= 8) score++;
            if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) score++;
            if (/\d/.test(pw)) score++;
            if (/[^a-zA-Z0-9]/.test(pw)) score++;

            return score;
        },

        /**
         * What the score says, in words — the meter's `aria-valuetext` and the
         * whole content of the polite region beside it.
         *
         * Empty while the field is empty, deliberately: a reader who tabs into an
         * untouched field should hear the label and the hint, not a verdict on a
         * password nobody has typed yet.
         *
         * Scores 0 and 1 share the weakest rung, matching what `barColor` does —
         * four bars, four names, and a score of 0 on a non-empty entry is the same
         * verdict as a score of 1 rather than a fifth one.
         */
        get strengthLabel() {
            if (! this.password) {
                return '';
            }

            return this.strengthLabels[Math.max(this.strength - 1, 0)] ?? '';
        },

        /**
         * The same verdict, for the meter's `aria-valuetext` — `false` where the
         * label is empty, so the attribute is ABSENT rather than empty.
         *
         * Two getters for one string, because the two surfaces need opposite
         * things out of "there is nothing to say yet". The polite region beside
         * the meter is `x-text`, and `x-text` on `false` writes the word "false"
         * into it; the meter is `x-bind`, and Alpine removes a bound attribute
         * only for `null`, `undefined` or `false` — an empty string it SETS.
         * Binding the label directly therefore shipped `aria-valuetext=""` on
         * every untouched field.
         *
         * That is worth a getter because of what the attribute means here. On a
         * `role="meter"`, `aria-valuetext` replaces the number in the
         * announcement, so an empty one is the case implementations split on:
         * fall back to `aria-valuenow="0"`, or announce nothing whatever. It is
         * also invisible — in the DOM an empty attribute is indistinguishable
         * from a considered one — and it makes the hydrated document disagree
         * with the pre-hydration one, which renders no `aria-valuetext` at all.
         *
         * `false` rather than `undefined`, which removes just as well but cannot
         * be written in a directive: Alpine's CSP evaluator resolves identifiers
         * against the Alpine scope with no `window` fallback, so `undefined` is
         * an unresolvable name that takes the whole `x-data` down with it. The
         * getter sidesteps that anyway by keeping the expression to one
         * identifier, which is the shape this component's directives all take.
         */
        get strengthValueText() {
            return this.strengthLabel || false;
        },

        /**
         * The fill color of bar `index`, as a token reference: the ladder the standalone
         * `strength-meter` draws too, from one place (utils/strength.js). Four bars and three
         * colors, because a distinct color per score would imply a precision the heuristic
         * does not have.
         */
        barColor(index) {
            return strengthBarColor(index, this.strength, 4);
        },

        /** The style of bar `index`, its fill and, while it is unlit, its edge; see utils/strength.js. */
        barStyle(index) {
            return strengthBarStyle(index, this.strength, 4);
        },
    };
}
