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

/**
 * @param {Object} config - Popover configuration from Blade
 * @param {string} config.placement - Floating UI placement (default: 'bottom')
 * @param {number} config.offset - Distance from trigger in px (default: 8)
 */
export default function wirekitPopover(config = {}) {
    return {
        /** Move the popup ARIA onto the trigger's focusable child. */
        initTriggerAria() {
            applyTriggerAria(this.$el, this.$watch.bind(this), { missingTriggerWarning: '[wirekit] popover: trigger slot has no focusable element (button/link). Keyboard users cannot open the popover. Wrap the trigger content in a <button>.' });
        },

        open: false,
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

        init() {
            // Cleanup on Livewire SPA navigation
            this._navCleanup = () => this._forceClose();
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });

            // Opening this one closes every other popover on the page.
            this._coordination = coordinateOverlay({
                channel: 'wirekit:popover-open',
                onOther: () => this._forceClose(),
            });
        },

        destroy() {
            this._coordination?.stop();
            this._coordination = null;

            if (this._navCleanup) {
                document.removeEventListener('livewire:navigating', this._navCleanup);
            }
            this._forceClose();
        },

        /**
         * Toggle popover open/close.
         */
        toggle() {
            this.open ? this.close() : this.show();
        },

        /**
         * Show popover, position via Floating UI, activate focus trap.
         */
        async show() {
            if (this.open) return;
            this.open = true;
            this._coordination?.announce();

            await this.$nextTick();

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
                });
                this._stopAutoUpdate = stop;

                // Activate focus trap — ESC deactivates and closes
                this._trap = createFocusTrap(panel, {
                    escapeDeactivates: true,
                    onDeactivate: () => this._closeFromTrap(),
                    // Allow clicking the trigger — or anything else — to close without trap
                    // interference, and RELEASE THE TRAP ON THE PRESS rather than on the click.
                    //
                    // The timing is the whole point. Returning a bare `true` lets the click
                    // through but leaves the trap armed, and the trap's `focusin` handler then
                    // pulls focus straight back into the panel before the click that closes
                    // this even fires. Measured in Chromium on one outside click, in order:
                    //
                    //   mousedown  target=outside control   active=<the panel's own input>
                    //   focusout   target=<panel input>     active=BODY
                    //   focusin    target=<panel input>     active=<panel input>   <- pulled back
                    //   click      target=outside control   active=<panel input>   <- close() here
                    //
                    // So by the time close() looks, the DOM says focus is inside the panel —
                    // identical to the Escape case, which wants the opposite answer. Reading
                    // `activeElement` cannot separate the two, and hiding the panel from that
                    // state drops focus on `<body>` (WCAG 2.4.3).
                    //
                    // focus-trap resolves this hook on `mousedown` in the capture phase, so
                    // letting go here happens BEFORE the focusin steal — and `returnFocus:
                    // false` is the library's own documented way to leave the outside click to
                    // put focus where it naturally would. The reader ends up on the control
                    // they pressed, which is the entire contract.
                    allowOutsideClick: () => {
                        this._trap?.deactivate({ returnFocus: false });

                        return true;
                    },
                    // WHERE FOCUS GOES WHEN THE TRAP LETS GO, named explicitly.
                    //
                    // The trap returns focus to whatever held it at activation, and that is
                    // not reliable here: the panel is teleported out of the wrapper, so the
                    // element it remembers is in a different subtree by the time it looks.
                    // Measured — Escape left focus on <body>, which drops a keyboard reader
                    // back to the top of the page (WCAG 2.4.3).
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
         * Close triggered by the trap deactivating, which now happens two ways: Escape (the
         * library's own `escapeDeactivates`) and a press outside the panel (the
         * `allowOutsideClick` hook lets go there so focus can land where the reader pressed).
         *
         * Deliberately does NOT call deactivate() again — this runs from inside it.
         */
        _closeFromTrap() {
            if (!this.open) return;
            this.open = false;
            this._stopAutoUpdate?.();
            this._stopAutoUpdate = null;
            this._trap = null;
        },

        /**
         * Close popover, deactivate the focus trap, and hand focus back.
         */
        close() {
            if (!this.open) return;

            // WAS THE READER INSIDE THE PANEL? The answer has to be taken BEFORE anything
            // hides, and it decides whether focus is ours to move.
            //
            // Escape and activating something inside close with focus in the panel, and
            // there the popover owes focus back to its trigger (WCAG 2.4.3) — otherwise it
            // lands on <body> and a keyboard reader resumes from the top of the page.
            // Measured: that is exactly where it went. A click on some other control also
            // closes this, and there focus belongs where the reader just put it; stealing
            // it back would be worse than doing nothing.
            //
            // THE OUTSIDE-CLICK PATH DOES NOT REACH HERE ANY MORE: the trap releases itself on
            // the press (see `allowOutsideClick` in show()), which closes this through
            // `_closeFromTrap()`, so the `click.outside` binding then finds `open` already
            // false and returns above. What is left for this method are the closes that happen
            // with focus somewhere settled — from inside the panel, or programmatically — and
            // for those `activeElement` is a truthful answer.
            const panel = this.$refs.panel;
            const hadFocus = Boolean(panel && panel.contains(document.activeElement));

            this.open = false;
            this._stopAutoUpdate?.();
            this._stopAutoUpdate = null;

            if (this._trap) {
                // THE DECISION IS HANDED OVER, not merely made. `utils/focus-trap.js` sets
                // `returnFocusOnDeactivate: true` for every trap in this package, so a bare
                // `deactivate()` answers this question by itself — and answers it the same
                // way every time, which is the way that is wrong half the time. The guard
                // below it could then only suppress our own second `focus()` call, never the
                // trap's, and the reader clicking a filter or a text field still got yanked
                // back onto a trigger they had left. Same shape as `color-picker.js`, which
                // is the sibling that already passes it through.
                this._trap.deactivate({ returnFocus: hadFocus });
                this._trap = null;
            } else if (hadFocus) {
                // THE TRAP-LESS PATH, and it is a real one rather than defensive padding:
                // `show()` builds a trap only when a trigger AND a panel both resolve, so a
                // trigger slot holding nothing focusable never gets one. Nothing else would
                // return focus there, and the panel is about to hide with focus inside it.
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
            if (!this.open) return;
            this.open = false;
            this._stopAutoUpdate?.();
            this._stopAutoUpdate = null;

            if (this._trap) {
                this._trap.deactivate();
                this._trap = null;
            }
        },
    };
}
