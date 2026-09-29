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
 * `keydown.escape` listener (the listener is window-level so Escape works
 * before the focus trap is armed; once armed, focus-trap listens on the
 * document as well). With two open modals, two
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
/*
 * Exported so a component with its own overlay machinery can still share THIS counter.
 *
 * The count is the whole point. Two overlays open at once, each with its own idea of the
 * body's overflow, is how a page ends up scrollable behind a modal or frozen after the last
 * one closed — `command-palette` carries a comment about exactly that failure, from when it
 * wrote the empty string unconditionally on close. A second, independent lock elsewhere in
 * the codebase would reintroduce it, so the helper is shared rather than copied.
 */
export function lockScroll() {
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
            // Whether the gutter below was published, and what the root carried inline before.
            publishedInset: false,
            rootInset: document.documentElement.style.getPropertyValue?.('--wk-scrollbar-inset') ?? '',
        };

        document.body.style.overflow = 'hidden';
        // iOS Safari fix: position:fixed pins the body where it is; without
        // top:-scrollY the page jumps to top:0 the moment we apply position:fixed.
        document.body.style.position = 'fixed';
        document.body.style.top = `-${scrollY}px`;
        document.body.style.width = '100%';
        if (scrollbarWidth > 0) {
            document.body.style.paddingRight = `${scrollbarWidth}px`;
            // A pinned surface is laid out against the viewport, and a classic scrollbar on the
            // document sits outside that box. With the scrollbar gone the box widens by the gutter,
            // and every surface pinned to the inline end would move toward the edge for as long as
            // the overlay is open. They fold `--wk-scrollbar-inset` into their offset, so the
            // gutter published here keeps them where they were.
            if (typeof document.documentElement.style.setProperty === 'function') {
                document.documentElement.style.setProperty('--wk-scrollbar-inset', `${scrollbarWidth}px`);
                scrollLockSnapshot.publishedInset = true;
            }
        }
    }
    scrollLockCount++;
}

export function unlockScroll() {
    scrollLockCount = Math.max(0, scrollLockCount - 1);
    if (scrollLockCount === 0 && scrollLockSnapshot) {
        const { scrollY, bodyOverflow, bodyPosition, bodyTop, bodyWidth, bodyPaddingRight, publishedInset, rootInset } = scrollLockSnapshot;
        // Restore the inline-style values we captured at lock time. Setting
        // them back to '' (empty string) where the developer had no inline
        // style preserves the cascade — class-based overrides keep working.
        document.body.style.overflow = bodyOverflow;
        document.body.style.position = bodyPosition;
        document.body.style.top = bodyTop;
        document.body.style.width = bodyWidth;
        document.body.style.paddingRight = bodyPaddingRight;
        // The root's own inline value comes back, or none: a gutter an application published for
        // an inner scroll container is its answer, and the lock only lent the token.
        if (publishedInset) {
            if (rootInset) {
                document.documentElement.style.setProperty('--wk-scrollbar-inset', rootInset);
            } else {
                document.documentElement.style.removeProperty('--wk-scrollbar-inset');
            }
        }
        // Restore scroll position before iOS-style position:fixed was applied.
        // window.scrollTo with `behavior: 'instant'` to avoid an animated
        // jump on close; the user's mental model is "nothing visibly moved".
        window.scrollTo({ top: scrollY, left: 0, behavior: 'instant' });
        scrollLockSnapshot = null;
    }
}

/**
 * The page behind a page-level modal overlay is inert.
 *
 * `aria-modal="true"` tells assistive technology that nothing outside the dialog exists, and the
 * focus trap keeps Tab inside it. Neither stops a pointer, a touch or a screen reader that does not
 * honor `aria-modal` from reaching the page behind; `inert` does. While a page-level modal overlay
 * is open, every element outside the overlay root and outside a toast region is inert. The overlay
 * root stays reachable because it holds the dialog's own backdrop and every panel the dialog opens
 * (a dropdown, a tooltip, a date picker). A toast region stays reachable because a reader has to
 * hear an announcement made while a dialog is open.
 *
 * Not focus-trap's own `isolateSubtrees`, which isolates the trapped panel alone: it would make the
 * dialog's backdrop inert, and with it every panel the dialog opens into the overlay root.
 *
 * Counted like the scroll lock, so the page comes back only when the last of them closes, and only
 * the attributes set here are taken off again: an element the page made inert on its own stays
 * inert. While the count is held, an element added to the page outside the kept regions is made
 * inert as well, so content a morph inserts behind the dialog does not become the way around it,
 * and an `inert` a framework's patch takes off an element is put back.
 */
