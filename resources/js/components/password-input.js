/**
 * Password input — visibility toggle and the optional strength meter.
 *
 * This lived as an inline `x-data` object literal until it was measured against
 * Alpine's CSP parser and did not parse: a literal carrying a getter and a
 * method is not an expression that parser accepts. Under a strict
 * Content-Security-Policy the element ended up with an EMPTY scope, so the
 * show/hide toggle and the whole meter were dead — silently, because an x-data
 * that throws leaves no error on the element, just a component that does
 * nothing.
 *
 * The audit that scans directive expressions could not decide this one on its
 * own: the literal carries Blade `@if`, so it was listed under "read these by
 * hand" — and nobody did. The audit now substitutes the control flow and parses
 * the result, so this class cannot come back as a manual chore again.
 *
 * @param {Object}  config
 * @param {boolean} config.strengthMeter  whether the meter is rendered at all
 */
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
         * The fill color of bar `index`, as a token reference.
         *
         * Tokens rather than literal colors, so a theme retint reaches the
         * meter like everything else. Two of the four scores share the warning
         * token on purpose: the ladder is four bars and three colors, because a
         * distinct color per score would imply a precision the heuristic does
         * not have.
         */
        barColor(index) {
            if (index >= this.strength) return 'var(--color-wk-bg-muted)';
            if (this.strength <= 1) return 'var(--color-wk-danger)';
            if (this.strength === 2) return 'var(--color-wk-warning)';
            if (this.strength === 3) return 'var(--color-wk-warning)';

            return 'var(--color-wk-success)';
        },
    };
}
