/**
 * The ApexCharts adapter, loaded where a chart starts.
 *
 * With `wirekit.scripts.apex` set to `on-demand`, no page carries the adapter's tag. A chart drawn
 * with ApexCharts names the adapter's address in `data-wk-apex-src`, and this hook holds such a
 * chart while Alpine walks to it: `_x_ignore` keeps Alpine's directives off the element and out of
 * its subtree, the adapter loads once, and the chart starts as soon as `wirekitApexChart` exists.
 * An element whose `x-data` names a component that is not there yet would otherwise stay dead,
 * because a later walk does not revive it. The same holds on a page `wire:navigate` brings and in a
 * lazy island, since Alpine walks every element it starts.
 *
 * The adapter registers `wirekitApexChart` as it loads when Alpine is running, and marks the page
 * with `window.__wirekitApexRegistered`, which this hook reads to let a chart start at once. A
 * script it adds carries the nonce of the page's own scripts, for a Content Security Policy that
 * names one.
 */

const REGISTERED = '__wirekitApexRegistered';
const INSTALLED = Symbol.for('wirekit.apexOnDemand');

/** The loads in flight or done, by address, per window. */
const loads = new WeakMap();

/**
 * Load the adapter from an address once per window, and resolve once it is in.
 *
 * @param {string} src
 * @param {Window} win
 * @returns {Promise<void>}
 */
export function loadApexAdapter(src, win) {
    if (win[REGISTERED]) {
        return Promise.resolve();
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

        script.src = src;
        script.addEventListener('load', () => resolve());
        script.addEventListener('error', () => {
            // A load that failed may succeed on the next chart: the address is free again.
            byAddress.delete(src);
            reject(new Error(`The ApexCharts adapter did not load from ${src}.`));
        });
        (doc.head ?? doc.body).appendChild(script);
    });

    byAddress.set(src, load);

    return load;
}

/**
 * Hold every chart that names the adapter until the adapter is in. Installed once per Alpine.
 *
 * @param {object} Alpine
 * @param {Window} win
 */
export function installApexOnDemand(Alpine, win = typeof window === 'undefined' ? null : window) {
    if (! Alpine || typeof Alpine.interceptInit !== 'function' || ! win || Alpine[INSTALLED]) {
        return;
    }

    Alpine[INSTALLED] = true;

    Alpine.interceptInit((el) => {
        const src = typeof el.getAttribute === 'function' ? el.getAttribute('data-wk-apex-src') : null;

        if (! src || win[REGISTERED]) {
            return;
        }

        el._x_ignore = true;

        loadApexAdapter(src, win).then(
            () => {
                delete el._x_ignore;

                if (el.isConnected) {
                    Alpine.initTree(el);
                }
            },
            (error) => {
                delete el._x_ignore;
                console.error('[wirekit] the ApexCharts adapter did not load, so this chart stays as the server wrote it.', error);
            },
        );
    });
}
