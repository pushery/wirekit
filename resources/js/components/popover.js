import { coordinateOverlay } from '../utils/overlay-coordination.js';
import { applyTriggerAria } from '../utils/trigger-aria.js';

/**
 * WireKit Popover Alpine Component.
 *
 * Click-triggered floating panel with focus trap. Unlike Tooltip (hover)
 * and HoverCard (hover + rich content), Popover opens on click and traps
 * focus inside the panel. Uses Floating UI for positioning.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/
 */
import { position } from '../utils/floating.js';
import { createFocusTrap } from '../utils/focus-trap.js';
import { closeOnOutsideRelease } from '../utils/outside-release.js';
import { withOpenAlias } from '../utils/open-alias.js';

/**
 * @param {Object} config - Popover configuration from Blade
 * @param {string} config.placement - Floating UI placement (default: 'bottom')
 * @param {number} config.offset - Distance from trigger in px (default: 8)
 */
export default function wirekitPopover(config = {}) {
    return withOpenAlias({
        /** Move the popup ARIA onto the trigger's focusable child. */
        initTriggerAria() {
            applyTriggerAria(this.$el, this.$watch.bind(this), { missingTriggerWarning: '[wirekit] popover: trigger slot has no focusable element (button/link). Keyboard users cannot open the popover. Wrap the trigger content in a <button>.' });
        },

        isOpen: false,
        _placement: config.placement || 'bottom',
        _offset: config.offset ?? 8,
        _trap: null,
        _navCleanup: null,
        // Cross-close channel — see utils/overlay-coordination.js. Two open
        // sibling popovers overlap, which a reader sees and no test does.
        _coordination: null,
        // Floating UI autoUpdate teardown handle — set in show(), called in EVERY
        // close path (close / _closeFromTrap / _forceClose) so the scroll+resize
        // listeners never outlive the panel (every teardown path must call stop()).
        _stopAutoUpdate: null,
        // Removes the outside-release listeners (utils/outside-release.js).
        _stopOutsideRelease: null,

        init() {
            // Cleanup on Livewire SPA navigation
            this._navCleanup = () => this._forceClose();
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });

            // Opening this one closes every other popover on the page.
            this._coordination = coordinateOverlay({
                channel: 'wirekit:popover-open',
                onOther: () => this._forceClose(),
            });

            // A press outside closes the popover on its release, not on the press (WCAG 2.5.2),
            // and only when it ends outside too. The trigger counts as inside: its own click
            // toggles. The panel is teleported out of the root, so it is named separately.
            //
            // On the press the trap has let the focus go where the reader pressed (see
            // `allowOutsideClick` in show()). A release outside closes with the focus left
            // there; a press that ends inside the panel, or that the browser cancels, takes
            // the trap back, and focus returns into the panel.
            this._stopOutsideRelease = closeOnOutsideRelease(document, {
                contains: (node) => this.$root.contains(node) || Boolean(this.$refs.panel?.contains(node)),
                isOpen: () => this.isOpen,
                close: () => this.close(),
                keep: () => this._trap?.unpause(),
            });
        },

        destroy() {
            this._coordination?.stop();
            this._coordination = null;

            if (this._navCleanup) {
                document.removeEventListener('livewire:navigating', this._navCleanup);
            }
            this._stopOutsideRelease?.();
            this._stopOutsideRelease = null;
            this._forceClose();
        },

        /**
         * Toggle popover open/close. Bound to the trigger, so a close from here is a close
         * from the trigger, and focus goes back to it.
         */
        toggle() {
            this.isOpen ? this.close({ fromTrigger: true }) : this.show();
        },

        /**
         * Show popover, position via Floating UI, activate focus trap.
         */
        async show() {
            if (this.isOpen) return;
            this.isOpen = true;
            this._coordination?.announce();

            await this.$nextTick();

            // Closed inside the tick. `close()` found no observer and no trap then, so
            // anything taken from here on would be left on a hidden panel.
            if (!this.isOpen) return;

            const trigger = this.$refs.trigger;
            const panel = this.$refs.panel;

            if (trigger && panel) {
                this._stopAutoUpdate?.();
                const { stop } = await position(trigger, panel, {
                    placement: this._placement,
                    offset: this._offset,
                    // Keep the panel inside the viewport on narrow screens for
                    // left/right placements — Floating UI's default main-axis
                    // shift can't pull a right-placed panel back from the right
                    // edge (main axis is vertical for left/right placements).
                    crossAxisShift: true,
                    // Follow the trigger on scroll/resize; torn down in every close path.
                    autoReposition: true,
                    // A framework update patches the teleported panel against its template, whose
                    // `style` carries none of what this call writes: the placement is gone while the
                    // panel stays open, with its box unchanged, so `autoReposition`, which watches
                    // boxes, sees nothing. This watches the attribute that is actually removed.
                    repairErasure: true,
                });

                // The placement can wait frames for the panel to get a box, and the popover
                // can close meanwhile: its observer would then follow a hidden panel.
                if (!this.isOpen) {
                    stop();
                    return;
                }

                // One observer at a time: a showing that began after this one may have
                // stored its own first.
                this._stopAutoUpdate?.();
                this._stopAutoUpdate = stop;

                // A showing that began after this one may have armed the panel already.
                if (this._trap) return;

                // Activate focus trap — ESC deactivates and closes
                this._trap = createFocusTrap(panel, {
                    escapeDeactivates: true,
                    onDeactivate: () => this._closeFromTrap(),
                    // A press outside the panel pauses the trap on the press. focus-trap
                    // resolves this hook on `mousedown` and `touchstart` in the capture phase,
                    // ahead of its own `focusin` handler. A trap still listening at that point
                    // pulls focus back into the panel, and the close on the release would then
                    // find focus inside the panel, the state Escape leaves, and hand it to the
                    // trigger. Paused, the trap lets the focus land on the control the reader
                    // pressed, and the panel stays open until the press ends (see init()).
                    //
                    // The trap asks the same question on `click`; paused, it no longer listens
                    // by then, and a click is no press, so it passes.
                    //
                    // The trigger is the exception. Its click runs `toggle()`, which closes the
                    // popover and returns focus to the trigger, so the trap stays armed through
                    // that press.
                    allowOutsideClick: (event) => {
                        if (this.$refs.trigger?.contains(event?.target)) {
                            return true;
                        }

                        if (event?.type === 'mousedown' || event?.type === 'touchstart') {
                            this._trap?.pause();
                        }

                        return true;
                    },
                    // Where focus goes when the trap lets go, named explicitly.
                    //
                    // The trap returns focus to whatever held it at activation, and that is
                    // not reliable here: the panel is teleported out of the wrapper, so the
                    // element it remembers is in a different subtree by the time it looks.
                    // Escape would leave focus on <body>, which drops a keyboard reader back
                    // to the top of the page (WCAG 2.4.3).
                    //
                    // The interactive descendant, not the wrapper: the wrapper is a plain
                    // div, and focusing it would be a stop that announces nothing.
                    setReturnFocus: () => this.$refs.trigger?.querySelector(
                        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                    ) ?? false,
                });
                this._trap.activate();
            }
        },

        /**
         * Close triggered by the trap deactivating, which is Escape (the library's own
         * `escapeDeactivates`). A press outside pauses the trap instead, and its release closes
         * through close() (see init()).
         *
         * Deliberately does NOT call deactivate() again — this runs from inside it.
         */
        _closeFromTrap() {
            if (!this.isOpen) return;
            this.isOpen = false;
            this._stopAutoUpdate?.();
            this._stopAutoUpdate = null;
            this._trap = null;
        },

        /**
         * Close popover, deactivate the focus trap, and hand focus back.
         *
         * @param {Object} [options]
         * @param {boolean} [options.fromTrigger] - Set by `toggle()`, the trigger's own close
         */
        close(options = {}) {
            if (!this.isOpen) return;

            // Whether focus is this popover's to move is decided before anything hides.
            //
            // A close with focus in the panel (Escape, a control inside that closes it)
            // returns focus to the trigger (WCAG 2.4.3); otherwise it would land on <body>
            // and a keyboard reader would resume from the top of the page. The trigger's
            // own toggle returns focus to the trigger too, and says so through
            // `fromTrigger`, because `activeElement` cannot answer for it: an engine that
            // does not focus a button on click leaves <body> focused by then.
            //
            // A press on any other control pauses the trap on the press (see
            // `allowOutsideClick` in show()), so by its release, which closes through here,
            // focus is on the control the reader pressed and stays there. Every other close
            // moves focus only when it was inside the panel.
            const panel = this.$refs.panel;
            const returnFocus = options.fromTrigger === true
                || Boolean(panel && panel.contains(document.activeElement));

            this.isOpen = false;
            this._stopAutoUpdate?.();
            this._stopAutoUpdate = null;

            if (this._trap) {
                // The decision is passed to the trap. `utils/focus-trap.js` sets
                // `returnFocusOnDeactivate: true` for every trap in this package, so a bare
                // `deactivate()` would return focus on every close, including one where the
                // reader has moved on to another control. `color-picker.js` passes it the
                // same way.
                this._trap.deactivate({ returnFocus });
                this._trap = null;
            } else if (returnFocus) {
                // Without a trap nothing else returns focus. `show()` builds the trap only
                // after it has positioned the panel, and only when both refs resolve, so a
                // close in between runs without one.
                //
                // The interactive descendant, not the wrapper: the wrapper is a plain div and
                // focusing it would be a stop that announces nothing.
                const trigger = this.$refs.trigger?.querySelector(
                    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                );

                trigger?.focus({ preventScroll: true });
            }
        },

        /**
         * Force close without transitions — SPA navigation cleanup.
         */
        _forceClose() {
            if (!this.isOpen) return;
            this.isOpen = false;
            this._stopAutoUpdate?.();
            this._stopAutoUpdate = null;

            if (this._trap) {
                this._trap.deactivate();
                this._trap = null;
            }
        },
    });
}
