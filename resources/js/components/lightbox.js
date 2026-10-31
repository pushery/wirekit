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
import { holdPageInert, inOverlayRoot, lockScroll, releasePageInert, unlockScroll } from '../utils/overlay.js';
import { FOCUSABLE } from '../utils/first-control.js';
import { withOpenAlias } from '../utils/open-alias.js';
import { sameName } from '../utils/same-name.js';
import { prefersReducedMotion } from '../utils/motion.js';
import { FITTED, panBy, settle, zoomTo } from '../utils/zoom-pan.js';

/** One step of the zoom buttons and keys, as a factor of the scale. */
const ZOOM_STEP = 1.5;

/** The scale a double click or a double tap toggles to. */
const ZOOM_DOUBLE = 2;

/** One step of the arrow keys on an enlarged picture, as a share of the viewer's stage. */
const PAN_STEP = 0.1;

/** Two taps closer than this, in time and in CSS pixels, are a double tap. */
const DOUBLE_TAP_MS = 300;
const DOUBLE_TAP_PX = 30;

/** Which way each arrow moves an enlarged image: the content the reader asked to see comes in. */
const ARROWS = Object.freeze({
    ArrowLeft: [1, 0],
    ArrowRight: [-1, 0],
    ArrowUp: [0, 1],
    ArrowDown: [0, -1],
});

/** The pan buttons, by side, each the step of its arrow key. */
const PAN_SIDES = Object.freeze({
    left: ARROWS.ArrowLeft,
    right: ARROWS.ArrowRight,
    up: ARROWS.ArrowUp,
    down: ARROWS.ArrowDown,
});

/** Whether a key event comes from a field, where `+`, `-`, `0` and the arrows are text. */
function typing(target) {
    if (! target || typeof target.closest !== 'function') {
        return false;
    }

    return target.closest('input, textarea, select, [contenteditable=""], [contenteditable="true"]') !== null;
}

/**
 * Whether a key comes with Ctrl, Cmd or Alt. Ctrl or Cmd with `+`, `-` or `0` zooms the whole page
 * in every desktop browser, and a reader who relies on that must keep it inside the viewer. AltGr,
 * which some layouts report as Ctrl with Alt, types a character and is not a modifier here.
 */
function modified(event) {
    if (typeof event.getModifierState === 'function' && event.getModifierState('AltGraph')) {
        return false;
    }

    return Boolean(event.ctrlKey || event.metaKey || event.altKey);
}

/** An element's box in viewport pixels, or null while it has none. */
function rectOf(element) {
    if (! element || typeof element.getBoundingClientRect !== 'function') {
        return null;
    }

    const rect = element.getBoundingClientRect();

    return rect.width > 0 && rect.height > 0
        ? { left: rect.left, top: rect.top, width: rect.width, height: rect.height }
        : null;
}

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
 * @param {boolean} [config.lockScroll=true] - Whether opening locks the page's scroll and makes
 *                                the page behind inert, as the other modal overlays do.
 */
