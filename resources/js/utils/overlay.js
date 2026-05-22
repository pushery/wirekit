/**
 * Shared overlay utilities for WireKit Modal and Drawer.
 *
 * Provides scroll lock, event handling, wire:model sync, and SPA cleanup.
 * Both Modal and Drawer share identical logic for these concerns.
 */
import { createFocusTrap } from './focus-trap.js';

/**
 * Global scroll lock reference counter.
 * Tracks how many overlays currently hold a scroll lock so that
 * body overflow is only restored when ALL overlays are closed.
 */
let scrollLockCount = 0;
// Snapshot of style values we mutated, so unlockScroll can restore the page
// to its exact pre-lock state instead of clearing inline styles the developer
// might have relied on.
let scrollLockSnapshot = null;

/**
 * Global active-overlay stack — supports modal-over-modal layering.
 *
 * Push on show, pop on close. The topmost entry (last in array) is the
 * overlay that should respond to ESC and carry `aria-modal="true"` —
 * any open overlay below it is "behind" the topmost in the user's
 * visual + interaction hierarchy and should NOT react to ESC (otherwise
 * one ESC press cascades through every open modal at once).
 *
 * Required because each Blade overlay registers its own window-level
 * `keydown.escape` listener (the listener is window-level so Playwright
 * `press('Escape')` against a non-focusable panel still hits it; without
 * the window listener focus-trap's `escapeDeactivates` would miss the
 * event because focus has fallen to body). With two open modals, two
 * window listeners both fire on a single ESC — without this stack guard
 * both modals would close instead of just the top one.
 */
const overlayStack = [];

function pushOverlay(token) {
    overlayStack.push(token);
}

function popOverlay(token) {
    const idx = overlayStack.lastIndexOf(token);
    if (idx !== -1) overlayStack.splice(idx, 1);
}

export function isTopmostOverlay(token) {
    return overlayStack.length > 0 && overlayStack[overlayStack.length - 1] === token;
}

/**
 * Broadcast a stack-changed event so every mounted overlay refreshes its
 * `isTopmost` flag. Each Alpine instance listens to this on init and
 * re-evaluates `isTopmostOverlay(stackToken)` against its own token —
 * cheap O(N) over open overlays, fires only when the stack mutates.
 */
function broadcastStackChange() {
    window.dispatchEvent(new CustomEvent('wirekit-overlay-stack-changed'));
}

/**
 * Engage scroll lock on <body>.
 *
 * Two compounding browser quirks force this to be more than a one-line
 * `overflow: hidden`:
 *
 *  1. **Scrollbar layout shift.** Removing the page's vertical scrollbar
 *     widens the viewport by the scrollbar's gutter (~15px on Windows /
 *     classic macOS scrollbars; 0px on macOS auto-hiding scrollbars).
 *     Without compensation the page's content visibly jumps right when
 *     an overlay opens, then jumps back on close — distracting and the
 *     #1 reason 'Known Limitations: ~15px layout shift' was a perennial
 *     bug for every UI library that did the naive overflow:hidden lock.
 *     Fix: `padding-right: scrollbarWidth` on <body> while locked, so the
 *     page width stays constant.
 *
 *  2. **iOS Safari ignores overflow:hidden on <body>.** A long-standing
 *     WebKit bug — touch scrolling continues to drag the page behind the
 *     overlay. Fix: position:fixed + capture-and-restore scrollY pattern.
 *     This pins the document at its current scroll position; on unlock
 *     we restore both the inline styles AND scroll back to the captured
 *     position so the page reads as if nothing happened.
 */
function lockScroll() {
    if (scrollLockCount === 0) {
        const scrollY = window.scrollY || document.documentElement.scrollTop;
        // Scrollbar width: difference between visual viewport (window.innerWidth,
        // includes scrollbar gutter) and document layout width (clientWidth,
        // excludes scrollbar gutter). 0px for users on auto-hiding scrollbars
        // (macOS default, mobile) — the no-op path stays a no-op.
        const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;

        scrollLockSnapshot = {
            scrollY,
            bodyOverflow: document.body.style.overflow,
            bodyPosition: document.body.style.position,
            bodyTop: document.body.style.top,
            bodyWidth: document.body.style.width,
            bodyPaddingRight: document.body.style.paddingRight,
        };

        document.body.style.overflow = 'hidden';
        // iOS Safari fix: position:fixed pins the body where it is; without
        // top:-scrollY the page jumps to top:0 the moment we apply position:fixed.
        document.body.style.position = 'fixed';
        document.body.style.top = `-${scrollY}px`;
        document.body.style.width = '100%';
        if (scrollbarWidth > 0) {
            document.body.style.paddingRight = `${scrollbarWidth}px`;
        }
    }
    scrollLockCount++;
}

