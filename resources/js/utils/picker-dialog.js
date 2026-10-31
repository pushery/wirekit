import { createFocusTrap } from './focus-trap.js';
import { position } from './floating.js';
import { holdPageInert, lockScroll, releasePageInert, unlockScroll } from './overlay.js';
import { anchorMoved, anchorSnapshot } from './scroll-anchor.js';
import { applyTriggerAria } from './trigger-aria.js';

/**
 * Where a picker's dialog opens as a modal: below the kit's `sm` width, and on a screen too short
 * for a panel below the trigger, as on a phone held sideways. A tablet keeps the panel either way.
 */
export const PHONE_MODAL_MEDIA = '(width < 40rem), (height < 30rem)';

/**
 * The dialog of a picker, spread into the factory of a field whose trigger opens a panel
 * teleported to the overlay root: the datetime picker, and the date and the time picker with
 * `popover`.
 *
 * It places the panel below the trigger (utils/floating.js), traps the focus in it from the
 * element the host names, and closes it with Escape, a press outside, a scroll that moves the
 * trigger, a resize of the viewport, or a Livewire navigation; a fixed panel placed once would be
 * stranded by the last three. The focus goes back to the trigger when it was in the panel, or when
 * a press in the panel left it on the body, as a button that takes no focus does in WebKit. A
 * trigger of the caller's, a button inside the `trigger` slot, gets the dialog's ARIA
 * (utils/trigger-aria.js).
 *
 * With `modalMedia`, a viewport that matches the query opens the panel as a modal instead:
 * `dialogModal` is set, nothing places the panel, the stylesheet centers it over a backdrop, and
 * the page behind it stops scrolling and becomes inert, through the counted holds `modal` shares
 * (utils/overlay.js). The query is read when the panel opens, never while it is open, and a modal
 * is not closed by a resize, which a phone fires when its keyboard comes up.
 *
 * The host's hooks, each optional: `_dialogOpening()` runs before the panel shows,
 * `_dialogOpened(panel)` once the trap is armed, `_dialogClosed()` after the panel closed, and
 * `_dialogAnchor()` names the element the panel stands below, the trigger when it names none.
 * The host calls pickerDialogInit() from its init() and pickerDialogDestroy() from its destroy().
 *
 * Lifecycle resources held on `this`, released in pickerDialogDestroy():
 *   - _trap — the focus trap of the open panel.
 *   - _onPanelPress, _pressedInPanel — the panel's press listener and whether the last press
 *     landed in the panel, released when the panel closes.
 *   - _stopPlacement — the observer that puts the placement back after a Livewire render.
 *   - _onScroll, _onResize — close the panel when the trigger moved or the viewport changed.
 *   - _navCleanup — closes the panel when Livewire navigates away, and when the page itself is
 *     left: the modal's scroll lock holds the body at a scroll position of 0, and a browser records
 *     the position for the history entry after `pagehide`, so Back would return to the top.
 *   - _stopTriggerAria — puts the dialog's ARIA back on a trigger of the caller's after a morph.
 *   - _scrollLocked, _inertHeld — the page's scroll lock and inert hold while the panel is open
 *     as a modal.
 *
 * @param {Object} options
 * @param {?string} [options.panelId]  the panel's id, for `aria-controls` on a caller's trigger
 * @param {string} options.openEvent  the event the root dispatches when the panel opens
 * @param {string} options.closeEvent  the event the root dispatches when it closes
 * @param {string} [options.missingTriggerWarning]  said in debug when the slot holds no button
 * @param {(panel: HTMLElement) => ?HTMLElement} [options.initialFocus]  where the focus starts
 * @param {?string} [options.modalMedia]  a media query; a viewport that matches it opens the panel as a modal
 */
