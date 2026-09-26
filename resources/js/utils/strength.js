/**
 * The color ladder of a strength meter: `strength-meter` and `password-input`'s own meter.
 *
 * One ladder for both, so the two cannot drift apart in how a step looks. Tokens rather than
 * literal colors, so a theme retint reaches the meter like everything else. The ladder is
 * three colors over any number of bars on purpose: a distinct color per step would imply a
 * precision a strength estimate does not have.
 *
 *   - an unlit bar is muted;
 *   - the first quarter of the steps (at least the first step) is danger;
 *   - every step below the top is warning;
 *   - the top step is success.
 *
 * With four bars that is the ladder password-input has always drawn: one bar danger, two and
 * three warning, four success. The server renders the same ladder before Alpine starts; see
 * the `strength-meter` template, which carries it as a PHP closure.
 */
export const STRENGTH_MUTED = 'var(--color-wk-bg-muted)';

/**
 * The fill of bar `index` (0-based) at `value` lit steps of `max`.
 *
 * @param {number} index
 * @param {number} value
 * @param {number} max
 * @returns {string} a CSS color, as a token reference
 */
export function strengthBarColor(index, value, max) {
    if (index >= value) {
        return STRENGTH_MUTED;
    }

    if (value >= max) {
        return 'var(--color-wk-success)';
    }

    if (value <= Math.max(1, Math.floor(max / 4))) {
        return 'var(--color-wk-danger)';
    }

    return 'var(--color-wk-warning)';
}

/**
 * A strength as a whole step between 0 and `max`: a meter lights whole bars, and a value from
 * outside (a server render, a bound model) can be anything.
 */
export function clampStrength(value, max) {
    const number = Number(value);

    if (! Number.isFinite(number)) {
        return 0;
    }

    return Math.min(Math.max(Math.round(number), 0), max);
}