function unlockScroll() {
    scrollLockCount = Math.max(0, scrollLockCount - 1);
    if (scrollLockCount === 0 && scrollLockSnapshot) {
        const { scrollY, bodyOverflow, bodyPosition, bodyTop, bodyWidth, bodyPaddingRight } = scrollLockSnapshot;
        // Restore the inline-style values we captured at lock time. Setting
        // them back to '' (empty string) where the developer had no inline
        // style preserves the cascade — class-based overrides keep working.
        document.body.style.overflow = bodyOverflow;
        document.body.style.position = bodyPosition;
        document.body.style.top = bodyTop;
        document.body.style.width = bodyWidth;
        document.body.style.paddingRight = bodyPaddingRight;
        // Restore scroll position before iOS-style position:fixed was applied.
        // window.scrollTo with `behavior: 'instant'` to avoid an animated
        // jump on close; the user's mental model is "nothing visibly moved".
        window.scrollTo({ top: scrollY, left: 0, behavior: 'instant' });
        scrollLockSnapshot = null;
    }
}

/**
 * Create shared overlay behavior for Modal and Drawer Alpine components.
 *
 * @param {Object} options - Overlay configuration
 * @param {string} options.name - Unique overlay identifier
 * @param {boolean} options.dismissible - Whether ESC/backdrop close is allowed
 * @param {string} options.showEvent - Event name for showing (e.g. 'wirekit-modal-show')
 * @param {string} options.closeEvent - Event name for closing (e.g. 'wirekit-modal-close')
 * @param {boolean} [options.escapeAlwaysCloses=false] - When true, ESC always
 *   closes the overlay regardless of `dismissible`. Used by `alert-dialog`:
 *   non-dismissible alert-dialogs still need an escape path so keyboard users
 *   aren't trapped (backdrop click stays gated by `dismissible` for the
 *   "don't approve destructive action by stray click" safety case).
 * @returns {Object} Alpine component data object with overlay methods
 */
