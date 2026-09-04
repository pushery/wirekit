/**
 * WireKit Submenu Alpine Component.
 *
 * A nested flyout opened from a parent menu item inside a dropdown,
 * context-menu, or menubar menu. The parent item carries
 * `aria-haspopup="menu"` + `aria-expanded`; the child panel is a
 * `role="menu"` positioned beside the parent via Floating UI.
 *
 * This factory is purely additive — a flat menu without a
 * `<x-wirekit::*.submenu>` never instantiates it, so existing menus are
 * unchanged. It is nested inside its parent menu's x-data, so its
 * expressions can read the parent scope (e.g. `open` on dropdown /
 * context-menu) for the close-on-parent-close reset.
 *
 * WAI-ARIA submenu keyboard model (https://www.w3.org/WAI/ARIA/apg/patterns/menu/):
 *   - On the parent item: ArrowRight / Enter / Space open the submenu and
 *     focus its first item. ArrowUp / ArrowDown are NOT handled here — they
 *     bubble to the parent menu's own handler so parent-level roving focus
 *     keeps working.
 *   - Inside the submenu panel: ArrowUp / ArrowDown / Home / End move within
 *     the level; ArrowLeft and Escape close the submenu and return focus to
 *     the parent item; a printable character jumps to a row by its label and
 *     Space activates the focused row. These keys stopPropagation so the parent
 *     menu's handler does not also act on them.
 *
 * WHY THE LAST TWO ARE STATED AS PART OF THE MODEL RATHER THAN AS EXTRAS: a
 * submenu panel is a `role="menu"`, and the pattern asks type-ahead of every
 * one. They were missing here, and missing keys do not fall on the floor — the
 * panel is an ordinary descendant of the parent menu's own keydown handler, so
 * they bubbled into it. That handler filters submenu rows out of its list by
 * construction, so it searched the PARENT level and focused a parent row: one
 * letter and the reader was lifted out of the level they were reading.
 *
 * Lifecycle resources held on `this` (released in destroy()):
 *   - `_closeTimer` — the hover-out close setTimeout. Cleared on teardown so a
 *     pending close can't fire against a destroyed scope.
 *   - `_typeAheadTimer` — the forget-what-was-typed setTimeout. Same reasoning:
 *     it outlives a Livewire morph or an SPA navigation otherwise, and fires
 *     against a scope that is gone.
 *
 * No listeners or observers are registered, so those two timers are the whole
 * cleanup surface.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/menu/
 */
import { position } from '../utils/floating.js';
import { typeAheadIndex } from '../utils/roving-focus.js';

// Hover-out close delay. A short grace period lets the pointer travel
// diagonally from the parent item onto the child panel without the submenu
// snapping shut mid-traverse (the classic "diagonal problem").
const HOVER_CLOSE_DELAY_MS = 140;

/**
 * @param {Object} config - Submenu configuration from Blade
 * @param {string} config.placement - Floating UI placement (default right-start)
 * @param {number} config.offset - Gap between parent item and child panel
 */
