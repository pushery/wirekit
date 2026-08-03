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

        // Stored cleanup handler for destroy()
        _navCleanup: null,

        init() {
            // Cleanup on SPA navigation
            this._navCleanup = () => this._forceClose();
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });
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
            this._showTimer = setTimeout(() => this.show(), this._delayShow);
        },

        /**
         * Desktop mouse leave — hide with delay.
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
         */
        keydownEscape() {
            this._clearAllTimers();
            this.close();
        },

        /**
         * Show tooltip and position via Floating UI.
         */
        async show() {
            if (this.open) return;

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

                await position(trigger, tooltip, {
                    placement: this._placement,
                    offset: this._offset,
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
            this._clearAllTimers();
        },
    };
}
