/**
 * WireKit Color Picker (popover mode) — a from-scratch HSV picker.
 *
 * State is held as HSV + alpha (the natural space for the saturation/value plane
 * and hue slider). The plane, sliders, and text field stay in sync; the chosen
 * value is mirrored into a hidden <input> (as the active format) for form submit
 * + wire:model. No third-party library — all conversions live in utils/color.js.
 */
import { hsvToRgb, rgbToHsv, rgbToHex, parseColor, formatColor } from '../utils/color.js';
import { position } from '../utils/floating.js';
import { createFocusTrap } from '../utils/focus-trap.js';

const clamp = (n, min, max) => Math.min(max, Math.max(min, n));

export default function wirekitColorPicker(config = {}) {
    return {
        /**
         * The alpha slider announces a whole percentage, not the 0–1 fraction it
         * stores. `Math` is unreachable from a directive under Alpine's CSP
         * build — the evaluator resolves names against the Alpine scope only —
         * so the rounding happens here.
         */
        alphaPercent() {
            return Math.round(this.a * 100);
        },

        open: false,
        // Floating UI autoUpdate teardown handle — set in _anchor(), cleared when
        // the panel closes (the open $watch else-branch) and on destroy(). Keeps the
        // fixed panel anchored to the swatch on scroll/resize without leaking
        // listeners (every teardown path must call stop()).
        _stopAutoUpdate: null,
        // Focus trap over the teleported panel — created when the panel opens,
        // released in EVERY close path (close / _closeFromTrap / destroy). The
        // panel carries role="dialog", so a trap plus a focus return to the
        // trigger is the contract, not a nicety.
        _trap: null,
        // Inline copy feedback: copy() flips this true for ~1.5s so the button
        // can swap its icon to a checkmark and announce "Copied" — self-contained,
        // unlike the wirekit-toast dispatch which needs an external toast listener.
        copied: false,
        _copyTimer: null,
        h: 0,
        s: 100,
        v: 100,
        a: 1,
        format: ['hex', 'rgb', 'hsl', 'oklch'].includes(config.format) ? config.format : 'hex',
        withAlpha: config.withAlpha !== false,
        // Opt-in "no color" affordance (popover mode only). `cleared` true means
        // the bound form value is empty; applying any color flips it back to
        // false via _sync(). Inert when withClear is false (button not rendered).
        withClear: config.withClear === true,
        cleared: false,
        // Opt-in escape hatch: on touch-primary devices, swap the popover
        // trigger for the platform's native color sheet (the non-popover
        // variant gets that for free via <input type="color">; the popover
        // variant otherwise ALWAYS opens the custom panel, even on mobile).
        nativeOnMobile: config.nativeOnMobile === true,
        useNative: false,
        invalidInput: false,
        recents: [],
        _recentsKey: config.recentsKey || 'wk-color-picker-recents',
        _drag: null,
        _moveHandler: null,
        _upHandler: null,

        hasEyeDropper: typeof window !== 'undefined' && 'EyeDropper' in window,

        init() {
            const parsed = parseColor(config.value || '#000000');
            if (parsed) {
                const hsv = rgbToHsv(parsed);
                this.h = hsv.h;
                this.s = hsv.s;
                this.v = hsv.v;
                this.a = parsed.a ?? 1;
            }
            // withClear + an empty initial value starts in the cleared ("no
            // color") state instead of defaulting the HSV plane to red.
            this.cleared = this.withClear && !config.value;
            this._loadRecents();
            // Decide native-vs-popover ONCE at mount. `(pointer: coarse)` is the
            // touch-primary discriminator (true on phones/tablets, false on
            // desktops — including desktops with a touchscreen but a mouse as
            // the primary pointer). Guarded so node-based logic tests and SSR
            // environments without matchMedia stay on the popover path.
            this.useNative = this.nativeOnMobile
                && typeof window !== 'undefined'
                && typeof window.matchMedia === 'function'
                && window.matchMedia('(pointer: coarse)').matches;
            // Do NOT sync on init: the hidden input already carries the developer's
            // exact value (server-rendered). Syncing here would re-write it as the
            // HSV-rounded value (±1 per channel), drifting an untouched form field.
            // We sync only once the user actually changes the color.

            // Anchor AND focus the teleported panel whenever it opens (any trigger
            // path: swatch click, programmatic). The panel teleports to <body>, so
            // it needs Floating UI to position it relative to the swatch — and for
            // the same reason it needs the focus handling below: in the document it
            // sits LAST, so a reader who activated the swatch would otherwise tab
            // through the whole rest of the page to reach the sliders.
            this.$watch('open', (isOpen) => {
                if (isOpen) {
                    this.$nextTick(() => this._openPanel());
                } else {
                    this._stopAutoUpdate?.();
                    this._stopAutoUpdate = null;
                }
            });
        },

        destroy() {
            this._endDrag();
            this._stopAutoUpdate?.();
            this._stopAutoUpdate = null;
            // The copy confirmation runs for 1.5s after the click, which is long
            // enough for a Livewire morph to take this element away underneath it.
            // The callback only clears a flag today, so the write lands on a torn
            // down scope and nothing visible happens — but a timer that outlives
            // its component is the null-callback class whatever it writes, and the
            // next edit to that callback is the one that turns it into an error.
            clearTimeout(this._copyTimer);
            this._copyTimer = null;
            // returnFocus false: the element is going away, and the trigger it
            // would hand focus back to is going away with it.
            this._trap?.deactivate({ returnFocus: false });
            this._trap = null;
        },

        // ── Open / close ──────────────────────────────────────────────

        /** Trigger toggle. Closing goes through close() so focus is handed back. */
        toggle() {
            if (this.open) {
                this.close();

                return;
            }

            this.open = true;
        },

        /**
         * Position the panel and take focus into it.
         *
         * Runs from the `open` watcher rather than from the trigger handler, so a
         * programmatic `open = true` gets the same treatment as a click.
         */
        async _openPanel() {
            if (! this.open) {
                return;
            }

            await this._anchor();

            const panel = this.$refs.panel;
            if (! panel) {
                return;
            }

            // A previous trap can still be standing if `open` was flipped off and on
            // again within one tick; releasing it first keeps the stack to one.
            this._trap?.deactivate({ returnFocus: false });

            this._trap = createFocusTrap(panel, {
                escapeDeactivates: true,
                onDeactivate: () => this._closeFromTrap(),
                // Let a click on the trigger (and on anything else outside) through:
                // the trigger's own toggle and the panel's click.outside handler are
                // what close the panel, and a trap that swallowed those would leave
                // the reader with a panel that only Escape can dismiss.
                allowOutsideClick: true,
                // The saturation/value plane, not the panel wrapper: it is the first
                // control and it announces itself, while the wrapper is a plain div
                // that would be a stop saying nothing.
                initialFocus: () => this.$refs.plane ?? panel,
                // WHERE FOCUS GOES WHEN THE TRAP LETS GO, named explicitly. The trap
                // otherwise returns focus to whatever held it at activation, and the
                // panel is teleported out of this subtree — measured on the sibling
                // popover, that left focus on <body>, which drops a keyboard reader
                // back to the top of the page (WCAG 2.4.3).
                setReturnFocus: () => this.$refs.trigger ?? false,
            });
            this._trap.activate();
        },

        /**
         * Close the panel and hand focus back to the swatch.
         *
         * Reached from the trigger toggle, from the panel's click.outside, and from
         * the window-level Escape handler in the template.
         */
        close() {
            if (! this.open) {
                return;
            }

            // WAS THE READER INSIDE THE PANEL? Asked BEFORE anything hides, because
            // it decides whether focus is ours to move at all. Programmatic closes
            // and closes that arrive while focus sits elsewhere leave it alone —
            // taking focus from somebody who never had it in the panel would be a
            // jump they did not ask for.
            const panel = this.$refs.panel;
            const hadFocus = panel ? panel.contains(document.activeElement) : false;

            // Release the trap BEFORE moving focus and before the hide. While a trap
            // is active it pulls any outside focus straight back in, so a focus()
            // call made first would simply bounce; and hiding the subtree that holds
            // focus makes the browser drop it on <body>, after which our call would
            // have accomplished nothing.
            if (this._trap) {
                this._trap.deactivate({ returnFocus: hadFocus });
                this._trap = null;
            } else if (hadFocus) {
                this.$refs.trigger?.focus({ preventScroll: true });
            }

            this.open = false;
            this._stopAutoUpdate?.();
            this._stopAutoUpdate = null;
        },

        /**
         * Close triggered by the trap deactivating itself (Escape).
         *
         * Deliberately does NOT call deactivate() again — this runs from inside it.
         * The focus return is the trap's own, through `setReturnFocus` above.
         */
        _closeFromTrap() {
            if (! this.open) {
                return;
            }

            this._trap = null;
            this.open = false;
            this._stopAutoUpdate?.();
            this._stopAutoUpdate = null;
        },

        // Position the teleported (fixed) panel below the swatch with the
        // house-standard popover gap (offset 8) and escape any clipping/stacking
        // ancestor — same separation as every other WireKit popover, so the panel
        // reads as a positioned surface, not a flush extension of the swatch.
        // crossAxisShift keeps the 18rem panel on-screen on narrow viewports /
        // when the swatch is near the right edge.
        async _anchor() {
            if (this.$refs.trigger && this.$refs.panel) {
                this._stopAutoUpdate?.();
                const { stop } = await position(this.$refs.trigger, this.$refs.panel, {
                    placement: 'bottom-start',
                    offset: 8,
                    crossAxisShift: true,
                    // Follow the swatch on scroll/resize; torn down on close/destroy.
                    autoReposition: true,
                });
                this._stopAutoUpdate = stop;
            }
        },

        // ── Derived values ────────────────────────────────────────────

        get rgb() {
            return hsvToRgb({ h: this.h, s: this.s, v: this.v });
        },

        get hex() {
            return rgbToHex(this.rgb);
        },

        get cssColor() {
            const { r, g, b } = this.rgb;

            return `rgba(${r}, ${g}, ${b}, ${this.a})`;
        },

        get hueCss() {
            const { r, g, b } = hsvToRgb({ h: this.h, s: 100, v: 100 });

            return `rgb(${r}, ${g}, ${b})`;
        },

        get formattedValue() {
            return formatColor({ ...this.rgb, a: this.a }, this.format, this.withAlpha);
        },

        // Readout text. The cleared ("no color") state overrides the formatted
        // value; withClear off keeps cleared false, so this always returns the
        // formatted value (byte-identical to before withClear existed).
        get displayValue() {
            return this.cleared ? 'No color' : this.formattedValue;
        },

        // Popover value-field text. Like displayValue but BLANK (not "No color")
        // while cleared, so the editable field shows its placeholder rather than a
        // stale color. withClear off keeps cleared false → always formattedValue.
        get popoverValue() {
            return this.cleared ? '' : this.formattedValue;
        },

        // Marker positions as percentages for inline style binding.
        get planeLeft() { return `${this.s}%`; },
        get planeTop() { return `${100 - this.v}%`; },
        get hueLeft() { return `${(this.h / 360) * 100}%`; },
        get alphaLeft() { return `${this.a * 100}%`; },

        /**
         * The style strings, built here rather than in the template.
         *
         * A template literal is one of the forms Alpine's CSP build cannot
         * parse, and this component is nothing but positioned gradients — so
         * under a strict Content-Security-Policy the picker rendered with every
         * marker stacked at the origin and no gradient at all.
         *
         * The getters above already produced the individual values; these only
         * assemble them, which is why they sit next to each other.
         */
        get swatchStyle() { return `background-color: ${this.cssColor}`; },
        get planeStyle() { return `background-color: ${this.hueCss}`; },
        get planeMarkerStyle() {
            return `left: ${this.planeLeft}; top: ${this.planeTop}; background-color: ${this.hex}`;
        },
        get hueMarkerStyle() { return `left: ${this.hueLeft}`; },
        get alphaTrackStyle() { return `background: linear-gradient(to right, transparent, ${this.hex})`; },
        get alphaMarkerStyle() { return `left: ${this.alphaLeft}`; },
        recentStyle(recent) { return `background-color: ${recent}`; },

        // ── Pointer drag (plane + sliders) ────────────────────────────

        startPlane(e) { this._startDrag('plane', e); },
        startHue(e) { this._startDrag('hue', e); },
        startAlpha(e) { this._startDrag('alpha', e); },

        _startDrag(which, e) {
            // The gesture starts HERE, at pointerdown — not at the pointerup that
            // commits it. `_apply` below already moves the color, and every frame
            // in between moves it again, so a baseline taken at commit time would
            // be the value being handed over.
            this._markGesture();
            this._drag = which;
            // Cache the target's rect ONCE per drag — the element is stationary while
            // dragging, so re-reading getBoundingClientRect on every pointermove (a hot
            // path) would force a needless layout read per frame.
            this._rect = this.$refs[which].getBoundingClientRect();
            this._apply(e);
            this._moveHandler = (ev) => this._apply(ev);
            this._upHandler = () => this._endDrag();
            // passive: the handler only reads pointer coords + updates state (no
            // preventDefault); touch-scroll is stopped via the plane's `touch-none`.
            document.addEventListener('pointermove', this._moveHandler, { passive: true });
            document.addEventListener('pointerup', this._upHandler);
            // pointercancel ends a drag too, and NO pointerup follows it — the
            // browser fires it when it takes the pointer away (a touch that
            // becomes a scroll, a system gesture). Without this the move handler
            // stays on document for the life of the page, recomputing a value
            // for a drag the user finished long ago, with text selection still
            // disabled from the drag start.
            document.addEventListener('pointercancel', this._upHandler);
            // Stop text selection during the drag.
            document.body.style.userSelect = 'none';
        },

        _endDrag() {
            if (this._moveHandler) {
                document.removeEventListener('pointermove', this._moveHandler);
                document.removeEventListener('pointerup', this._upHandler);
                document.removeEventListener('pointercancel', this._upHandler);
                this._moveHandler = null;
                this._upHandler = null;
            }
            if (this._drag) {
                document.body.style.userSelect = '';
                this._drag = null;
                this._commitRecent();
            }
        },

        _apply(e) {
            const rect = this._rect;
            if (this._drag === 'plane') {
                this.s = Math.round(clamp((e.clientX - rect.left) / rect.width, 0, 1) * 100);
                this.v = Math.round((1 - clamp((e.clientY - rect.top) / rect.height, 0, 1)) * 100);
            } else if (this._drag === 'hue') {
                this.h = Math.round(clamp((e.clientX - rect.left) / rect.width, 0, 1) * 360);
            } else if (this._drag === 'alpha') {
                this.a = +clamp((e.clientX - rect.left) / rect.width, 0, 1).toFixed(2);
            }
            this._sync(false);
        },

        // ── Keyboard on plane / sliders (WAI-ARIA slider keys) ────────

        nudgePlane(dx, dy) {
            this.s = clamp(this.s + dx, 0, 100);
            this.v = clamp(this.v + dy, 0, 100);
            this._sync();
        },
        nudgeHue(d) { this.h = clamp(this.h + d, 0, 360); this._sync(); },
        nudgeAlpha(d) { this.a = +clamp(this.a + d, 0, 1).toFixed(2); this._sync(); },

        // Home / End on a slider jump to the ends of its range — part of the
        // pattern the `role="slider"` on these strips announces, so a reader who
        // follows it and gets no response has been told something untrue. They
        // live here rather than as an inline `h = 0; _sync()` so the template
        // keeps handing Alpine one call per binding, which is the only shape the
        // CSP build evaluates.
        setHue(v) { this.h = clamp(v, 0, 360); this._sync(); },
        setAlpha(v) { this.a = +clamp(v, 0, 1).toFixed(2); this._sync(); },

        // ── Text field + format toggle ────────────────────────────────

        onInput(value) {
            const parsed = parseColor(value);
            if (!parsed) {
                this.invalidInput = true;

                return;
            }
            this.invalidInput = false;
            const hsv = rgbToHsv(parsed);
            this.h = hsv.h;
            this.s = hsv.s;
            this.v = hsv.v;
            this.a = parsed.a ?? 1;
            this._sync(false);
        },

        cycleFormat() {
            const order = ['hex', 'rgb', 'hsl', 'oklch'];
            this.format = order[(order.indexOf(this.format) + 1) % order.length];
            this._sync(false);
        },

        // ── Actions ───────────────────────────────────────────────────

        async eyedropper() {
            if (!this.hasEyeDropper) { return; }
            try {
                const result = await new window.EyeDropper().open();
                this.onInput(result.sRGBHex);
                this._commitRecent();
            } catch {
                // user canceled — no-op
            }
        },

        async copy() {
            try {
                await navigator.clipboard.writeText(this.formattedValue);
                // Inline confirmation (button icon → checkmark + sr-only announce),
                // self-contained so it works without any external listener. The
                // wirekit-toast dispatch stays as a bonus for apps that show toasts.
                this.copied = true;
                clearTimeout(this._copyTimer);
                this._copyTimer = setTimeout(() => { this.copied = false; }, 1500);
                this.$dispatch('wirekit-toast', { message: 'Copied ' + this.formattedValue, variant: 'success' });
            } catch {
                // clipboard blocked — no-op
            }
        },

        pickColor(value) {
            this.onInput(value);
            this._commitRecent();
        },

        // Clear to "no color": empty the bound form value + dispatch input/change
        // so wire:model picks up the empty string, AND reset the picker apparatus
        // to its neutral default so the popover stops displaying the just-cleared
        // color — the plane marker, hue, and value field all reset (matching a
        // fresh empty `withClear` picker). The next applied color un-clears via
        // _sync(). Popover mode only — see the Blade @props note.
        clear() {
            this.cleared = true;
            this.h = 0;
            this.s = 0;
            this.v = 0;
            this.a = 1;
            if (this.$refs.input) {
                this.$refs.input.value = '';
                this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
                this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },

        // ── Sync + recents ────────────────────────────────────────────

        _sync(commitRecent = true) {
            // Applying any color leaves the cleared ("no color") state.
            this.cleared = false;
            if (this.$refs.input) {
                this.$refs.input.value = this.formattedValue;
                this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
                this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (commitRecent) {
                this._commitRecent();
            }
        },

        /**
         * The commit boundary, and this method already WAS one before any of
         * this existed. It is called from exactly the four places where a color is
         * SETTLED rather than moving: the end of a drag, the eyedropper, a swatch
         * or recent, and `_sync(true)` for a typed value or an arrow-key nudge.
         * That is also why a color lands in "recents" here and nowhere else.
         *
         * `_sync(false)` runs for every frame of a drag and deliberately does not
         * reach this. So the boundary is an event the component already knew about
         * — never a timer, and nothing new had to be invented to find it.
         */
        _commitRecent() {
            const hex = this.hex;
            this.recents = [hex, ...this.recents.filter((c) => c !== hex)].slice(0, 8);
            try {
                window.localStorage.setItem(this._recentsKey, JSON.stringify(this.recents));
            } catch {
                // storage blocked / full — ignore
            }
            this._commit();
        },

        /**
         * Mark the START of a gesture, so the layer's rollback baseline is the
         * color from BEFORE the drag rather than the one the drag produced.
         *
         * `failure: 'keep'` was very nearly the argument for leaving this out —
         * a refused save never rolls back, so why hold a baseline at all? Because
         * `keep` covers only the SERVER's refusal. A CANCELED request still
         * restores, and canceling is exactly what a second drag started during
         * the first one's round trip does. Without a mark, that restore writes
         * back the value the drag itself produced: the plane marker stays put and
         * the cancellation is invisible.
         *
         * Looked up rather than assumed, like `run` below: without a layer this
         * component behaves exactly as before, down to the byte.
         */
        _markGesture() {
            if (typeof this.mark === 'function') {
                this.mark();
            }
        },

        /**
         * Hand the settled color to the optimistic layer, if one wraps this.
         *
         * `run` is looked up rather than assumed: without a layer this component
         * behaves exactly as it did before, down to the byte — the property the
         * whole opt-in shape rests on.
         */
        _commit() {
            if (typeof this.run === 'function') {
                this.run(this.formattedValue);
            }
        },

        _loadRecents() {
            try {
                const raw = window.localStorage.getItem(this._recentsKey);
                const parsed = raw ? JSON.parse(raw) : [];
                // Coerce to an array: a corrupted write, a key collision, or another
                // script could leave a non-array value here, which would crash
                // _commitRecent (`.filter`) and the x-for that iterates recents.
                this.recents = Array.isArray(parsed) ? parsed : [];
            } catch {
                this.recents = [];
            }
        },
    };
}
