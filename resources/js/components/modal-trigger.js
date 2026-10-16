import { isNamedOverlayOpen } from '../utils/overlay.js';

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
 * `x-data`, which is the part that actually bites. A drawer has the same problem with nearly every
 * trigger it has: it is opened from a toolbar, a navbar or a filter button above a list, never
 * from inside itself, so `drawer.trigger` only exists in this named form.
 *
 * **An element outside every `x-data` tree is never walked, so its directive is not refused —
 * it is never installed.** That is worse than a refusal in the one way that matters: a refused
 * expression at least leaves the modifiers working, while an uninstalled directive leaves
 * `.prevent` off too, so the anchor plainly navigates. It is not a policy effect: it happens in
 * every bundle.
 *
 * So the trigger brings its own scope. That is the whole reason this factory exists — the dispatch
 * itself is one line.
 *
 * Once it can open its overlay, the trigger also says so: `aria-haspopup="dialog"`, and
 * `aria-expanded` in step with the overlay, read from the stack of open overlays, which announces
 * every change. While the overlay is open, `aria-controls` names its dialog. None of the three is
 * rendered on the server: without JavaScript a link trigger is an ordinary link, and the attributes
 * would describe a dialog that never opens.
 *
 * @param {Object} config
 * @param {string} config.name - the `name` of the overlay to open
 * @param {string} [config.overlay='modal'] - which kind of overlay it opens, 'modal' or 'drawer'
 * @param {string} [config.panel=''] - the id of the overlay's dialog, for `aria-controls`
 */
export default function wirekitModalTrigger(config = {}) {
    // Each kind listens for its own event. Any other value opens a modal, as every named trigger
    // did before it could name a kind.
    const drawer = config.overlay === 'drawer';

    return {
        _name: config.name || '',
        _showEvent: drawer ? 'wirekit-drawer-show' : 'wirekit-modal-show',
        // An id reference cannot hold whitespace: `aria-controls` takes a list of ids.
        _panel: typeof config.panel === 'string' && ! /\s/.test(config.panel) ? config.panel : '',
        _stackHandler: null,

        init() {
            // A trigger that names nothing opens nothing, so it claims no popup either.
            if (this._name === '') {
                return;
            }

            this.$el.setAttribute('aria-haspopup', 'dialog');

            this._stackHandler = () => this.syncExpanded();
            window.addEventListener('wirekit-overlay-stack-changed', this._stackHandler);
            this.syncExpanded();
        },

        destroy() {
            if (this._stackHandler) {
                window.removeEventListener('wirekit-overlay-stack-changed', this._stackHandler);
                this._stackHandler = null;
            }
        },

        /**
         * `aria-expanded` from whether the named overlay is open, and `aria-controls` while it is.
         *
         * Only while it is open, and only when its dialog is in the document: a closed dialog is
         * hidden, and a reference to it, or to an id nothing carries, sends a reader nowhere.
         */
        syncExpanded() {
            const open = isNamedOverlayOpen(this._showEvent, this._name);

            this.$el.setAttribute('aria-expanded', open ? 'true' : 'false');

            if (open && this._panel !== '' && document.getElementById(this._panel)) {
                this.$el.setAttribute('aria-controls', this._panel);
            } else {
                this.$el.removeAttribute('aria-controls');
            }
        },

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
         *
         * Both events are written out where they are sent, so a reading of the source finds every
         * name the catalog dispatches.
         *
         * The click is handled here rather than by `.prevent.stop`, because a trigger with `href`
         * is a link, and a click with Ctrl, Cmd, Shift or Alt on a link is the reader asking for a
         * new tab, a new window or a download of its page. That click stays the browser's and
         * opens nothing; every other click opens the overlay and keeps the link from navigating.
         * No click reaches the trigger's ancestors, so a `<label>` around it ticks no checkbox.
         *
         * @param {MouseEvent|null} [event]
         */
        openNamedOverlay(event = null) {
            if (event) {
                event.stopPropagation();

                const link = typeof event.currentTarget?.hasAttribute === 'function' && event.currentTarget.hasAttribute('href');

                if (link && (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey)) {
                    return;
                }

                event.preventDefault();
            }

            if (this._name === '') {
                return;
            }

            const init = { detail: { name: this._name }, bubbles: true };

            window.dispatchEvent(drawer
                ? new CustomEvent('wirekit-drawer-show', init)
                : new CustomEvent('wirekit-modal-show', init));
        },
    };
}
