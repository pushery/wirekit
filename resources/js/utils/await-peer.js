/**
 * A peer library that arrives after the component that needs it.
 *
 * The chart adapters ship the Alpine glue and never bundle their library: the application
 * installs Chart.js or ApexCharts and puts it on the page. An application may load the library
 * lazily, to keep it out of the bundle every page downloads, so it can land a moment after the
 * component starts, and a single look in init() would paint the developer panel for good.
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
