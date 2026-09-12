/**
 * Coalesce a burst of events into at most one unit of work per frame.
 *
 * The problem this removes is not the number of callbacks — it is the shape a
 * pointer or scroll handler falls into when it does its work inline. Reading a
 * layout property (`getBoundingClientRect`, `offsetTop`, `scrollTop`) after a
 * style has been written forces the browser to recompute layout SYNCHRONOUSLY,
 * before the read can answer. A handler that writes on event N and reads on
 * event N+1 pays that on every single event, and `pointermove` alone can fire
 * faster than the display refreshes.
 *
 * Scheduling the work in `requestAnimationFrame` fixes it structurally rather
 * than by doing less: the read lands at the start of a frame, after the browser
 * has recomputed layout on its own schedule, and the write lands at the end of
 * the same one. The intervening events collapse into that single run.
 *
 * ⚠️ THE LAST EVENT WINS, WHICH IS THE POINT AND ALSO THE CONSTRAINT. This is
 * for work that is IDEMPOTENT over a burst — "put the panel where the cursor
 * is", "measure where we are scrolled to". It is the wrong tool for work that
 * must observe every event, such as accumulating a delta or recording a
 * gesture path; those need the events themselves.
 *
 * Fourteen components hand-rolled a `_ticking` flag before this file existed,
 * and each of them is correct. This is not a criticism of them — it is where
 * the fifteenth should go, so the discipline is inherited instead of
 * remembered, exactly as `safeObserver()` does for observer teardown.
 *
 * @example
 *     import { frameCoalesce } from '../utils/frame-coalesce';
 *
 *     init() {
 *         this._draw = frameCoalesce(() => this._applyPointer());
 *         this.$root.addEventListener('pointermove', (e) => {
 *             this._lastPointer = e;   // cheap: no layout touched here
 *             this._draw.schedule();
 *         }, { passive: true });
 *     },
 *
 *     destroy() {
 *         this._draw.cancel();
 *     },
 *
 * @param {Function} work      run at most once a frame
 * @param {Object}   [options]
 * @param {Window}   [options.scope=window]  the object owning requestAnimationFrame
 * @returns {{schedule: Function, cancel: Function, isScheduled: Function}}
 */
export function frameCoalesce(work, options = {}) {
    if (typeof work !== 'function') {
        // Thrown rather than tolerated, for the same reason safeObserver throws:
        // a scheduler that silently schedules nothing looks installed and does
        // nothing, and the first evidence is behavior that never happens.
        throw new TypeError('frameCoalesce: first argument must be a function.');
    }

    const scope = options.scope ?? (typeof window !== 'undefined' ? window : undefined);

    // No rAF at all — a bare Node harness, or a very early call. Running the work
    // synchronously keeps the caller correct there; it only loses the batching,
    // which is an optimization rather than a behavior.
    const raf = typeof scope?.requestAnimationFrame === 'function'
        ? scope.requestAnimationFrame.bind(scope)
        : null;
    const caf = typeof scope?.cancelAnimationFrame === 'function'
        ? scope.cancelAnimationFrame.bind(scope)
        : null;

    let handle = null;

    const run = () => {
        // Cleared BEFORE the work runs, not after. Work that schedules itself
        // again — a drag that re-measures — would otherwise be dropped, because
        // its `schedule()` would see a handle that this frame is about to
        // discard anyway.
        handle = null;
        work();
    };

    return {
        schedule() {
            if (handle !== null) {
                return;
            }

            if (!raf) {
                run();

                return;
            }

            handle = raf(run);
        },

        /** Drop any pending frame. Call this from `destroy()`. */
        cancel() {
            if (handle !== null && caf) {
                caf(handle);
            }
            handle = null;
        },

        /** Whether a frame is currently pending. Useful in a test. */
        isScheduled: () => handle !== null,
    };
}

export default frameCoalesce;
