/**
 * Run a component's periodic work only while the page is actually being looked at.
 *
 * A `setInterval` does not stop when its tab goes to the background. Browsers throttle
 * one — usually to a tick a second, and less on battery — but throttled is not stopped:
 * a carousel keeps advancing through slides nobody sees, and comes back to a reader on
 * whatever slide the clock happened to land on rather than the one they left. That last
 * part is why this is a correctness question and not only a battery one.
 *
 * ⚠️ THIS IS FOR WORK WHOSE RESULT IS RE-DERIVABLE ON RETURN, and every current caller
 * qualifies for the same reason: they read the clock. A countdown recomputes from
 * `Date.now()`, a calendar's current-time line from `new Date()` — so the ticks missed
 * while hidden were not carrying state, and the first tick after `onShow` is already
 * correct. Work that ACCUMULATES (a poll that must not miss a message, a timer that
 * counts its own ticks) must not be paused this way; it would come back short.
 *
 * The listener wiring is what this exists to carry. Registering it is easy to remember
 * and removing it is easy to forget, and a component that leaks a `visibilitychange`
 * handler keeps a whole destroyed component alive through the closure.
 *
 * @example
 *     this._visibility = pauseWhileHidden({
 *         onHide: () => this._stopTimer(),
 *         onShow: () => this._restartTimer(),
 *     });
 *     // in destroy():
 *     this._visibility?.stop();
 *
 * @param {Object}   handlers
 * @param {Function} handlers.onHide  the page became hidden
 * @param {Function} handlers.onShow  the page became visible again
 * @param {Document} [handlers.scope] the document to listen on
 * @returns {{stop: Function, isHidden: Function}}
 */
export function pauseWhileHidden({ onHide, onShow, scope } = {}) {
    if (typeof onHide !== 'function' || typeof onShow !== 'function') {
        // Thrown rather than tolerated, like the other factories here: a half-wired
        // pause looks installed and does nothing, and the evidence is a battery that
        // drains slightly faster, which nobody traces back to this.
        throw new TypeError('pauseWhileHidden: onHide and onShow must both be functions.');
    }

    const doc = scope ?? (typeof document !== 'undefined' ? document : null);

    // No document — a bare Node harness, which is where every one of these factories is
    // constructed by the ESM suite. Returning an inert handle keeps the caller correct
    // there; it simply never pauses, which is what "always visible" means.
    if (!doc || typeof doc.addEventListener !== 'function') {
        return { stop: () => {}, isHidden: () => false };
    }

    /*
     * `visibilityState` rather than `hidden`, and the difference matters here: a document
     * that has never reported either — a stub, an older embedded webview — gives
     * `undefined` for both, and `!undefined` is TRUE. Reading it as "hidden" would pause
     * every timer on the page permanently, so the comparison is written to fall to
     * VISIBLE on anything it does not recognize.
     */
    const isHidden = () => doc.visibilityState === 'hidden';

    const handler = () => {
        if (isHidden()) {
            onHide();

            return;
        }

        onShow();
    };

    doc.addEventListener('visibilitychange', handler);

    return {
        /** Remove the listener. Call this from `destroy()`. */
        stop() {
            doc.removeEventListener('visibilitychange', handler);
        },

        isHidden,
    };
}

export default pauseWhileHidden;
