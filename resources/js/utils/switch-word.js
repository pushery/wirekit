// What PHP's `trim()` takes from either end: space, tab, line feed, carriage return, NUL and the
// vertical tab.
const TRIMMED = ' \t\n\r\0\x0B';

/**
 * A text with the spaces PHP's `trim()` takes removed from either end, and only those: a no-break
 * space stays, as it does on the server.
 *
 * @param {string} value
 * @returns {string}
 */
export function phpTrim(value) {
    let start = 0;
    let end = value.length;

    while (start < end && TRIMMED.includes(value[start])) start++;
    while (end > start && TRIMMED.includes(value[end - 1])) end--;

    return value.slice(start, end);
}

/**
 * The switch a value names, read as the server reads one (`Support\BooleanProp::orNull()`): a
 * boolean as it is, a number as on unless it is 0, and the words `1`, `true`, `on` and `yes` as on
 * and `0`, `false`, `off` and `no` as off, in any case and with spaces around them. Every other
 * value names no switch and reads as null: an empty text, another word, NaN, a list.
 *
 * The spaces are the ones PHP's `trim()` takes, so a value reads the same on both sides.
 * tests/fixtures/switch-word-cases.json holds both halves to the same cases.
 *
 * @param {unknown} value
 * @returns {boolean|null}
 */
export function switchWord(value) {
    if (typeof value === 'boolean') return value;
    if (typeof value === 'number') return Number.isNaN(value) ? null : value !== 0;
    if (typeof value !== 'string') return null;

    const word = phpTrim(value).toLowerCase();

    if (['1', 'true', 'on', 'yes'].includes(word)) return true;
    if (['0', 'false', 'off', 'no'].includes(word)) return false;

    return null;
}
