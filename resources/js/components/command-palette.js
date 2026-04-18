/**
 * WireKit Command Palette Alpine Component.
 *
 * Spotlight-style search modal triggered by Cmd/Ctrl+K.
 * Follows combobox + listbox pattern for keyboard navigation.
 * Uses focus-trap to keep keyboard focus inside the palette.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/combobox/
 */
import { createFocusTrap } from '../utils/focus-trap.js';

/**
 * @param {Object} config - Command palette configuration from Blade
 * @param {string} config.hotkey - Keyboard shortcut (default: 'cmd+k')
 * @param {boolean} [config.lockScroll=true] - Whether to set `document.body.style.overflow = 'hidden'`
 *   while the palette is open. Set to false when the palette is embedded inside a scoped
 *   container (e.g. docs preview card) where a global body-scroll lock would be disruptive.
 */
export default function wirekitCommandPalette(config = {}) {
    const lockScroll = config.lockScroll !== false;

    return {
        open: false,
        query: '',
        _activeIndex: -1,
        _trap: null,
        _navCleanup: null,
        _hotkeyHandler: null,

        init() {
            // Parse hotkey (e.g. 'cmd+k') and register global listener
            this._hotkeyHandler = (e) => {
                const hotkey = config.hotkey || 'cmd+k';
                const parts = hotkey.toLowerCase().split('+');
                const key = parts[parts.length - 1];
                const needsMeta = parts.includes('cmd') || parts.includes('meta');
                const needsCtrl = parts.includes('ctrl');

                const metaMatch = needsMeta ? (e.metaKey || e.ctrlKey) : true;
                const ctrlMatch = needsCtrl ? e.ctrlKey : true;

                if (e.key.toLowerCase() === key && metaMatch && ctrlMatch) {
                    e.preventDefault();
                    this.toggle();
                }
            };

            document.addEventListener('keydown', this._hotkeyHandler);

            // Listen for programmatic open events
            window.addEventListener('wirekit-command-palette-show', () => this.show());

            // SPA cleanup
            this._navCleanup = () => this._forceClose();
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });
        },

        destroy() {
            if (this._hotkeyHandler) {
                document.removeEventListener('keydown', this._hotkeyHandler);
            }
            if (this._navCleanup) {
                document.removeEventListener('livewire:navigating', this._navCleanup);
            }
            this._forceClose();
        },

        toggle() {
            this.open ? this.close() : this.show();
        },

        /**
         * Show command palette and activate focus trap.
         */
        show() {
            if (this.open) return;
            this.open = true;
            this.query = '';
            this._activeIndex = -1;

            // Lock body scroll (standard modal behavior). Skipped when the palette
            // is embedded inside a scoped container where global body-scroll lock
            // would be disruptive — see `lockScroll` prop on the Blade component.
            if (lockScroll) {
                document.body.style.overflow = 'hidden';
            }

            this.$nextTick(() => {
                const panel = this.$refs.panel;
                if (panel) {
                    this._trap = createFocusTrap(panel, {
                        escapeDeactivates: true,
                        onDeactivate: () => this._closeFromTrap(),
                        allowOutsideClick: true,
                        initialFocus: () => this.$refs.input,
                    });
                    this._trap.activate();
                }
            });
        },

        /**
         * Close triggered by focus-trap deactivation (ESC).
         */
        _closeFromTrap() {
            if (!this.open) return;
            this.open = false;
            this._trap = null;
            document.body.style.overflow = '';
        },

        /**
         * Close command palette.
         */
        close() {
            if (!this.open) return;
            this.open = false;

            if (this._trap) {
                this._trap.deactivate();
                this._trap = null;
            }

            document.body.style.overflow = '';
        },

        /**
         * Force close — SPA navigation.
         */
        _forceClose() {
            this.open = false;

            if (this._trap) {
                this._trap.deactivate();
                this._trap = null;
            }

            document.body.style.overflow = '';
        },

        /**
         * Get all visible command items.
         */
        _getItems() {
            const panel = this.$refs.list;
            if (!panel) return [];
            return [...panel.querySelectorAll('[role="option"]:not([aria-disabled="true"])')];
        },

        /**
         * Handle keyboard navigation in the command list.
         */
        handleKeydown(event) {
            const items = this._getItems();
            if (!items.length) return;

            switch (event.key) {
                case 'ArrowDown':
                    event.preventDefault();
                    this._activeIndex = (this._activeIndex + 1) % items.length;
                    this._scrollToActive(items);
                    break;

                case 'ArrowUp':
                    event.preventDefault();
                    this._activeIndex = this._activeIndex <= 0 ? items.length - 1 : this._activeIndex - 1;
                    this._scrollToActive(items);
                    break;

                case 'Enter':
                    event.preventDefault();
                    if (this._activeIndex >= 0 && items[this._activeIndex]) {
                        items[this._activeIndex].click();
                    }
                    break;

                case 'Home':
                    event.preventDefault();
                    this._activeIndex = 0;
                    this._scrollToActive(items);
                    break;

                case 'End':
                    event.preventDefault();
                    this._activeIndex = items.length - 1;
                    this._scrollToActive(items);
                    break;
            }
        },

        /**
         * Scroll the active item into view and update aria-activedescendant.
         */
        _scrollToActive(items) {
            const item = items[this._activeIndex];
            if (item) {
                item.scrollIntoView({ block: 'nearest' });
                // Update all items' active state
                items.forEach((el, i) => {
                    el.setAttribute('data-active', i === this._activeIndex ? 'true' : 'false');
                });
            }
        },

        /**
         * Get the id of the currently active item for aria-activedescendant.
         */
        get activeDescendant() {
            if (this._activeIndex < 0) return null;
            const items = this._getItems();
            return items[this._activeIndex]?.id || null;
        },
    };
}
