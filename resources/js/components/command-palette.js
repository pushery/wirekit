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
import { withOpenAlias } from '../utils/open-alias.js';

/**
 * @param {Object} config - Command palette configuration from Blade
 * @param {string} config.hotkey - Keyboard shortcut (default: 'cmd+k')
 * @param {boolean} [config.lockScroll=true] - Whether to set `document.body.style.overflow = 'hidden'`
 *   while the palette is open. Set to false when the palette is embedded inside a scoped
 *   container (e.g. docs preview card) where a global body-scroll lock would be disruptive.
 */
export default function wirekitCommandPalette(config = {}) {
    const lockScroll = config.lockScroll !== false;

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
        // The body's own inline `overflow` as it stood the moment this palette
        // locked it — null while the palette holds no lock at all.
        //
        // The close paths used to write the empty string unconditionally, which
        // is only correct when nothing else was already holding the page still.
        // The hotkey is registered on `document`, so the palette opens from
        // inside an open modal or drawer as readily as from the page: closing it
        // then handed the empty string to a body that a still-open dialog had
        // set to `hidden`, and the page scrolled behind that dialog. With
        // `lockScroll: false` it was worse — the palette released a lock it had
        // never taken.
        _bodyOverflow: null,

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

            /*
             * And the closing half of the pair.
             *
             * ⚠️ ONLY `-show` WAS REGISTERED, while `docs/components/command-palette.md` ships a
             * two-button block whose second button dispatches `wirekit-command-palette-close`.
             * A developer copied the pair, wired "Close" into their UI, and it silently did
             * nothing — beside a sibling button that worked, which reads as an Alpine scoping
             * problem in their own application rather than a missing listener here.
             *
             * `docs/overlays/events.md` described the component as show-only, so the two pages
             * contradicted each other. This resolves it toward the family rather than away from
             * it: modal, drawer and alert-dialog all carry `-show` / `-close`, and that same
             * page names "`-show` / `-close` everywhere" as the standard verb scheme.
             */
            this._closeHandler = () => this._forceClose();
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
             * Page-global like `-show` and `-close`, which read no payload either: a page
             * carries one palette. Livewire's `$this->dispatch(…, state: 'error')` arrives as
             * `detail.state`, the same place an Alpine `$dispatch(…, { state })` puts it.
             */
            this._stateHandler = (event) => this._setRemoteState(event?.detail?.state);
            window.addEventListener('wirekit:command-palette-state', this._stateHandler);

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

            // Lock body scroll (standard modal behavior). Skipped when the palette
            // is embedded inside a scoped container where global body-scroll lock
            // would be disruptive — see `lockScroll` prop on the Blade component.
            //
            // Snapshot first, restore on close: the value being replaced may be a
            // lock somebody else is still holding. See `_bodyOverflow` above.
            if (lockScroll) {
                this._bodyOverflow = document.body.style.overflow;
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
            if (!this.isOpen) return;
            this.isOpen = false;
            this._trap = null;
            this._releaseScroll();
        },

        /**
         * Hand the body's `overflow` back exactly as it was found.
         *
         * Doing nothing when this palette never locked is the load-bearing half:
         * a release that runs unconditionally is indistinguishable from one that
         * releases somebody else's lock, and the second is what the page behind
         * an open dialog notices.
         */
        _releaseScroll() {
            if (this._bodyOverflow === null) return;

            // …and the other half: hand back only a lock this palette is still the
            // one holding. A dialog underneath can close FIRST and release its own
            // lock while the palette is open, and the snapshot taken at open time
            // then says `hidden` about a page nothing covers any more — writing it
            // back would leave the reader unable to scroll with no dialog in sight.
            // Anything other than the value written here belongs to somebody else.
            if (document.body.style.overflow === 'hidden') {
                document.body.style.overflow = this._bodyOverflow;
            }

            this._bodyOverflow = null;
        },

        /**
         * Close command palette.
         */
        close() {
            if (!this.isOpen) return;
            this.isOpen = false;

            if (this._trap) {
                this._trap.deactivate();
                this._trap = null;
            }

            this._releaseScroll();
        },

        /**
         * Force close — SPA navigation.
         */
        _forceClose() {
            this.isOpen = false;

            if (this._trap) {
                this._trap.deactivate();
                this._trap = null;
            }

            this._releaseScroll();
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
         * Get the id of the currently active item for aria-activedescendant.
         */
        get activeDescendant() {
            if (this._activeIndex < 0) return null;
            const items = this._getItems();
            return items[this._activeIndex]?.id || null;
        },
    });
}
