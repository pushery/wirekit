/**
 * Run a callback once the frame in which Livewire renders a response is over.
 *
 * Livewire 4's legacy `commit` hook runs `respond` when a message ends, after the morph, and
 * `succeed` in the animation frame it requests right after that; `fail` runs for an error or a
 * cancel. A request lost on the network reaches neither `succeed` nor `fail`: only `respond`
 * runs. A callback queued from `respond` for the frame after the render therefore runs after a
 * response's `succeed`, and is how a commit that ended any other way is noticed.
 *
 * Two frames, not one: the first is requested from `respond`, before Livewire requests the
 * render frame, so it runs ahead of the render in the same frame.
 *
 * @param {() => void} callback
 */
export function afterRenderFrame(callback) {
    if (typeof requestAnimationFrame !== 'function') {
        setTimeout(callback, 0);

        return;
    }

    requestAnimationFrame(() => requestAnimationFrame(callback));
}