export function createOverlay({ name, dismissible, showEvent, closeEvent, escapeAlwaysCloses = false }) {
    // Stable token identifying this overlay instance on the global stack —
    // not a string id, just an object reference equality check works.
    const stackToken = {};

    return {
        // Expose `dismissible` as a public Alpine property so descendant
        // scopes (e.g. modal.header's auto-rendered close button) can gate
        // their visibility with `x-show="dismissible"`. Non-reactive — the
        // value is set at init and never changes during the overlay lifetime.
        dismissible,
        open: false,
        // Reactive `isTopmost` mirror of overlayStack[last] === stackToken.
        // Updated synchronously in show() / close() / _forceClose() /
        // _closeFromTrap() so x-bind:aria-modal and x-on:keydown.escape
        // gates re-evaluate when modal stacking changes. Without this
        // mirror Alpine has no way to react to a non-Alpine module-level
        // array mutation.
        isTopmost: false,
        _trap: null,
        _showHandler: null,
        _closeHandler: null,
        _navCleanup: null,

        /**
         * Initialize overlay event listeners and wire:model sync.
         * Called from the component's init() method.
         */
        initOverlay() {
            // Store named handler references so they can be removed on cleanup
            this._showHandler = (e) => {
                if (e.detail?.name === name) {
                    this.show();
                }
            };

            this._closeHandler = (e) => {
                if (e.detail?.name === name) {
                    this.close();
                }
            };

            window.addEventListener(showEvent, this._showHandler);
            window.addEventListener(closeEvent, this._closeHandler);

            // Listen for stack-changed broadcasts so this instance refreshes
            // its `isTopmost` flag whenever any other overlay opens or closes.
            // Required so a covered modal flips to non-topmost and stops
            // responding to ESC the moment a new one opens above it.
            this._stackHandler = () => {
                this.isTopmost = isTopmostOverlay(stackToken);
            };
            window.addEventListener('wirekit-overlay-stack-changed', this._stackHandler);

            // wire:model support — watch Livewire property if bound
            if (this.$wire) {
                const wireModelAttr = this.$el.getAttribute('wire:model')
                    || this.$el.getAttribute('wire:model.live');

                if (wireModelAttr) {
                    // Watch the Livewire property for changes
                    this.$watch('open', (value) => {
                        this.$wire.set(wireModelAttr, value);
                    });

                    // React to external Livewire property changes
                    const initialValue = this.$wire.get(wireModelAttr);
                    if (initialValue) {
                        this.$nextTick(() => this.show());
                    }
                }
            }

            // Cleanup on Livewire SPA navigation
            this._navCleanup = () => this._forceClose();
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });
        },

        /**
         * Alpine destroy() hook — cleanup event listeners and close overlay.
         * Called automatically when the component's DOM element is removed.
         */
        destroyOverlay() {
            if (this._navCleanup) {
                document.removeEventListener('livewire:navigating', this._navCleanup);
            }
            window.removeEventListener(showEvent, this._showHandler);
            window.removeEventListener(closeEvent, this._closeHandler);
            if (this._stackHandler) {
                window.removeEventListener('wirekit-overlay-stack-changed', this._stackHandler);
            }
            this._forceClose();
        },

        /**
         * Show the overlay — activate focus trap and scroll lock.
         * Resolves $refs.panel lazily to avoid stale element references.
         */
        show() {
            if (this.open) return;
            this.open = true;

            // Reference-counted scroll lock — safe with multiple overlays
            lockScroll();

            // Push onto the global active-overlay stack. This overlay is now
            // topmost; any previously-open overlay flips to non-topmost so its
            // ESC handler stops firing and its aria-modal flips to false.
            pushOverlay(stackToken);
            this.isTopmost = isTopmostOverlay(stackToken);
            broadcastStackChange();

            // Focus trap — activate after Alpine renders the panel
            this.$nextTick(() => {
                const panelEl = this.$refs.panel;
                if (panelEl) {
                    this._trap = createFocusTrap(panelEl, {
                        // ESC closes when EITHER the overlay is generally
                        // dismissible OR the caller opted into the
                        // ESC-always-closes contract (alert-dialog's
                        // escape hatch — see option doc above).
                        escapeDeactivates: dismissible || escapeAlwaysCloses,
                        // onDeactivate fires when ESC is pressed — close without
                        // calling deactivate() again (it's already deactivating)
                        onDeactivate: () => this._closeFromTrap(),
                        // Dismissible: allow outside clicks so backdrop click
                        // handlers (handleBackdropClick) can fire.
                        // Non-dismissible: block outside clicks entirely to
                        // prevent unintended page interaction behind the overlay.
                        // escapeAlwaysCloses does NOT widen this — backdrop
                        // click stays gated by the safety-strict `dismissible`.
                        allowOutsideClick: dismissible,
                    });
                    this._trap.activate();
                }
            });
        },

        /**
         * Close triggered by focus-trap deactivation (ESC key).
         * Skips deactivate() call since the trap is already deactivating.
         */
        _closeFromTrap() {
            if (!this.open) return;
            this.open = false;
            this._trap = null;
            unlockScroll();
            popOverlay(stackToken);
            this.isTopmost = isTopmostOverlay(stackToken);
            broadcastStackChange();
        },

        /**
         * Close the overlay — deactivate focus trap and restore scroll.
         */
        close() {
            if (!this.open) return;
            this.open = false;

            // Deactivate focus trap (returns focus to trigger automatically)
            if (this._trap) {
                this._trap.deactivate();
                this._trap = null;
            }

            unlockScroll();
            popOverlay(stackToken);
            this.isTopmost = isTopmostOverlay(stackToken);
            broadcastStackChange();
        },

        /**
         * Force close without transitions — used during SPA navigation cleanup.
         */
        _forceClose() {
            if (!this.open) return;
            this.open = false;

            if (this._trap) {
                this._trap.deactivate();
                this._trap = null;
            }

            unlockScroll();
            popOverlay(stackToken);
            this.isTopmost = isTopmostOverlay(stackToken);
            broadcastStackChange();
        },

        /**
         * Handle backdrop click — close only if dismissible.
         */
        handleBackdropClick() {
            if (dismissible) {
                this.close();
            }
        },
    };
}
