/**
 * WireKit Tooltip Alpine Component.
 *
 * Supports hover (desktop), focus (keyboard), and long-press (touch).
 * Positioning via Floating UI with auto flip/shift.
 */
import { position } from '../utils/floating.js';

/**
 * @param {Object} config - Tooltip configuration from Blade
 * @param {string} config.placement - Floating UI placement
 * @param {number} config.offset - Distance between trigger and tooltip in px
 * @param {number} config.delayShow - Delay before showing on hover (ms)
 * @param {number} config.delayHide - Delay before hiding on mouseleave (ms)
 */
export default function wirekitTooltip(config = {}) {
    return {
        open: false,
        // `||` is CORRECT here — placement is a string enum and '' is not a valid
        // placement, so there is no falsy-but-legitimate value to preserve.
        _placement: config.placement || 'top',
        // `??` (not `||`) for the numeric props: 0 is a LEGITIMATE value (`offset="0"`
        // = flush tooltip, `delay-show="0"` = instant), and `0 || default` would
        // silently discard exactly that value and revert to the default. Only
        // undefined/null should fall back. Do NOT "consistency-fix" these to `||`.
        _offset: config.offset ?? 6,
        _delayShow: config.delayShow ?? 300,
        _delayHide: config.delayHide ?? 100,
        _showTimer: null,
        _hideTimer: null,
        _longPressTimer: null,
        _autoDismissTimer: null,

        // The positioner's teardown handle, held only while the tooltip is open. One
        // attribute-filtered MutationObserver per showing, disconnected on every hide — see the
        // note beside `repairErasure` below for why a tooltip of all things needs one.
        _stopRepair: null,

        // Stored cleanup handler for destroy()
        _navCleanup: null,

        init() {
            this._moveDescriptionToTheFocusableTrigger();

            // Cleanup on SPA navigation
            this._navCleanup = () => this._forceClose();
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });
        },

        /**
         * Put `aria-describedby` on the element a reader actually lands on.
         *
         * The template can only annotate its own wrapper `<div>`, because the trigger is the
         * CALLER's markup — a `<button>`, a link, whatever they passed. A wrapper is not
         * focusable and is not announced, so without `focusable-trigger` the description was
         * attached to nothing a reader ever reaches: the tooltip existed and was announced to
         * nobody.
         *
         * Moved rather than duplicated. Two elements describing themselves with the same id
         * makes a nested pair announce the text twice, and the wrapper's copy is the one with
         * no reader on it.
         */
        _moveDescriptionToTheFocusableTrigger() {
            const root = this.$refs?.trigger;
            if (!root || typeof root.querySelector !== 'function') {
                return;
            }

            const id = root.getAttribute('data-wk-tooltip-describedby');
            if (!id) {
                return;
            }

            // The wrapper itself takes the tab stop under `focusable-trigger`; in that case
            // it IS the element a reader lands on and the attribute is already right.
            if (root.hasAttribute('tabindex')) {
                return;
            }

            const focusable = root.querySelector(
                'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            );

            if (!focusable) {
                return;
            }

            // A caller who described their own control keeps their description — replacing it
            // would silence something they chose deliberately.
            if (!focusable.hasAttribute('aria-describedby')) {
                focusable.setAttribute('aria-describedby', id);
            }

            root.removeAttribute('aria-describedby');
        },

        destroy() {
            if (this._navCleanup) {
                document.removeEventListener('livewire:navigating', this._navCleanup);
            }
            this._forceClose();
        },

        /**
         * Desktop mouse hover — show with delay.
         */
        mouseenter() {
            clearTimeout(this._hideTimer);

            // Guarded on `open`, because this handler is bound to the PANEL as well as
            // to the trigger — that pairing is what makes the tip hoverable, and WCAG
            // 1.4.13 asks for it. Moving the pointer onto an already-open panel has to
            // cancel the pending hide (the line above) and nothing else; arming a
            // second show timer there would only fire into a `show()` that returns at
            // its first line. Same shape as the hover card, for the same reason.
            if (! this.open) {
                this._showTimer = setTimeout(() => this.show(), this._delayShow);
            }
        },

        /**
         * Desktop mouse leave — hide with delay.
         *
         * Bound to the trigger AND to the panel. The delay is what bridges the `offset`
         * gap between the two: leaving the trigger towards the panel arms this timer,
         * and the panel's own `mouseenter` clears it on the way in. Setting
         * `delay-hide="0"` therefore switches the hoverable behavior off — the panel
         * closes before the pointer can reach it.
         */
        mouseleave() {
            clearTimeout(this._showTimer);
            this._hideTimer = setTimeout(() => this.close(), this._delayHide);
        },

        /**
         * Keyboard focus — show immediately.
         */
        focusin() {
            clearTimeout(this._hideTimer);
            this.show();
        },

        /**
         * Keyboard blur — hide immediately.
         */
        focusout() {
            clearTimeout(this._showTimer);
            this.close();
        },

        /**
         * Touch long-press start — begin 500ms timer.
         * Only triggers on touch devices (pointerType === 'touch').
         */
        pointerdown(e) {
            if (e.pointerType !== 'touch') return;
            this._longPressTimer = setTimeout(() => {
                this.show();
            }, 500);
        },

        /**
         * Touch long-press end — clear timer, auto-dismiss after 1.5s.
         */
        pointerup(e) {
            if (e.pointerType !== 'touch') return;
            clearTimeout(this._longPressTimer);

            if (this.open) {
                // Auto-dismiss tooltip after 1.5 seconds on touch
                this._autoDismissTimer = setTimeout(() => this.close(), 1500);
            }
        },

        /**
         * Touch pointer leaves — cancel long-press timer.
         */
        pointerleave(e) {
            if (e.pointerType !== 'touch') return;
            clearTimeout(this._longPressTimer);
        },

        /**
         * ESC key — immediately hide tooltip and clear all pending timers.
         *
         * Reached from a listener on the WINDOW, not on the component, because a
         * tooltip is normally opened by a pointer and a pointer moves no focus: a
         * key bound inside the component never saw the keystroke that was meant for
         * it. WCAG 1.4.13 names Escape as the way hover-shown content is dismissed
         * without moving the pointer or the focus.
         *
         * Clearing the timers is not housekeeping here — it is the point. A pending
         * `delayShow` that survives Escape puts the panel on screen a moment AFTER
         * the key that was meant to answer it.
         */
        keydownEscape() {
            this._clearAllTimers();
            this.close();
        },

        /**
         * Show tooltip and position via Floating UI.
         */
        /**
         * Is this tooltip switched off right now?
         *
         * Read off the ROOT ATTRIBUTE rather than held as state, and that is the
         * whole design. A boolean passed into the factory would only ever answer
         * for the render that created it — but the case this exists for is a
         * tooltip that must go quiet when something else on the page changes,
         * a collapsing sidebar being the concrete case. Reading the attribute
         * at trigger time means `x-bind:data-wk-tooltip-disabled` from the
         * call site works reactively with no further API at all.
         *
         * `pointer-events-none` on the root is NOT a way to do this, however
         * much it looks like one: `mouseenter` is delivered to every ancestor of
         * the element actually hit, whatever their own pointer-events value, so
         * the handler fires and the tooltip appears over a control that is
         * supposed to be inert.
         */
        _disabled() {
            return this.$root?.getAttribute('data-wk-tooltip-disabled') === 'true';
        },

        async show() {
            if (this.open) return;

            // Checked HERE as well as in the handlers, because show() is public:
            // it is reachable from a call site that dispatches it directly, and a
            // switch that only guards the doors it knows about is not a switch.
            if (this._disabled()) return;

            // Color the panel BEFORE it is shown, not after.
            //
            // `open = true` flips x-show, which sets display immediately; the
            // copy used to run after the $nextTick that follows. Between those
            // two moments the panel is displayed and still carries the default
            // color — measured, reproducibly, by polling a shown panel and
            // reading the default value off it. Whether a browser paints inside
            // that window was never established, and it does not need to be:
            // an observable window makes the contract untestable without a
            // race, which is how a healthy component held a downstream check
            // red for weeks.
            //
            // The panel is teleported by `<template x-teleport>` at init and
            // only display-toggled afterwards, so the ref is already there —
            // but it is guarded rather than assumed, and the copy after the
            // tick stays as the fallback for a first show that resolves late.
            // Running it twice costs nothing: it reads computed style and
            // writes the same values.
            if (this.$refs.tooltip) {
                this._inheritThemeVars(this.$refs.tooltip);
            }

            this.open = true;

            await this.$nextTick();

            const trigger = this.$refs.trigger;
            const tooltip = this.$refs.tooltip;

            if (trigger && tooltip) {
                this._inheritThemeVars(tooltip);

                // Drop the previous showing's observer before making another one. A tooltip is
                // shown and hidden constantly, so one left behind per hover adds up fastest here
                // of anywhere in the catalog.
                this._stopRepair?.();
                this._stopRepair = null;

                const placement = await position(trigger, tooltip, {
                    placement: this._placement,
                    offset: this._offset,

                    // ⚠️ A TOOLTIP LOOKS LIKE THE ONE OVERLAY THAT CANNOT NEED THIS, AND THE
                    // MEASUREMENT SAYS OTHERWISE — which is why the reasoning is written out
                    // rather than assumed.
                    //
                    // Everything this call writes is inline style, and a framework update patches
                    // the panel against its own template, whose `style` attribute carries none of
                    // it. The placement is then gone while the tooltip is still open, and since
                    // this panel is teleported to the overlay root, with no `top` it sits at the
                    // END of the document.
                    //
                    // The obvious objection is that a tooltip lasts a moment, so an update can
                    // hardly catch one. That holds for the pointer, and not at all for the
                    // keyboard: a tooltip opened by FOCUS stands for as long as the focus does,
                    // which on a form is minutes. Measured with a focused trigger and one
                    // refresh: still visible, focus still on the trigger, `top` gone from
                    // `295.5px` to empty.
                    //
                    // ⚠️ That is an accessibility path rather than a cosmetic one. The trigger's
                    // `aria-describedby` still points at this panel, so a screen reader is
                    // describing a control with a box that is now nowhere near it.
                    repairErasure: true,
                    // Without this a `placement="right"` tooltip runs off the
                    // right edge of a phone and stays there. Floating UI's
                    // default shift only moves along the placement's MAIN axis,
                    // which for left/right is vertical — so nothing pulls it
                    // back horizontally, and `flip` gives up when both sides
                    // overflow, which on a 375px viewport they do.
                    // Measured before the fix: the panel occupied x 369..559 in
                    // a 375px viewport — 184px of it off-screen.
                    // The sibling overlays (popover, navigation-menu, filter-
                    // builder, color-picker, event-calendar, notification-
                    // center) already pass this; the tooltip was the one that
                    // did not.
                    crossAxisShift: true,
                });

                if (placement && typeof placement.stop === 'function') {
                    if (this.open) {
                        this._stopRepair = placement.stop;
                    } else {
                        // Hidden while the placement was in flight — its observer would outlive
                        // the panel it belongs to, and on a tooltip that race is the common case
                        // rather than the exotic one.
                        placement.stop();
                    }
                }
            }
        },

        /**
         * Carry the tooltip's themeable variables onto the teleported panel.
         *
         * The documented way to restyle one tooltip is an inline `style` on the component,
         * which sets `--color-wk-tooltip-bg` / `-text` on the trigger wrapper; the panel read
         * them by INHERITANCE, as a descendant.
         *
         * Teleporting the panel to `<body>` — which it needs, so a masking or clipping
         * ancestor cannot cut it off — ends that descent. The panel keeps rendering, with the
         * theme defaults, and every per-tooltip override silently stops working. The whole
         * "Styling Variants" section of the documentation was showing six identical tooltips.
         *
         * So the values are copied explicitly. Read from the WRAPPER with `getComputedStyle`,
         * which resolves whatever actually applies there — an inline style, a class, a scoped
         * theme — rather than reading the inline attribute and missing the other two routes.
         *
         * Copied on every show rather than once at init: a developer may change the variable
         * at runtime, and a value captured at mount would then be quietly stale.
         */
        _inheritThemeVars(tooltip) {
            const source = getComputedStyle(this.$el);

            for (const name of ['--color-wk-tooltip-bg', '--color-wk-tooltip-text', '--size-wk-tooltip-max']) {
                const value = source.getPropertyValue(name).trim();

                if (value !== '') {
                    tooltip.style.setProperty(name, value);
                }
            }
        },

        /**
         * Hide tooltip and clear all pending timers.
         */
        close() {
            this.open = false;
            this._stopRepair?.();
            this._stopRepair = null;
            this._clearAllTimers();
        },

        /**
         * Clear all pending timers — prevents stale callbacks from reopening.
         */
        _clearAllTimers() {
            clearTimeout(this._showTimer);
            clearTimeout(this._hideTimer);
            clearTimeout(this._longPressTimer);
            clearTimeout(this._autoDismissTimer);
        },

        /**
         * Force close — used during SPA cleanup.
         */
        _forceClose() {
            this.open = false;
            this._stopRepair?.();
            this._stopRepair = null;
            this._clearAllTimers();
        },
    };
}
