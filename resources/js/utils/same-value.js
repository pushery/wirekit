/**
 * Whether two option values name the same option: the same value, or the same text.
 *
 * An option carries its value as text, while a value bound with `wire:model` to an `int` property
 * arrives from the server as a number, so `2` and `"2"` name one option. `null` and `undefined`
 * name no option and match only themselves.
 *
 * @param {*} a
 * @param {*} b
 * @returns {boolean}
 */
export function sameValue(a, b) {
    if (a === b) {
        return true;
    }

    if (a === null || a === undefined || b === null || b === undefined) {
        return false;
    }

    return String(a) === String(b);
}
