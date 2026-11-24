/**
 * A script of another party, loaded once per window and address.
 *
 * Two components that need the same script share one load: the second waits for the first. The
 * tag carries the nonce of the page's own scripts, for a Content Security Policy that names one,
 * and loads as a module where the script is one. A load that fails frees its address, so the next
 * component tries again instead of waiting on a promise that never settles.
 */

/** The loads in flight or done, by address, per window. */
const loads = new WeakMap();

/**
 * Load a script from an address once per window, and resolve once it has run.
 *
 * @param {string} src
 * @param {{ module?: boolean, win?: Window }} [options]
 * @returns {Promise<void>}
 */
export function loadScriptOnce(src, { module = false, win = typeof window === 'undefined' ? null : window } = {}) {
    if (! win || typeof src !== 'string' || src === '') {
        return Promise.reject(new Error('A script needs an address and a window to load into.'));
    }

    let byAddress = loads.get(win);

    if (! byAddress) {
        byAddress = new Map();
        loads.set(win, byAddress);
    }

    if (byAddress.has(src)) {
        return byAddress.get(src);
    }

    const load = new Promise((resolve, reject) => {
        const doc = win.document;
        const script = doc.createElement('script');
        const nonce = doc.querySelector('script[nonce]')?.nonce;

        if (nonce) {
            script.nonce = nonce;
        }

        if (module) {
            script.type = 'module';
        }

        script.src = src;
        script.addEventListener('load', () => resolve());
        script.addEventListener('error', () => {
            byAddress.delete(src);
            reject(new Error(`The script did not load from ${src}.`));
        });
        (doc.head ?? doc.body).appendChild(script);
    });

    byAddress.set(src, load);

    return load;
}