export function pickerDialogState(options) {
    return {
        isOpen: false,
        // How many times the panel has opened. A calendar in the panel binds it to
        // `data-wk-opened` and shows its value again on each opening.
        dialogOpenings: 0,
        _trap: null,
        _stopPlacement: null,
        _onScroll: null,
        _onResize: null,
        _navCleanup: null,
        _stopTriggerAria: null,
        // Where the trigger stood when the panel opened (utils/scroll-anchor.js).
        _anchorAt: null,
        // Whether the panel is open as a modal over the page, decided when it opens.
        dialogModal: false,
        _scrollLocked: false,
        _inertHeld: false,

        /** The listeners on the page; without a document, as in a unit harness, there are none. */
        pickerDialogInit() {
            if (typeof window === 'undefined' || typeof document === 'undefined') {
                return;
            }

            // The trap ends here too, without handing the focus back: a page that comes back from
            // the back-forward cache keeps this component, and a trap left armed around a closed
            // panel would hold the focus there.
            this._navCleanup = () => {
                this._trap?.deactivate({ returnFocus: false });
                this._trap = null;
                this._closeNow();
            };
            document.addEventListener('livewire:navigating', this._navCleanup);
            // A plain link, a form or a script leaves the page without `livewire:navigating`.
            window.addEventListener('pagehide', this._navCleanup);

            this._onScroll = (event) => {
                if (! this.isOpen) {
                    return;
                }

                const panel = this.$refs?.panel;

                if (panel && event.target instanceof Node && panel.contains(event.target)) {
                    return;
                }

                // A modal is not anchored, and the page behind it does not scroll.
                if (! this.dialogModal && anchorMoved(this._anchorAt, this._anchor())) {
                    this.closePanel();
                }
            };
            window.addEventListener('scroll', this._onScroll, { passive: true, capture: true });

            this._onResize = () => {
                if (this.isOpen && ! this.dialogModal) {
                    this.closePanel();
                }
            };
            window.addEventListener('resize', this._onResize, { passive: true });
        },

        pickerDialogDestroy() {
            this._trap?.deactivate({ returnFocus: false });
            this._trap = null;
            this._releaseModal();
            this._stopPlacement?.();
            this._stopPlacement = null;

            if (this._onScroll && typeof window !== 'undefined') {
                window.removeEventListener('scroll', this._onScroll, { capture: true });
                this._onScroll = null;
            }

            if (this._onResize && typeof window !== 'undefined') {
                window.removeEventListener('resize', this._onResize);
                this._onResize = null;
            }

            if (this._navCleanup && typeof document !== 'undefined') {
                document.removeEventListener('livewire:navigating', this._navCleanup);
                window.removeEventListener('pagehide', this._navCleanup);
                this._navCleanup = null;
            }

            this._stopTriggerAria?.();
            this._stopTriggerAria = null;
        },

        /** A trigger of the caller's: the dialog's ARIA goes on the button inside the slot. */
        initTriggerAria() {
            this._stopTriggerAria?.();
            this._stopTriggerAria = applyTriggerAria(this.$el, this.$watch?.bind(this), {
                haspopup: 'dialog',
                controls: options.panelId ?? null,
                missingTriggerWarning: options.missingTriggerWarning,
            });
        },

        /** The element that opens the panel: the picker's own trigger, or the button in the slot. */
        _trigger() {
            return this.$refs?.trigger ?? this.$refs?.triggerSlot?.querySelector('button, [role=button], a') ?? null;
        },

        /** Whether the panel opens as a modal: `modalMedia` is set and the viewport matches it. */
        _opensAsModal() {
            if (! options.modalMedia || typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
                return false;
            }

            return window.matchMedia(options.modalMedia).matches === true;
        },

        /** The page scrolls and answers again once the modal is gone; only the holds this panel took are given back. */
        _releaseModal() {
            if (this._scrollLocked) {
                this._scrollLocked = false;
                unlockScroll();
            }

            if (this._inertHeld) {
                this._inertHeld = false;
                releasePageInert();
            }
        },

        /** The element the panel stands below: the one the host names, else the trigger. */
        _anchor() {
            return this._dialogAnchor?.() ?? this._trigger();
        },

        /** The keyboard of the trigger: Down Arrow opens the dialog, as Enter and Space do. */
        triggerKeydown(event) {
            if (event.key === 'ArrowDown' && ! this.isOpen) {
                event.preventDefault();
                this.openPanel();
            }
        },

        /** Open or close, from the trigger. */
        togglePanel() {
            if (this.isOpen) {
                this.closePanel();

                return;
            }

            this.openPanel();
        },

        async openPanel() {
            if (this.isOpen || this._trigger()?.disabled || this._trigger()?.getAttribute('aria-disabled') === 'true') {
                return;
            }

            this.dialogModal = this._opensAsModal();
            this._dialogOpening?.();
            this.dialogOpenings++;
            this.isOpen = true;

            if (this.dialogModal && ! this._scrollLocked && typeof document !== 'undefined') {
                lockScroll();
                this._scrollLocked = true;
                this._inertHeld = holdPageInert();
            }

            this._anchorAt = anchorSnapshot(this._anchor());
            this.$root?.dispatchEvent(new CustomEvent(options.openEvent, { bubbles: true }));

            await this.$nextTick?.();
            await this._place();

            // Closed while it was being placed: a trap armed now would hold a hidden panel.
            if (! this.isOpen || this._trap || ! this.$refs?.panel) {
                return;
            }

            const panel = this.$refs.panel;

            // Where the last press landed. A press on a button in the panel focuses it in Blink and
            // Gecko, and in WebKit leaves the focus on the body, so what closes the panel from inside
            // it reads the press as well as the focus.
            this._pressedInPanel = false;
            this._onPanelPress = () => {
                this._pressedInPanel = true;
            };
            panel.addEventListener?.('pointerdown', this._onPanelPress, true);

            this._trap = createFocusTrap(panel, {
                escapeDeactivates: true,
                onDeactivate: () => this._closeFromTrap(),
                // A press on the trigger, or anywhere outside, closes through its own handler:
                // a trap that swallowed it would leave a panel only Escape can dismiss.
                allowOutsideClick: true,
                initialFocus: () => options.initialFocus?.(panel) ?? panel,
                // The panel is teleported, so the trap would return the focus to <body>.
                setReturnFocus: () => this._trigger() ?? false,
            });
            this._trap.activate();
            this._dialogOpened?.(panel);
        },

        /**
         * The panel's press outside. The panel is teleported out of the root, so `click.outside`
         * counts the trigger as outside, and the click that opens the panel could close it in the
         * same click. A press on the trigger, or anywhere in a trigger slot of the caller's, is
         * left to the trigger's own click, which toggles the panel.
         */
        closePanelOutside(event) {
            const target = event?.target ?? null;
            const onTrigger = target !== null && [this._trigger(), this.$refs?.triggerSlot]
                .some((element) => element?.contains?.(target) === true);

            if (! onTrigger) {
                this._pressedInPanel = false;
                this.closePanel();
            }
        },

        /**
         * Close the panel, and hand the focus back to the trigger when it was in the panel, or when
         * a press in the panel left it on the body, as WebKit does on a button that takes no focus.
         */
        closePanel() {
            if (! this.isOpen) {
                return;
            }

            const panel = this.$refs?.panel;
            let hadFocus = false;

            if (panel) {
                const active = document.activeElement;
                const onBody = active === null || active === document.body;

                hadFocus = panel.contains(active) || (onBody && this._pressedInPanel === true);
            }

            if (this._trap) {
                this._trap.deactivate({ returnFocus: hadFocus });
                this._trap = null;
            } else if (hadFocus) {
                this._trigger()?.focus({ preventScroll: true });
            }

            this._closeNow();
        },

        /** Close from the trap, which Escape deactivates: the focus return is the trap's own. */
        _closeFromTrap() {
            this._trap = null;
            this._closeNow();
        },

        _closeNow() {
            if (! this.isOpen) {
                return;
            }

            this.isOpen = false;
            this.$refs?.panel?.removeEventListener?.('pointerdown', this._onPanelPress, true);
            this._onPanelPress = null;
            this._pressedInPanel = false;
            this._stopPlacement?.();
            this._stopPlacement = null;
            this._releaseModal();
            this._dialogClosed?.();
            this.$root?.dispatchEvent(new CustomEvent(options.closeEvent, { bubbles: true }));
        },

        /** Place the teleported panel below its anchor, and put the placement back after a render. */
        async _place() {
            const anchor = this._anchor();
            const panel = this.$refs?.panel;

            if (! anchor || ! panel) {
                return;
            }

            this._stopPlacement?.();
            this._stopPlacement = null;

            // A modal is centered by the stylesheet, so what an earlier placement wrote inline,
            // which would outweigh it, goes.
            if (this.dialogModal) {
                for (const property of ['left', 'top', 'max-height', 'width', 'min-width', 'max-width', 'z-index']) {
                    panel.style?.removeProperty?.(property);
                }

                return;
            }

            const { stop } = await position(anchor, panel, {
                placement: 'bottom-start',
                offset: 8,
                // Shifted along the trigger only. Below the trigger the other axis is the vertical
                // one, and a shift there lifts a dialog taller than the room beneath over its own
                // trigger. On a short viewport the dialog stays below, or turns above where the
                // room is larger, is capped to that room and scrolls inside (WCAG 1.4.10).
                fitViewport: true,
                // A Livewire update patches the teleported panel against its template, whose
                // `style` carries none of the placement: this puts it back.
                repairErasure: true,
            });

            // Closed while the placement waited for a box: nothing is left to watch.
            if (! this.isOpen) {
                stop?.();

                return;
            }

            this._stopPlacement?.();
            this._stopPlacement = stop ?? null;
        },
    };
}
