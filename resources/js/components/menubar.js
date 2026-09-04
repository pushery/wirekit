/**
 * WireKit Menubar Alpine Component.
 *
 * Desktop-style horizontal menu bar with dropdown menus.
 *
 * TWO LEVELS, and which one a key belongs to is decided by whether a menu is open. On
 * the closed bar the arrows move focus between triggers without opening anything, Enter,
 * Space and ArrowDown open the trigger the reader is STANDING ON (ArrowUp opens it onto
 * its last item), and the bar is a single tab stop — one trigger carries tabindex 0 and
 * the rest carry -1. Inside an open menu the arrows walk the items, and Escape closes and
 * hands focus back to the trigger it came from rather than dropping it on <body>.
 *
 * Where the reader is standing comes from the EVENT, not from `activeMenu`. Deriving it
 * from the open menu alone gives the same answer for every trigger while the bar is
 * closed, and the arithmetic then runs off that: ArrowDown on the third menu opened the
 * first, and ArrowLeft opened the last.
 *
 * Lifecycle resources held on `this`:
 *   - _navCleanup / _onPointerDown (document listeners) — removed in destroy()
 *   - _coordination (cross-close channel) — stopped in destroy()
 *   - _stopAutoUpdate (Floating UI autoUpdate) — called and nulled on every close path
 *   - _typeAheadTimer (setTimeout) — cleared by _resetTypeAhead(), which runs from
 *     destroy() and from every path that closes a menu, so a pending timeout can never
 *     fire into a menu that is gone.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/menubar/
 */
import { coordinateOverlay } from '../utils/overlay-coordination.js';
import { position } from '../utils/floating.js';
import { typeAheadIndex } from '../utils/roving-focus.js';

