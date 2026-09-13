/**
 * A peer library that arrives after the component that needs it.
 *
 * The chart adapters ship the Alpine glue and never bundle their library: the application
 * installs Chart.js or ApexCharts and puts it on the page. They used to look for it exactly once,
 * in init(), and paint a developer panel on the spot when it was missing — so an application that
 * loads the library lazily, to keep it out of the bundle every page downloads, lost that race
 * every time. The library landed a moment later and nothing looked again. Measured in a consuming
 * application: eight report screens showed the panel instead of a chart, with the library sitting
 * on `window` two seconds after the load.
 *
 * So the question is asked until it can be answered. Quickly at first; once the page has loaded
 * and a grace period has passed, the component is told the library is missing — and the check
 * goes on at a slower pace, so a library that arrives even later still replaces the panel.
 */

const FAST_POLL_MS = 100;
const SLOW_POLL_MS = 1000;

/** How long after the load a component waits before it calls its library missing. */
export const PEER_GRACE_MS = 3000;

/**
 * @param {Object}        options
 * @param {() => boolean} options.isReady    is the library on the page now?
 * @param {() => void}    options.onReady    called once, the moment it is
 * @param {() => void}    options.onMissing  called once, when the grace period ends without it
 * @param {number}        [options.graceMs]
 * @returns {() => void} stop — releases every timer and listener; call it from destroy()
 */
export function awaitPeer({ isReady, onReady, onMissing, graceMs = PEER_GRACE_MS }) {
    if (isReady()) {
        onReady();

        return () => {};
    }

    // No page to wait on — the factories are also built in a bare Node harness. Say it now,
    // which is what the adapters always did.
    if (typeof window === 'undefined' || typeof document === 'undefined' || typeof setTimeout !== 'function') {
        onMissing();

        return () => {};
    }

    let stopped = false;
    let missing = false;
    let pollTimer = null;
    let graceTimer = null;
    let onLoad = null;

    const stop = () => {
        stopped = true;
        clearTimeout(pollTimer);
        clearTimeout(graceTimer);
        pollTimer = null;
        graceTimer = null;

        if (onLoad) {
            window.removeEventListener('load', onLoad);
            onLoad = null;
        }
    };

    const poll = () => {
        pollTimer = null;

        if (stopped) return;

        if (isReady()) {
            stop();
            onReady();

            return;
        }

        pollTimer = setTimeout(poll, missing ? SLOW_POLL_MS : FAST_POLL_MS);
    };

    const armGrace = () => {
        onLoad = null;
        graceTimer = setTimeout(() => {
            graceTimer = null;

            // Arrived between two polls: the next one boots it, and nothing is missing.
            if (stopped || isReady()) return;

            missing = true;
            onMissing();
        }, graceMs);
    };

    pollTimer = setTimeout(poll, FAST_POLL_MS);

    if (document.readyState === 'complete') {
        armGrace();
    } else {
        onLoad = armGrace;
        window.addEventListener('load', onLoad, { once: true });
    }

    return stop;
}

export default awaitPeer;
