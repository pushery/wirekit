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
        _observer: null,
        _lastSignature: '',
        _navCleanup: null,
        _hotkeyHandler: null,
        _showHandler: null,

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

            // Listen for programmatic open events — store reference for cleanup
            this._showHandler = () => this.show();
            window.addEventListener('wirekit-command-palette-show', this._showHandler);

            // SPA cleanup
            this._navCleanup = () => this._forceClose();
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });
        },

        destroy() {
            this._unwatchList();
            if (this._hotkeyHandler) {
                document.removeEventListener('keydown', this._hotkeyHandler);
            }
            if (this._showHandler) {
                window.removeEventListener('wirekit-command-palette-show', this._showHandler);
                this._showHandler = null;
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

                // The list only exists once the panel has rendered.
                this._watchList();
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
            }

            this._paintActive(items);
        },

        /**
         * Write the highlight onto the list from `_activeIndex`.
         *
         * Split out of _scrollToActive because it has a second caller: a
         * re-render. `data-active` exists only in the live DOM — the server never
         * emits it — so Livewire's morph strips it from every row on every
         * response, and the highlight would vanish on each keystroke of a
         * server-driven search. Re-painting after a mutation puts it back.
         */
        _paintActive(items = this._getItems()) {
            items.forEach((el, i) => {
                el.setAttribute('data-active', i === this._activeIndex ? 'true' : 'false');
            });
        },

        /**
         * Identity of the current result set — the ids, in order.
         *
         * Used to tell "the same list was re-rendered" (restore the highlight)
         * from "these are different results" (the old index means nothing now).
         */
        _itemsSignature(items = this._getItems()) {
            return items.map((el) => el.id).join('|');
        },

        /**
         * Keep the keyboard state honest across re-renders.
         *
         * `_activeIndex` used to be reset only in show(), which is correct only
         * while the list is rendered once. With server-side search the list is
         * rebuilt on every keystroke: the index survived and pointed into the NEW
         * results, so Enter activated whatever now sat at that position — the user
         * arrowed to the third hit, typed one more character, and confirmed
         * something they never looked at.
         *
         * A changed result set therefore clears the selection; an unchanged one
         * that merely re-rendered gets its highlight painted back on.
         */
        _watchList() {
            const list = this.$refs.list;
            if (!list || typeof MutationObserver === 'undefined') return;

            this._lastSignature = this._itemsSignature();

            this._observer = new MutationObserver(() => {
                const signature = this._itemsSignature();

                if (signature !== this._lastSignature) {
                    this._lastSignature = signature;
                    this._activeIndex = -1;
                }

                this._paintActive();
            });

            // childList only, deliberately: _paintActive writes attributes, and
            // observing attributes as well would make this observer retrigger
            // itself on its own write, forever.
            this._observer.observe(list, { childList: true, subtree: true });
        },

        _unwatchList() {
            if (this._observer) {
                this._observer.disconnect();
                this._observer = null;
            }
        },

        /**
         * Emit the query for a host driving server-side search.
         *
         * Dispatched from the component ROOT, not from the input. The input lives
         * inside `<template x-teleport="body">`, so at runtime it is a child of
         * <body>: an event fired there never passes the root element, and
         * `<x-wirekit::command-palette x-on:wirekit-command-palette-query="…">` —
         * the obvious way to wire this up — silently never fired. Alpine only
         * forwards events across a teleport when they are registered on the
         * <template> itself.
         */
        emitQuery() {
            this.$root.dispatchEvent(new CustomEvent('wirekit-command-palette-query', {
                detail: { query: this.query },
                bubbles: true,
                composed: true,
            }));
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
