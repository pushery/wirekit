import { resolveCssVarsDeep } from './chart-theme-colors.js';

/**
 * The colors a Chart.js configuration names by a token, such as `'var(--color-wk-accent)'`.
 *
 * Chart.js hands a color to the Canvas as it is written, and a Canvas ignores a `fillStyle` or
 * `strokeStyle` that is no color: a dataset or an annotation colored by a token drew in Chart.js's
 * own default. These helpers find each such string, resolve it to an rgb()/rgba() value where it
 * stands, and keep its path from the configuration's root, so a change of mode or theme resolves
 * the same tokens again in the live chart.
 *
 * Only plain objects and arrays are walked. A function, a date or a gradient a page built is left
 * as it is, and so is everything under it.
 */

const TOKEN = /var\((--[a-zA-Z0-9-]+)\)/g;

/**
 * Whether `node` is an object literal or an object without a prototype, the two shapes a
 * configuration decoded from JSON or written in a script is made of.
 *
 * @param {*} node
 * @returns {boolean}
 */
function isPlainObject(node) {
    if (node === null || typeof node !== 'object') {
        return false;
    }

    const prototype = Object.getPrototypeOf(node);

    return prototype === Object.prototype || prototype === null;
}

/**
 * Every string in `config` that holds a `var(--…)`, with the keys that lead to it from the root.
 *
 * @param {*} config - a Chart.js configuration, or the part of one a caller roots at the same place
 * @returns {{path: (string|number)[], source: string}[]}
 */
export function tokenColorsOf(config) {
    const found = [];
    const seen = new Set();
    // One path for the whole walk, copied only where a token is found: the data of a large chart
    // holds thousands of points, and a path per point would be thousands of arrays for nothing.
    const path = [];

    const walk = (node) => {
        if (typeof node === 'string') {
            if (node.includes('var(--')) {
                found.push({ path: [...path], source: node });
            }

            return;
        }

        const isArray = Array.isArray(node);

        // An object reached twice is walked once: a configuration may share an object between two
        // places, and one that refers to itself would not end.
        if ((! isArray && ! isPlainObject(node)) || seen.has(node)) {
            return;
        }

        seen.add(node);

        const keys = isArray ? node.keys() : Object.keys(node);

        for (const key of keys) {
            path.push(key);
            walk(node[key]);
            path.pop();
        }
    };

    walk(config);

    return found;
}

/**
 * Writes each token color, resolved against `style`, at its path under `root`: the configuration
 * before `new Chart()`, or a live chart's `config` after a change of mode. A path that no longer
 * leads anywhere is passed over.
 *
 * @param {object} root
 * @param {{path: (string|number)[], source: string}[]} tokens - from `tokenColorsOf()`
 * @param {CSSStyleDeclaration} style - getComputedStyle() of an element in the chart's cascade
 * @param {Element|null} [host] - where a token the style does not answer is probed
 */
export function applyTokenColors(root, tokens, style, host = null) {
    // One cache and one canvas for the whole pass: a configuration repeats its handful of tokens.
    const cache = new Map();
    const scratch = host ? { host } : {};

    for (const { path, source } of tokens) {
        if (path.length === 0) {
            continue;
        }

        const owner = path.slice(0, -1).reduce((node, key) => (node === null || node === undefined ? node : node[key]), root);

        if (owner !== null && typeof owner === 'object') {
            owner[path[path.length - 1]] = resolveCssVarsDeep(source, style, cache, scratch);
        }
    }
}

/**
 * The current values of the tokens a configuration names, as one string: part of the signature
 * that tells a change of theme from an unrelated class toggle, so a token outside the chart's
 * palette that changes with the mode is resolved again as well.
 *
 * @param {{source: string}[]} tokens - from `tokenColorsOf()`
 * @param {CSSStyleDeclaration} style
 * @returns {string}
 */
export function tokenColorsSignature(tokens, style) {
    const names = new Set();

    for (const { source } of tokens) {
        for (const [, name] of source.matchAll(TOKEN)) {
            names.add(name);
        }
    }

    return [...names].map((name) => `${name}:${style.getPropertyValue(name).trim()}`).join(';');
}
