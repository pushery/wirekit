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

const clamp = (n, min, max) => Math.min(max, Math.max(min, n));

export default function wirekitColorPicker(config = {}) {
    return {
        open: false,
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

            // Anchor the teleported panel whenever it opens (any trigger path:
            // swatch click, programmatic). The panel teleports to <body>, so it
            // needs Floating UI to position it relative to the swatch.
            this.$watch('open', (isOpen) => {
                if (isOpen) this.$nextTick(() => this._anchor());
            });
        },

        destroy() {
            this._endDrag();
        },

        // Position the teleported (fixed) panel below the swatch with the
        // house-standard popover gap (offset 8) and escape any clipping/stacking
        // ancestor — same separation as every other WireKit popover, so the panel
        // reads as a positioned surface, not a flush extension of the swatch.
        // crossAxisShift keeps the 18rem panel on-screen on narrow viewports /
        // when the swatch is near the right edge.
        async _anchor() {
            if (this.$refs.trigger && this.$refs.panel) {
                await position(this.$refs.trigger, this.$refs.panel, {
                    placement: 'bottom-start',
                    offset: 8,
                    crossAxisShift: true,
                });
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

        // ── Pointer drag (plane + sliders) ────────────────────────────

        startPlane(e) { this._startDrag('plane', e); },
        startHue(e) { this._startDrag('hue', e); },
        startAlpha(e) { this._startDrag('alpha', e); },

        _startDrag(which, e) {
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
            // Stop text selection during the drag.
            document.body.style.userSelect = 'none';
        },

        _endDrag() {
            if (this._moveHandler) {
                document.removeEventListener('pointermove', this._moveHandler);
                document.removeEventListener('pointerup', this._upHandler);
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

        _commitRecent() {
            const hex = this.hex;
            this.recents = [hex, ...this.recents.filter((c) => c !== hex)].slice(0, 8);
            try {
                window.localStorage.setItem(this._recentsKey, JSON.stringify(this.recents));
            } catch {
                // storage blocked / full — ignore
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
