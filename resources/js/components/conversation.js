/**
 * WireKit Conversation Alpine Component.
 *
 * Stick-to-bottom transcript scroller for chat / streaming message lists. It
 * solves the cluster of scroll problems a plain overflow container gets wrong
 * in a live conversation:
 *
 *   1. FOLLOW-OUTPUT — pins the viewport to the bottom as messages (or streamed
 *      tokens) arrive, but ONLY while the reader is already at the bottom. The
 *      moment they scroll up to read back, the pin releases so their position
 *      is never yanked away mid-sentence.
 *   2. ANCHOR-PRESERVE — when older history is prepended, the reader's position
 *      is kept (the scrollHeight delta is added back to scrollTop) instead of
 *      the content jumping under them.
 *   3. JUMP-TO-LATEST — an unread counter while scrolled away, cleared on
 *      return to the bottom.
 *   4. REACHED-TOP — dispatches `conversation-reached-top` so the app can load
 *      older history (wire:click / Livewire dispatch — no JS needed).
 *
 * DOM-source-agnostic by design: the observers watch the DOM itself, so
 * `wire:poll`, `wire:stream` and Echo broadcasts all drive it with no extra
 * wiring — the Livewire-native property React-only equivalents cannot offer.
 *
 * Cleanup contract:
 *   - _mutationObserver (MutationObserver) — disconnected in destroy()
 *   - _resizeObserver (ResizeObserver) — disconnected in destroy()
 *   - _onScrollBound (scroll listener on the viewport) — removed in destroy()
 *   Every callback null-guards `_viewport` first: browser-queued observer and
 *   scroll callbacks can fire AFTER destroy() has torn the component down.
 */
