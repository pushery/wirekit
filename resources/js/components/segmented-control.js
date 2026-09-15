import { observeServerValue, WK_SERVER_VALUE_ATTRIBUTE } from '../utils/server-value.js';
import { safeObserver } from '../utils/safe-observer.js';

/**
 * Segmented control — a radiogroup that behaves like the native one it imitates.
 *
 * The handlers moved out of the template because none of the three parsed under
 * Alpine's CSP build: the click handler was three statements and a `new`, and
 * both arrow handlers used optional chaining. Under a strict
 * Content-Security-Policy the segments rendered, took focus, and did nothing.
 *
 * Moving them here also closed the gap between what the component did and what
 * its documentation promised, which was wider than the CSP problem:
 *
 *   - ArrowDown / ArrowUp / Home / End were documented and never bound at all.
 *   - Neither arrow wrapped. On the last segment ArrowRight reached for a
 *     nextElementSibling that does not exist and stopped; on the FIRST segment
 *     ArrowLeft reached backwards into the hidden input that carries the form
 *     value — not focusable, so the key simply died there. Native radio groups
 *     wrap in both directions, and so does the WAI-ARIA radio pattern.
 *
 * Navigation is written against the segments themselves rather than sibling
 * traversal, which is what made the hidden input reachable in the first place.
 *
 * Lifecycle resources held on `this`, every one released in destroy():
 *   - _stopServerSync (the server-value observer) — stopped.
 *   - _trackResizeObserver (ResizeObserver, on the track and on every segment) —
 *     disconnected, and null-guarded inside its callback against a notification
 *     queued before teardown.
 *   - _edgeHintObserver (IntersectionObserver over the two edge sentinels, through
 *     safeObserver) — stopped, which also turns a delivery already queued into a no-op.
 *
 * @param {Object} config
 * @param {string} config.selected  the option value selected at render time
 */
