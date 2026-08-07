/**
 * Observers that cannot outlive the component that made them.
 *
 * THE DEFECT THIS EXISTS FOR, stated as what a developer sees. An observer's
 * callback keeps running after the element is gone — Livewire morphs the node
 * away, Alpine tears the component down, and the callback fires once more into
 * a `this` whose fields are already null. The page fills with
 * `Cannot read properties of null`, every one of them pointing at the plugin
 * rather than at the teardown that actually happened, and the browser tests of
 * whoever wrote the plugin go red for a reason that is not theirs.
 *
 * The discipline that avoids it is three rules — store the resource under a `_`
 * prefix, release it in `destroy()`, and guard inside every callback — and
 * WireKit's own components are held to all three by dedicated drift tests. A
 * developer writing their own Alpine plugin is held to nothing: they get the
 * rules as prose in
 * https://docs.wirekit.app/extending/authoring-custom-alpine-plugins and the
 * discipline is theirs to remember.
 *
 * So this gives them the discipline instead of the reminder:
 *
 *     // Ships with the Composer package, not from npm — so it is imported by
 *     // path. From resources/js/app.js in a standard Laravel layout:
 *     import { safeObserver } from '../../vendor/pushery/wirekit/resources/js/utils/safe-observer';
 *
 *     init() {
 *         this._io = safeObserver(IntersectionObserver, (entries) => {
 *             // Reached only while the observer is live. After stop() — and
 *             // therefore after destroy() — this body does not run at all.
 *         }, { threshold: 0.5 });
 *
 *         this._io.observe(this.$el);
 *     },
 *
 *     destroy() {
 *         this._io.stop();
 *     },
 *
 * A MODULE AND NOT A GLOBAL, deliberately. Putting this on `window.WireKit`
 * would be a versioned public surface — cheap to add, expensive to take back,
 * and a namespace holding one function is the costlier road to the same place.
 * Plugin authors bundle; an import reaches them and commits us to nothing.
 *
 * WHAT IT DOES NOT DO. It does not free you from calling `stop()` in
 * `destroy()`. Nothing can: a plugin that never tears down leaks its observer
 * whatever wrapper it used, and a helper that pretended otherwise would be
 * worse than none because it would read as a guarantee. What it removes is the
 * OTHER half — the null-guard in every callback, which is the half that is easy
 * to forget and impossible to notice until a morph proves it.
 */

/**
 * Wrap an observer so its callback stops firing the moment it is stopped.
 *
 * @param {typeof IntersectionObserver|typeof MutationObserver|typeof ResizeObserver} Observer
 * @param {Function} callback  invoked only while the observer is live
 * @param {Object} [options]   passed straight to the observer's constructor
 * @returns {{observe: Function, unobserve: Function, disconnect: Function, stop: Function, isLive: Function, raw: Object}}
 */
export function safeObserver(Observer, callback, options) {
    if (typeof Observer !== 'function') {
        // Thrown rather than tolerated. A silently-inert observer is the exact
        // failure this helper exists to remove — it looks installed and watches
        // nothing, and the first evidence is behavior that never happens.
        throw new TypeError('safeObserver: first argument must be an observer constructor.');
    }

    if (typeof callback !== 'function') {
        throw new TypeError('safeObserver: second argument must be a callback function.');
    }

    let live = true;

    // The guard lives HERE rather than in the caller's callback, which is the
    // whole point: the discipline is inherited instead of remembered.
    const observer = new Observer((...args) => {
        if (! live) {
            return;
        }

        callback(...args);
    }, options);

    const stop = () => {
        // Order matters. Clearing the flag first means a callback already
        // queued by the browser — an observer can deliver one after
        // `disconnect()` — finds the observer stopped and returns, instead of
        // running against a component that is being torn down.
        live = false;
        observer.disconnect();
    };

    return {
        observe: (...args) => live && observer.observe(...args),
        unobserve: (...args) => live && observer.unobserve(...args),
        disconnect: stop,
        stop,

        /** Whether the callback would still run. Useful in a test. */
        isLive: () => live,

        /**
         * The underlying observer, for the rare API this wrapper does not
         * forward (`takeRecords`, say). Exposed rather than hidden: a helper
         * that makes a native API unreachable gets worked around, and the
         * workaround is a second unguarded observer.
         */
        raw: observer,
    };
}

export default safeObserver;
