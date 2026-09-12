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
import { frameCoalesce } from '../utils/frame-coalesce.js';
import { prefersReducedMotion } from '../utils/motion.js';
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

        // Coalescer for the scroll handler. See init() for why the work moved
        // off the event and onto the frame.
        _scrollFrame: null,

        // Coalescer for the post-write anchor re-capture on the follow-output path.
        _anchorFrame: null,

        init() {
            // The scrollable viewport — x-ref="viewport" when the component
            // wraps chrome (jump button) around the scroller; else the root.
            this._viewport = this.$refs.viewport || this.$el;

            // Bind ONCE: addEventListener/removeEventListener identity-match on
            // the function reference, so an inline bind would make destroy()'s
            // removal a silent no-op and leak a listener per Livewire morph.
            //
            // Coalesced to one run a frame. `_onScroll` ends in `_captureAnchor()`,
            // which walks EVERY child of the viewport reading two layout properties
            // each — so in a long conversation the cost per scroll event grows with
            // the transcript, and scroll events arrive faster than frames do. The
            // work is idempotent over a burst (it answers "where are we now?"), so
            // the last event of a frame is the only one whose answer is wanted.
            this._scrollFrame = frameCoalesce(() => this._onScroll());
            this._onScrollBound = () => this._scrollFrame.schedule();
            this._viewport.addEventListener('scroll', this._onScrollBound, { passive: true });

            // childList = a new message row; characterData = streamed tokens
            // appended into an existing bubble.
            /*
             * The records are READ, not discarded. Every path into this callback used to
             * count as a new message, and only one of them is one.
             *
             * `characterData` fires once per streamed token, so a single 200-token reply
             * counted 200 unread messages. The ResizeObserver below fires on any size change
             * — a window resize, a phone rotating, the soft keyboard opening — and counted
             * one more each time. The badge on the jump-to-latest button is what a reader
             * uses to decide whether to scroll back down, and it was reporting the length of
             * the reply and the number of times they had turned their phone.
             *
             * A new message is a childList mutation that ADDS an element. Everything else
             * still runs the anchor restore and the follow-output, which is what those
             * events are genuinely for.
             */
            this._mutationObserver = new MutationObserver((records) => {
                this._onContentChange(records.some(
                    (record) => record.type === 'childList'
                        && [...record.addedNodes].some((node) => node.nodeType === 1)
                ));
            });
            this._mutationObserver.observe(this._viewport, {
                childList: true,
                subtree: true,
                characterData: true,
            });

            // A streaming bubble can grow (wrap to a new line) without mutating
            // the viewport itself — size changes must drive follow-output too.
            // `false` — a size change is never a new message. A streaming bubble wrapping to
            // a new line has to drive follow-output, and that is all it is.
            this._resizeObserver = new ResizeObserver(() => this._onContentChange(false));
            this._resizeObserver.observe(this._viewport);

            this.scrollToBottom(false);
            this._captureAnchor();
        },

        destroy() {
            this._scrollFrame?.cancel();
            this._scrollFrame = null;
            this._anchorFrame?.cancel();
            this._anchorFrame = null;
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
            return prefersReducedMotion();
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

        /**
         * @param {boolean} [isNewMessage=false] Whether this change ADDED a message row, as
         *   opposed to streaming tokens into an existing one or the viewport resizing. Only
         *   a real addition may move the unread counter.
         */
        _onContentChange(isNewMessage = false) {
            // Null-guard: observer callbacks are browser-queued and can fire
            // after destroy() nulled the viewport.
            if (!this._viewport) {
                return;
            }

            if (this.atBottom) {
                // Follow-output: stay pinned to the newest content.
                //
                // The re-capture is deferred rather than run inline. `scrollToBottom`
                // WRITES scrollTop and `_captureAnchor` READS offsetTop off every
                // child — back to back that is a write between two reads, which makes
                // the browser recompute layout synchronously before the second one can
                // answer, on every streamed token. A frame later the same walk reads a
                // layout the browser has already settled.
                this.scrollToBottom(false);
                (this._anchorFrame ??= frameCoalesce(() => this._captureAnchor())).schedule();

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

            if (addedAbove <= 0 && isNewMessage) {
                // Nothing was inserted above → this is new content further down. And it has
                // to BE a message: the anchor holding still is equally true of a token
                // streaming in below and of the viewport changing size.
                this.unread += 1;
            }

            this._captureAnchor();
        },
    };
}
