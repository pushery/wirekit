/**
 * Countdown — a clock that ticks toward a deadline and says when it passes.
 *
 * The inline `x-data` did not parse under Alpine's CSP build (method shorthand,
 * getters, arrow callbacks, `new Intl.NumberFormat`), so under a strict
 * Content-Security-Policy the object failed to build and the timer rendered its
 * static markup and then stood still.
 *
 * The `done` / `_fired` split is the subtle part and is unchanged. They look
 * redundant and are not:
 *
 *   `_fired` de-dupes the EVENT — it announces the deadline once.
 *   `done` is a MODEL VALUE that x-modelable binds, and it must be re-derivable.
 *
 * Conflating them was a defect. x-modelable entangles on a microtask AFTER
 * init() runs, and its first pass copies the caller's outer value inward — so
 * for a deadline that had already passed at init, the `done = true` set during
 * init was immediately overwritten with the caller's `false`. Latching `done`
 * behind `_fired` then made the overwrite permanent: the binding stayed false
 * for the life of the page and only a reload recovered it. Deriving `done` from
 * `expired` on every pass is what survives the copy, and watching the VALUE
 * rather than guessing at microtask order is what makes it independent of when
 * Alpine happens to run the entangle pass.
 *
 * Lifecycle resources held on `this`:
 *   - _timer (setInterval, 1s) — cleared in destroy(). A visual clock does not
 *     need a server round-trip, so this ticks client-side rather than polling.
 *
 * @param {Object} config
 * @param {number}  config.target        deadline as a unix timestamp in ms
 * @param {?number} config.warnSeconds   seconds before the deadline that count as urgent
 * @param {Array}   config.activeUnits   units to display, largest first
 * @param {boolean} config.autoMode      drop leading zero-units
 * @param {boolean} config.separators    group digits in the leading unit
 * @param {string}  config.locale        locale for that grouping
 * @param {boolean} config.animate       re-key each value so it can transition
 * @param {string}  config.expiredText   what to say once the deadline has passed
 * @param {Object}  config.unitPhrases   unit -> [singular, plural] with a :count placeholder
 */
import { pluralize } from '../utils/plural.js';

export default function wirekitCountdown(config = {}) {
    return {
        target: Number(config.target || 0),
        warnSeconds: config.warnSeconds ?? null,
        activeUnits: config.activeUnits || [],
        autoMode: Boolean(config.autoMode),
        separators: Boolean(config.separators),
        locale: config.locale || 'en',
        animate: Boolean(config.animate),
        expiredText: config.expiredText || '',

        unitSuffix: { years: 'y', days: 'd', hours: 'h', minutes: 'm', seconds: 's' },

        _div: { years: 31536000, days: 86400, hours: 3600, minutes: 60, seconds: 1 },
        _phrases: config.unitPhrases || {},
        _locale: config.locale || 'en',
        _timer: null,

        // Completion state. `done` is a plain reactive prop so x-modelable can
        // bind it (the read-only `expired` getter cannot be bound); `_fired`
        // de-dupes the one-shot event. See the header for why they are separate.
        _fired: false,
        done: false,

        now: Date.now(),

        init() {
            this.now = Date.now();
            this._timer = setInterval(() => { this.now = Date.now(); }, 1000);

            const sync = () => {
                if (! this.expired) {
                    return;
                }

                this.done = true;

                if (! this._fired) {
                    this._fired = true;
                    this.$dispatch('wirekit-countdown-expired');
                }
            };

            // An already-past deadline still notifies.
            sync();
            this.$watch('now', () => sync());

            // Re-assert after any write that clears `done` while the deadline is
            // past — that write is the entangle copy, not the application.
            this.$watch('done', (value) => {
                if (! value && this.expired) {
                    this.done = true;
                }
            });
        },

        destroy() {
            if (this._timer) {
                clearInterval(this._timer);
                this._timer = null;
            }
        },

        get remainingMs() {
            return this.target - this.now;
        },

        get expired() {
            return this.remainingMs <= 0;
        },

        /**
         * The complete remaining-time ladder, for a HEADLESS display.
         *
         * A developer whose app renders its own copy around the number (a
         * localized "Resend in N seconds" with its own pluralization) reads this
         * instead of rebuilding the clock, the resync and the expiry core.
         * Unlike `computed` — which is filtered to the active units and drops
         * leading zeros — this is ALWAYS complete, so `remaining.seconds` and
         * `remaining.totalSeconds` are stable regardless of the `units` prop.
         */
        get remaining() {
            const totalMs = Math.max(0, this.remainingMs);

            let s = Math.floor(totalMs / 1000);
            const totalSeconds = s;

            const years = Math.floor(s / 31536000); s -= years * 31536000;
            const days = Math.floor(s / 86400); s -= days * 86400;
            const hours = Math.floor(s / 3600); s -= hours * 3600;
            const minutes = Math.floor(s / 60); s -= minutes * 60;

            return { years, days, hours, minutes, seconds: s, totalSeconds, totalMs };
        },

        get urgent() {
            return this.warnSeconds !== null && ! this.expired && this.remainingMs <= this.warnSeconds * 1000;
        },

        /**
         * The remaining time across the ACTIVE units.
         *
         * The first active unit carries all overflow above it, so units='hours'
         * shows total hours rather than hours-within-a-day; the rest cascade. A
         * year is a 365-day approximation — a running deadline reads in whole
         * years and days, not calendar-exact leap math.
         */
        get computed() {
            let s = Math.max(0, Math.floor(this.remainingMs / 1000));
            const segments = [];

            for (const unit of this.activeUnits) {
                const div = this._div[unit];
                const value = Math.floor(s / div);
                s -= value * div;
                segments.push({ unit, value });
            }

            if (! this.autoMode) {
                return segments;
            }

            // Drop leading zero-units, keeping at least the last one.
            let start = 0;
            while (start < segments.length - 1 && segments[start].value === 0) {
                start++;
            }

            return segments.slice(start);
        },

        /**
         * One unit value, formatted.
         *
         * The leading unit gets locale digit grouping because it can be large
         * (years, days); the rest are zero-padded to two digits so the clock
         * keeps a stable rhythm instead of jittering as values cross ten.
         */
        segValue(seg, index) {
            if (index !== 0) {
                return String(seg.value).padStart(2, '0');
            }

            return this.separators
                ? new Intl.NumberFormat(this.locale).format(seg.value)
                : String(seg.value);
        },

        /**
         * A per-value key, so a changed value re-mounts its node and the enter
         * transition fires. Stable per-unit when animation is off, so nothing
         * re-mounts.
         */
        segKey(seg) {
            return this.animate ? `${seg.unit}-${seg.value}` : seg.unit;
        },

        /**
         * Coarse text for assistive technology.
         *
         * NOT a per-second live region — `role="timer"` is `aria-live="off"` —
         * so this is read on navigation rather than announced every tick.
         */
        get srText() {
            if (this.expired) {
                return this.expiredText;
            }

            const segments = this.computed;

            return segments
                .filter((seg, i) => seg.value > 0 || i === segments.length - 1)
                .map((seg) => {
                    const forms = this._phrases[seg.unit];

                    if (! forms) {
                        return `${seg.value} ${seg.unit}`;
                    }

                    return pluralize(forms, seg.value, this._locale);
                })
                .join(', ');
        },
    };
}
