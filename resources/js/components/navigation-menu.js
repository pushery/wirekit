/**
 * WireKit Navigation Menu Alpine Component.
 *
 * Top-level navigation with rich flyout panels (mega menu pattern).
 * Hover/click to open panels. Follows disclosure pattern for a11y.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/disclosure/
 */
import { position } from '../utils/floating.js';

export default function wirekitNavigationMenu() {
    return {
        activeItem: null,
        _hideTimer: null,
        _navCleanup: null,

        init() {
            this._navCleanup = () => { this.activeItem = null; };
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });
        },

        destroy() {
            if (this._navCleanup) {
                document.removeEventListener('livewire:navigating', this._navCleanup);
            }
        },

        /**
         * Open a panel on hover/click.
         */
        async open(name) {
            clearTimeout(this._hideTimer);
            this.activeItem = name;

            await this.$nextTick();

            const trigger = this.$el.querySelector(`[data-wk-nav-trigger="${name}"]`);
            const panel = this.$el.querySelector(`[data-wk-nav-panel="${name}"]`);

            if (trigger && panel) {
                await position(trigger, panel, {
                    placement: 'bottom-start',
                    offset: 4,
                });
            }
        },

        /**
         * Delay close — allows moving between trigger and panel.
         * 300ms gives enough time to cross the offset gap between
         * trigger button and fixed-positioned panel without flickering.
         */
        scheduleClose() {
            this._hideTimer = setTimeout(() => {
                this.activeItem = null;
            }, 300);
        },

        /**
         * Cancel pending close (user moved into panel).
         */
        cancelClose() {
            clearTimeout(this._hideTimer);
        },

        /**
         * Close all panels immediately.
         */
        closeAll() {
            clearTimeout(this._hideTimer);
            this.activeItem = null;
        },
    };
}
