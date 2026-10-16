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
 * The edge of an unlit bar. The muted fill alone stands at 1.09:1 on the page, so how many bars
 * there are, and so how far the lit ones reach, was hard to see (WCAG 1.4.11); a line in the
 * strong border color inside the bar stands at 3:1. A lit bar keeps its fill without one.
 */
export const STRENGTH_UNLIT_EDGE = 'inset 0 0 0 max(1px, var(--border-wk-width)) var(--color-wk-border-strong)';

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
        return 'var(--color-wk-success-text)';
    }

    if (value <= Math.max(1, Math.floor(max / 4))) {
        return 'var(--color-wk-danger-text)';
    }

    return 'var(--color-wk-warning-text)';
}

/**
 * The style of bar `index` as an object, its fill and, while it is unlit, its edge. An object,
 * because Alpine sets it property by property, where a string would be written with
 * `setAttribute()`, which a `style-src` without `'unsafe-inline'` refuses.
 *
 * @param {number} index
 * @param {number} value
 * @param {number} max
 * @returns {{backgroundColor: string, boxShadow: string}}
 */
export function strengthBarStyle(index, value, max) {
    const color = strengthBarColor(index, value, max);

    return { backgroundColor: color, boxShadow: color === STRENGTH_MUTED ? STRENGTH_UNLIT_EDGE : 'none' };
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
