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
import {
    holdPageInert,
    inOverlayRoot,
    lockScroll as lockPageScroll,
    releasePageInert,
    unlockScroll as unlockPageScroll,
} from '../utils/overlay.js';
import { withOpenAlias } from '../utils/open-alias.js';

/**
 * @param {Object} config - Command palette configuration from Blade
 * @param {string|false|null} [config.hotkey] - Keyboard shortcut. Absent means 'cmd+k'; an
 *   empty string, `false` or `null` binds no shortcut at all.
 * @param {string|null} [config.name] - The palette's name. An event that carries
 *   `detail.name` reaches only the palette of that name; an event without one reaches every
 *   palette on the page.
 * @param {boolean} [config.lockScroll=true] - Whether to hold the page still while the palette
 *   is open, with the counted lock modal and drawer share. Set to false when the palette is
 *   embedded inside a scoped container (e.g. docs preview card) where a global body-scroll lock
 *   would be disruptive.
 */
export default function wirekitCommandPalette(config = {}) {
    const lockScroll = config.lockScroll !== false;

    // An empty, false or null shortcut binds nothing: a page that manages the key itself, or
    // carries a second palette, needs a way to leave it off. Absent keeps the default.
    const hotkey = config.hotkey === undefined
        ? 'cmd+k'
        : (typeof config.hotkey === 'string' ? config.hotkey.trim().toLowerCase() : '');

    const name = typeof config.name === 'string' && config.name !== '' ? config.name : null;

    // Whether an event is meant for this palette. Without a `detail.name` it is meant for every
    // palette on the page, which is how these events have always been read; with one, only for
    // the palette of that name.
    const addressed = (event) => {
        const target = event?.detail?.name;

        return target === undefined || target === null || target === '' || target === name;
    };

    return withOpenAlias({
        // Handles set while the component runs, declared so that they are its own: Alpine stores a
        // property no scope declares on the outermost scope around the component.
        _closeHandler: null,
        _stateHandler: null,

        isOpen: false,
        query: '',

        // Where a remote source stands: 'idle', 'loading' or 'error'. The host owns the source,
        // so the host reports it — see the `wirekit:command-palette-state` listener in init().
        remoteState: 'idle',

        // Whether the list holds at least one option. Read from the DOM, because the host owns
        // the result set and the palette only ever sees what was rendered into its list.
        _hasOptions: true,

        _activeIndex: -1,
        _trap: null,
        _observer: null,
        _lastSignature: '',
        _navCleanup: null,
        _hotkeyHandler: null,
        _showHandler: null,
        // Whether this palette holds one of the page's scroll locks. The lock is the counted
        // one modal and drawer share: the hotkey is registered on `document`, so the palette
        // opens from inside an open dialog as readily as from the page, and the page must stay
        // still until the last of them closes. The flag is what keeps a close from releasing a
        // lock this palette never took, which `lockScroll: false` and a second close both are.
        _holdsScrollLock: false,
        // Whether this palette holds one of the counted holds that keep the page behind a modal
        // overlay inert (see `holdPageInert` in utils/overlay.js). Taken once the trap has focus
        // in the panel, and only for a palette rendered in the overlay root that holds the page
        // still; given back before the trap returns focus to the page.
        _holdsPageInert: false,
        // The visual viewport listener while the palette is open; see _fitListToVisibleViewport().
        _onVisibleViewport: null,

        init() {
            // Parse hotkey (e.g. 'cmd+k') and register global listener
            if (hotkey !== '') {
                const parts = hotkey.split('+');
                const key = parts[parts.length - 1];
                const needsMeta = parts.includes('cmd') || parts.includes('meta');
                const needsCtrl = parts.includes('ctrl');

                this._hotkeyHandler = (e) => {
                    const metaMatch = needsMeta ? (e.metaKey || e.ctrlKey) : true;
                    const ctrlMatch = needsCtrl ? e.ctrlKey : true;

                    if (typeof e.key === 'string' && e.key.toLowerCase() === key && metaMatch && ctrlMatch) {
                        e.preventDefault();
                        this.toggle();
                    }
                };

                document.addEventListener('keydown', this._hotkeyHandler);
            }

            // Listen for programmatic open events — store reference for cleanup
            this._showHandler = (event) => {
                if (addressed(event)) {
                    this.show();
                }
            };
            window.addEventListener('wirekit-command-palette-show', this._showHandler);

            /*
             * And the closing half of the pair.
             *
             * `docs/components/command-palette.md` ships a two-button block whose second button
             * dispatches `wirekit-command-palette-close`. Without this listener a copied "Close"
             * would silently do nothing beside a sibling button that works, which reads as an
             * Alpine scoping problem in the developer's own application rather than a missing
             * listener here.
             *
             * The family carries both: modal, drawer and alert-dialog all answer `-show` and
             * `-close`, and `docs/overlays/events.md` names "`-show` / `-close` everywhere" as
             * the standard verb scheme.
             */
            this._closeHandler = (event) => {
                if (addressed(event)) {
                    this._forceClose();
                }
            };
            window.addEventListener('wirekit-command-palette-close', this._closeHandler);

            /*
             * The state of a remote source, reported by the host that queries it.
             *
             * "Nothing matched" and "the answer has not arrived" are different sentences, and
             * the palette cannot tell them apart on its own: it renders whatever the host puts
             * into its list and never sees the request. So the host says where the request
             * stands, and the palette shows the matching slot and hides the empty state while
             * it is not the truth.
             *
             * Addressed like `-show` and `-close`: a `detail.name` reaches only the palette of
             * that name, and a report without one reaches every palette on the page. Livewire's
             * `$this->dispatch(…, state: 'error', name: 'search')` arrives as `detail.state` and
             * `detail.name`, the same place an Alpine `$dispatch(…, { state, name })` puts them.
             */
            this._stateHandler = (event) => {
                if (addressed(event)) {
                    this._setRemoteState(event?.detail?.state);
                }
            };
            window.addEventListener('wirekit:command-palette-state', this._stateHandler);

            // SPA cleanup
            this._navCleanup = () => this._forceClose();
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });
        },

        destroy() {
            this._unwatchList();
            if (this._hotkeyHandler) {
                document.removeEventListener('keydown', this._hotkeyHandler);
                this._hotkeyHandler = null;
            }
            if (this._showHandler) {
                window.removeEventListener('wirekit-command-palette-show', this._showHandler);
                this._showHandler = null;
            }
            // Removed by the SAME reference it was added with; a re-bound arrow would make the
            // removal a silent no-op and leak a listener on every Livewire morph.
            if (this._closeHandler) {
                window.removeEventListener('wirekit-command-palette-close', this._closeHandler);
                this._closeHandler = null;
            }
            if (this._stateHandler) {
                window.removeEventListener('wirekit:command-palette-state', this._stateHandler);
                this._stateHandler = null;
            }
            if (this._navCleanup) {
                document.removeEventListener('livewire:navigating', this._navCleanup);
            }
            this._forceClose();
        },

        toggle() {
            this.isOpen ? this.close() : this.show();
        },

        /**
         * Show command palette and activate focus trap.
         */
        show() {
            if (this.isOpen) return;

            // Read before the panel shows, in the same tick as `isOpen`, so the first frame
            // already knows whether the list is empty. Waiting for the list observer would
            // flash the empty state over a list that has results.
            this._syncOptions();

            this.isOpen = true;
            this.query = '';
            this._activeIndex = -1;

            // A state left over from the last open describes a request nobody is waiting for.
            // Reset BEFORE the query goes out below: a host that answers it synchronously with
            // 'loading' must not have its report overwritten by this line.
            this.remoteState = 'idle';

            // Clearing `query` is a plain assignment, and an assignment fires no
            // `input` event — so the only dispatcher, the input's own handler,
            // never ran. A server-driven host therefore kept the LAST search
            // alive: type "strategy", close, reopen, and the field is empty while
            // the list still shows the results for a word nobody can see. Every
            // open began on a visibly wrong frame.
            //
            // Dispatched here rather than in close() on purpose. A host that
            // keeps its results between opens is a legitimate design, and
            // clearing on close would take that away; announcing the empty query
            // at the moment the empty field appears is the one point where the
            // two cannot disagree.
            this.emitQuery();

            // Hold the page still, as a modal does. `overflow: hidden` alone does not do that
            // on iOS, where a swipe over the backdrop still drags the page; the shared lock pins
            // the body at its scroll position and puts it back on the last release. Skipped
            // when the palette is embedded inside a scoped container where a global lock would
            // be disruptive — see the `lockScroll` prop on the Blade component.
            if (lockScroll && ! this._holdsScrollLock) {
                this._holdsScrollLock = true;
                lockPageScroll();
            }

            this.$nextTick(() => {
                // The tick runs a task later, and the palette can close inside it. `close()`
                // found no trap and no hold to give back then, so whatever this callback took
                // now would stay taken: a trap on a hidden panel, and a page left inert.
                if (! this.isOpen || this._trap) {
                    return;
                }

                const panel = this.$refs.panel;
                if (panel) {
                    this._trap = createFocusTrap(panel, {
                        escapeDeactivates: true,
                        onDeactivate: () => this._closeFromTrap(),
                        allowOutsideClick: true,
                        initialFocus: () => this.$refs.input,
                    });
                    this._trap.activate();

                    if (lockScroll && ! this._holdsPageInert && inOverlayRoot(panel)) {
                        this._holdsPageInert = holdPageInert();
                    }
                }

                // The list only exists once the panel has rendered.
                this._watchList();
                this._watchVisibleViewport();
            });
        },

        /**
         * Close triggered by focus-trap deactivation (ESC).
         */
        _closeFromTrap() {
            if (!this.isOpen) return;
            this.isOpen = false;
            this._trap = null;
            this._releasePageInert();
            this._releaseScroll();
            this._unwatchVisibleViewport();
        },

        /**
         * Give back this palette's hold on the page behind it, and only its own, before the trap
         * returns focus there: an inert element cannot take focus.
         */
        _releasePageInert() {
            if (! this._holdsPageInert) return;

            this._holdsPageInert = false;
            releasePageInert();
        },

        /**
         * Give back this palette's scroll lock, and only its own.
         *
         * The count decides when the page moves again: a dialog underneath that is still open
         * keeps it still, and one that closed first has already given its lock back. Doing
         * nothing when this palette holds none is the load-bearing half, because a release
         * that runs unconditionally takes a lock that belongs to somebody else.
         */
        _releaseScroll() {
            if (! this._holdsScrollLock) return;

            this._holdsScrollLock = false;
            unlockPageScroll();
        },

        /**
         * Close command palette.
         */
        close() {
            if (!this.isOpen) return;
            this.isOpen = false;
            this._releasePageInert();

            if (this._trap) {
                this._trap.deactivate();
                this._trap = null;
            }

            this._releaseScroll();
            this._unwatchVisibleViewport();
        },

        /**
         * Force close — SPA navigation.
         */
        _forceClose() {
            this.isOpen = false;
            this._releasePageInert();

            if (this._trap) {
                this._trap.deactivate();
                this._trap = null;
            }

            this._releaseScroll();
            this._unwatchVisibleViewport();
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
            // Escape belongs to whichever overlay is on top, and while the palette
            // stands open that is the palette.
            //
            // Modal, drawer and alert-dialog listen for Escape on `window` and gate
            // themselves on being topmost — a flag they can only lower for an overlay
            // that announced itself. The palette does not, and its hotkey is bound to
            // `document`, so it opens from inside an open dialog: one Escape then
            // closed the palette AND the dialog underneath it, because the dialog
            // never learned anything had opened above it.
            //
            // Stopping the event here is the same guarantee the stack gives, made
            // where the palette can make it. The trap has usually deactivated by now
            // (it listens on `document` in the capture phase, so it runs before this
            // bubble handler), which is why `close()` is the fallback for a palette
            // whose trap never activated rather than a second close.
            if (event.key === 'Escape') {
                event.stopPropagation();
                this.close();

                return;
            }

            // The list keys belong to the combobox input, and only to it. This handler sits on
            // the panel, so it also hears keys meant for a button in the footer or the filter
            // row; acting on those would swallow the button's Enter and activate the highlighted
            // option instead, and move the list on an arrow key meant for a radio group. Escape
            // above stays panel-wide on purpose — it closes the palette from anywhere inside it.
            if (event.target && event.target !== this.$refs?.input) return;

            const items = this._getItems();

            // Enter while an input method composes text confirms the composition, not a choice.
            if (event.key === 'Enter' && (event.isComposing || event.keyCode === 229)) return;

            // Enter with no option highlighted belongs to the host. A search submits its query to
            // a full results page, takes its single hit, or jumps where the query points; the
            // palette itself never activates a row the reader did not highlight, so it only says
            // that Enter was pressed, and with which query. Before the empty-list check, because
            // a query that matched nothing can still be submitted. The default is prevented where
            // it always was, with options in the list.
            if (event.key === 'Enter' && ! (this._activeIndex >= 0 && items[this._activeIndex])) {
                if (items.length) {
                    event.preventDefault();
                }

                this.emitSubmit();

                return;
            }

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
                    // A highlighted option: the branch above took every other Enter.
                    event.preventDefault();
                    items[this._activeIndex].click();
                    break;

                // Home and End move through the list only once an option is highlighted. Before
                // that the reader is still editing the query, and the keys belong to the text
                // field, as the combobox pattern leaves them: the caret goes to the start or the
                // end of the query.
                case 'Home':
                    if (this._activeIndex < 0) {
                        break;
                    }

                    event.preventDefault();
                    this._activeIndex = 0;
                    this._scrollToActive(items);
                    break;

                case 'End':
                    if (this._activeIndex < 0) {
                        break;
                    }

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
                const active = i === this._activeIndex;

                el.setAttribute('data-active', active ? 'true' : 'false');
                el.setAttribute('aria-selected', active ? 'true' : 'false');

                // The ARIA state beside the styling hook, deliberately in the same write.
                // `data-active` drove the highlight and nothing published it: the rows carry
                // `role="option"`, and `aria-selected` is the state ARIA defines for that
                // role. The list is a single-select listbox driven by `aria-activedescendant`,
                // so the active option IS the selected one.
                //
                // Set HERE rather than only in the template because this method is what runs
                // again after a Livewire morph — a static attribute alone would survive the
                // morph and then describe whichever row was active before it.
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
         * Resetting `_activeIndex` only in show() is correct only while the list is
         * rendered once. With server-side search the list is rebuilt on every
         * keystroke, and a surviving index would point into the NEW results, so
         * Enter would activate whatever now sat at that position: the user arrows
         * to the third hit, types one more character, and confirms something they
         * never looked at.
         *
         * A changed result set therefore clears the selection; an unchanged one
         * that merely re-rendered gets its highlight painted back on.
         */
        _watchList() {
            // show() calls this on every open, and the observer from the last open is still
            // attached — nothing disconnects it on close. Dropping it first keeps one observer
            // per palette, however often it opens.
            this._unwatchList();

            const list = this.$refs.list;
            if (!list || typeof MutationObserver === 'undefined') return;

            this._lastSignature = this._itemsSignature();
            this._syncOptions();

            this._observer = new MutationObserver(() => {
                const signature = this._itemsSignature();

                if (signature !== this._lastSignature) {
                    this._lastSignature = signature;
                    this._activeIndex = -1;
                }

                this._syncOptions();
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
         * Keep the end of the list above an on-screen keyboard.
         *
         * A keyboard leaves the layout viewport as it is and shrinks the visual one, so a list
         * sized in viewport units or rem keeps its height and its last options sit under the
         * keyboard, where scrolling the list cannot bring them, because the end of the scroll
         * range is under the keyboard too. While the visible viewport is shorter than the layout
         * viewport, the list's height token is capped at the room between the list's top and the
         * visible bottom, less whatever the panel shows below the list. Otherwise the token is
         * left as the page set it, so the height without a keyboard does not change.
         */
        _fitListToVisibleViewport() {
            const viewport = typeof window !== 'undefined' ? window.visualViewport : null;
            const list = this.$refs?.list;

            if (! viewport || ! list || typeof list.style?.setProperty !== 'function'
                || typeof list.getBoundingClientRect !== 'function' || typeof getComputedStyle !== 'function') {
                return;
            }

            list.style.removeProperty('--wk-command-palette-list-max-height');

            if (viewport.height >= window.innerHeight - 1) {
                return;
            }

            const box = list.getBoundingClientRect();
            const panel = this.$refs.panel;
            const below = panel && typeof panel.getBoundingClientRect === 'function'
                ? Math.max(0, panel.getBoundingClientRect().bottom - box.bottom)
                : 0;
            const room = Math.floor(viewport.offsetTop + viewport.height - box.top - below - 8);
            // Read after the removal above, so this is the value the page set, or the default.
            const token = getComputedStyle(list).getPropertyValue('--wk-command-palette-list-max-height').trim() || '18rem';

            // A floor of a few rows: a list squeezed to nothing hides every option at once.
            list.style.setProperty('--wk-command-palette-list-max-height', `min(${token}, ${Math.max(room, 96)}px)`);
        },

        _watchVisibleViewport() {
            this._unwatchVisibleViewport();

            const viewport = typeof window !== 'undefined' ? window.visualViewport : null;

            if (! viewport || typeof viewport.addEventListener !== 'function') {
                return;
            }

            this._onVisibleViewport = () => this._fitListToVisibleViewport();
            // Passive: the handler only measures, and a non-passive scroll listener makes the
            // browser wait for it before it scrolls.
            viewport.addEventListener('resize', this._onVisibleViewport, { passive: true });
            viewport.addEventListener('scroll', this._onVisibleViewport, { passive: true });
            this._fitListToVisibleViewport();
        },

        /** Idempotent: every close path calls it, and the cap goes with the listener. */
        _unwatchVisibleViewport() {
            if (! this._onVisibleViewport) {
                return;
            }

            const viewport = typeof window !== 'undefined' ? window.visualViewport : null;

            viewport?.removeEventListener?.('resize', this._onVisibleViewport);
            viewport?.removeEventListener?.('scroll', this._onVisibleViewport);
            this._onVisibleViewport = null;
            this.$refs?.list?.style?.removeProperty?.('--wk-command-palette-list-max-height');
        },

        /**
         * Close once an option has been chosen, by click or by Enter (which clicks it).
         *
         * Bound on the list, so it runs AFTER the option's own handlers: the click reaches the
         * option first and bubbles up, which keeps a `wire:click` or `x-on:click` on the item
         * in charge of what the choice does. A disabled option, or a click on a group heading,
         * chose nothing and leaves the palette open.
         */
        closeAfterChoice(event) {
            const option = event?.target?.closest?.('[role="option"]');

            if (!option || option.getAttribute('aria-disabled') === 'true') return;

            this.close();
        },

        /**
         * Read whether the list holds any option at all.
         *
         * A disabled option counts: it is a result the reader can see, only not one they can
         * pick, and "nothing matched" beneath it would be false.
         */
        _syncOptions() {
            const list = this.$refs?.list;

            this._hasOptions = Boolean(list && list.querySelector('[role="option"]'));
        },

        /**
         * Take the host's report on its remote source.
         *
         * Anything but the two named states means idle, a typo included. The alternative, keeping
         * the previous state, would leave a palette showing "loading" for good after a host sent
         * a word it thought meant done.
         */
        _setRemoteState(state) {
            this.remoteState = state === 'loading' || state === 'error' ? state : 'idle';
        },

        /**
         * The three bindings the template reads. Getters rather than inline comparisons, because
         * an operator in a binding is outside the grammar of Alpine's CSP build, where the
         * binding would silently never evaluate.
         */
        get isLoading() {
            return this.remoteState === 'loading';
        },

        get hasError() {
            return this.remoteState === 'error';
        },

        // Empty means the list holds no option AND no request is pending or failed. While one
        // is, "nothing matched" is not yet known, or not true.
        get showsEmpty() {
            return this.remoteState === 'idle' && !this._hasOptions;
        },

        /**
         * Emit the query for a host driving server-side search.
         *
         * Dispatched from the component ROOT, not from the input. The input lives
         * inside a teleporting `<template>`, so at runtime it is a child of
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
         * Tell the host that Enter was pressed with no option highlighted, and with which query.
         *
         * Dispatched from the root for the reason `emitQuery()` gives. The palette stays open: the
         * host closes it, navigates, or leaves it as it is.
         */
        emitSubmit() {
            this.$root.dispatchEvent(new CustomEvent('wirekit:command-palette-submit', {
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
    });
}
