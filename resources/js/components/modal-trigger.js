/**
 * Opens a NAMED overlay from a trigger that is nowhere near it.
 *
 * `modal.trigger` without a name is a child of its modal and calls `show()` on the scope it sits
 * in. That covers the ordinary case and needs nothing from this file.
 *
 * It does not cover the case where the trigger CANNOT sit there. An application reported the
 * shape: a consent checkbox names its document inside the sentence and links the word, and that
 * anchor has to open the full text in a dialog. The checkbox renders its slot inside its own
 * `<label>`, so the modal cannot be placed around it — and the anchor is then outside every
 * `x-data`, which is the part that actually bites.
 *
 * ⚠️ **An element outside every `x-data` tree is never walked, so its directive is not refused —
 * it is never installed.** That is worse than a refusal in the one way that matters: a refused
 * expression at least leaves the modifiers working, while an uninstalled directive leaves
 * `.prevent` off too, so the anchor plainly navigates. Measured under the strict-CSP fixture,
 * where the identical directive on a scoped anchor opens the dialog and on an unscoped one does
 * not. It is not a policy effect: it happens in every bundle.
 *
 * So the trigger brings its own scope. That is the whole reason this factory exists — the dispatch
 * itself is one line and needs no state.
 *
 * @param {Object} config
 * @param {string} config.name - the `name` of the overlay to open
 */
export default function wirekitModalTrigger(config = {}) {
    return {
        _name: config.name || '',

        /**
         * Ask the named overlay to open.
         *
         * By event rather than by reference, because the point is that this trigger has no handle
         * on the overlay: `utils/overlay.js` listens on `window`, so the message arrives from
         * anywhere in the document, including across a teleport.
         *
         * `bubbles` is set explicitly rather than left to Alpine's `$dispatch` default, because
         * this is a plain `dispatchEvent` on the element — an event that does not bubble never
         * reaches the window listener, and the failure would be silent in the usual way: the
         * trigger looks wired, and nothing opens.
         */
        openNamedOverlay() {
            if (this._name === '') {
                return;
            }

            window.dispatchEvent(new CustomEvent('wirekit-modal-show', {
                detail: { name: this._name },
                bubbles: true,
            }));
        },
    };
}
