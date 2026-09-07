/**
 * WireKit Toast Alpine Component.
 *
 * Manages a reactive queue of toast notifications dispatched via
 * `$dispatch('wirekit-toast', { ... })`. Supports auto-dismiss,
 * pause-on-hover, and swipe-to-dismiss.
 */
export default function wirekitToast(config = {}) {
    // The element focus came FROM when the reader first tabbed into the region,
    // kept for the case where dismissing the last toast leaves nothing inside to
    // hand focus to.
    //
    // A closure variable and not a property on purpose: Alpine makes the
    // returned object reactive, and a DOM node stored on a reactive object comes
    // back out as a Proxy. Calling `.focus()` through that proxy is not the same
    // call, and this is the one place where the value has to stay the node
    // itself.
    let focusOrigin = null;

    // The region element itself, captured at init.
    //
    // ⚠️ IT EXISTS BECAUSE `$root` IS RESOLVED WHEN IT IS READ, AND ONE OF THE
    // READS HAPPENS AFTER THE ELEMENT IT RESOLVES FROM IS GONE.
    //
    // `remove()` runs from the dismiss button's own `@click`, so Alpine anchors the
    // magics to that BUTTON. `_focusSuccessor` reads `$root` synchronously, while
    // the button is still in the document, and gets the region — that half was
    // always fine. `_restoreFocus` reads it inside `$nextTick`, by which time the
    // button has gone with its card, `closest('[x-data]')` from a detached node
    // finds nothing, and the lookup that should hand focus to the next toast never
    // happens. Focus stays where the browser dropped it: `<body>`.
    //
    // The file already knew the button gets detached — the note in `_restoreFocus`
    // says "by the time the tick lands that button is detached anyway" — and reached
    // for `$root` in the same breath. That is the second time this path has been
    // dead while everything around it stayed green.
    //
    // Measured, not reasoned. `ToastKeyboardFocusTest` fails on exactly the two
    // neighbor cases when that one lookup goes back to `$root`, and passes on all
    // four with this handle; the `{element}` branch — an origin outside the region
    // — is unaffected either way, which is the asymmetry that named the cause.
    //
    // A closure variable for the same reason as `focusOrigin` above: a DOM node
    // stored on the reactive object comes back out as a Proxy.
    let regionEl = null;

    /**
     * The region, resolved ONCE while something is still attached to resolve it
     * from, and kept.
     *
     * Every caller below reaches this synchronously — from `init()`, from a
     * `focusin` on the region, or from `remove()` before the splice — so `$root`
     * still answers. The one read that happens after the fact takes the cached
     * value instead of asking again.
     *
     * @param {{$root?: Element, $el?: Element}} ctx
     * @returns {Element|null}
     */
    const region = (ctx) => {
        if (! regionEl && ctx) {
            regionEl = ctx.$root ?? null;
        }

        return regionEl;
    };

    return {
        /** @type {Array<{id: number, title: string, message: string, variant: string, _timer: number|null}>} */
        toasts: [],

        /**
         * The two persistent announcement slots. They are bound to live regions
         * that exist from first render and start empty — see _announce() for why
         * the region cannot be the toast element itself.
         */
        politeMessage: '',
        assertiveMessage: '',

        /** Max visible toasts (oldest removed when exceeded) */
        _max: config.max ?? 5,

        /** Default auto-dismiss duration in ms */
        _duration: config.duration ?? 5000,

        /** Auto-incrementing toast ID */
        _nextId: 1,

        init() {
            // The earliest and most certain moment: init runs on the element that
            // carries the x-data, so `$el` and `$root` are the same element here.
            regionEl = this.$root ?? this.$el ?? null;

            // Scoped event name — when name is set, only this region receives
            // events dispatched to 'wirekit-toast-{name}'. Without a name,
            // the region listens on the global 'wirekit-toast' event.
            this._eventName = config.name
                ? `wirekit-toast-${config.name}`
                : 'wirekit-toast';

            // Optional DOM-containment scope. When set to a CSS selector,
            // only events whose dispatching element matches `closest(scope)`
            // are handled — useful for "per-section toast surfaces" where
            // multiple toast regions share the same event name but each
            // owns a portion of the DOM tree (e.g. an embedded live-preview
            // pattern, or a real app with per-card local toast queues).
            //
            // Falsy / missing → no containment filter, every dispatched
            // event is handled (current behavior, fully back-compat).
            this._scope = typeof config.scope === 'string' && config.scope !== ''
                ? config.scope
                : null;

            this._handler = (event) => {
                // Containment filter: if a scope is set, the dispatching
                // element must be inside an ancestor matching the selector.
                // event.target is the element on which $dispatch was called;
                // closest() walks up to find a match (or returns null).
                if (this._scope) {
                    const target = event.target;
                    if (! target || typeof target.closest !== 'function' || ! target.closest(this._scope)) {
                        return;
                    }
                }
                this.add(event.detail);
            };
            window.addEventListener(this._eventName, this._handler);

            // Livewire hook: bridge session flash toasts on navigate
            this._livewireHandler = () => {
                // Livewire injects flash data as a custom event after navigation
                // This hook will be consumed by the Blade component if needed
            };
        },

        destroy() {
            window.removeEventListener(this._eventName, this._handler);
            // Clear all pending timers
            this.toasts.forEach((t) => {
                if (t._timer) clearTimeout(t._timer);
            });
        },

        /**
         * Add a new toast to the queue.
         * @param {Object} detail - Toast payload
         * @param {string} [detail.title] - Bold heading
         * @param {string} [detail.message] - Body text
         * @param {string} [detail.variant='info'] - info|success|warning|danger
         * @param {number} [detail.duration] - Override auto-dismiss ms (0 = persistent)
         */
        /**
         * Write a toast's text into the persistent live region.
         *
         * The regions live OUTSIDE the x-for and start empty, because a live region
         * has to exist before the text it announces: an element created together
         * with its message is a new node, not a region that changed, and assistive
         * technology stays silent. Every toast in this component announced nothing
         * until this existed.
         *
         * Two slots, because urgency is a property of the region and cannot be
         * switched per message — an aria-live value that changes on an existing
         * region is not reliably re-read. `danger` goes to the assertive slot,
         * everything else to the polite one.
         */
        _announce(toast) {
            const slot = toast.variant === 'danger' ? 'assertiveMessage' : 'politeMessage';

            // Cleared first so two identical messages in a row still register as a
            // change. Without this a repeated "Saved" is announced once.
            this[slot] = '';

            queueMicrotask(() => {
                this[slot] = [toast.title, toast.message].filter(Boolean).join('. ');
            });
        },

        add(detail) {
            const id = this._nextId++;
            const duration = detail.duration !== undefined ? detail.duration : this._duration;

            const toast = {
                id,
                title: detail.title || null,
                message: detail.message || '',
                variant: detail.variant || 'info',
                _timer: null,
                _paused: false,
                _remaining: duration,
                _started: Date.now(),
            };

            // Auto-dismiss after duration (0 = no auto-dismiss)
            if (duration > 0) {
                toast._timer = setTimeout(() => this.remove(id), duration);
            }

            this.toasts.push(toast);
            this._announce(toast);

            // Enforce max queue length — remove oldest.
            //
            // ⚠️ THE EVICTION IS THE SECOND WAY A TOAST LEAVES THE DOM, and it takes
            // focus with it exactly as the dismiss button does — the mechanism and the
            // WCAG 2.4.3 requirement are written out on `remove()` below. This path is
            // the worse of the two, because the reader did not press anything: the jump
            // to the top of the document is unprompted.
            //
            // It is also not a corner case. `focusin` pauses a focused toast's timer, so
            // the toast a keyboard reader is parked in is the one that never auto-
            // dismisses — its neighbors expire around it, it ages into slot 0, and the
            // next notification evicts precisely it.
            //
            // Routed through the same two methods rather than a second implementation.
            // `_focusSuccessor` returns null unless focus really sits inside the leaving
            // toast, so a pointer dismissal, an auto-dismiss and a programmatic push all
            // behave exactly as they did.
            let successor = null;

            while (this.toasts.length > this._max) {
                const oldest = this.toasts[0];

                // Read BEFORE the shift: afterwards `toasts[0]` is a different toast and
                // the lookup would measure the wrong one. The first non-null answer is
                // kept, because a second pass looks at a toast focus was never inside and
                // returns null — which would otherwise erase the landing found here.
                successor = successor ?? this._focusSuccessor(0);

                if (oldest._timer) clearTimeout(oldest._timer);
                this.toasts.shift();
            }

            if (successor) {
                this._restoreFocus(successor);
            }
        },

        /**
         * Record where focus entered the region from.
         *
         * `focusin` bubbles, so this fires for every focus move inside the stack
         * too — the `relatedTarget` test keeps only the move that CROSSED the
         * boundary, which is the one worth returning to.
         *
         * The captured region rather than `$el` for the boundary test, for the same
         * reason the lookups below use it: the region IS the boundary, and `$el`
         * only happens to be the region while this listener stays on the `x-data`
         * element. Moving it onto a card would silently turn every intra-stack
         * focus move into a "crossing" and overwrite the origin.
         *
         * It reads `regionEl` and not `$root` for the reason at the top of this
         * file — this one happens to work, because the listener sits ON the
         * `x-data` element, and "happens to work" is what the other two also did
         * until they were reached from a handler one level down.
         *
         * @param {FocusEvent} event
         */
        noteFocusOrigin(event) {
            const from = event && event.relatedTarget;

            const root = region(this);

            if (from && root && typeof root.contains === 'function' && ! root.contains(from)) {
                focusOrigin = from;
            }
        },

        /**
         * Remove a toast by ID.
         *
         * The dismiss button lives inside the `x-for`, so removing a toast
         * removes the button that was just pressed — and a keyboard user who
         * tabbed to "Dismiss notification" and hit Enter was thrown to the top of
         * the document, because focus on a detached element falls to `<body>`.
         * With a stack of toasts that means tabbing through the whole page again
         * for each one. WCAG 2.4.3 asks for the opposite: focus follows the
         * reader's place in the sequence.
         *
         * Only the KEYBOARD path is touched. Focus is looked at first and left
         * alone unless it sits inside the toast being removed — so an auto-
         * dismiss, a hover dismiss, and a programmatic `remove()` all behave
         * exactly as before. (The timer path cannot fire on a focused toast
         * anyway: `focusin` pauses it.)
         *
         * @param {number} id
         */
        remove(id) {
            const idx = this.toasts.findIndex((t) => t.id === id);

            if (idx === -1) {
                return;
            }

            const successor = this._focusSuccessor(idx);
            const toast = this.toasts[idx];

            if (toast._timer) clearTimeout(toast._timer);
            this.toasts.splice(idx, 1);

            if (successor) {
                this._restoreFocus(successor);
            }
        },

        /**
         * Where focus should land once the toast at `idx` is gone — or null when
         * focus is not inside it and nothing should move.
         *
         * The next toast first: it is the one that slides into the position the
         * reader was just looking at, so dismissing a stack top to bottom keeps
         * the finger on the same key. Then the previous one, for the bottom of
         * the stack. Then, with the region emptied, whatever the reader was on
         * before they tabbed in.
         *
         * ⚠️ The scope is `$root`, not `$el`. `remove()` is reached from the dismiss
         * button's own click handler, so Alpine binds `$el` to that BUTTON — and a
         * button contains no `[data-wk-toast-id]` card, so the lookup returned null,
         * this method returned null, and focus was never restored. The whole
         * keyboard path was dead while every assertion around it stayed green.
         * `$root` is the region whichever descendant dispatched the event.
         *
         * @param {number} idx
         * @returns {{toastId: number}|{element: Element}|null}
         */
        _focusSuccessor(idx) {
            const root = region(this);

            if (typeof document === 'undefined' || ! root || typeof root.querySelector !== 'function') {
                return null;
            }

            const active = document.activeElement;
            const leaving = root.querySelector(`[data-wk-toast-id="${this.toasts[idx].id}"]`);

            if (! active || ! leaving || typeof leaving.contains !== 'function' || ! leaving.contains(active)) {
                return null;
            }

            const neighbor = this.toasts[idx + 1] || this.toasts[idx - 1] || null;

            return neighbor ? { toastId: neighbor.id } : { element: focusOrigin };
        },

        /**
         * Move focus to the successor once Alpine has re-rendered the stack.
         *
         * Resolved after the tick rather than before it: the neighbor's node is
         * the same element either way, but the removed toast is still on screen
         * during its leave transition, and focusing while it is there is what
         * takes focus off it.
         *
         * ⚠️ A LATER LANDING WAS BUILT AND THEN TAKEN BACK OUT, and that is worth a
         * line because the obvious next fix is to put it back. The suspicion was
         * that the tick is too early — that the leaving card is removed by its own
         * transition, outside Alpine's tick, and takes focus with it. A second
         * landing via `MutationObserver`, once the card really detaches, was
         * written and measured: the four browser cases pass identically without
         * it, because the card is already detached by the tick and, when it is
         * not, focus has moved to the successor before the removal reaches it.
         * Shipping it anyway would have been a mechanism nothing exercises,
         * standing where the real cause was.
         *
         * The real cause was `$root` — see the note at the top of this file.
         *
         * @param {{toastId: number}|{element: Element}} successor
         */
        _restoreFocus(successor) {
            const land = () => {
                // `$root` for the same reason as above: this runs off the dismiss
                // button's handler, and by the time the tick lands that button is
                // detached anyway — searching from it would find nothing twice over.
                const target = successor.toastId !== undefined
                    ? (regionEl ? regionEl.querySelector(`[data-wk-toast-id="${successor.toastId}"] button`) : null)
                    : successor.element;

                // `isConnected === false` is the case worth skipping: an origin
                // element that has since been removed from the page. Focusing it
                // would be the same silent drop to <body> this exists to prevent,
                // and leaving focus where it is at least keeps it on screen.
                if (target && target.isConnected !== false && typeof target.focus === 'function') {
                    target.focus();
                }
            };

            if (typeof this.$nextTick === 'function') {
                this.$nextTick(land);
            } else {
                land();
            }
        },

        /**
         * Pause auto-dismiss on hover (preserves remaining time).
         * @param {number} id
         */
        pause(id) {
            const toast = this.toasts.find((t) => t.id === id);
            if (toast && toast._timer) {
                clearTimeout(toast._timer);
                toast._timer = null;
                toast._paused = true;
                toast._remaining = Math.max(0, toast._remaining - (Date.now() - toast._started));
            }
        },

        /**
         * Resume auto-dismiss after hover out.
         * @param {number} id
         */
        resume(id) {
            const toast = this.toasts.find((t) => t.id === id);
            if (toast && toast._paused && toast._remaining > 0) {
                toast._paused = false;
                toast._started = Date.now();
                toast._timer = setTimeout(() => this.remove(id), toast._remaining);
            }
        },

    };
}