let pageInertCount = 0;
let pageInertApplied = new Set();
let pageInertWatcher = null;

// Elements a holder asked to keep reachable besides the overlay root and toast regions: the
// parts of a dialog rendered in place, such as the app shell's drawer and its backdrop. Counted
// per element, because the same drawer can be held again before a release has run.
const pageInertKeptElements = new Map();

// What stays reachable behind a page-level modal overlay.
const PAGE_INERT_KEEP = '#wk-overlay-root, [data-wk-toast-region]';

// Elements that render nothing a reader can reach; an attribute on them would change nothing.
const PAGE_INERT_SKIP = new Set(['SCRIPT', 'STYLE', 'TEMPLATE', 'LINK', 'META', 'NOSCRIPT']);

/**
 * Whether an element renders in the overlay root, which is what makes an overlay page-level: an
 * overlay rendered in place, such as one in a preview, leaves the page around it alone.
 *
 * @param {Element|null|undefined} el
 * @returns {boolean}
 */
export function inOverlayRoot(el) {
    return Boolean(el && typeof el.closest === 'function' && el.closest('#wk-overlay-root'));
}

function inertOutsideKeptRegions() {
    const kept = [...document.querySelectorAll(PAGE_INERT_KEEP), ...pageInertKeptElements.keys()];

    // The ancestors of a kept element stay reachable, or the kept element would be inert with
    // them; their other children are what becomes inert.
    const path = new Set();

    for (const el of kept) {
        for (let node = el.parentElement; node; node = node.parentElement) {
            path.add(node);
        }
    }

    const visit = (parent) => {
        for (const child of Array.from(parent.children ?? [])) {
            if (kept.includes(child) || PAGE_INERT_SKIP.has(child.tagName)) {
                continue;
            }

            if (path.has(child)) {
                visit(child);
            } else if (! child.hasAttribute('inert')) {
                child.setAttribute('inert', '');
                pageInertApplied.add(child);
            }
        }
    };

    visit(document.body);
}

/**
 * Take one of the counted holds that keep the page behind a modal overlay inert. Returns whether a
 * hold was taken: none is without a document, so a caller releases only what it holds.
 *
 * A dialog rendered in place names the elements of its own that have to stay reachable, and gives
 * the same list back on release. They count only for the hold that makes the page inert: a panel
 * handed over while the page is inert already is not taken out of it again.
 *
 * @param {Element[]} [keep]
 * @returns {boolean}
 */
export function holdPageInert(keep = []) {
    // Guarded rather than assumed: a unit harness hands a factory a `document` with only the
    // fields its case needs, and a hold on a document that cannot be walked holds nothing.
    if (typeof document === 'undefined' || ! document.body || typeof document.querySelectorAll !== 'function') {
        return false;
    }

    for (const el of keep) {
        if (el) {
            pageInertKeptElements.set(el, (pageInertKeptElements.get(el) ?? 0) + 1);
        }
    }

    if (pageInertCount === 0) {
        inertOutsideKeptRegions();

        // No body, nothing to watch: observing null throws, and a throw here would end the
        // bundle's evaluation.
        if (document.body && typeof MutationObserver === 'function') {
            pageInertWatcher = new MutationObserver((records) => {
                let behind = false;

                for (const record of records) {
                    if (record.type === 'attributes') {
                        // A framework that patches an element against its own markup takes off
                        // an attribute the markup does not carry, which is what Livewire's morph
                        // does to a component root beside the dialog. Put it back.
                        if (pageInertApplied.has(record.target) && ! record.target.hasAttribute('inert')) {
                            record.target.setAttribute('inert', '');
                        }

                        continue;
                    }

                    // A change inside the overlay root or a toast region is the dialog or a
                    // toast at work, and a change inside an inert element is inert already.
                    // Anything else may have added an element behind the dialog.
                    if (record.addedNodes.length > 0
                        && ! (typeof record.target.closest === 'function'
                            && record.target.closest(`${PAGE_INERT_KEEP}, [inert]`))) {
                        behind = true;
                    }
                }

                if (behind) {
                    inertOutsideKeptRegions();
                }
            });
            pageInertWatcher.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['inert'],
            });
        }
    }

    pageInertCount++;

    return true;
}

