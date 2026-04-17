/**
 * WireKit Hover Card Alpine Component.
 *
 * Similar to Tooltip but designed for rich content (avatar, bio, actions).
 * Shows on hover with delay, hides on leave. Stays open when hovering
 * the card itself. Uses Floating UI for positioning.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/tooltip/
 */
import { position } from '../utils/floating.js';

/**
 * @param {Object} config - Hover card configuration from Blade
 * @param {string} config.placement - Floating UI placement (default: 'bottom')
 * @param {number} config.offset - Distance from trigger in px (default: 8)
 * @param {number} config.delayShow - Delay before showing (ms, default: 300)
 * @param {number} config.delayHide - Delay before hiding (ms, default: 200)
 */
export default function wirekitHoverCard(config = {}) {
    return {
        open: false,
        _placement: config.placement || 'bottom',
        _offset: config.offset || 8,
        _delayShow: config.delayShow || 300,
        _delayHide: config.delayHide || 200,
        _showTimer: null,
        _hideTimer: null,

        // SPA cleanup handler
        _navCleanup: null,

        init() {
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
         * Mouse enters trigger or panel — show with delay, cancel hide.
         */
        mouseenter() {
            clearTimeout(this._hideTimer);
            if (!this.open) {
                this._showTimer = setTimeout(() => this.show(), this._delayShow);
            }
        },

        /**
         * Mouse leaves trigger or panel — hide with delay.
         * The delay allows moving between trigger and panel without closing.
         */
        mouseleave() {
            clearTimeout(this._showTimer);
            this._hideTimer = setTimeout(() => this.close(), this._delayHide);
        },

        /**
         * Keyboard focus on trigger — show immediately.
         */
        focusin() {
            clearTimeout(this._hideTimer);
            this.show();
        },

        /**
         * Keyboard blur — hide with delay (allows tabbing into panel).
         */
        focusout() {
            clearTimeout(this._showTimer);
            this._hideTimer = setTimeout(() => {
                // Don't close if focus moved into the panel
                if (!this.$el.contains(document.activeElement)) {
                    this.close();
                }
            }, this._delayHide);
        },

        /**
         * Show hover card and position via Floating UI.
         */
        async show() {
            if (this.open) return;
            this.open = true;

            await this.$nextTick();

            const trigger = this.$refs.trigger;
            const panel = this.$refs.panel;

            if (trigger && panel) {
                await position(trigger, panel, {
                    placement: this._placement,
                    offset: this._offset,
                });
            }
        },

        /**
         * Hide hover card.
         */
        close() {
            this.open = false;
            clearTimeout(this._showTimer);
            clearTimeout(this._hideTimer);
        },

        /**
         * Force close — used during SPA cleanup.
         */
        _forceClose() {
            this.open = false;
            clearTimeout(this._showTimer);
            clearTimeout(this._hideTimer);
        },
    };
}