export default function wirekitSegmentedControl(config = {}) {
    return {
        // Mirrors the component's `disabled` prop. See select().
        disabled: config.disabled === true,

        selected: config.selected != null ? String(config.selected) : '',

        _trackResizeObserver: null,

        // Whether options sit past the start / end edge of the visible track. They drive the
        // two edge shadows in the template; see _observeEdgeHints().
        startHint: false,
        endHint: false,

        _edgeHintObserver: null,

        init() {
            // Seed from the server attribute when the caller passed nothing.
            //
            // The seed used to be interpolated into `x-data`, which looked
            // harmless because Alpine reads that attribute once. A Livewire morph
            // REWRITES it, though, and Alpine re-initializes on the change — so
            // every round trip replaced the scope, and an effect queued against
            // the old one flushed afterwards and wrote the old value last. That
            // is invisible on an outward click (old and new agree) and shows up
            // only when a reader returns to a value they already had.
            //
            // Reading it here instead keeps the attribute byte-identical across
            // renders, so the scope survives and observeServerValue is the single
            // path a server-side change travels — which is what its own docblock
            // already claimed. The config argument still wins when given, so a
            // caller constructing this factory by hand is unaffected.
            //
            // `$root` is capability-checked, not assumed. Alpine hands a real element
            // here, but the ESM harness constructs each factory with a deliberately
            // barren stub — `test-segmented-control.mjs` passes `{ querySelectorAll }` and
            // `test-server-value-seed.mjs` a lone `getAttribute`, each on purpose — and a
            // factory that requires more than it uses turns that into a TypeError at init.
            // Measured on 2026-08-16: one of 63 ESM scripts, red in CI and invisible to the
            // PHP suite, which does not run them.
            if (config.selected == null) {
                const seed = typeof this.$root?.getAttribute === 'function'
                    ? this.$root.getAttribute(WK_SERVER_VALUE_ATTRIBUTE)
                    : null;

                if (seed != null) {
                    this.selected = String(seed);
                }
            }

            // The hidden input is what a form (or wire:model) actually submits.
            // It starts empty in the markup, so the initial selection has to be
            // written into it before anything reads it.
            //
            // Silently: no `input` event. Announcing a change that the user did
            // not make would cost a Livewire round trip on every page load, for
            // a value the server already had.
            this._writeHiddenInput();

            // A selected option past the edge of a scrolling track is a choice the reader cannot
            // see was made, so the track scrolls it into view — whenever a size changes, not
            // once at init. Measured: at init the track did not overflow yet. Livewire starts
            // Alpine before the page's stylesheets apply, so the track still measured its full
            // content width, and a reveal written for that moment returned without scrolling;
            // the same call made a second later scrolled by 120px. The track's size changes when
            // the stylesheet applies and again whenever its column does, and a notification is
            // what arrives at exactly those moments.
            //
            // The segments are observed as well, because a label can widen while the track,
            // capped by its column, keeps its box. The web font does exactly that, and in more
            // than one batch: the regular weight settled `document.fonts.ready`, which this code
            // used to wait for, and the medium weight arrived after it and left the selected
            // segment past the edge. Invisible on a machine whose fallback face is metric-matched
            // to the web font, 2px out on one where it is not. A segment's own box changes with
            // its label, whatever changed the label.
            if (typeof ResizeObserver === 'function' && typeof this.$root?.getBoundingClientRect === 'function') {
                this._trackResizeObserver = new ResizeObserver(() => {
                    // Null-guard: a notification queued before destroy() can still arrive after it.
                    if (!this._trackResizeObserver) {
                        return;
                    }

                    this._revealSelected();
                });
                this._trackResizeObserver.observe(this.$root);

                if (typeof this.$root.querySelectorAll === 'function') {
                    this.$root.querySelectorAll('[role="radio"]').forEach((segment) => this._trackResizeObserver.observe(segment));
                }
            }

            this._observeEdgeHints();

            // A value the server changed has to reach the segments. Alpine read
            // `selected` once, here, and will not look at the seed again — so
            // without this the control keeps showing whatever it was born with
            // while the form submits something else entirely. Measured: the
            // hidden input said `max`, the checked segment said Basic.
            //
            // Guarded on a real change so an unrelated round trip cannot undo a
            // choice the reader just made: every morph rewrites the attribute,
            // including the ones that carry the same value back.
            this._stopServerSync = observeServerValue(this.$root, (value) => {
                if (value === this.selected) {
                    return;
                }

                this.selected = value;
                this._writeHiddenInput();
                this._scheduleReveal();
            });
        },

        destroy() {
            // The observer outlives the scope otherwise, and fires into it.
            this._stopServerSync?.();

            this._trackResizeObserver?.disconnect();
            this._trackResizeObserver = null;

            this._edgeHintObserver?.stop();
            this._edgeHintObserver = null;
        },

        /**
         * Select a segment and tell the form about it.
         *
         * The `input` event is dispatched by hand because assigning `.value`
         * fires nothing — without it wire:model on the hidden input would never
         * see a change, which is the whole reason the input exists.
         */
        select(value) {
            // A disabled group stays FOCUSABLE — a natively disabled radiogroup vanishes
            // from the tab order and a reader never learns the setting exists — so the
            // refusal has to happen here instead of at the browser level.
            if (this.disabled) {
                return;
            }

            this.selected = String(value);
            this._notify();
        },

        /**
         * Tell the form what `selected` now holds.
         *
         * Split out of select() because the optimistic layer writes `selected`
         * itself — on the flip AND on the rollback — and has to be able to run
         * the same sync afterwards. Without it a rolled-back control would show
         * the old segment while the form still submitted the new one.
         */
        _notify() {
            const input = this._writeHiddenInput();

            // Assigning `.value` fires nothing, so the event is dispatched by
            // hand — without it wire:model on the hidden input would never see
            // the change, which is the whole reason the input exists.
            input?.dispatchEvent(new Event('input', { bubbles: true }));
        },

        /**
         * Move focus AND selection, wrapping at both ends.
         *
         * Selection follows focus here because that is the radio pattern: an
         * arrow key on a radio group checks as it moves. `.click()` is used
         * rather than calling select() directly so a segment stays the single
         * place that knows its own value.
         */
        focusNext(current) {
            this._focusAt(this._indexOf(current) + 1);
        },

        focusPrevious(current) {
            this._focusAt(this._indexOf(current) - 1);
        },

        focusFirst() {
            this._focusAt(0);
        },

        focusLast() {
            this._focusAt(this._segments().length - 1);
        },

        /**
         * The segments, in document order. Never the hidden input.
         *
         * `$root`, not `$el`. Every caller is reached from a keydown handler on
         * a BUTTON, and Alpine binds `$el` to the element the handler sits on —
         * so `$el` here is one segment, which contains no segments, and the
         * lookup would come back empty with the navigation silently dead.
         * `$root` is the x-data element regardless of which child dispatched.
         */
        _segments() {
            return Array.from(this.$root.querySelectorAll('[role="radio"]:not([disabled])'));
        },

        _indexOf(el) {
            // A missing element resolves to -1, which _focusAt turns into the
            // last segment — the same place ArrowLeft from the first one goes.
            return this._segments().indexOf(el);
        },

        _focusAt(index) {
            const segments = this._segments();

            if (segments.length === 0) {
                return;
            }

            // Modulo twice: JS keeps the sign of the dividend, so -1 % 3 is -1
            // rather than 2, and the backwards wrap would land nowhere.
            const target = segments[((index % segments.length) + segments.length) % segments.length];

            target.focus();
            target.click();
        },

        /**
         * Say at each edge whether options continue past it.
         *
         * The track scrolls on its own, and on a phone that leaves a bar that can start
         * mid-word with nothing to say options lie before it: there is no scrollbar until a
         * drag is already underway. `table` and `data-table` answer the same question with
         * a one-pixel sentinel at each inline edge of the scroll content and an observer over
         * them: a sentinel outside the visible track means content continues that way. No
         * scroll listener, and no direction arithmetic, because an intersection does not
         * care which way the writing runs.
         *
         * Through `safeObserver`, so a delivery queued before teardown finds the observer
         * stopped instead of writing into a scope that is gone. Capability-checked like the
         * resize observer: the ESM harness has neither the observer nor the sentinels.
         */
        _observeEdgeHints() {
            const start = this.$refs?.startSentinel;
            const end = this.$refs?.endSentinel;

            if (typeof IntersectionObserver !== 'function' || !start || !end) {
                return;
            }

            this._edgeHintObserver = safeObserver(IntersectionObserver, (entries) => {
                for (const entry of entries) {
                    if (entry.target === start) {
                        this.startHint = !entry.isIntersecting;
                    } else if (entry.target === end) {
                        this.endHint = !entry.isIntersecting;
                    }
                }
            }, { root: this.$root });

            this._edgeHintObserver.observe(start);
            this._edgeHintObserver.observe(end);
        },

        /**
         * Reveal the selected segment after Alpine's next flush.
         *
         * A selection the server changed reaches `aria-checked` in that flush, so a
         * measurement taken before it would reveal the previous segment. No-op where the
         * magic is missing, as in the ESM harness.
         */
        _scheduleReveal() {
            if (typeof this.$nextTick === 'function') {
                this.$nextTick(() => this._revealSelected());
            }
        },

        /**
         * Scroll the selected segment into the visible part of the track.
         *
         * Only the track moves. `scrollIntoView()` would scroll every scrollable ancestor as
         * well, the page included, and a page that jumps sideways on load is the defect the
         * track's own scroll exists to prevent. The arithmetic uses rectangles rather than
         * `offsetLeft`, so it does not depend on the writing direction: `scrollLeft` counts
         * from the other edge in a right-to-left track, and a rectangle delta does not care.
         */
        _revealSelected() {
            const track = this.$root;

            // Capability-checked like init(): a harness stub has no layout, and nothing to reveal.
            if (typeof track?.getBoundingClientRect !== 'function' || typeof track.querySelector !== 'function') {
                return;
            }

            // A track that does not scroll has nothing outside its visible part.
            if (track.scrollWidth <= track.clientWidth) {
                return;
            }

            const segment = track.querySelector('[role="radio"][aria-checked="true"]');

            if (!segment) {
                return;
            }

            const trackBox = track.getBoundingClientRect();
            const box = segment.getBoundingClientRect();
            // The track's scroll padding is the margin a segment scrolled into view keeps from the
            // edge, so its focus ring is not cut off there. The reveal keeps the same margin.
            const inset = typeof getComputedStyle === 'function'
                ? parseFloat(getComputedStyle(track).scrollPaddingLeft) || 0
                : 0;

            if (box.left - inset < trackBox.left) {
                track.scrollLeft -= trackBox.left - (box.left - inset);
            } else if (box.right + inset > trackBox.right) {
                track.scrollLeft += box.right + inset - trackBox.right;
            }
        },

        /** Writes the value and returns the input, or null when there is none. */
        _writeHiddenInput() {
            const input = this.$refs.hiddenInput;

            if (input) {
                input.value = this.selected;
            }

            return input || null;
        },
    };
}
