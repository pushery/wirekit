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
import { lockScroll, unlockScroll } from '../utils/overlay.js';
import { FOCUSABLE } from '../utils/first-control.js';

/**
 * The focusable control an event came from, or null. Duck-typed rather than `instanceof Element`,
 * so a dispatch on `window` (whose target is the window) and the Node harness both answer null.
 */
function focusableFrom(target) {
    return target && typeof target.closest === 'function' ? target.closest(FOCUSABLE) : null;
}

/**
 * @param {Object} config
 * @param {string} config.name  - Identifier this instance answers to for the
 *                                `wirekit-lightbox-open` event.
 * @param {number} config.count - Number of items.
 * @param {boolean} config.loop - Whether prev/next wraps at the ends.
 */
export default function wirekitLightbox(config = {}) {
    /*
     * This component's own scope, bound once it starts, and what every method that changes state
     * writes through.
     *
     * A method called from an expression runs with `this` set to the MERGED scope of the element
     * the expression is on. A trigger inside a tooltip (the house shape for an icon button) sees
     * the tooltip's scope first, and the tooltip has an `open` of its own, so `this.open = true`
     * in `openAt()` opened the tooltip and left the gallery closed, with no error. The same held
     * for `$refs`: from inside the tooltip, `this.$refs` are the tooltip's. Bound at `init()`,
     * this is the scope of the lightbox's own root, where `open` and `$refs` are the lightbox's.
     */
    let host = null;

    return {
        open: false,
        current: 0,
        count: config.count || 0,
        loop: config.loop !== false,
        _name: config.name || '',
        // The captions travel with the config so the template can ask for the
        // current one by name. It used to reach into the array itself —
        // `slides[current]?.caption` — which is an optional chain, and Alpine's
        // CSP build has no such thing in its grammar. A getter also means the
        // out-of-range case is handled once here rather than at each of the two
        // bindings that need it.
        _slides: Array.isArray(config.slides) ? config.slides : [],

        /** The current slide's caption, or '' when there is none. */
        get currentCaption() {
            const slide = this._slides[this.current];

            return slide && slide.caption ? slide.caption : '';
        },

        /**
         * The one string a screen reader hears on every slide change.
         *
         * Stepping the gallery swaps the media and leaves focus on the control that
         * did it, so nothing about the change reaches a reader who cannot see it —
         * not the position, not the alt text, not the caption. This is what the
         * live region in the template says.
         *
         * The position arrives as a TEMPLATE the server already translated: a
         * sentence assembled from fragments here cannot be, and "of" is not a word
         * every language puts in the middle. The English fallback covers a caller
         * who constructs the factory by hand rather than through the component.
         *
         * The slide's own text is appended as a SECOND sentence rather than folded
         * into the first. A full stop is punctuation in every language this ships
         * to, so joining two finished sentences is safe where joining two fragments
         * would not be — and a reader gets a pause between where they are and what
         * is there.
         */
        get announcement() {
            const template = typeof config.announcement === 'string' && config.announcement !== ''
                ? config.announcement
                : 'Slide :current of :total';

            const position = template
                .replace(':current', String(this.current + 1))
                .replace(':total', String(this.count));

            const slide = this._slides[this.current];
            const label = slide ? (slide.alt || slide.caption || '') : '';

            return label ? `${position}. ${label}` : position;
        },
        _trap: null,
        // Whether THIS lightbox is currently holding the shared scroll lock. Not a
        // boolean about the page — several overlays can hold it at once, and the count
        // lives in `overlay.js`.
        _holdsScrollLock: false,
        _openHandler: null,
        // The control that opened the viewer, and where focus goes back when it closes.
        // focus-trap returns focus to whatever was focused when it activated, and in Safari
        // that is <body>: a mouse click does not focus a button there. Measured in WebKit,
        // focus sat on <body> after the click and again after Escape, for a gallery thumbnail
        // and a standalone trigger alike, while Chromium returned it to the button. So the
        // opener is noted from the click, or from the open event's target, and handed to the
        // trap as its return target.
        _pendingTrigger: null,
        _returnTo: null,
        _triggerNoter: null,

        init() {
            host = this;

            // Page-level open: any control can dispatch
            // wirekit-lightbox-open { name, index } to open THIS instance.
            this._openHandler = (e) => {
                const d = e.detail || {};
                if ((d.name || '') === this._name) {
                    // `$dispatch` fires from the control itself, so the event's target is the
                    // opener. A dispatch on `window` has none, and the trap's default stands.
                    this._pendingTrigger = focusableFrom(e.target);
                    this.openAt(d.index || 0);
                }
            };
            window.addEventListener('wirekit-lightbox-open', this._openHandler);

            // Note the control a click inside this component came from. Capture phase, so it is
            // noted before that control's own `openAt()` runs; cleared once the click has finished
            // dispatching, so a click that opened nothing cannot stand in for a later programmatic
            // open. Clicks inside the viewer never reach it: the viewer is teleported out of here.
            this._triggerNoter = (e) => {
                this._pendingTrigger = focusableFrom(e.target);
                setTimeout(() => { this._pendingTrigger = null; }, 0);
            };
            this.$el?.addEventListener?.('click', this._triggerNoter, true);
        },

        /**
         * Point an embed slide's frame at its source.
         *
         * Alpine's CSP build evaluates nothing on an `<iframe>`, so a `:src` on the frame never
         * ran there and the slide stayed blank. The frame carries no binding; the wrapper around it
         * calls this, which is plain JavaScript and runs under both builds. The source was checked
         * on the server, where a scheme other than http(s) or a path is dropped.
         *
         * @param {HTMLElement} wrapper - The element around the frame.
         * @param {{src?: string, alt?: string}} item - The slide.
         */
        embed(wrapper, item) {
            const frame = wrapper && typeof wrapper.querySelector === 'function'
                ? wrapper.querySelector('iframe')
                : null;

            if (!frame || !item) return;

            frame.setAttribute('title', String(item.alt || ''));
            frame.setAttribute('src', String(item.src || ''));
        },

        openAt(index) {
            const self = host ?? this;

            if (self.count === 0) {
                return;
            }
            self.current = Math.max(0, Math.min(index, self.count - 1));

            /*
             * Already open? Then this is a NAVIGATION, not an opening.
             *
             * Called on a viewer that is already up — a second thumbnail clicked behind the
             * overlay, a `wirekit-lightbox-open` event fired twice, a developer calling
             * `openAt()` to jump — this built a SECOND focus trap over the same container
             * and assigned it over `this._trap`. The first stayed active with nobody holding
             * it: `close()` deactivates one trap, so the orphan kept its document-level
             * keydown listener and went on trapping Tab inside markup the reader had already
             * left. The only way out was a reload.
             *
             * The index is set above, before this returns, so the navigation still happens —
             * it is only the arming that is skipped, because it has already been done.
             */
            if (self.open && self._trap) {
                return;
            }

            // Whoever opened it gets focus back when it closes; see `_returnTo`.
            self._returnTo = self._pendingTrigger;
            self._pendingTrigger = null;

            self.open = true;

            /*
             * Hold the page still. This is `role="dialog" aria-modal="true"` and it took no
             * scroll lock at all, so the page scrolled behind it — a wheel or a swipe over
             * the backdrop moved the article underneath, and on iOS the rubber-band ran the
             * whole document while the viewer stayed put.
             *
             * `aria-modal="true"` is the part that makes it a defect rather than a nicety:
             * the attribute tells assistive technology that everything outside is inert,
             * and the page behind was still both scrollable and, to a pointer, live.
             *
             * The REFERENCE-COUNTED helper from `overlay.js`, not a private `body.style`
             * write. A lightbox opens from inside a modal readily enough, and two components
             * each holding their own idea of the body's overflow is how a page ends up
             * frozen after the last one closes — `command-palette` carries a comment about
             * exactly that, from when it did keep its own.
             */
            self._holdsScrollLock = true;
            lockScroll();

            self.$nextTick(() => {
                const container = self.$refs.stage;
                if (!container) {
                    return;
                }

                // The state can have changed inside the tick — Escape during the frame, a
                // Livewire morph, a close from anywhere. Arming here would put a trap on an
                // overlay that is no longer shown, and nothing would ever take it off.
                if (!self.open || self._trap) {
                    return;
                }

                self._trap = createFocusTrap(container, {
                    escapeDeactivates: true,
                    // Back to the opener while it is still in the page; otherwise the library's
                    // own choice, which is whatever was focused when the trap activated.
                    setReturnFocus: (previous) => (self._returnTo && self._returnTo.isConnected ? self._returnTo : previous),
                    // Escape / programmatic deactivate tears down + flips the flag
                    // so x-show hides the overlay; focus returns to the trigger.
                    onDeactivate: () => {
                        // Escape lands here without passing through `close()`, so the
                        // release has to be on this path too — it was the commonest way out
                        // of the viewer and would have left the page locked for good.
                        self._releaseScrollLock();
                        self.open = false;
                        self._trap = null;
                    },
                });
                self._trap.activate();
            });
        },

        close() {
            const self = host ?? this;

            self._releaseScrollLock();

            if (self._trap) {
                self._trap.deactivate();
            } else {
                self.open = false;
            }
        },

        /**
         * Give the page back, once.
         *
         * Idempotent through `_holdsScrollLock`, because the close paths overlap: Escape
         * reaches `onDeactivate`, a backdrop click reaches `close()`, and a teardown reaches
         * `destroy()`. The count in `overlay.js` is shared with every other overlay, so
         * releasing twice would decrement somebody else's lock and unfreeze a page a modal
         * is still holding.
         */
        _releaseScrollLock() {
            if (this._holdsScrollLock) {
                this._holdsScrollLock = false;
                unlockScroll();
            }
        },

        next() {
            const self = host ?? this;

            if (self.count === 0) {
                return;
            }
            self.current = self.loop
                ? (self.current + 1) % self.count
                : Math.min(self.current + 1, self.count - 1);
        },

        prev() {
            const self = host ?? this;

            if (self.count === 0) {
                return;
            }
            self.current = self.loop
                ? (self.current - 1 + self.count) % self.count
                : Math.max(self.current - 1, 0);
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
            if (this._triggerNoter) {
                this.$el?.removeEventListener?.('click', this._triggerNoter, true);
                this._triggerNoter = null;
            }
            this._returnTo = null;
            // Never leave an active trap behind on teardown (SPA nav, Livewire
            // morph) — it would keep focus locked to a detached node.
            if (this._trap) {
                this._trap.deactivate();
                this._trap = null;
            }

            // And never leave the page locked. A teardown while the viewer is open is the
            // one path where nothing else releases: `close()` is not called, and the trap's
            // `onDeactivate` fires into a scope that is going away. The count is shared, so
            // an unreleased hold freezes the page for every overlay that comes after.
            this._releaseScrollLock();
        },
    };
}
