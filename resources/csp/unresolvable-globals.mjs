/**
 * The SECOND half of the CSP restriction: which names an expression may READ.
 *
 * `wirekit:csp-audit` used to answer only the first half — does this parse under
 * Alpine's CSP grammar. Both halves are needed, and the gap between them is not
 * academic: `Illuminate\Support\Js::from()` emits `JSON.parse('…')`, which is a
 * member call and therefore perfectly good grammar. It parses, the audit says
 * PASS, and the expression throws in the browser.
 *
 * This module ships (`resources/` is not export-ignored) because the audit a
 * developer runs is the one that has to know. It was previously reachable only
 * from `scripts/`, which is export-ignored — so the knowledge existed and never
 * left this repository.
 */

/**
 * Globals a directive expression may not name — the SECOND half of the CSP
 * restriction, and the half a parser cannot see.
 *
 * Alpine's CSP Evaluator resolves an identifier like this:
 *
 *     case "Identifier":
 *       if (node.name in scope) { … }
 *       throw new Error(`Undefined variable: ${node.name}`);
 *
 * There is no window fallback. `scope` is the merged Alpine data stack, so a
 * global is simply not reachable — and even a value that were reachable is
 * refused by `checkForDangerousValues`, whose set is built from
 * `Object.getOwnPropertyNames(globalThis)`.
 *
 * The consequence is worse than one dead binding: an `x-data` that names a
 * global throws while BUILDING the component, so the element ends up with an
 * empty scope and every directive on it silently does nothing. Measured in a
 * real browser under `script-src 'self'`: an event-calendar handed one event
 * rendered "No events in this range", `Alpine.$data(el)` returned an object
 * with zero keys, and nothing was logged where a developer would look.
 *
 * The list is written out rather than read from this process's `globalThis`,
 * because node's globals and a browser's differ in both directions — deriving
 * it here would silently stop covering `window` / `document` / `localStorage`,
 * which is exactly the half that matters.
 */
const FORBIDDEN_GLOBALS = new Set([
    // Namespaces a directive reaches for most often.
    'JSON', 'Math', 'Date', 'Number', 'String', 'Boolean', 'Object', 'Array',
    'RegExp', 'Promise', 'Map', 'Set', 'Intl', 'Symbol', 'BigInt', 'Error',
    // Browser surface.
    'window', 'document', 'navigator', 'location', 'history', 'screen',
    'localStorage', 'sessionStorage', 'console', 'fetch', 'alert', 'confirm',
    'prompt', 'getComputedStyle', 'matchMedia', 'requestAnimationFrame',
    'cancelAnimationFrame', 'setTimeout', 'clearTimeout', 'setInterval',
    'clearInterval', 'queueMicrotask', 'structuredClone', 'CustomEvent',
    'Event', 'KeyboardEvent', 'MouseEvent', 'FormData', 'URL', 'URLSearchParams',
    'IntersectionObserver', 'ResizeObserver', 'MutationObserver', 'AbortController',
    'Blob', 'File', 'FileReader', 'Image', 'Audio', 'DOMParser', 'crypto',
    // Bare functions.
    'parseInt', 'parseFloat', 'isNaN', 'isFinite', 'encodeURIComponent',
    'decodeURIComponent', 'encodeURI', 'decodeURI', 'globalThis', 'self', 'top',
]);

/**
 * Root identifiers an expression READS — the names the evaluator has to resolve.
 *
 * A member expression contributes only its object (`foo.bar` needs `foo`, never
 * `bar`), and an object literal's keys are not reads at all. Both distinctions
 * matter: without them `{ Date: 1 }` and `item.window` would be reported, and a
 * guard that cries wolf gets an allowlist entry instead of a fix.
 */
function readIdentifiers(node, found = new Set()) {
    if (! node || typeof node !== 'object') {
        return found;
    }

    if (Array.isArray(node)) {
        for (const child of node) {
            readIdentifiers(child, found);
        }

        return found;
    }

    switch (node.type) {
        case 'Identifier':
            found.add(node.name);

            return found;

        case 'MemberExpression':
            readIdentifiers(node.object, found);

            // Only a computed access evaluates its property: `a[b]` reads `b`,
            // `a.b` does not.
            if (node.computed) {
                readIdentifiers(node.property, found);
            }

            return found;

        case 'Property':
            // The key is a name, not a read. Alpine's parser accepts an
            // identifier key, so counting it would flag `{ Date: … }`.
            readIdentifiers(node.value, found);

            return found;

        default:
            for (const value of Object.values(node)) {
                readIdentifiers(value, found);
            }

            return found;
    }
}

/** Globals an expression names, which the CSP evaluator will refuse. */
function forbiddenGlobalsIn(ast) {
    return [...readIdentifiers(ast)].filter((name) => FORBIDDEN_GLOBALS.has(name)).sort();
}

export { FORBIDDEN_GLOBALS, readIdentifiers, forbiddenGlobalsIn };
