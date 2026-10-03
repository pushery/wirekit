/**
 * WireKit Range Slider Alpine Component.
 *
 * Dual-handle slider for selecting a value range.
 * Supports keyboard navigation, pointer drag, a click on the track, and step increments.
 *
 * @param {Object} config
 * @param {number} config.min - Minimum track value
 * @param {number} config.max - Maximum track value
 * @param {number} config.step - Step increment
 * @param {number} config.minValue - Initial minimum selection
 * @param {number} config.maxValue - Initial maximum selection
 * @param {string} config.name - Input name for form submission
 * @param {string} config.labelId - The visible label, whose click focuses the first thumb
 *
 * Lifecycle resources held on `this`:
 *   - _resizes (ResizeObserver) on the track and both badges, disconnected in destroy()
 *     and null-guarded in its callback against a notification that arrives after it.
 *   - _dragMove, _dragEnd (document listeners) during a drag, released in destroy().
 *   - _measureFrame (a frame-coalesced measurement), canceled in destroy().
 *   - _modelEvents — `change` and `blur` on both hidden inputs for `wire:model.change`,
 *     `wire:model.lazy` and `wire:model.blur`, which listen on those inputs alone
 *     (utils/model-events.js): `change` at the commit boundary, a key press or the end of a drag,
 *     and `blur` when the reader leaves the slider. Disposed in destroy().
 *   - _label, _onLabelClick — a click listener on the visible label, which sits outside the
 *     root, released in destroy().
 */
import { frameCoalesce } from '../utils/frame-coalesce.js';
import { watchModelEvents } from '../utils/model-events.js';

/**
 * The most places after the point that any of `numbers` carries.
 *
 * Binary floating point holds no tenth exactly, so 0.1 + 0.2 is 0.30000000000000004. The numbers
 * a slider is configured with say how many places its values can need, and rounding to that many
 * gives the value a decimal step means. `String()` writes very small numbers with an exponent
 * (`1e-7`), which is counted as places too.
 *
 * @param {...number} numbers
 * @returns {number}
 */
function decimalPlaces(...numbers) {
    return Math.max(0, ...numbers.map((number) => {
        const [mantissa, exponent = '0'] = String(number).toLowerCase().split('e');
        const fraction = (mantissa.split('.')[1] || '').replace(/0+$/, '');

        return Math.min(100, Math.max(0, fraction.length - Number(exponent)));
    }));
}