export default function wirekitConversation(config = {}) {
    return {
        // True while the reader is parked at (or within `threshold` of) the
        // bottom. Drives follow-output and the jump-to-latest affordance.
        atBottom: true,
        // Messages that arrived while the reader was scrolled away.
        unread: 0,
        // px tolerance for "at bottom" — absorbs sub-pixel + zoom rounding so a
        // 0.5px gap does not silently disable follow-output.
        threshold: config.threshold ?? 24,
        // Translated accessible names for the jump-to-latest control, handed in
        // from Blade. They live in the Alpine scope rather than inside the
        // attribute binding because a Blade `::`-escaped attribute is passed
        // through VERBATIM — an `@js()` inside it is never compiled, so the
        // binding would carry the literal directive text, fail to evaluate, and
        // leave the button with no accessible name at all.
        jumpLabel: config.jumpLabel ?? 'Jump to latest',
        jumpLabelCount: config.jumpLabelCount ?? 'Jump to latest, :count new',

        _mutationObserver: null,
        _resizeObserver: null,
        _onScrollBound: null,
        _viewport: null,
        // Scroll anchor: the child currently at the top of the viewport, plus
        // its offset from the scroll position. Re-captured on every scroll and
        // restored after content changes — this is what distinguishes a history
        // PREPEND (the anchor moves down → give the height back) from a new
        // message APPENDED below (the anchor does not move → count it unread).
        // A naive "a prepend is coming" flag cannot tell those apart and
        // mis-scrolls on the first append after the reader reaches the top.
        _anchorEl: null,
        _anchorDelta: 0,
        _anchorOffsetTop: 0,

        init() {
            // The scrollable viewport — x-ref="viewport" when the component
            // wraps chrome (jump button) around the scroller; else the root.
            this._viewport = this.$refs.viewport || this.$el;

            // Bind ONCE: addEventListener/removeEventListener identity-match on
            // the function reference, so an inline bind would make destroy()'s
            // removal a silent no-op and leak a listener per Livewire morph.
            this._onScrollBound = this._onScroll.bind(this);
            this._viewport.addEventListener('scroll', this._onScrollBound, { passive: true });

            // childList = a new message row; characterData = streamed tokens
            // appended into an existing bubble.
            this._mutationObserver = new MutationObserver(() => this._onContentChange());
            this._mutationObserver.observe(this._viewport, {
                childList: true,
                subtree: true,
                characterData: true,
            });

            // A streaming bubble can grow (wrap to a new line) without mutating
            // the viewport itself — size changes must drive follow-output too.
            this._resizeObserver = new ResizeObserver(() => this._onContentChange());
            this._resizeObserver.observe(this._viewport);

            this.scrollToBottom(false);
            this._captureAnchor();
        },

        destroy() {
            this._mutationObserver?.disconnect();
            this._mutationObserver = null;
            this._resizeObserver?.disconnect();
            this._resizeObserver = null;
            if (this._onScrollBound && this._viewport) {
                this._viewport.removeEventListener('scroll', this._onScrollBound);
            }
            this._onScrollBound = null;
            this._viewport = null;
        },

        /**
         * Is the reader parked at the bottom (within threshold)?
         */
        isAtBottom() {
            if (!this._viewport) {
                return true;
            }
            const el = this._viewport;

            return el.scrollHeight - el.scrollTop - el.clientHeight <= this.threshold;
        },

        /**
         * Scroll to the newest message and clear the unread counter.
         */
        scrollToBottom(smooth = true) {
            if (!this._viewport) {
                return;
            }
            this._viewport.scrollTo({
                top: this._viewport.scrollHeight,
                behavior: smooth && !this._prefersReducedMotion() ? 'smooth' : 'auto',
            });
            this.atBottom = true;
            this.unread = 0;
        },

        /**
         * Jump to a specific message by id and flash it. Powers reply-to-quote
         * deep links.
         */
        scrollToMessage(id, smooth = true) {
            if (!this._viewport) {
                return;
            }
            const target = this._viewport.querySelector(`[data-wk-message-id="${id}"]`);
            if (!target) {
                return;
            }
            target.scrollIntoView({
                behavior: smooth && !this._prefersReducedMotion() ? 'smooth' : 'auto',
                block: 'center',
            });
        },

        _prefersReducedMotion() {
            return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
        },

        /**
         * Remember which child sits at the top of the viewport and how far it
         * is from the scroll position, so we can put it back after the DOM
         * changes under us.
         */
        _captureAnchor() {
            if (!this._viewport) {
                return;
            }
            const top = this._viewport.scrollTop;
            this._anchorEl = null;
            this._anchorDelta = 0;
            this._anchorOffsetTop = 0;

            for (const child of this._viewport.children) {
                if (child.offsetTop + child.offsetHeight > top) {
                    this._anchorEl = child;
                    this._anchorDelta = child.offsetTop - top;
                    this._anchorOffsetTop = child.offsetTop;
                    break;
                }
            }
        },

        _onScroll() {
            // Null-guard: a queued scroll callback can land after destroy().
            if (!this._viewport) {
                return;
            }
            this.atBottom = this.isAtBottom();
            if (this.atBottom) {
                this.unread = 0;
            }
            this._captureAnchor();

            // Near the top → ask the app for older history. Purely advisory: the
            // anchor below handles the prepend correctly whether or not the app
            // responds.
            if (this._viewport.scrollTop <= this.threshold) {
                this.$dispatch('conversation-reached-top');
            }
        },

        _onContentChange() {
            // Null-guard: observer callbacks are browser-queued and can fire
            // after destroy() nulled the viewport.
            if (!this._viewport) {
                return;
            }

            if (this.atBottom) {
                // Follow-output: stay pinned to the newest content.
                this.scrollToBottom(false);
                this._captureAnchor();

                return;
            }

            // Scrolled away: the reader's position is sacred. Restore the
            // anchor, then decide what actually happened by how far the anchor
            // moved.
            if (!this._anchorEl || !this._anchorEl.isConnected) {
                this._captureAnchor();

                return;
            }

            const addedAbove = this._anchorEl.offsetTop - this._anchorOffsetTop;

            // Give back exactly the height that landed ABOVE the anchor, so the
            // message the reader was on does not move a pixel.
            this._viewport.scrollTop = this._anchorEl.offsetTop - this._anchorDelta;

            if (addedAbove <= 0) {
                // Nothing was inserted above → this is new content further down.
                this.unread += 1;
            }

            this._captureAnchor();
        },
    };
}