export default function wirekitSubmenu(config = {}) {
    return {
        subOpen: false,
        _subPlacement: config.placement || 'right-start',
        _subOffset: config.offset ?? 0,
        _closeTimer: null,
        _typeAheadBuffer: '',
        _typeAheadTimer: null,

        /**
         * Release both lifecycle resources: the pending hover-out close timer and the
         * type-ahead forget-timer. Alpine calls this on teardown (Livewire morph / SPA
         * navigation), so neither can fire against a destroyed component scope.
         */
        destroy() {
            this._clearCloseTimer();
            this._resetTypeAhead();
        },

        /**
         * Open the submenu and position the child panel beside the parent item.
         * @param {boolean} focusFirst - Move focus to the first child item.
         */
        async openSub(focusFirst = false) {
            this._clearCloseTimer();

            if (this.subOpen) {
                if (focusFirst) this._focusFirstSubItem();

                return;
            }

            this.subOpen = true;
            await this.$nextTick();

            const trigger = this.$refs.subTrigger;
            const panel = this.$refs.subPanel;
            if (trigger && panel) {
                await position(trigger, panel, {
                    placement: this._subPlacement,
                    offset: this._subOffset,
                });
                if (focusFirst) this._focusFirstSubItem();
            }
        },

        /**
         * Close the submenu. Optionally return focus to the parent item (the
         * ArrowLeft / Escape path); the parent-close reset path does not
         * refocus (the whole menu is going away).
         * @param {boolean} refocusParent
         */
        closeSub(refocusParent = false) {
            this._clearCloseTimer();
            // A search the reader abandoned must not resume when the flyout is opened again.
            this._resetTypeAhead();

            if (!this.subOpen) return;

            this.subOpen = false;
            if (refocusParent) {
                this.$refs.subTrigger?.focus({ preventScroll: true });
            }
        },

        /**
         * Hover open (no focus move — pointer users don't need roving focus).
         */
        scheduleOpen() {
            this._clearCloseTimer();
            this.openSub(false);
        },

        /**
         * Hover-out close after a short grace period (the diagonal problem).
         *
         * Sets `subOpen = false` inline rather than routing through closeSub() on
         * purpose: a pointer-driven close must NOT refocus the parent item (that
         * would yank focus on a mouse interaction). closeSub(true) is reserved for
         * the keyboard paths (ArrowLeft / Escape) where refocusing IS correct.
         */
        scheduleClose() {
            this._clearCloseTimer();
            this._closeTimer = setTimeout(() => {
                this.subOpen = false;
                this._closeTimer = null;
                this._resetTypeAhead();
            }, HOVER_CLOSE_DELAY_MS);
        },

        _clearCloseTimer() {
            if (this._closeTimer) {
                clearTimeout(this._closeTimer);
                this._closeTimer = null;
            }
        },

        /**
         * Keyboard on the parent item. Only opens-the-submenu keys are handled
         * (and stopPropagation'd); everything else bubbles to the parent menu's
         * handler so parent-level navigation is untouched.
         * @param {KeyboardEvent} e
         */
        onTriggerKey(e) {
            switch (e.key) {
                case 'ArrowRight':
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    e.stopPropagation();
                    this.openSub(true);
                    break;
            }
        },

        /**
         * Keyboard within the submenu panel. Handled keys stopPropagation so the
         * parent menu's roving-focus handler does not also fire.
         * @param {KeyboardEvent} e
         */
        onSubKey(e) {
            const items = this._subItems();
            if (!items.length) return;

            const idx = items.indexOf(document.activeElement);

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    e.stopPropagation();
                    items[(idx + 1) % items.length]?.focus({ preventScroll: true });
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    e.stopPropagation();
                    items[(idx - 1 + items.length) % items.length]?.focus({ preventScroll: true });
                    break;

                case 'Home':
                    e.preventDefault();
                    e.stopPropagation();
                    items[0]?.focus({ preventScroll: true });
                    break;

                case 'End':
                    e.preventDefault();
                    e.stopPropagation();
                    items[items.length - 1]?.focus({ preventScroll: true });
                    break;

                case 'ArrowLeft':
                case 'Escape':
                    e.preventDefault();
                    e.stopPropagation();
                    this.closeSub(true);
                    break;

                case ' ':
                    // SPACE ACTIVATES THE FOCUSED ROW, and it needs its own case ahead of the
                    // type-ahead branch below — a space is a printable character one byte
                    // long, so without this it would be buffered as a search term and its
                    // preventDefault() would suppress the native activation a <button> row
                    // performs on keyup. The menu would answer Space with nothing at all.
                    //
                    // Activating EXPLICITLY rather than by letting the default through,
                    // because the rows are not all buttons: `dropdown.item` renders an <a>
                    // whenever it is given an href, and Space on a focused link does not
                    // activate it — it scrolls the page, behind an open menu. One synthetic
                    // click covers both tags. This is the parent level's own answer, applied
                    // at the level that never received it.
                    //
                    // Nothing focused means nothing to activate: break WITHOUT preventDefault
                    // so the key keeps whatever meaning it had.
                    if (idx < 0) break;

                    e.preventDefault();
                    e.stopPropagation();
                    items[idx].click();
                    break;

                default:
                    // TYPE-AHEAD, and stopping it here is the point rather than a detail. An
                    // unhandled key does not stop at this panel: the panel is an ordinary
                    // descendant of the parent menu's keydown handler, and that handler
                    // filters submenu rows out of its own list — so it searched the parent
                    // level and focused a parent row, lifting the reader out of the submenu
                    // for a keystroke they aimed inside it. When no parent label matched, its
                    // preventDefault() swallowed the key regardless.
                    //
                    // Single printable characters only. A modifier means the reader is
                    // reaching for a browser or OS shortcut, and swallowing those is how a
                    // widget stops being a good citizen of the page.
                    if (e.key.length !== 1 || e.ctrlKey || e.metaKey || e.altKey) {
                        break;
                    }

                    e.preventDefault();
                    e.stopPropagation();
                    this._subTypeAhead(e.key, items, idx);
                    break;
            }
        },

        /**
         * Move focus to the next row of THIS level matching what has been typed.
         *
         * The arithmetic lives in `typeAheadIndex` — pure, shared with the parent menu, and
         * unit-tested — so this is only the buffer and its timer. Same half-second to forget
         * as the parent level, and the same reason to match it: a reader who types a word
         * across a menu and its submenu should not meet two different rhythms.
         *
         * Disabled rows need no handling here — `_subItems()` already excludes
         * `aria-disabled`, so they are not in the list this searches.
         */
        _subTypeAhead(char, items, currentIndex) {
            this._typeAheadBuffer += char;

            if (this._typeAheadTimer) {
                clearTimeout(this._typeAheadTimer);
            }

            this._typeAheadTimer = setTimeout(() => {
                this._typeAheadBuffer = '';
                this._typeAheadTimer = null;
            }, 500);

            const labels = items.map((el) => el.textContent || '');
            const index = typeAheadIndex(labels, this._typeAheadBuffer, currentIndex);

            if (index >= 0) {
                items[index]?.focus({ preventScroll: true });
            }
        },

        /** Forget what was typed. Called wherever the submenu stops being open. */
        _resetTypeAhead() {
            if (this._typeAheadTimer) {
                clearTimeout(this._typeAheadTimer);
                this._typeAheadTimer = null;
            }

            this._typeAheadBuffer = '';
        },

        /**
         * Direct-level items of THIS submenu (a deeper nested submenu's items
         * are excluded — their closest submenu panel is the deeper one).
         * @returns {HTMLElement[]}
         */
        _subItems() {
            const panel = this.$refs.subPanel;
            if (!panel) return [];

            // All three row roles, because a submenu panel holds the same rows its parent
            // does: `dropdown.radio-item` and `dropdown.checkbox-item` render
            // `menuitemradio` and `menuitemcheckbox`. Asking for `menuitem` alone returned an
            // EMPTY list for a submenu built from them — and an empty list makes the key
            // handler return before it does anything, so every key, arrow keys included, fell
            // through to the level above. The parent collector learned this already; this is
            // the same lesson one level down.
            return [...panel.querySelectorAll(
                '[role="menuitem"]:not([aria-disabled="true"]),'
                + '[role="menuitemradio"]:not([aria-disabled="true"]),'
                + '[role="menuitemcheckbox"]:not([aria-disabled="true"])'
            )]
                .filter((el) => el.closest('[data-wk-submenu-panel]') === panel);
        },

        _focusFirstSubItem() {
            this._subItems()[0]?.focus({ preventScroll: true });
        },
    };
}