export default function wirekitLightbox(config = {}) {
    /*
     * This component's own scope, bound once it starts, and what every method that changes state
     * writes through.
     *
     * A method called from an expression runs with `this` set to the MERGED scope of the element
     * the expression is on. A trigger inside a tooltip (the house shape for an icon button) sees
     * the tooltip's scope first, and the tooltip has an `open` of its own, so `this.open = true`
     * in `openAt()` would open the tooltip and leave the gallery closed, with no error. The same
     * holds for `$refs`: from inside the tooltip, `this.$refs` are the tooltip's. Bound at `init()`,
     * this is the scope of the lightbox's own root, where `open` and `$refs` are the lightbox's.
     */
    let host = null;

    return withOpenAlias({
        isOpen: false,
        current: 0,
        count: config.count || 0,
        loop: config.loop !== false,
        _name: config.name || '',
        // False for a viewer inside a page region, such as a preview: it takes neither the
        // page's scroll lock nor its inert hold, so the page around it keeps working.
        _lockScroll: config.lockScroll !== false,
        // The captions travel with the config so the template can ask for the
        // current one by name rather than reach into the array itself:
        // `slides[current]?.caption` is an optional chain, and Alpine's CSP
        // build has no such thing in its grammar. A getter also means the
        // out-of-range case is handled once here rather than at each of the two
        // bindings that need it.
        _slides: Array.isArray(config.slides) ? config.slides : [],

        // Zoom inside an open image, with `zoomable`. `_zoom` is the current image's scale and
        // translation (utils/zoom-pan.js), back to fitted on every change of slide and on close.
        // The enlarged image may cover the whole stage, not only the box it was fitted to.
        // `_zoomAnimated` lets the buttons and the keys ease the transform; a gesture follows the
        // pointer without one.
        _zoomable: config.zoomable === true,
        _maxZoom: typeof config.maxZoom === 'number' && config.maxZoom > 1 ? config.maxZoom : 4,
        _zoom: FITTED,
        _zoomAnimated: false,
        _dragging: false,
        // The pointers down on the image, by id, and what they are doing together: a drag of one,
        // a pinch of two. `_trackpadPinch` is a pinch that arrives as WebKit's gesture events.
        _pointers: null,
        _gesture: null,
        _trackpadPinch: null,
        _lastTap: null,
        _lastPointerType: '',
        _resizeHandler: null,
        // What a button or a key did, said by the dialog's second live region: "Zoom 200%".
        zoomAnnouncement: '',

        /** The current slide's caption, or '' when there is none. */
        get currentCaption() {
            const slide = this._slides[this.current];

            return slide && slide.caption ? slide.caption : '';
        },

        /** The language of the current slide's caption where it differs from the page's, else null. */
        get currentCaptionLang() {
            const slide = this._slides[this.current];

            return slide && slide.captionLang ? slide.captionLang : null;
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
         *
         * The live region holds the two parts apart, `announcementLead` and
         * `announcementLabel`, so the slide's text can carry its own language
         * (`announcementLang`): an alternative text in German on an English page is
         * spoken as German only where something says that it is.
         */
        get announcement() {
            return `${this.announcementLead}${this.announcementLabel}`;
        },

        /** The position in the page's language, with the full stop before the slide's text when it has one. */
        get announcementLead() {
            const template = typeof config.announcement === 'string' && config.announcement !== ''
                ? config.announcement
                : 'Slide :current of :total';

            const position = template
                .replace(':current', String(this.current + 1))
                .replace(':total', String(this.count));

            return this.announcementLabel ? `${position}. ` : position;
        },

        /** The slide's own text, its alternative text or else its caption, or '' when it has neither. */
        get announcementLabel() {
            const slide = this._slides[this.current];

            return slide ? (slide.alt || slide.caption || '') : '';
        },

        /** The language of that text where it differs from the page's, else null. */
        get announcementLang() {
            const slide = this._slides[this.current];

            if (! slide) {
                return null;
            }

            if (slide.alt) {
                return slide.altLang || null;
            }

            return slide.caption ? (slide.captionLang || null) : null;
        },
        _trap: null,
        // Whether THIS lightbox is currently holding the shared scroll lock. Not a
        // boolean about the page — several overlays can hold it at once, and the count
        // lives in `overlay.js`.
        _holdsScrollLock: false,
        // Whether this viewer holds one of the counted holds that keep the page behind it inert
        // (see `holdPageInert` in utils/overlay.js). Taken once the trap has focus in the stage,
        // given back before the trap returns focus to the page.
        _holdsPageInert: false,
        _openHandler: null,
        // The control that opened the viewer, and where focus goes back when it closes.
        // focus-trap returns focus to whatever was focused when it activated, and in Safari
        // that is <body>: a mouse click does not focus a button there, for a gallery
        // thumbnail and a standalone trigger alike. So the opener is noted from the click, or
        // from the open event's target, and handed to the trap as its return target.
        _pendingTrigger: null,
        _returnTo: null,
        _triggerNoter: null,
        _leaveHandler: null,

        init() {
            host = this;

            // Page-level open: any control can dispatch
            // wirekit-lightbox-open { name, index } to open THIS instance.
            this._openHandler = (e) => {
                const d = e.detail || {};
                if (sameName(d.name, this._name)) {
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

            // Close when the page is left, by a link in a caption or any other way: the scroll
            // lock holds the page at 0 until it is released, and the browser records the position
            // for Back after `pagehide`. See the same listener in `utils/overlay.js`.
            this._leaveHandler = () => {
                if (this.isOpen) {
                    this.close();
                }
            };
            window.addEventListener('pagehide', this._leaveHandler);

            // A phone turned on its side, or a browser bar that comes and goes, resizes the stage
            // and the fitted box under an enlarged image: settled again, it leaves no gap.
            if (this._zoomable) {
                this._resizeHandler = () => {
                    if (this.isOpen && this._zoom.scale > 1) {
                        this._settleZoom();
                    }
                };
                window.addEventListener('resize', this._resizeHandler, { passive: true });
            }
        },

        /**
         * Point an embed slide's frame at its source.
         *
         * Alpine's CSP build evaluates nothing on an `<iframe>`, so a `:src` on the frame would never
         * run there and the slide would stay blank. The frame carries no binding; the wrapper around it
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
            self._resetZoom();

            /*
             * Already open? Then this is a NAVIGATION, not an opening.
             *
             * Called on a viewer that is already up — a second thumbnail clicked behind the
             * overlay, a `wirekit-lightbox-open` event fired twice, a developer calling
             * `openAt()` to jump — arming again would build a SECOND focus trap over the same
             * container and assign it over `this._trap`. The first would stay active with
             * nobody holding it: `close()` deactivates one trap, so the orphan would keep its
             * document-level keydown listener and go on trapping Tab inside markup the reader
             * had already left, until a reload.
             *
             * The index is set above, before this returns, so the navigation still happens —
             * it is only the arming that is skipped, because it has already been done.
             */
            if (self.isOpen && self._trap) {
                return;
            }

            // Whoever opened it gets focus back when it closes; see `_returnTo`.
            self._returnTo = self._pendingTrigger;
            self._pendingTrigger = null;

            self.isOpen = true;

            /*
             * Hold the page still. This is `role="dialog" aria-modal="true"`, and without a
             * scroll lock the page would scroll behind it — a wheel or a swipe over the
             * backdrop would move the article underneath, and on iOS the rubber-band would
             * run the whole document while the viewer stayed put.
             *
             * `aria-modal="true"` is the part that makes that a defect rather than a nicety:
             * the attribute tells assistive technology that everything outside is inert, and
             * the page behind would still be both scrollable and, to a pointer, live.
             *
             * The REFERENCE-COUNTED helper from `overlay.js`, not a private `body.style`
             * write. A lightbox opens from inside a modal readily enough, and two components
             * each holding their own idea of the body's overflow is how a page ends up
             * frozen after the last one closes.
             */
            if (self._lockScroll) {
                self._holdsScrollLock = true;
                lockScroll();
            }

            self.$nextTick(() => {
                const container = self.$refs.stage;
                if (!container) {
                    return;
                }

                // The state can have changed inside the tick — Escape during the frame, a
                // Livewire morph, a close from anywhere. Arming here would put a trap on an
                // overlay that is no longer shown, and nothing would ever take it off.
                if (!self.isOpen || self._trap) {
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
                        // release has to be on this path too — it is the commonest way out
                        // of the viewer. It runs before the trap returns focus, which an
                        // inert page would refuse.
                        self._releasePageInert();
                        self._releaseScrollLock();
                        self._resetZoom();
                        self.isOpen = false;
                        self._trap = null;
                    },
                });
                self._trap.activate();

                if (self._lockScroll && ! self._holdsPageInert && inOverlayRoot(container)) {
                    self._holdsPageInert = holdPageInert();
                }
            });
        },

        close() {
            const self = host ?? this;

            self._resetZoom();

            self._releasePageInert();
            self._releaseScrollLock();

            if (self._trap) {
                self._trap.deactivate();
            } else {
                self.isOpen = false;
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

        /** Give back the hold on the page behind the viewer, once, for the same reason. */
        _releasePageInert() {
            if (this._holdsPageInert) {
                this._holdsPageInert = false;
                releasePageInert();
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
            self._resetZoom();
        },

        prev() {
            const self = host ?? this;

            if (self.count === 0) {
                return;
            }
            self.current = self.loop
                ? (self.current - 1 + self.count) % self.count
                : Math.max(self.current - 1, 0);
            self._resetZoom();
        },

        // Whether prev/next is available (for disabling the controls at the ends
        // when loop is off).
        get hasPrev() { return this.loop || this.current > 0; },
        get hasNext() { return this.loop || this.current < this.count - 1; },

        get canZoomIn() { return this._zoom.scale < this._maxZoom; },
        get canZoomOut() { return this._zoom.scale > 1; },
        // An enlarged image can be moved, and the pan buttons show only then.
        get canPan() { return this._zoom.scale > 1; },

        /**
         * The transform of slide `idx`'s image: the zoom of the current one, fitted for the others.
         * A method rather than an inline expression, so the CSP build evaluates it. An object, so
         * Alpine sets it property by property through the element's `style`: a string it writes
         * with `setAttribute()`, which takes every other inline style with it and which a
         * `style-src` without `'unsafe-inline'` refuses.
         */
        zoomStyle(idx) {
            const self = host ?? this;
            const zoom = idx === self.current ? self._zoom : FITTED;
            const style = {
                transform: `translate(${zoom.x}px, ${zoom.y}px) scale(${zoom.scale})`,
                transformOrigin: '0 0',
                cursor: zoom.scale > 1 ? (self._dragging ? 'grabbing' : 'grab') : 'zoom-in',
            };

            if (self._zoomAnimated && ! prefersReducedMotion()) {
                style.transitionProperty = 'opacity, transform';
            }

            return style;
        },

        zoomIn() { (host ?? this)._zoomBy(ZOOM_STEP); },
        zoomOut() { (host ?? this)._zoomBy(1 / ZOOM_STEP); },

        /**
         * Move an enlarged image one step toward `side`, as its arrow key does: the content on
         * that side comes in. The way to move it with a pointer that cannot drag (WCAG 2.5.7).
         */
        pan(side) {
            const self = host ?? this;

            if (PAN_SIDES[side] && self._zoom.scale > 1) {
                self._panStep(PAN_SIDES[side]);
            }
        },

        /** One step of the arrow keys and the pan buttons; false when the stage has no size yet. */
        _panStep([dx, dy]) {
            const self = host ?? this;
            const geometry = self._geometry();

            if (! geometry) {
                return false;
            }

            self._zoomAnimated = true;
            self._zoom = panBy(self._zoom, dx * PAN_STEP * geometry.frame.width, dy * PAN_STEP * geometry.frame.height, geometry.box, geometry.frame);

            return true;
        },

        resetZoom() {
            const self = host ?? this;

            if (self._zoom.scale === 1) {
                return;
            }

            self._zoomAnimated = true;
            self._zoom = FITTED;
            self._announceZoom();
        },

        /** `+` and `=` zoom in, `-` out, `0` back; the arrows move an enlarged image. */
        onZoomKey(event) {
            const self = host ?? this;

            if (! self._zoomable || modified(event) || typing(event.target)) {
                return;
            }

            if (event.key === '+' || event.key === '=') {
                self.zoomIn();
            } else if (event.key === '-') {
                self.zoomOut();
            } else if (event.key === '0') {
                self.resetZoom();
            } else if (ARROWS[event.key] && self._zoom.scale > 1) {
                // While the image is enlarged the arrows move it, and they are Prev and Next again
                // at the fitted size: the slide handlers after this one must not see the key.
                if (! self._panStep(ARROWS[event.key])) {
                    return;
                }
            } else {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation?.();
        },

        /**
         * The wheel zooms around the pointer. A wheel with `ctrlKey` is the form a trackpad pinch takes
         * where it arrives as a wheel at all, and it steps faster, as a pinch reports small deltas.
         */
        onZoomWheel(event) {
            const self = host ?? this;

            if (! self._zoomable) {
                return;
            }

            // Over a zoomable image the wheel zooms, at either end of the scale: it must never
            // scroll a page left unlocked behind the viewer instead.
            event.preventDefault();

            // A pinch that also arrives as gesture events is read from those alone.
            if (self._trackpadPinch) {
                return;
            }

            const geometry = self._geometry(event.currentTarget);

            if (! geometry) {
                return;
            }

            // A line or a page of scrolling, as some mice report it, in pixels.
            const unit = event.deltaMode === 1 ? 16 : (event.deltaMode === 2 ? geometry.frame.height : 1);
            const factor = Math.exp(-event.deltaY * unit * (event.ctrlKey ? 0.01 : 0.002));
            const at = self._pointIn(geometry.box, event.clientX, event.clientY);

            self._zoomAnimated = false;
            self._zoom = zoomTo(self._zoom, self._zoom.scale * factor, at, at, geometry.box, geometry.frame, self._maxZoom);
        },

        /**
         * WebKit's own gesture events (`gesturestart`, `gesturechange`, `gestureend`), whose `scale`
         * counts from the start of the pinch: the form a pinch takes where it does not arrive as a
         * wheel. A pinch on a touch screen is read from the pointers, and a browser that sends both
         * is answered once, by them.
         */
        onZoomGesture(event) {
            const self = host ?? this;

            if (! self._zoomable || (self._pointers?.size ?? 0) > 0) {
                return;
            }

            event.preventDefault();

            if (event.type === 'gestureend') {
                self._trackpadPinch = null;

                return;
            }

            if (event.type === 'gesturestart' || ! self._trackpadPinch) {
                self._trackpadPinch = { from: self._zoom };
            }

            const geometry = self._geometry(event.currentTarget);

            if (! geometry || ! Number.isFinite(event.scale)) {
                return;
            }

            const from = self._trackpadPinch.from;
            const at = self._pointIn(geometry.box, event.clientX, event.clientY);

            self._zoomAnimated = false;
            self._zoom = zoomTo(from, from.scale * event.scale, at, at, geometry.box, geometry.frame, self._maxZoom);
        },

        /** A double click toggles between the fitted size and twice it, around the point. */
        onZoomDoubleClick(event) {
            const self = host ?? this;

            // A double tap is read from the pointer events, which a touch screen also turns into a
            // double click: answering both would zoom in and straight back out.
            if (self._lastPointerType === 'touch') {
                return;
            }

            self._toggleZoomAt(event.clientX, event.clientY, event.currentTarget);
        },

        onZoomPointerDown(event) {
            const self = host ?? this;

            // The main button of a mouse, a pen or a finger; a right click opens its menu.
            if (! self._zoomable || (event.pointerType === 'mouse' && event.button !== 0)) {
                return;
            }

            self._lastPointerType = event.pointerType || '';
            self._pointers ??= new Map();
            self._pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
            // Held by the image until it is let go, so a drag that ends over the backdrop does not
            // become a click there, which would close the viewer. A pointer the browser no longer
            // counts as active cannot be held, and the gesture goes on without it.
            try {
                event.currentTarget?.setPointerCapture?.(event.pointerId);
            } catch {
                // NotFoundError: nothing to hold.
            }
            self._startGesture(event.currentTarget);
        },

        onZoomPointerMove(event) {
            const self = host ?? this;
            const gesture = self._gesture;

            if (! gesture || ! self._pointers?.has(event.pointerId)) {
                return;
            }

            self._pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
            const points = [...self._pointers.values()];
            const { box, frame } = gesture;
            self._zoomAnimated = false;

            if (gesture.type === 'pinch' && points.length >= 2) {
                // The point of the image that was under the fingers stays under them, scaled by how
                // far apart they have moved and carried along with them.
                const mid = { x: (points[0].x + points[1].x) / 2, y: (points[0].y + points[1].y) / 2 };
                const distance = Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y);
                self._zoom = zoomTo(gesture.from, gesture.from.scale * (distance / gesture.distance), self._pointIn(box, gesture.mid.x, gesture.mid.y), self._pointIn(box, mid.x, mid.y), box, frame, self._maxZoom);
                gesture.moved = true;
            } else if (gesture.type === 'drag') {
                const dx = event.clientX - gesture.start.x;
                const dy = event.clientY - gesture.start.y;

                if (Math.abs(dx) + Math.abs(dy) > 3) {
                    gesture.moved = true;
                    self._dragging = true;
                }

                self._zoom = panBy(gesture.from, dx, dy, box, frame);
            }
        },

        /** The end of a press on the image, and a `pointercancel`, which ends it without a tap. */
        onZoomPointerUp(event) {
            const self = host ?? this;

            if (! self._pointers?.has(event.pointerId)) {
                return;
            }

            const gesture = self._gesture;
            self._pointers.delete(event.pointerId);

            if (event.type !== 'pointercancel' && event.pointerType === 'touch' && gesture && ! gesture.moved && self._pointers.size === 0) {
                const now = Date.now();
                const last = self._lastTap;

                if (last && now - last.time < DOUBLE_TAP_MS && Math.hypot(event.clientX - last.x, event.clientY - last.y) < DOUBLE_TAP_PX) {
                    self._lastTap = null;
                    self._toggleZoomAt(event.clientX, event.clientY, event.currentTarget);
                } else {
                    self._lastTap = { time: now, x: event.clientX, y: event.clientY };
                }
            }

            self._dragging = false;
            self._gesture = null;

            // A pinch that loses one finger goes on as a drag of the other, from where the image
            // now is. That finger was part of a pinch, so its lift is not a tap.
            if (self._pointers.size > 0) {
                self._startGesture(event.currentTarget);

                if (self._gesture) {
                    self._gesture.moved = true;
                }
            }
        },

        _startGesture(target) {
            const geometry = this._geometry(target);
            const points = [...(this._pointers?.values() ?? [])];

            if (! geometry || points.length === 0) {
                this._gesture = null;

                return;
            }

            if (points.length >= 2) {
                this._gesture = {
                    type: 'pinch',
                    ...geometry,
                    from: this._zoom,
                    mid: { x: (points[0].x + points[1].x) / 2, y: (points[0].y + points[1].y) / 2 },
                    distance: Math.max(1, Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y)),
                    moved: false,
                };
            } else {
                this._gesture = { type: 'drag', ...geometry, from: this._zoom, start: points[0], moved: false };
            }
        },

        _toggleZoomAt(clientX, clientY, target) {
            const self = host ?? this;
            const geometry = self._geometry(target);

            if (! self._zoomable || ! geometry) {
                return;
            }

            const at = self._pointIn(geometry.box, clientX, clientY);
            self._zoomAnimated = true;
            self._zoom = self._zoom.scale > 1
                ? FITTED
                : zoomTo(self._zoom, ZOOM_DOUBLE, at, at, geometry.box, geometry.frame, self._maxZoom);
        },

        /** The buttons and the keys: a step around the middle of the stage, said aloud. */
        _zoomBy(factor) {
            const self = host ?? this;
            const geometry = self._geometry();

            if (! self._zoomable || ! geometry) {
                return;
            }

            const { box, frame } = geometry;
            const center = self._pointIn(box, frame.left + frame.width / 2, frame.top + frame.height / 2);
            const next = zoomTo(self._zoom, self._zoom.scale * factor, center, center, box, frame, self._maxZoom);

            // At either end the step changes nothing, and saying the same scale again would only
            // repeat what the reader already heard.
            if (next.scale === self._zoom.scale) {
                return;
            }

            self._zoomAnimated = true;
            self._zoom = next;
            self._announceZoom();
        },

        _settleZoom() {
            const geometry = this._geometry();

            if (geometry) {
                this._zoomAnimated = false;
                this._zoom = settle(this._zoom, geometry.box, geometry.frame);
            }
        },

        /**
         * Where the current image stands at its fitted size (`box`), and the area its enlarged
         * form may cover (`frame`, the dialog's stage), both in viewport pixels. The box is the
         * wrapper around the image, which the image's own transform never moves. Null while either
         * has no size, such as a video slide or a closed viewer.
         */
        _geometry(target = null) {
            const self = host ?? this;
            const stage = self.$refs?.stage ?? null;
            const holder = target && typeof target.closest === 'function'
                ? target.closest('[data-wk-lightbox-zoom]')
                : stage?.querySelector?.(`[data-wk-lightbox-zoom="${self.current}"]`) ?? null;
            const box = rectOf(holder);
            const frame = rectOf(stage);

            return box && frame ? { box, frame } : null;
        },

        /** A viewport point relative to the fitted box, or the box's middle when there is none. */
        _pointIn(box, clientX, clientY) {
            return Number.isFinite(clientX) && Number.isFinite(clientY)
                ? { x: clientX - box.left, y: clientY - box.top }
                : { x: box.width / 2, y: box.height / 2 };
        },

        _announceZoom() {
            const template = typeof config.zoomAnnouncement === 'string' && config.zoomAnnouncement !== ''
                ? config.zoomAnnouncement
                : 'Zoom :percent%';

            this.zoomAnnouncement = template.replace(':percent', String(Math.round(this._zoom.scale * 100)));
        },

        _resetZoom() {
            this._zoom = FITTED;
            this._zoomAnimated = false;
            this._dragging = false;
            this._gesture = null;
            this._trackpadPinch = null;
            this._lastTap = null;
            this._pointers?.clear();
            this.zoomAnnouncement = '';
        },

        destroy() {
            if (this._openHandler) {
                window.removeEventListener('wirekit-lightbox-open', this._openHandler);
                this._openHandler = null;
            }
            if (this._triggerNoter) {
                this.$el?.removeEventListener?.('click', this._triggerNoter, true);
                this._triggerNoter = null;
            }
            if (this._leaveHandler) {
                window.removeEventListener('pagehide', this._leaveHandler);
                this._leaveHandler = null;
            }
            if (this._resizeHandler) {
                window.removeEventListener('resize', this._resizeHandler);
                this._resizeHandler = null;
            }
            this._returnTo = null;
            this._releasePageInert();
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
    });
}
