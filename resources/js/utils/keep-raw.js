/**
 * Keep a library's object out of Alpine's reactivity.
 *
 * Alpine wraps the state of a component in reactive Proxies, deeply, so an object stored on it is
 * read back as a Proxy of itself. A library driven through such a Proxy works against a copy of its
 * own identity: a chart's update recurses into its option Proxies until the stack overflows, an
 * editor's transaction no longer matches its document, a map's canvas paints blank, and an
 * animation queue that keeps its entries by identity never lets go of one. `__v_skip` is the flag
 * Vue's `markRaw()` sets and Alpine's `reactive()` honors: an object that carries it is never
 * wrapped, whoever reads it and however.
 *
 * @template T
 * @param {T} object - The library's object, as the library returned it.
 * @returns {T} The same object. A frozen or sealed one cannot take the flag and is returned as it is.
 */
export function keepRaw(object) {
    if (object && typeof object === 'object') {
        try {
            Object.defineProperty(object, '__v_skip', { value: true });
        } catch {
            // A frozen or sealed object cannot take the flag.
        }
    }

    return object;
}
