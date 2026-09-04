/**
 * WireKit Dropdown Alpine Component.
 *
 * Handles positioning via Floating UI, keyboard navigation (arrow keys),
 * click-outside closing, and ARIA menu pattern.
 */
import { coordinateOverlay } from '../utils/overlay-coordination.js';
import { position } from '../utils/floating.js';
import { typeAheadIndex } from '../utils/roving-focus.js';

/**
 * @param {Object} config - Dropdown configuration from Blade
 * @param {string} config.placement - Floating UI placement
 * @param {number} config.offset - Distance between trigger and panel in px
 */
export default function wirekitDropdown(config = {}) {
    return {
        open: false,
        // Read by the panel through the scope chain, which survives its teleport.
        panelId: config.panelId || '',
        _placement: config.placement || 'bottom-start',
        _offset: config.offset ?? 8,

        // Stored cleanup handler for destroy()
        _navCleanup: null,
        // Cross-close channel — see utils/overlay-coordination.js. Two sibling
        // dropdowns standing open at once overlap; opening one closes the rest.
        _coordination: null,
        // Floating UI autoUpdate teardown handle (set in show(), called in close()
        // + destroy()). Keeping the panel pinned to its trigger on scroll/resize
        // attaches ancestor-scroll + resize listeners; so every teardown path must call stop() or they leak
        // they MUST be torn down on every close path or they leak.
        _stopAutoUpdate: null,

        // Type-ahead state. The buffer is what the reader has typed so far; the timer is
        // what forgets it. Both are torn down in close() and destroy() — a pending timeout
        // that fires into a closed menu is the null-callback class this codebase has been
        // bitten by before.
        _typeAheadBuffer: '',
        _typeAheadTimer: null,

        init() {
            // The panel's id is written here rather than bound in the template.
            //
            // It used to be `x-bind:id="panelId"` on the panel — reading the value out of
            // this scope, which is correct across the TELEPORT (Alpine keeps the scope;
            // `closest()` would not) and wrong across a Livewire MORPH. On every morph
            // that binding was re-evaluated in a scope that no longer had `panelId`, and
            // threw `panelId is not defined`.
            //
            // A JavaScript error during a morph ends evaluation at that point, so whatever
            // the same pass would have done next does not happen — and nothing turns red,
            // because a console error is not a failed assertion. It sat in a consuming
            // application for weeks that way.
            //
            // Assigning it imperatively removes the last scope-dependent expression from
            // the teleported node: it runs on init, re-runs when the morph re-initializes
            // this component, and cannot be re-evaluated in a scope it does not belong to.
            // AFTER the tick, not during init(). Alpine walks a component's
            // children only once the parent's init() has returned, and the panel
            // lives inside a child `<template x-teleport>` — so at init() time
            // `$refs.panel` is not registered yet, the guard in _applyPanelId()
            // takes its silent branch, and the id is never written. Every
            // trigger's `aria-controls` then announces a relationship to an
            // element that is not in the document.
            //
            // This was tried once before and reverted, because writing the id
            // made the panel the morph's key and a mismatch swapped it for a
            // scopeless clone. The panel's `wire:key` is what makes it safe now:
            // the key no longer falls back to the id, both sides of the morph
            // agree, and the node is patched rather than replaced. Removing
            // either half brings the other's defect back.
            this.$nextTick(() => this._applyPanelId());

            // Cleanup on Livewire SPA navigation
            this._navCleanup = () => { this.open = false; };
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });

            // Sibling dropdowns close when this one opens. Two panels standing
            // open at once overlap, which is what a reader sees rather than a
            // console error — so nothing reports it.
            this._coordination = coordinateOverlay({
                channel: 'wirekit:dropdown-open',
                onOther: () => this.close(),
            });
        },

        /**
         * Put the panel id on the panel node.
         *
         * The value is preferred from the root's own attribute over this scope, because
         * the attribute is a fact about the DOM and survives anything the scope does not.
         */
        _applyPanelId() {
            const panel = this.$refs.panel;
            const id = (this.$el && this.$el.dataset && this.$el.dataset.wkPanelId) || this.panelId;

            if (panel && id) {
                panel.id = id;
            }
        },

        destroy() {
            this._resetTypeAhead();
            this._coordination?.stop();
            this._coordination = null;

            if (this._navCleanup) {
                document.removeEventListener('livewire:navigating', this._navCleanup);
            }
            this._stopAutoUpdate?.();
            this._stopAutoUpdate = null;
        },

        /**
         * Toggle dropdown open/close state.
         */
        toggle() {
            if (this.open) {
                this.close();
            } else {
                this.show();
            }
        },

        /**
         * Open dropdown and position panel relative to trigger.
         */
        async show() {
            this.open = true;
            this._coordination?.announce();

            // Wait for Alpine to render the panel, then position it
            await this.$nextTick();

            const trigger = this.$refs.trigger;
            const panel = this.$refs.panel;

            if (trigger && panel) {
                this._stopAutoUpdate?.();
                const { stop } = await position(trigger, panel, {
                    placement: this._placement,
                    offset: this._offset,
                    // Cap the panel to the viewport and let it scroll — a 12-item
                    // menu opening upward from the foot of a short window used to
                    // pin to the top edge and clip its first (often most
                    // important) item. See floating.js size middleware.
                    fitViewport: true,
                    // Follow the trigger while open; teardown handle stored for close().
                    autoReposition: true,
                });
                this._stopAutoUpdate = stop;

                // Focus first menu item for keyboard users
                this._focusFirstItem();
            }
        },

        /**
         * Close dropdown and return focus to trigger.
         *
         * The leading `if (!this.open) return;` guard is REQUIRED, not optional.
         * Every <x-wirekit::dropdown> wrapper carries an `x-on:click.outside="close()"`
         * listener that fires for EVERY dropdown on EVERY click anywhere on the page.
         * Without the guard, three dropdowns + one click on an unrelated input means
         * three close() invocations, three trigger.focus() calls, and the last one
         * wins — silently stealing focus from whatever element the user actually
         * clicked. Inputs lose focus mid-typing, dropdowns flicker open-then-shut,
         * any focusable element next to a dropdown is collateral damage.
         *
         * Matches the same guard pattern used in popover.js, command-palette.js,
         * and overlay.js (modal / drawer / alert-dialog).
         */
        close() {
            if (!this.open) return;

            // Forget what was typed. A buffer that survives a close would make the next
            // opening search for a word the reader typed into a menu that is gone, and a
            // pending timeout firing into a closed menu is the null-callback class this
            // codebase already has a rule about.
            this._resetTypeAhead();

            // Was the user's focus inside the menu when it closed? The answer decides
            // whether focus is OURS to move, and it has to be read BEFORE anything hides.
            //
            // Escape, Tab and activating an item all close with focus inside — there the
            // menu owes focus back to its trigger (WCAG 2.4.3), or a keyboard user
            // restarts from the top of the page. A click on some other control also
            // closes the menu, and there focus belongs where the user just put it;
            // pulling it to the trigger would take it out of the input they clicked.
            // The docblock above describes that theft from the other direction — and
            // moving the focus call earlier, as this fix does, would have made it fire
            // more reliably rather than less.
            const focusWasInside = this.$refs.panel?.contains(document.activeElement) ?? false;

            // Return focus to the trigger button. Use preventScroll so the page
            // does not jump to the trigger when the dropdown closes — the trigger
            // is already in view (the user just clicked it) and browser scroll
            // alignment would otherwise cause a visible jump on long pages.
            // `$refs.trigger` FIRST, then the DOM marker — and the fallback is the one that
            // actually fires. The trigger element declares its own `x-data`, which makes it a
            // scope root, and an `x-ref` registers into the closest scope; so that ref has
            // always belonged to the trigger rather than to this component.
            //
            // `$root`, NOT `$el`. `close()` is reached from handlers on more than one element:
            // `click.outside` sits on the wrapper, where the two are the same — but `Tab`
            // arrives through `handleKeydown`, which is bound on the PANEL, and there Alpine
            // binds `$el` to the panel. The panel does not contain the trigger, so the lookup
            // would return null and Tab would silently stop returning focus. `$root` is the
            // x-data element whichever child dispatched the event. Written as `$el` first and
            // caught by the guard that exists because this class has shipped twice before.
            const triggerRoot = this.$refs.trigger
                ?? this.$root?.querySelector('[data-wk-dropdown-trigger]');
            const target = triggerRoot?.querySelector('button, [role="button"], a')
                ?? triggerRoot;

            // BEFORE the hide, not after. `this.open = false` makes `x-show` write
            // `display: none`, and hiding the subtree that holds focus makes the browser
            // drop focus on `<body>` — after our focus() call, which therefore accomplished
            // nothing. Measured: Escape on an open menu left `document.activeElement ===
            // document.body` in both engines. Moving focus first means there is never a
            // moment where the focused element is inside a hidden subtree.
            if (focusWasInside) {
                target?.focus({ preventScroll: true });
            }

            this.open = false;
            this._stopAutoUpdate?.();
            this._stopAutoUpdate = null;
        },

        /**
         * Navigate menu items with arrow keys.
         * Implements WAI-ARIA menu keyboard pattern.
         */
        handleKeydown(e) {
            if (!this.open) return;

            const items = this._getItems();
            if (!items.length) return;

            const current = document.activeElement;
            const currentIndex = items.indexOf(current);

            // All navigation focus calls use preventScroll for the same reason
            // as _focusFirstItem(): the panel is `position: fixed` at viewport
            // coordinates, so letting the browser scroll the page on focus
            // change causes a jarring jump when the trigger sits mid-page.
            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    // Move to next item, wrap to first
                    items[(currentIndex + 1) % items.length]?.focus({ preventScroll: true });
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    // Move to previous item, wrap to last
                    items[(currentIndex - 1 + items.length) % items.length]?.focus({ preventScroll: true });
                    break;

                case 'Home':
                    e.preventDefault();
                    items[0]?.focus({ preventScroll: true });
                    break;

                case 'End':
                    e.preventDefault();
                    items[items.length - 1]?.focus({ preventScroll: true });
                    break;

                case 'Escape':
                    e.preventDefault();
                    this.close();
                    break;

                case 'Tab':
                    // Let tab leave the dropdown naturally, but close it
                    this.close();
                    break;

                case ' ':
                    // SPACE ACTIVATES THE FOCUSED ROW, and it has to be its own case rather
                    // than something the branch below leaves alone. A space is a printable
                    // character one byte long, so it fell into the type-ahead guard beneath —
                    // which calls preventDefault() and so suppressed the native keyup click a
                    // <button> row activates on. The menu answered Space with nothing at all:
                    // no activation, no close, no movement. Enter worked the whole time, which
                    // is why it read as "works" to everyone who tried it that way, while the
                    // menu pattern and this component's own keyboard table both promise both
                    // keys.
                    //
                    // Activating EXPLICITLY rather than by removing the preventDefault, because
                    // the rows are not all buttons: `dropdown.item` renders an <a> whenever it
                    // is given an href, and a space on a focused link does not activate it — it
                    // scrolls the page, behind an open menu. One synthetic click covers both
                    // tags, and it is the same event a mouse click sends, so the wrapper's
                    // delegated close() runs exactly as it does for Enter.
                    //
                    // Nothing focused inside the menu means nothing to activate: break WITHOUT
                    // preventDefault so the key keeps whatever meaning it had. That is the case
                    // when focus is still on the trigger — this handler sits on the wrapper the
                    // trigger lives in, and swallowing Space there stopped the trigger's own
                    // native toggle from closing the menu again.
                    if (currentIndex < 0) {
                        break;
                    }

                    e.preventDefault();
                    items[currentIndex].click();
                    break;

                default:
                    // TYPE-AHEAD. It was documented for a long time before it existed, and
                    // the line was eventually removed from the docs rather than the behavior
                    // added — which left the menu the one composite widget here a keyboard
                    // reader could not jump around in. This is that behavior; the sentence
                    // above survived its own fix and read as if the gap were still open.
                    //
                    // Single printable characters only. A modifier means the reader is
                    // reaching for a browser or OS shortcut, and swallowing those is how a
                    // widget stops being a good citizen of the page.
                    //
                    // A space never arrives here — the case above claims it for activation, so
                    // the buffer holds no spaces and a two-word label is reached by its first
                    // word. That is the trade the menu pattern names: in a menu the key belongs
                    // to activation, and a search that swallowed it would leave the row the
                    // reader had just found unreachable.
                    if (e.key.length !== 1 || e.ctrlKey || e.metaKey || e.altKey) {
                        break;
                    }

                    e.preventDefault();
                    this._typeAhead(e.key, items, currentIndex);
                    break;
            }
        },

        /**
         * Move focus to the next item matching what has been typed.
         *
         * The arithmetic lives in `typeAheadIndex` — pure, shared, and unit-tested — so this
         * is only the buffer and its timer.
         *
         * Half a second to forget. The menu pattern does not name a figure, so this is the
         * ticket's number rather than a specification's: long enough to finish a short word,
         * short enough that a pause reads as the start of a fresh search rather than as a
         * continuation of the last one.
         *
         * Disabled rows need no handling here — `_getItems()` already excludes
         * `aria-disabled`, so they are not in the list this searches.
         */
        _typeAhead(char, items, currentIndex) {
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

        /** Forget what was typed. Called wherever the menu stops being open. */
        _resetTypeAhead() {
            if (this._typeAheadTimer) {
                clearTimeout(this._typeAheadTimer);
                this._typeAheadTimer = null;
            }

            this._typeAheadBuffer = '';
        },

        /**
         * Focus the first enabled menu item.
         *
         * Uses `preventScroll: true` because the panel uses `position: fixed`
         * and there is a brief window between `x-show` making the panel visible
         * and Floating UI applying its computed left/top coordinates where the
         * panel is rendered at the viewport origin (0, 0). Without preventScroll
         * the browser scrolls the page to bring the focused item into view at
         * that origin — manifesting as an unexpected jump when the trigger sits
         * mid-page. The panel is already visible once Floating UI finishes, so
         * we don't need (or want) the browser to scroll anything.
         */
        _focusFirstItem() {
            const items = this._getItems();
            items[0]?.focus({ preventScroll: true });
        },

        /**
         * Get all focusable menu items (not disabled) at THIS level.
         *
         * Items nested inside a submenu's child panel (`[data-wk-submenu-panel]`)
         * are excluded so parent-level roving focus (ArrowUp/Down/Home/End) stays
         * flat — the submenu owns its own level via wirekitSubmenu. The submenu
         * PARENT item is itself a `[role="menuitem"]` that is NOT inside a submenu
         * panel, so it is correctly included at this level.
         *
         * @returns {HTMLElement[]}
         */
        _getItems() {
            const panel = this.$refs.panel;
            if (!panel) return [];

            // All THREE menu roles, and the two extra ones are not decoration: an
            // attribute-VALUE selector is an exact match, so `[role="menuitem"]` does not
            // match `menuitemradio` or `menuitemcheckbox` — the roles this component's own
            // `dropdown.radio-item` and `dropdown.checkbox-item` render. A menu built from
            // them returned an EMPTY list, so `_focusFirstItem()` focused nothing and the
            // arrow keys had nothing to walk even once they were delivered.
            return Array.from(
                panel.querySelectorAll(
                    '[role="menuitem"]:not([aria-disabled="true"]),'
                    +'[role="menuitemradio"]:not([aria-disabled="true"]),'
                    +'[role="menuitemcheckbox"]:not([aria-disabled="true"])'
                )
            ).filter((el) => !el.closest('[data-wk-submenu-panel]'));
        },
    };
}