/**
 * Give back one hold, with the elements it asked to keep. The last one takes off every `inert` the
 * holds set, and nothing else.
 *
 * @param {Element[]} [keep]
 */
export function releasePageInert(keep = []) {
    if (pageInertCount === 0) {
        return;
    }

    for (const el of keep) {
        const count = el ? pageInertKeptElements.get(el) : undefined;

        if (count === 1) {
            pageInertKeptElements.delete(el);
        } else if (count !== undefined) {
            pageInertKeptElements.set(el, count - 1);
        }
    }

    pageInertCount--;

    if (pageInertCount > 0) {
        return;
    }

    if (pageInertWatcher) {
        pageInertWatcher.disconnect();
        pageInertWatcher = null;
    }

    for (const el of pageInertApplied) {
        el.removeAttribute('inert');
    }

    pageInertApplied = new Set();
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
 * @param {string|null} [options.dismissedEvent=null] - Window event sent when the READER
 *   dismisses the overlay: a click on the backdrop, Escape, or the built-in close button. Its
 *   detail is `{ name, via }`. Never for a close the page asked for (the close event, a
 *   `wire:model` set to false, a composed close control) nor for the forced close of a
 *   navigation, so a page that closed its own overlay never hears about it a second time.
 * @param {boolean} [options.lockScroll=true] - Whether opening locks the page's scroll and, for an
 *   overlay rendered in the overlay root, makes the page behind it inert. False for an overlay that
 *   lives inside a page region, such as a preview, where the page around it has to keep scrolling
 *   and working. An overlay that took no lock releases none.
 * @returns {Object} Alpine component data object with overlay methods
 */
export function createOverlay({
    name,
    dismissible,
    showEvent,
    closeEvent,
    escapeAlwaysCloses = false,
    initialFocus = undefined,
    focusReturnTo = undefined,
    dismissedEvent = null,
    lockScroll: locksScroll = true,
}) {
    // Whether this instance holds one of the counted scroll locks right now. Every close path
    // releases through it, so an overlay built with `lockScroll: false`, which took no lock,
    // never releases one another overlay holds.
    let holdingScrollLock = false;

    const takeScrollLock = () => {
        if (locksScroll && ! holdingScrollLock) {
            lockScroll();
            holdingScrollLock = true;
        }
    };

    const releaseScrollLock = () => {
        if (holdingScrollLock) {
            holdingScrollLock = false;
            unlockScroll();
        }
    };

    // Whether this instance holds one of the counted holds that keep the page behind it inert.
    // Taken once the trap has focus inside the panel, because an `inert` ancestor takes focus off
    // the opener, and the trap has to record the opener first to return focus to it. Released
    // before the trap lets go, so the element focus returns to is reachable again.
    let holdingPageInert = false;

    const takePageInert = (panel) => {
        if (locksScroll && ! holdingPageInert && inOverlayRoot(panel)) {
            holdingPageInert = holdPageInert();
        }
    };

    const releasePageInertHold = () => {
        if (holdingPageInert) {
            holdingPageInert = false;
            releasePageInert();
        }
    };

    // Stable token identifying this overlay instance on the global stack —
    // not a string id, just an object reference equality check works.
    const stackToken = {};

    // The ancestor chain of whatever was focused when the overlay opened,
    // captured AT OPEN TIME because that is the last moment it is still
    // attached. A destructive confirmation usually removes the row that held
    // its own trigger, so by deactivation both the trigger and its parents are
    // detached — but something further up (the table, the list, the section)
    // almost always survives, and only a snapshot taken while the chain was
    // still whole can name it.
    let openerChain = [];

    /**
     * Whether the close in progress is a DISMISSAL: Cancel, Escape, or a click on the backdrop of a
     * dismissible overlay. Set by those paths just before they close, and cleared on the next open.
     *
     * A dismissal acted on nothing, so the reader belongs back on the control they opened it from,
     * and a named return target is no reason to move them. Every other close is read as the action
     * having gone through, which is when a trigger inside a row the action deletes is about to
     * disappear, and a named target is exactly what that close needs.
     */
    let dismissing = false;

    /**
     * Where focus should land when the trap deactivates.
     *
     * Order: an explicit target the developer named > the original opener, if it
     * is still in the document > the nearest surviving ancestor of the opener >
     * the document body (the browser's own answer, and the one this exists to
     * avoid).
     *
     * Except on a dismissal, where a surviving opener comes first: a keyboard user who backs out
     * of a delete belongs on the row they were on, not on the list heading. The order cannot
     * simply be flipped: a confirmation closes on the next task, before the re-render removes
     * its row, so at that moment the trigger still exists, would win, and would
     * vanish a moment later. What separates the two is how the dialog closed, not whether the
     * trigger exists.
     *
     * @param {HTMLElement|undefined} opener - element focused before the trap opened
     * @returns {HTMLElement}
     */
    const resolveReturnFocus = (opener) => {
        if (dismissing && opener && opener.isConnected) {
            return opener;
        }

        if (focusReturnTo) {
            const named = typeof focusReturnTo === 'function'
                ? focusReturnTo()
                : document.querySelector(focusReturnTo);

            if (named && named.isConnected) {
                return makeFocusable(named);
            }
        }

        if (opener && opener.isConnected) {
            return opener;
        }

        const survivor = openerChain.find((el) => el && el.isConnected);

        return survivor ? makeFocusable(survivor) : document.body;
    };

    /**
     * A container that survived the removal is rarely focusable on its own.
     * tabindex="-1" makes it programmatically focusable WITHOUT adding it to the
     * tab order, which is the standard way to park focus on a region — the same
     * thing an app would otherwise hand-roll after every destructive action.
     */
    const makeFocusable = (el) => {
        if (!el.hasAttribute('tabindex')) {
            el.setAttribute('tabindex', '-1');
        }

        return el;
    };

    return {
        // Expose `dismissible` as a public Alpine property so descendant
        // scopes (e.g. modal.header's auto-rendered close button) can gate
        // their visibility with `x-show="dismissible"`. Non-reactive — the
        // value is set at init and never changes during the overlay lifetime.
        dismissible,
        isOpen: false,
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
                    this.$watch('isOpen', (value) => {
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
            if (this.isOpen) return;
            this.isOpen = true;
            dismissing = false;

            // Snapshot the opener's ancestor chain while it is still attached —
            // see openerChain above. Taken before anything renders, so the DOM is
            // exactly the one the reader triggered from.
            openerChain = [];
            let node = document.activeElement;
            while (node && node !== document.body) {
                openerChain.push(node);
                node = node.parentElement;
            }
            openerChain.push(document.body);

            // Reference-counted scroll lock — safe with multiple overlays
            takeScrollLock();

            // Push onto the global active-overlay stack. This overlay is now
            // topmost; any previously-open overlay flips to non-topmost so its
            // ESC handler stops firing and its aria-modal flips to false.
            pushOverlay(stackToken);
            this.isTopmost = isTopmostOverlay(stackToken);
            broadcastStackChange();

            // Focus trap — activate after Alpine renders the panel
            this.$nextTick(() => {
                /*
                 * Re-check the state the tick was queued under. It was not checked at all.
                 *
                 * `show()` guards `if (this.isOpen) return` at the TOP, which prevents a
                 * second show while one is open — and says nothing about the frame in
                 * between. Anything can close the overlay inside that tick: Escape, a
                 * `wirekit-overlay-close` event, a Livewire morph, `wire:model` flipping
                 * false, another overlay opening above this one. The callback then armed a
                 * trap on a panel that is no longer shown, and nothing takes it off —
                 * `close()` had already run and found `_trap` empty, so the trap it never
                 * saw kept its document-level keydown listener and went on trapping Tab
                 * inside a hidden panel.
                 *
                 * `_trap` is checked for the same reason: two ticks can be queued (show,
                 * close, show again within a frame) and the second would overwrite the first
                 * handle, orphaning a live trap the same way.
                 */
                if (!this.isOpen || this._trap) {
                    return;
                }

                /*
                 * And the tick is no promise that the panel is shown.
                 *
                 * It relies on `x-transition` holding the next ticks until the second frame,
                 * and that hold is GLOBAL: another component's `$nextTick` schedules a
                 * `setTimeout(releaseNextTicks)` first, and when that timer fires it empties
                 * the whole stack — this callback included — while the panel is still
                 * `display: none`. focus-trap then finds no tabbable node, falls back to the
                 * panel, cannot focus a hidden element, and never tries again: it listens for
                 * `focusin` outside and for Tab, and focus never left the opener. The reader
                 * stays on the trigger for good, which is WCAG 2.4.3. It is a race between a
                 * timer and a frame, so it is lost only some of the time.
                 *
                 * So the arming waits for the panel to have a box and re-checks both conditions
                 * above after every wait — the overlay can close inside these frames exactly as
                 * it can inside the tick. The wait is bounded: a panel that never reports a box
                 * is armed anyway, because a late trap beats no trap at all.
                 */
                const armWhenPanelIsShown = (attempt = 0) => {
                    if (!this.isOpen || this._trap) {
                        return;
                    }

                    const panelEl = this.$refs.panel;

                    if (!panelEl) {
                        return;
                    }

                    if (attempt < 10
                        && typeof requestAnimationFrame === 'function'
                        && typeof panelEl.getClientRects === 'function'
                        && panelEl.getClientRects().length === 0) {
                        requestAnimationFrame(() => armWhenPanelIsShown(attempt + 1));

                        return;
                    }

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
                        // Where focus starts. Passed straight through when the
                        // overlay named one (alert-dialog points at Cancel);
                        // undefined leaves focus-trap's own default in place.
                        initialFocus: typeof initialFocus === 'function'
                            ? () => initialFocus(panelEl)
                            : initialFocus,
                        // …and where it lands again, which the browser gets wrong
                        // whenever the opener has been removed meanwhile.
                        setReturnFocus: resolveReturnFocus,
                    });
                    this._trap.activate();
                    takePageInert(panelEl);
                };

                armWhenPanelIsShown();
            });
        },

        /**
         * Close triggered by focus-trap deactivation (ESC key).
         * Skips deactivate() call since the trap is already deactivating.
         */
        _closeFromTrap() {
            if (!this.isOpen) return;
            // Only Escape reaches this while the overlay is still open: every other close sets
            // `isOpen` false before it deactivates the trap. So this close is a dismissal.
            dismissing = true;
            this.isOpen = false;
            this._trap = null;
            releasePageInertHold();
            releaseScrollLock();
            popOverlay(stackToken);
            this.isTopmost = isTopmostOverlay(stackToken);
            broadcastStackChange();
            this._announceDismissal('escape');
        },

        /**
         * Close the overlay — deactivate focus trap and restore scroll.
         */
        close() {
            if (!this.isOpen) return;
            this.isOpen = false;

            // Before the trap lets go: the element it returns focus to is on the page behind.
            releasePageInertHold();

            // Deactivate focus trap (returns focus to trigger automatically)
            if (this._trap) {
                this._trap.deactivate();
                this._trap = null;
            }

            releaseScrollLock();
            popOverlay(stackToken);
            this.isTopmost = isTopmostOverlay(stackToken);
            broadcastStackChange();
        },

        /**
         * Force close without transitions — used during SPA navigation cleanup.
         */
        _forceClose() {
            if (!this.isOpen) return;
            this.isOpen = false;
            releasePageInertHold();

            if (this._trap) {
                this._trap.deactivate();
                this._trap = null;
            }

            releaseScrollLock();
            popOverlay(stackToken);
            this.isTopmost = isTopmostOverlay(stackToken);
            broadcastStackChange();
        },

        /**
         * Handle backdrop click — close only if dismissible. A dismissal, like Escape.
         */
        handleBackdropClick() {
            if (dismissible) {
                this.dismissByReader('backdrop');
            }
        },

        /**
         * A close the READER made, with the overlay's own controls: Escape, the built-in close
         * button, a click beside the panel. It is a dismissal (see `dismissOverlay`), and it is
         * announced, because the page did not ask for it and may have state to clean up: a typed
         * reason, a PIN, a search. Only when it actually closed something, so a second path
         * reaching an overlay already closed announces nothing.
         *
         * @param {'escape'|'close-button'|'backdrop'} via
         */
        dismissByReader(via = 'close-button') {
            if (!this.isOpen) return;
            this.dismissOverlay();
            this._announceDismissal(via);
        },

        _announceDismissal(via) {
            if (!dismissedEvent) return;
            window.dispatchEvent(new CustomEvent(dismissedEvent, { detail: { name, via } }));
        },

        /**
         * Close without the action having happened: what a Cancel control calls. Focus goes back
         * to the control the overlay was opened from while that still exists, ahead of a named
         * return target, because nothing was acted on (see `resolveReturnFocus`).
         */
        dismissOverlay() {
            if (!this.isOpen) return;
            dismissing = true;
            this.close();
        },
    };
}
