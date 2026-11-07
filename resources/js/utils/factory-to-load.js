/**
 * Which factory an element waits for under the per-page bundle (`resources/js/wirekit.auto.js`).
 */

/**
 * The factory an element's `x-data` starts that this bundle has and the page has not loaded, or
 * null for every other element.
 *
 * @param {string|null} expression
 * @param {Set<string>} names the names this bundle has a module for
 * @param {Map<string, Function>} loaded
 * @returns {string|null}
 */
export function factoryToLoad(expression, names, loaded) {
    const match = expression === null ? null : /^\s*(wirekit[A-Z]\w*)/.exec(expression);

    return match !== null && names.has(match[1]) && !loaded.has(match[1]) ? match[1] : null;
}
