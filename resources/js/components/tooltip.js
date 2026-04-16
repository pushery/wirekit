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
        _placement: config.placement || 'top',
        _offset: config.offset || 6,
        _delayShow: config.delayShow || 300,
        _delayHide: config.delayHide || 100,
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
            this.open = true;

            await this.$nextTick();

            const trigger = this.$refs.trigger;
            const tooltip = this.$refs.tooltip;

            if (trigger && tooltip) {
                await position(trigger, tooltip, {
                    placement: this._placement,
                    offset: this._offset,
                });
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
