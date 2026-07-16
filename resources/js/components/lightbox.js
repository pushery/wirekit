/**
 * WireKit Lightbox Alpine component.
 *
 * A reusable, accessible zoom overlay for media (images, video, embeds). It is a
 * teleported `role="dialog"` that is focus-trapped (returns focus to the trigger
 * on close), closes on Escape, and steps through its items with the arrow keys.
 * Built on the same `createFocusTrap` helper Modal and Drawer use, so the focus
 * contract is the tested one.
 *
 * Opened either from a child trigger in the same scope (`openAt(index)`) or from
 * anywhere on the page by dispatching `wirekit-lightbox-open` with a matching
 * `name` + `index` — so a gallery, a single thumbnail, or any custom control can
 * drive it.
 */
import { createFocusTrap } from '../utils/focus-trap.js';

/**
 * @param {Object} config
 * @param {string} config.name  - Identifier this instance answers to for the
 *                                `wirekit-lightbox-open` event.
 * @param {number} config.count - Number of items.
 * @param {boolean} config.loop - Whether prev/next wraps at the ends.
 */
export default function wirekitLightbox(config = {}) {
    return {
        open: false,
        current: 0,
        count: config.count || 0,
        loop: config.loop !== false,
        _name: config.name || '',
        _trap: null,
        _openHandler: null,

        init() {
            // Page-level open: any control can dispatch
            // wirekit-lightbox-open { name, index } to open THIS instance.
            this._openHandler = (e) => {
                const d = e.detail || {};
                if ((d.name || '') === this._name) {
                    this.openAt(d.index || 0);
                }
            };
            window.addEventListener('wirekit-lightbox-open', this._openHandler);
        },

        openAt(index) {
            if (this.count === 0) {
                return;
            }
            this.current = Math.max(0, Math.min(index, this.count - 1));
            this.open = true;
            this.$nextTick(() => {
                const container = this.$refs.stage;
                if (!container) {
                    return;
                }
                this._trap = createFocusTrap(container, {
                    escapeDeactivates: true,
                    // Escape / programmatic deactivate tears down + flips the flag
                    // so x-show hides the overlay; focus returns to the trigger.
                    onDeactivate: () => {
                        this.open = false;
                        this._trap = null;
                    },
                });
                this._trap.activate();
            });
        },

        close() {
            if (this._trap) {
                this._trap.deactivate();
            } else {
                this.open = false;
            }
        },

        next() {
            if (this.count === 0) {
                return;
            }
            this.current = this.loop
                ? (this.current + 1) % this.count
                : Math.min(this.current + 1, this.count - 1);
        },

        prev() {
            if (this.count === 0) {
                return;
            }
            this.current = this.loop
                ? (this.current - 1 + this.count) % this.count
                : Math.max(this.current - 1, 0);
        },

        // Whether prev/next is available (for disabling the controls at the ends
        // when loop is off).
        get hasPrev() { return this.loop || this.current > 0; },
        get hasNext() { return this.loop || this.current < this.count - 1; },

        destroy() {
            if (this._openHandler) {
                window.removeEventListener('wirekit-lightbox-open', this._openHandler);
                this._openHandler = null;
            }
            // Never leave an active trap behind on teardown (SPA nav, Livewire
            // morph) — it would keep focus locked to a detached node.
            if (this._trap) {
                this._trap.deactivate();
                this._trap = null;
            }
        },
    };
}