export default function wirekitMenubar() {
    return {
        activeMenu: null,

        // WHICH TRIGGER IS THE BAR'S ONE TAB STOP. The menubar pattern gives the whole
        // bar a single stop and moves between the triggers with the arrow keys, so a
        // reader tabbing past a five-menu bar passes it in one press rather than five.
        // Null until the reader has been anywhere, which `tabindexFor` reads as "the
        // first menu" — the bar has to be enterable before anyone has touched it.
        rovingTrigger: null,

        // Type-ahead buffer and the timeout that forgets it. Both are cleared wherever a
        // menu stops being open: a pending timeout firing into a closed menu is the
        // null-callback class this codebase already has a rule about.
        _typeAheadBuffer: '',
        _typeAheadTimer: null,

        _navCleanup: null,
        _onPointerDown: null,
        // Floating UI autoUpdate teardown handle for the ONE open menu panel. Set in
        // _positionActiveMenu (stopping the previous menu's first), cleared whenever
        // activeMenu returns to null. Prevents leaked scroll/resize listeners when
        // switching or closing menus (every teardown path must call stop()).
        _stopAutoUpdate: null,

        // Cross-close channel — see utils/overlay-coordination.js. Two menubars
        // on one page could each hold a menu open, and the two panels overlap.
        _coordination: null,

        init() {
            this._navCleanup = () => { this.activeMenu = null; };
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });

            // Outside-click close. The dropdown panels are teleported to
            // <body> (to escape transformed ancestors), so they are no longer
            // DOM descendants of `this.$el` — a Blade `x-on:click.outside` on
            // the menubar root would treat a click INSIDE an open panel as
            // "outside" and close the menu before the item's own click handler
            // ran. This document-level handler instead closes only when the
            // pointer lands outside BOTH the menubar bar AND the active
            // teleported panel (looked up via the teleport-safe $refs).
            this._onPointerDown = (event) => {
                if (!this.activeMenu) return;
                const target = event.target;
                if (!(target instanceof Node)) return;
                if (this.$root.contains(target)) return;
                const panel = this.$refs[`panel-${this.activeMenu}`];
                if (panel && panel.contains(target)) return;
                this.closeAll();
            };
            document.addEventListener('pointerdown', this._onPointerDown, { capture: true });

            // Another menubar opening a menu closes this one's.
            this._coordination = coordinateOverlay({
                channel: 'wirekit:menubar-open',
                onOther: () => this.closeAll(),
            });
        },

        destroy() {
            this._resetTypeAhead();
            this._coordination?.stop();
            this._coordination = null;

            if (this._navCleanup) {
                document.removeEventListener('livewire:navigating', this._navCleanup);
            }
            if (this._onPointerDown) {
                document.removeEventListener('pointerdown', this._onPointerDown, { capture: true });
            }
            this._stopAutoUpdate?.();
            this._stopAutoUpdate = null;
        },

        /**
         * Toggle a specific menu open/close.
         */
        async toggleMenu(name) {
            this._resetTypeAhead();

            if (this.activeMenu === name) {
                this.activeMenu = null;
                this._stopAutoUpdate?.();
                this._stopAutoUpdate = null;
            } else {
                this.activeMenu = name;
                this.rovingTrigger = name;
                this._coordination?.announce();
                await this._positionActiveMenu(name);
            }
        },

        /**
         * Open a menu (used for hover when another menu is already open).
         */
        async openMenu(name) {
            if (this.activeMenu && this.activeMenu !== name) {
                this._resetTypeAhead();
                this.activeMenu = name;
                this.rovingTrigger = name;
                this._coordination?.announce();
                await this._positionActiveMenu(name);
            }
        },

        /**
         * Close all menus.
         *
         * Focus is deliberately NOT moved here. This runs from an outside pointerdown and
         * from another menubar announcing itself, and in both the reader has just put the
         * focus somewhere on purpose — pulling it back to a trigger would take it out of
         * the control they clicked. The keyboard paths that DO owe focus back go through
         * `closeAndFocusTrigger()` instead.
         */
        closeAll() {
            this._resetTypeAhead();
            this.activeMenu = null;
            this._stopAutoUpdate?.();
            this._stopAutoUpdate = null;
        },

        /**
         * Close the open menu and put focus back on the trigger it belongs to.
         *
         * The counterpart to `closeAll()`, and the one every keyboard exit takes: Escape,
         * and activating an item. Closing a menu that holds the focus leaves the reader on
         * `<body>` — the next Tab restarts at the top of the document, which WCAG 2.4.3
         * calls a focus-order failure and a reader experiences as losing their place.
         *
         * Read the owning trigger BEFORE closing: `activeMenu` is what names it, and
         * `closeAll()` clears that.
         */
        closeAndFocusTrigger() {
            const trigger = this._triggerFor(this.activeMenu);

            this.closeAll();

            // `preventScroll`, as everywhere else here: the panel is `position: fixed` at
            // viewport coordinates, so letting the browser scroll to the trigger makes a
            // mid-page menubar jump on close.
            trigger?.focus({ preventScroll: true });
        },

        /**
         * Position the active menu's dropdown panel.
         */
        async _positionActiveMenu(name) {
            await this.$nextTick();

            // Trigger stays in the bar (not teleported). Query from $root (the
            // menubar element), NOT $el — when this runs off a trigger's
            // x-on:click, Alpine binds $el to the clicked menuitem button
            // (no trigger descendants), so $el.querySelector would miss and
            // positioning would silently never run. Panel is teleported to
            // <body> → resolve via the teleport-safe ref.
            const trigger = this.$root.querySelector(`[data-wk-menubar-trigger="${name}"]`);
            const panel = this.$refs[`panel-${name}`];

            if (trigger && panel) {
                this._stopAutoUpdate?.();
                const { stop } = await position(trigger, panel, {
                    placement: 'bottom-start',
                    offset: 4,
                    autoReposition: true,
                });
                this._stopAutoUpdate = stop;
            }
        },

        /**
         * Get menu trigger buttons.
         */
        _getTriggers() {
            // $root, not $el — see _positionActiveMenu for why ($el can be a
            // clicked menuitem button rather than the menubar root).
            return [...this.$root.querySelectorAll('[data-wk-menubar-trigger]')];
        },

        /**
         * The trigger button owning a menu name, or null.
         */
        _triggerFor(name) {
            if (! name) return null;

            return this.$root.querySelector(`[data-wk-menubar-trigger="${name}"]`);
        },

        /**
         * The trigger the reader is standing ON, resolved from the event itself.
         *
         * This is the piece the keyboard model was missing. Every arrow branch used to
         * derive its position from `activeMenu` alone, so with the bar closed the position
         * was -1 for every trigger equally: ArrowDown on the third menu opened the first,
         * and ArrowLeft opened the last. The reader's focus IS the position when nothing
         * is open, and only the event knows where that is.
         *
         * Scoped to `$root` because the handler is also bound on the teleported panel,
         * which no longer sits inside this component's markup — an unscoped `closest()`
         * could climb out of a nested menubar into the one wrapping it.
         */
        _triggerFromEvent(event) {
            const target = event?.target;

            if (! target || typeof target.closest !== 'function') return null;

            const trigger = target.closest('[data-wk-menubar-trigger]');

            return trigger && this.$root.contains(trigger) ? trigger : null;
        },

        /**
         * Index of the trigger the reader is on: the open menu, else the focused trigger.
         *
         * -1 means neither — a keydown that reached this component from somewhere with no
         * position to move from, where the arrow branches do nothing rather than guess.
         */
        _currentTriggerIndex(triggers, event) {
            if (this.activeMenu) {
                return triggers.findIndex((t) => t.dataset.wkMenubarTrigger === this.activeMenu);
            }

            const trigger = this._triggerFromEvent(event);

            return trigger ? triggers.indexOf(trigger) : -1;
        },

        /**
         * The bar's single tab stop, as a tabindex value for one trigger.
         *
         * Bound on every trigger. Before this the triggers carried no tabindex at all, so
         * each was its own tab stop and a five-menu bar cost five presses to pass — the
         * opposite of what the menubar pattern is for, and what this component's own
         * keyboard table already promised readers.
         *
         * The DOM read is the fallback rather than the rule: `rovingTrigger` is reactive
         * and re-runs this binding, a `querySelector` would not. It only answers the first
         * evaluation, before the reader has moved anywhere.
         */
        tabindexFor(name) {
            const roving = this.rovingTrigger ?? this._getTriggers()[0]?.dataset.wkMenubarTrigger;

            return roving === name ? 0 : -1;
        },

        /**
         * Remember the trigger that just took focus, so it stays the bar's tab stop.
         *
         * Bound to the triggers' own focus event rather than set only by the arrow keys:
         * focus also arrives by mouse and by Tab, and a tab stop that only tracked the
         * keyboard would send the reader back to the first menu on the way out and in.
         */
        markRovingTrigger(name) {
            this.rovingTrigger = name;
        },

        /**
         * Open one menu and land on the item at one end of it.
         *
         * `edge` is 'first' or 'last' — Enter, Space and ArrowDown open onto the first
         * item, ArrowUp onto the last, which is the menubar pattern's whole reason for
         * treating ArrowUp on a trigger as an open rather than as movement.
         *
         * Awaited, not fired and forgotten: `toggleMenu` positions the panel, and the
         * items cannot be collected until it exists in the document.
         */
        async openMenuAt(name, edge) {
            if (this.activeMenu !== name) {
                await this.toggleMenu(name);
            } else {
                await this.$nextTick();
            }

            const items = this._getActiveItems();

            if (! items.length) return;

            const item = edge === 'last' ? items[items.length - 1] : items[0];

            item?.focus({ preventScroll: true });
        },

        /**
         * Get menu items at the TOP level of the active panel.
         *
         * Items nested inside a submenu's child panel (`[data-wk-submenu-panel]`)
         * are excluded so top-level roving focus stays flat — the submenu owns
         * its own level via wirekitSubmenu. The submenu PARENT item is itself a
         * `[role="menuitem"]` NOT inside a submenu panel, so it stays included.
         */
        _getActiveItems() {
            if (!this.activeMenu) return [];
            // Panel is teleported to <body>; resolve via the teleport-safe ref.
            const panel = this.$refs[`panel-${this.activeMenu}`];
            if (!panel) return [];
            return [...panel.querySelectorAll('[role="menuitem"]:not([aria-disabled="true"])')]
                .filter((el) => !el.closest('[data-wk-submenu-panel]'));
        },

        /**
         * Handle keyboard navigation for the menubar.
         *
         * TWO LEVELS SHARE THIS HANDLER, and which one a key belongs to is decided by
         * whether a menu is open — not by which element the event came from. Bound on the
         * bar AND on the teleported panel (the panel is no longer a DOM descendant, so
         * nothing bubbles from it to the bar), so both levels arrive here.
         *
         * Position within the bar comes from `_currentTriggerIndex`, which reads the
         * reader's focus when nothing is open. Deriving it from `activeMenu` alone — as
         * every branch here once did — gave -1 for every trigger equally with the bar
         * closed, so ArrowDown on the third menu opened the FIRST and ArrowLeft opened
         * the LAST. Both looked like the widget ignoring where the reader was standing,
         * which is exactly what it was doing.
         */
        handleKeydown(event) {
            const triggers = this._getTriggers();

            if (! triggers.length) return;

            const currentIdx = this._currentTriggerIndex(triggers, event);
            const onTrigger = ! this.activeMenu && currentIdx >= 0;

            switch (event.key) {
                case 'ArrowRight':
                case 'ArrowLeft': {
                    // Nowhere to move FROM — a keydown that reached this component
                    // without the reader standing on a trigger or having a menu open.
                    // Break without claiming the key rather than guessing a position.
                    if (currentIdx < 0) break;

                    event.preventDefault();

                    const step = event.key === 'ArrowRight' ? 1 : -1;
                    const target = triggers[(currentIdx + step + triggers.length) % triggers.length];
                    const name = target?.dataset.wkMenubarTrigger;

                    if (! name) break;

                    this.markRovingTrigger(name);

                    // With a menu open the movement carries the open state along; with
                    // the bar closed it moves focus ONLY. Arrowing across a closed bar
                    // used to open every menu it passed, which is the pattern's own
                    // distinction between browsing the bar and being inside a menu.
                    if (this.activeMenu) {
                        this.openMenuAt(name, 'first');
                    } else {
                        target.focus({ preventScroll: true });
                    }

                    break;
                }

                case 'ArrowDown': {
                    event.preventDefault();

                    if (onTrigger) {
                        this.openMenuAt(triggers[currentIdx].dataset.wkMenubarTrigger, 'first');
                        break;
                    }

                    const items = this._getActiveItems();

                    if (! items.length) break;

                    const idx = items.indexOf(document.activeElement);

                    items[(idx + 1) % items.length]?.focus({ preventScroll: true });
                    break;
                }

                case 'ArrowUp': {
                    event.preventDefault();

                    // On a trigger this OPENS onto the last item rather than moving —
                    // the one asymmetry in the pattern, and the reason a reader can reach
                    // the bottom of a long menu in one press.
                    if (onTrigger) {
                        this.openMenuAt(triggers[currentIdx].dataset.wkMenubarTrigger, 'last');
                        break;
                    }

                    const items = this._getActiveItems();

                    if (! items.length) break;

                    const idx = items.indexOf(document.activeElement);

                    items[(idx - 1 + items.length) % items.length]?.focus({ preventScroll: true });
                    break;
                }

                case 'Enter':
                case ' ': {
                    // ONLY on a trigger. The same handler sits on the panel, where these
                    // two keys belong to the focused item — a native <button> or <a> the
                    // browser already activates, and swallowing the key there would leave
                    // an item that answers nothing.
                    if (! onTrigger) break;

                    event.preventDefault();
                    this.openMenuAt(triggers[currentIdx].dataset.wkMenubarTrigger, 'first');
                    break;
                }

                case 'Escape':
                    event.preventDefault();
                    this.closeAndFocusTrigger();
                    break;

                case 'Home':
                case 'End': {
                    // Whichever level is current — the open menu's items, or the bar's
                    // own triggers. Jumping to an end is meaningful at both, and a Home
                    // that only ever answered inside an open menu swallowed the key on a
                    // closed bar while doing nothing with it.
                    const elements = this.activeMenu ? this._getActiveItems() : triggers;

                    if (! elements.length) break;

                    event.preventDefault();

                    const target = event.key === 'Home' ? elements[0] : elements[elements.length - 1];

                    target?.focus({ preventScroll: true });

                    if (! this.activeMenu) {
                        this.markRovingTrigger(target?.dataset?.wkMenubarTrigger);
                    }

                    break;
                }

                default: {
                    // TYPE-AHEAD over whichever level is current: the open menu's items,
                    // or the triggers themselves when the bar is closed. Single printable
                    // characters only — a modifier means the reader is reaching for a
                    // browser or OS shortcut, and swallowing those is how a widget stops
                    // being a good citizen of the page.
                    if (event.key.length !== 1 || event.ctrlKey || event.metaKey || event.altKey) {
                        break;
                    }

                    // A submenu runs its own level and does not stop propagation for
                    // printable keys, so a search started inside one would move focus out
                    // of it and into the parent menu behind.
                    if (event.target?.closest?.('[data-wk-submenu-panel]')) break;

                    const elements = this.activeMenu ? this._getActiveItems() : triggers;

                    if (! elements.length) break;

                    event.preventDefault();
                    this._typeAhead(event.key, elements, elements.indexOf(document.activeElement));
                    break;
                }
            }
        },

        /**
         * Move focus to the next element matching what has been typed.
         *
         * The arithmetic lives in `typeAheadIndex` — pure, shared and unit-tested — so
         * this is only the buffer and its timer. Half a second to forget: long enough to
         * finish a short word, short enough that a pause reads as a fresh search.
         */
        _typeAhead(char, elements, currentIndex) {
            this._typeAheadBuffer += char;

            if (this._typeAheadTimer) {
                clearTimeout(this._typeAheadTimer);
            }

            this._typeAheadTimer = setTimeout(() => {
                this._typeAheadBuffer = '';
                this._typeAheadTimer = null;
            }, 500);

            const labels = elements.map((el) => el.textContent || '');
            const index = typeAheadIndex(labels, this._typeAheadBuffer, currentIndex);

            if (index >= 0) {
                elements[index]?.focus({ preventScroll: true });

                // On the closed bar the search moves the tab stop with it, or Tab would
                // leave from a trigger the reader is no longer on.
                if (! this.activeMenu) {
                    this.markRovingTrigger(elements[index]?.dataset?.wkMenubarTrigger);
                }
            }
        },

        /** Forget what was typed. Called wherever a menu stops being open. */
        _resetTypeAhead() {
            if (this._typeAheadTimer) {
                clearTimeout(this._typeAheadTimer);
                this._typeAheadTimer = null;
            }

            this._typeAheadBuffer = '';
        },
    };
}