export default function wirekitRangeSlider(config = {}) {
    return {
        // Handles set while the component runs, declared so that they are its own: Alpine stores a
        // property no scope declares on the outermost scope around the component.
        _dragRect: null,
        _measureFrame: null,
        _resizes: null,
        _modelEvents: null,
        _label: null,
        _onLabelClick: null,

        minVal: config.minValue ?? config.min ?? 0,
        maxVal: config.maxValue ?? config.max ?? 100,
        _min: config.min ?? 0,
        _max: config.max ?? 100,
        _step: config.step ?? 1,

        /*
         * The places a value on this slider can carry: as many as the bounds, the step or a
         * starting value has. Every value written is rounded to them (`_toPlaces()`), so a step
         * of 0.1 lands on 0.3 and not on its binary neighbor.
         */
        _places: decimalPlaces(config.min ?? 0, config.max ?? 100, config.step ?? 1, config.minValue ?? 0, config.maxValue ?? 0),
        _dragging: null,
        /*
         * The three document-level drag listeners, held so a teardown can reach them.
         *
         * Closures inside `_startDrag`, removed by the `onUp` that ends the gesture, are
         * fine for every drag that ENDS. A component torn down mid-gesture (a Livewire
         * morph, a conditional render flipping, an SPA navigation) never gets that
         * pointerup: three handlers would stay on `document`, each closing over a dead
         * scope, and the next pointer move anywhere on the page would run `_onDrag`
         * against it.
         *
         * Declared here with the other state rather than assigned inside the method,
         * because a handle that only ever appears inside a method is one nobody reading
         * the teardown knows to look for.
         */
        _dragMove: null,
        _dragEnd: null,

        /*
         * Whether the press behind the next click on the track began on the track itself.
         *
         * A click is dispatched at the nearest element that both the press and the release were
         * over. A handle dragged until it stops against the other one leaves the pointer over the
         * track, so the click that ends that drag reaches the track too, and acting on it would
         * move a handle the reader never pointed at. A press on a handle bubbles to the track as
         * well, so every press inside the track sets this, and only a click it allowed counts.
         */
        _trackPressed: false,

        // True when the two thumbs are close enough that their individual value
        // badges would overlap — the blade then shows ONE merged "min – max"
        // badge instead. Set by _measureBubbles() from the badges' real boxes.
        _merged: false,

        /**
         * Gates the badge fade so it never runs on the FIRST measurement.
         *
         * `_merged` starts false and the first measurement may immediately flip
         * it, which — with the fade applied unconditionally — animated the
         * merged badge in from nothing on page load. Two costs: the reader sees
         * a blink where the layout was never in doubt, and for the duration of
         * that fade the text sits at a PARTIAL opacity. A near-black at partial
         * opacity over white is mid-gray, so an accessibility scan that samples
         * mid-fade reads ~4.3:1 against a token that is 15:1 when settled — that
         * is what reddened CI on the storefront blueprint while every local run
         * passed: the scan raced the fade.
         */
        _ready: false,

        /**
         * Value-text overrides, keyed by value.
         *
         * What a thumb ANNOUNCES. The map wins where it has an entry, otherwise
         * the number is the value. Live, so dragging and arrow keys update the
         * announcement as the thumb moves — the same contract the single-value
         * slider keeps.
         */
        marksMap: config.marksMap && typeof config.marksMap === 'object' ? config.marksMap : {},

        /** The geometry, built here — a template literal cannot be parsed
         *  by Alpine's CSP build, and these are all one expression each. */
        rangeFillStyle() {
            return `left: ${this.minPercent}%; width: ${this.maxPercent - this.minPercent}%`;
        },

        minThumbStyle() { return `left: ${this.minPercent}%`; },
        maxThumbStyle() { return `left: ${this.maxPercent}%`; },

        minBadgeStyle() {
            return `left: ${this.minPercent}%; transform: translateX(-${this.minPercent}%)`;
        },

        maxBadgeStyle() {
            return `left: ${this.maxPercent}%; transform: translateX(-${this.maxPercent}%)`;
        },

        mergedBadgeStyle() {
            return `left: ${(this.minPercent + this.maxPercent) / 2}%`;
        },

        valueTextFor(value) {
            const mapped = this.marksMap[String(value)];

            return mapped === undefined || mapped === null ? String(value) : mapped;
        },

        /** The merged badge's text, and the live region's sentence. */
        mergedBadgeText() {
            return `${this.valueTextFor(this.minVal)} – ${this.valueTextFor(this.maxVal)}`;
        },

        get minPercent() {
            return ((this.minVal - this._min) / (this._max - this._min)) * 100;
        },

        get maxPercent() {
            return ((this.maxVal - this._min) / (this._max - this._min)) * 100;
        },

        /**
         * The highest value the LOWER handle can hold — what it announces as
         * `aria-valuemax`.
         *
         * Not the track's `_max`: `_setMin` clamps at `maxVal - _step`, and so
         * does every path that reaches it (arrows, page keys, End, pointer drag).
         * The two thumbs never meet, so the lower one stops one step short of the
         * upper one, and a reader told otherwise hears a control that appears to
         * hang at a value the announcement never acknowledges.
         *
         * The bound is EXACT rather than an upper estimate — the clamp is a
         * `Math.min` against this very number, so the handle lands on it whether
         * or not it sits on the step grid.
         *
         * Floored at `_min` because nothing clamps the initial values at mount:
         * a `maxValue` set nearer the floor than one step would otherwise report
         * a maximum below the minimum, which is an invalid slider rather than a
         * merely inaccurate one.
         *
         * A getter rather than the arithmetic in the directive: Alpine's CSP
         * build parses a restricted grammar, so the expressions this component
         * binds are built here — the same reason the geometry above lives in JS.
         */
        get minThumbCeiling() {
            return this._toPlaces(Math.max(this._min, this.maxVal - this._step));
        },

        /** The lowest value the UPPER handle can hold — the mirror of `minThumbCeiling`. */
        get maxThumbFloor() {
            return this._toPlaces(Math.min(this._max, this.minVal + this._step));
        },

        /**
         * Step the minimum value by direction.
         *
         * `direction` is a MULTIPLIER on the step, not a sign: the arrow keys
         * pass ±1 and the page keys pass ±10, so PageUp/PageDown reuse this path
         * instead of introducing a second clamp that could drift from it.
         */
        stepMin(direction) {
            this._setMin(this.minVal + (direction * this._step));
        },

        /**
         * Step the maximum value by direction — the mirror of `stepMin`.
         */
        stepMax(direction) {
            this._setMax(this.maxVal + (direction * this._step));
        },

        /**
         * Jump the minimum handle to one end of ITS OWN travel (Home / End).
         *
         * The travel a handle owns is not the whole track. The lower handle's
         * ceiling is the upper handle — one step below it, because the pair may
         * never meet — which is the same bound `stepMin` clamps to, so End here
         * lands exactly where holding ArrowRight would.
         *
         * `toUpperEnd` is true for End and false for Home. A boolean rather than
         * two methods because both ends run the identical write path, and the
         * single argument keeps the directive a plain call, which is what Alpine's
         * CSP build can parse.
         */
        jumpMin(toUpperEnd) {
            this._setMin(toUpperEnd ? this.maxVal - this._step : this._min);
        },

        /**
         * Jump the maximum handle to one end of its own travel — the mirror of
         * `jumpMin`. Home lands one step above the lower handle; End lands on the
         * track maximum.
         */
        jumpMax(toUpperEnd) {
            this._setMax(toUpperEnd ? this._max : this.minVal + this._step);
        },

        /**
         * The one place `minVal` is written by a keyboard gesture or a click on the track.
         *
         * Every key path funnels through here so the clamp, the gesture mark, the
         * hidden-input dispatch and the commit boundary are stated once. A key
         * press IS a completed decision, so it commits immediately — unlike a
         * drag, which commits at pointerup. A click on the track is one as well.
         */
        _setMin(value) {
            this._markGesture();
            this.minVal = this._toPlaces(Math.max(this._min, Math.min(value, this.maxVal - this._step)));
            this._dispatchInputEvent();
            this._commit();
        },

        /** The one place `maxVal` is written by a keyboard gesture or a click on the track. */
        _setMax(value) {
            this._markGesture();
            this.maxVal = this._toPlaces(Math.min(this._max, Math.max(value, this.minVal + this._step)));
            this._dispatchInputEvent();
            this._commit();
        },

        /**
         * Tell the optimistic layer the gesture starts HERE, before the pair
         * moves.
         *
         * Both commit paths change `minVal`/`maxVal` before `_commit()` runs —
         * a keypress steps first, a drag moves the thumb for the whole gesture.
         * Without this the layer would snapshot the value it is about to be
         * handed, and a refused range would roll back onto itself: the thumb
         * stays where the user left it and the refusal is silent.
         *
         * Looked up rather than assumed, like `run` below: without a layer this
         * component behaves exactly as it did before, down to the byte.
         */
        _markGesture() {
            if (typeof this.mark === 'function') {
                this.mark();
            }
        },

        /**
         * Hand the pair to the optimistic layer, if one is nested here.
         *
         * The commit boundary. A keypress calls this at once, because one
         * press IS a completed decision; a drag calls it only at pointerup, not
         * per frame. There is no timer either way.
         *
         * `run` is looked up rather than assumed: without the layer this
         * component behaves exactly as before, down to the byte, which is the
         * property the whole opt-in shape rests on.
         */
        _commit() {
            // The same boundary is the inputs' `change`, as a native range input fires it.
            this._modelEvents?.commit();

            if (typeof this.run === 'function') {
                this.run([this.minVal, this.maxVal]);
            }
        },

        /**
         * A press inside the track: whether it began on the track itself or on a handle.
         */
        pressTrack(event) {
            this._trackPressed = ! event?.target?.closest?.('[role="slider"]');
        },

        /**
         * A click on the track brings the nearer handle to that point.
         *
         * Dragging was the only way a pointer could set a value, and WCAG 2.2 SC 2.5.7 asks for a
         * single-pointer way that does not drag. A native range input moves its thumb to a click
         * on its track; with two handles the nearer one comes, on the same step grid and inside
         * the same bounds a drag keeps. It takes the focus, so an arrow key fine-tunes from there,
         * and one click is one completed decision, so it commits at once, like a key press.
         */
        clickTrack(event) {
            if (! this._trackPressed) return;

            this._trackPressed = false;

            const track = this.$refs.track;
            if (!track) return;

            const value = this._pointerValue(event.clientX, track.getBoundingClientRect());
            const lower = Math.abs(value - this.minVal) <= Math.abs(value - this.maxVal);

            if (lower) {
                this._setMin(this._snapToStep(value));
            } else {
                this._setMax(this._snapToStep(value));
            }

            // The handles in document order, the lower one first: the role is what the keyboard
            // bindings sit on, so it is the element that has to end up focused.
            const handles = typeof track.querySelectorAll === 'function' ? track.querySelectorAll('[role="slider"]') : [];
            handles[lower ? 0 : 1]?.focus?.({ preventScroll: true });
        },

        /**
         * Start drag on a thumb.
         */
        startDrag(handle, event) {
            event.preventDefault();

            // Canceling pointerdown also cancels the FOCUS it would have given
            // this element — moving focus to the pressed control is part of that
            // default action, and a `div[tabindex="0"]` has no other route to it.
            // Without this line the thumb the reader just grabbed is not the
            // focused element: the focus ring never appears, and the ArrowRight
            // pressed to fine-tune the value scrolls the page instead of moving
            // the handle, which is exactly the pointer-then-keyboard sequence the
            // component documents.
            //
            // `preventScroll` because the thumb is already under the pointer —
            // there is nothing to scroll into view, and scrolling here would drag
            // the track out from under the gesture that is starting.
            event.currentTarget?.focus?.({ preventScroll: true });

            // The gesture starts at pointerdown, not at the pointerup that
            // commits it — the pair moves for every frame in between.
            this._markGesture();
            this._dragging = handle;

            // Cache the track's rect ONCE per drag — the track is stationary while
            // dragging, so re-reading getBoundingClientRect on every pointermove (a
            // hot path) would force a needless layout read per frame. Mirrors the
            // color-picker drag optimization.
            this._dragRect = this.$refs.track ? this.$refs.track.getBoundingClientRect() : null;

            // A second pointerdown before the first gesture ended would add a second set
            // of listeners over the first, and only the newest pair would ever be removed.
            this._releaseDragListeners();

            this._dragMove = (e) => this._onDrag(e);
            this._dragEnd = () => {
                // The commit boundary for a drag. pointercancel lands here too,
                // and that is not an oversight: a canceled drag still leaves
                // the thumb somewhere, and a value on screen the server was
                // never told is the one state this layer exists to prevent.
                this._commit();

                this._dragging = null;
                this._dragRect = null;
                this._releaseDragListeners();
            };

            // Passive — onDrag only computes the value from pointer position;
            // it never calls preventDefault, so it must not block scroll.
            document.addEventListener('pointermove', this._dragMove, { passive: true });
            document.addEventListener('pointerup', this._dragEnd);
            document.addEventListener('pointercancel', this._dragEnd);
        },

        /**
         * Drop the document-level drag listeners, from wherever the gesture ended.
         *
         * Idempotent on purpose: it is called by the pointerup that ends a normal drag, by
         * a second pointerdown, and by `destroy()`, and only one of those is guaranteed to
         * happen.
         */
        _releaseDragListeners() {
            if (this._dragMove) {
                document.removeEventListener('pointermove', this._dragMove);
                this._dragMove = null;
            }

            if (this._dragEnd) {
                document.removeEventListener('pointerup', this._dragEnd);
                document.removeEventListener('pointercancel', this._dragEnd);
                this._dragEnd = null;
            }
        },

        /**
         * Watch the track and both badges.
         *
         * Whether the badges merge compares their widths with the track's, and `x-effect` runs
         * that comparison again only when a value moves. A web font that lands after the first
         * measurement widens the badges, and a column that narrows shortens the track, with no
         * value moving in either case, so the badges overlapped until the reader touched a thumb.
         * The individual badges stay in layout when merged (opacity, not display), so a merge
         * changes no size watched here and cannot answer itself.
         */
        init() {
            // First, because the returns below are about measuring, and a slider bound with
            // `.change` or `.blur` needs these events wherever it is drawn.
            this._modelEvents = watchModelEvents(this.$root, () => [this.$refs?.minInput, this.$refs?.maxInput]);

            // Before the returns below too: the label is not about measuring.
            this._wireLabel();

            if (typeof ResizeObserver !== 'function') {
                return;
            }

            const watched = [this.$refs?.track, this.$refs?.minBubble, this.$refs?.maxBubble].filter(Boolean);

            if (watched.length === 0) {
                return;
            }

            this._resizes = new ResizeObserver(() => {
                // A notification queued before destroy() can still arrive after it.
                if (! this._resizes) {
                    return;
                }

                this.remeasure();
            });
            watched.forEach((el) => this._resizes.observe(el));
        },

        /**
         * A click on the visible label focuses the first thumb that can move.
         *
         * A label names an element by `for` and focuses it on a click, and a thumb is a
         * `div[role="slider"]`, which `for` cannot name. So the label names the group by
         * reference instead, and the click is wired here: this component's own label, or a
         * field's that took its place. Both sit outside the root.
         */
        _wireLabel() {
            const label = config.labelId && typeof document !== 'undefined' && typeof document.getElementById === 'function'
                ? document.getElementById(config.labelId)
                : null;

            if (!label) return;

            this._label = label;
            this._onLabelClick = () => {
                // Torn down between the click and this callback.
                if (!this._label) return;

                this.$root?.querySelector('[role="slider"]:not([aria-disabled="true"])')?.focus();
            };
            label.addEventListener('click', this._onLabelClick);
        },

        /**
         * Alpine's teardown hook. This component had none.
         *
         * Without it a drag interrupted by a morph or a navigation left three handlers on
         * `document` writing into a scope nobody owns any more — and `_onDrag` reads
         * `this.$refs.track`, so the leak is not merely idle: every pointer move on the
         * page ran a measurement against a detached element.
         *
         * `_commit()` is deliberately NOT called here. Teardown is not a commit boundary
         * — the component is going away, and sending a value the reader never let go of
         * would be the optimistic layer's worst case: a write nobody asked for, on a
         * surface that no longer exists to roll it back.
         */
        destroy() {
            this._modelEvents?.dispose();
            this._modelEvents = null;
            this._label?.removeEventListener('click', this._onLabelClick);
            this._label = null;
            this._onLabelClick = null;
            this._releaseDragListeners();
            this._resizes?.disconnect();
            this._resizes = null;
            this._measureFrame?.cancel();
            this._measureFrame = null;
            this._dragging = null;
            this._dragRect = null;
        },

        /**
         * Handle drag movement — calculate value from pointer position.
         */
        _onDrag(event) {
            if (!this._dragging) return;

            const track = this.$refs.track;
            if (!track) return;

            // Use the per-drag cached rect (set in startDrag); fall back to a
            // fresh read only if it's somehow absent.
            const rect = this._dragRect || track.getBoundingClientRect();
            const stepped = this._snapToStep(this._pointerValue(event.clientX, rect));

            if (this._dragging === 'min') {
                this.minVal = this._toPlaces(Math.max(this._min, Math.min(stepped, this.maxVal - this._step)));
            } else {
                this.maxVal = this._toPlaces(Math.min(this._max, Math.max(stepped, this.minVal + this._step)));
            }

            this._dispatchInputEvent();
        },

        /**
         * The value under a pointer at `clientX`, before it goes on the step grid: what a drag
         * and a click on the track both read, so the two cannot disagree about a point. A pointer
         * past either end of the track reads as that end.
         */
        _pointerValue(clientX, rect) {
            const percent = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));

            return this._min + percent * (this._max - this._min);
        },

        /**
         * A pointer's value on the step grid, before the clamp of the handle it moves.
         *
         * The grid starts at `min`, as a native range input's does, so it is the grid the arrow
         * keys walk from `min` as well, and `min` itself is a point on it.
         */
        _snapToStep(value) {
            return this._toPlaces(this._min + Math.round((value - this._min) / this._step) * this._step);
        },

        /** A value rounded to the places this slider's numbers carry (`_places`). */
        _toPlaces(value) {
            return Number(value.toFixed(this._places));
        },

        /**
         * Merge the two value badges into one "min – max" badge when the thumbs
         * sit close enough that the individual badges would overlap. Measures the
         * rendered badge widths against the gap between the
         * thumb centers — robust to track width and digit count, unlike a guessed
         * % threshold. Driven by an x-effect on minVal/maxVal, so it tracks live
         * during a drag, plus first paint. The individual badges stay in layout
         * (toggled via opacity, not display) so they remain measurable.
         */
        _measureBubbles() {
            const track = this.$refs.track;
            const lo = this.$refs.minBubble;
            const hi = this.$refs.maxBubble;
            if (!track || !lo || !hi) return;
            const tw = track.getBoundingClientRect().width;
            if (!tw) return;
            const minCenter = (this.minPercent / 100) * tw;
            const maxCenter = (this.maxPercent / 100) * tw;
            // Overlap when the gap between the two badge centers is less than
            // their combined half-widths plus a small breathing gap (6px).
            const need = (lo.offsetWidth + hi.offsetWidth) / 2 + 6;
            this._merged = (maxCenter - minCenter) < need;

            // Enable the fade only AFTER the first measurement has painted.
            // Deferring by a frame is what makes the initial state a paint
            // rather than an animation — setting it synchronously here would
            // still let the very first `_merged` flip transition.
            if (! this._ready) {
                requestAnimationFrame(() => { this._ready = true; });
            }
        },

        /**
         * Re-measure whenever either handle moves.
         *
         * Bound to `x-effect`, which re-runs whatever its expression READ. Spelled
         * out in the template as `minVal; maxVal; remeasure()` it would be three
         * statements, which Alpine's CSP build does not parse. The reads
         * have to stay, and stay BEFORE the call: an effect only tracks what it
         * actually touches, so dropping them would leave the geometry stale
         * until something else happened to re-run it.
         */
        remeasureOnValueChange() {
            // Deliberately assigned rather than discarded: a bare member
            // expression statement is what a minifier removes first, and the
            // whole point is that reading them registers the dependency.
            const tracked = [this.minVal, this.maxVal];

            this.remeasure();

            return tracked.length;
        },

        remeasure() {
            /*
             * Coalesced to one measurement a frame.
             *
             * This is reached from `x-effect`, so it fires on every write to minVal /
             * maxVal — which during a drag is every pointermove, faster than the display
             * refreshes. `_measureBubbles()` then does three layout reads (the track's
             * rect and both bubbles' offsetWidth) against a DOM the same gesture is
             * writing, which is exactly the per-event cost the drag's own `_dragRect`
             * cache keeps off the other path.
             *
             * A frame is also strictly later than a `$nextTick`, so the Alpine-rendered
             * DOM being read is current.
             */
            (this._measureFrame ??= frameCoalesce(() => this._measureBubbles())).schedule();
        },

        /**
         * Write both values into the hidden inputs and fire `input` on them, for `wire:model`.
         *
         * The inputs are bound with `:value`, and Alpine writes a binding a microtask later. Fired
         * right after the assignment, the events found the inputs still holding the previous
         * values, which is what `wire:model` reads, so the server stayed one step behind every key
         * press and every move of a drag. So the values are written here as well, before the
         * events.
         */
        _dispatchInputEvent() {
            for (const [input, value] of [[this.$refs.minInput, this.minVal], [this.$refs.maxInput, this.maxVal]]) {
                if (! input) {
                    continue;
                }

                input.value = String(value);
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },
    };
}
