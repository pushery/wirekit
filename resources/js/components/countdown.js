/**
 * Countdown — a clock that ticks toward a deadline and says when it passes.
 *
 * A registered factory rather than an inline `x-data`: built inline, the object
 * would need method shorthand, getters, arrow callbacks and `new Intl.NumberFormat`,
 * none of which Alpine's CSP build parses, so under a strict
 * Content-Security-Policy the timer would render its static markup and then
 * stand still.
 *
 * The `done` / `_fired` split is the subtle part. They look redundant and are
 * not:
 *
 *   `_fired` de-dupes the EVENT — it announces the deadline once.
 *   `done` is a MODEL VALUE that x-modelable binds, and it must be re-derivable.
 *
 * Conflating them would be a defect. x-modelable entangles on a microtask AFTER
 * init() runs, and its first pass copies the caller's outer value inward — so
 * for a deadline that has already passed at init, the `done = true` set during
 * init is immediately overwritten with the caller's `false`. Latching `done`
 * behind `_fired` would make the overwrite permanent: the binding would stay
 * false for the life of the page, until a reload. Deriving `done` from
 * `expired` on every pass is what survives the copy, and watching the VALUE
 * rather than guessing at microtask order is what makes it independent of when
 * Alpine happens to run the entangle pass.
 *
 * Lifecycle resources held on `this`:
 *   - _timer (setInterval, every `_tickMs()`: a second, 30 seconds when the smallest
 *     unit shown is minutes, a minute when it is larger) — cleared at the deadline and in
 *     destroy(). A visual clock does not need a server round-trip, so this ticks
 *     client-side rather than polling.
 *   - _expiryTimer (setTimeout, fires `sync()` at the deadline) — cleared in destroy().
 *
 * @param {Object} config
 * @param {number}  config.target        deadline as a unix timestamp in ms
 * @param {?number} config.warnSeconds   seconds before the deadline that count as urgent
 * @param {Array}   config.activeUnits   units to display, largest first
 * @param {boolean} config.autoMode      drop leading zero-units
 * @param {boolean} config.separators    group digits in the leading unit
 * @param {{group: string, decimal: string}|null} [config.numberMarks]  the application's own marks for that grouping, if any
 * @param {string}  config.locale        locale for that grouping
 * @param {boolean} config.animate       re-key each value so it can transition
 * @param {string}  config.animateStyle  'box' or 'text' for the segments variant, 'none' when off
 * @param {string}  config.expiredText   what to say once the deadline has passed
 * @param {Object}  config.unitPhrases   unit -> { sample count: template with a :count placeholder },
 *                                       as `PluralPhrases::from()` builds it for `pluralize()`
 * @param {boolean} config.static        work the time out once, as the page loads, and never tick
 */
import { focusTargetAround } from '../utils/focus-around.js';
import { formatDecimal } from '../utils/locale-number.js';
import { pauseWhileHidden } from '../utils/page-visibility.js';
import { pluralize } from '../utils/plural.js';
import { watchCurrent } from '../utils/watch-current.js';

// How long after it appears a countdown plays its change animation, in ms.
const CHANGE_ANIMATION_MS = 4300;

export default function wirekitCountdown(config = {}) {
    return {
        // Handles set while the component runs, declared so that they are its own: Alpine stores a
        // property no scope declares on the outermost scope around the component.
        _visibility: null,

        target: Number(config.target || 0),
        warnSeconds: config.warnSeconds ?? null,
        activeUnits: config.activeUnits || [],
        autoMode: Boolean(config.autoMode),
        separators: Boolean(config.separators),
        _numberMarks: config.numberMarks && typeof config.numberMarks === 'object' ? config.numberMarks : null,
        locale: config.locale || 'en',
        animate: Boolean(config.animate),
        animateStyle: config.animateStyle || 'none',

        /*
         * The change animation plays for the first seconds after the countdown appears and
         * then stops; the value keeps ticking. A box that pulsed every second for as long as
         * the page stayed open would be blinking content with no control to stop it (WCAG 2.2.2
         * allows five seconds). `calm` turns at CHANGE_ANIMATION_MS: the last pulse starts
         * before then and its 700 ms end inside the five seconds.
         */
        calm: ! config.animate,
        _calmTimer: null,
        expiredText: config.expiredText || '',

        /*
         * The ways to stop the clock (WCAG 2.2.2: a value that updates by itself beside other
         * content needs one, unless the task needs it to tick). `static` works the time out once,
         * on the reader's clock as the page loads, and never ticks. `paused` is the reader's: the
         * button of `pausable`, or a headless display's own control through `togglePause()`,
         * holds the clock until they resume it. The deadline still turns the countdown to its
         * expired text, once, through the timer armed for it, and fires the event.
         */
        static: Boolean(config.static),
        paused: false,

        unitSuffix: { years: 'y', days: 'd', hours: 'h', minutes: 'm', seconds: 's' },

        _div: { years: 31536000, days: 86400, hours: 3600, minutes: 60, seconds: 1 },
        _phrases: config.unitPhrases || {},
        _locale: config.locale || 'en',
        _timer: null,
        // The one-shot that fires AT the deadline rather than at the next display tick.
        _expiryTimer: null,

        // Completion state. `done` is a plain reactive prop so x-modelable can
        // bind it (the read-only `expired` getter cannot be bound); `_fired`
        // de-dupes the one-shot event. See the header for why they are separate.
        _fired: false,
        done: false,

        now: Date.now(),

        init() {
            this._setNow(Date.now());

            if (! this.static) {
                this._startTicking();
            }

            if (! this.calm) {
                this._calmTimer = setTimeout(() => {
                    this.calm = true;
                    this._calmTimer = null;
                }, CHANGE_ANIMATION_MS);
            }

            /*
             * A backgrounded tab throttles this timer but does not stop it, so a clock
             * nobody can see would go on waking the main thread once a second. Pausing is
             * safe here precisely because the tick carries no state: it assigns
             * `Date.now()`, so the first tick after the tab comes back is already the
             * right time — and `_startTicking` assigns it immediately rather than
             * waiting out an interval, so there is no stale second on return.
             */
            this._visibility = pauseWhileHidden({
                onHide: () => this._stopTicking(),
                onShow: () => {
                    if (! this.static && ! this.paused && ! this.expired) {
                        this._startTicking();
                    }
                },
            });

            const sync = () => {
                if (! this.expired) {
                    return;
                }

                this.done = true;

                // Past the deadline nothing the tick drives changes any more: `remaining` holds at
                // zero, and the countdown shows its expired text. So the tick stops here.
                this._stopTicking();

                if (! this._fired) {
                    this._fired = true;
                    this.$dispatch('wirekit-countdown-expired');
                }
            };

            // An already-past deadline still notifies.
            sync();
            watchCurrent(this, 'now', () => sync());

            // And the deadline gets its own timer, which is what lets the display tick be
            // coarse without making the EVENT coarse. `sync()` hangs on the interval, so a
            // 30-second display tick would delay `wirekit-countdown-expired` by up to thirty
            // seconds — a component that reads "0 days" while nothing has fired yet.
            //
            // A timer armed for the deadline itself fires the event at the deadline; waiting
            // for the next 1 Hz tick would deliver it up to a full second late.
            this._armExpiry(sync);

            // Re-assert after any write that clears `done` while the deadline is
            // past — that write is the entangle copy, not the application.
            watchCurrent(this, 'done', (value) => {
                if (! value && this.expired) {
                    this.done = true;
                }
            });
        },

        _startTicking() {
            this._stopTicking();
            this._setNow(Date.now());
            this._timer = setInterval(() => { this._setNow(Date.now()); }, this._tickMs());
        },

        _stopTicking() {
            if (this._timer) {
                clearInterval(this._timer);
                this._timer = null;
            }
        },

        /**
         * Set the clock. The write that carries it past the deadline hides the button of
         * `pausable` (`x-show="! expired"`), so when that button holds the focus, the focus
         * moves on first: to the next control after the countdown, or the last one before it,
         * as a dismissed alert hands it on. Without that the button takes the focus with it as
         * it hides, and the browser drops it to the page (WCAG 2.4.3). It happens before the
         * write because everything the write sets off runs after it: the button has hidden by
         * the time a `$watch` callback hears of the change.
         *
         * @param {number} time a unix timestamp in ms
         */
        _setNow(time) {
            if (! this.expired && this.target - time <= 0) {
                const button = this.$root?.querySelector?.('[data-wk-countdown-pause]');

                if (button && typeof document !== 'undefined' && document.activeElement === button) {
                    focusTargetAround(this.$root)?.focus();
                }
            }

            this.now = time;
        },

        /**
         * Stop the clock where it stands, or start it again from the time it is now. A countdown
         * that is `static` has no clock to start, and one past its deadline has none left to run,
         * so `paused` stays as it is there.
         */
        togglePause() {
            if (this.static || this.expired) {
                return;
            }

            this.paused = ! this.paused;

            if (this.paused) {
                this._stopTicking();
            } else {
                this._startTicking();
            }
        },

        destroy() {
            this._stopTicking();

            if (this._calmTimer) {
                clearTimeout(this._calmTimer);
                this._calmTimer = null;
            }
            this._visibility?.stop();
            this._visibility = null;

            if (this._expiryTimer) {
                clearTimeout(this._expiryTimer);
                this._expiryTimer = null;
            }
        },

        /**
         * How often the DISPLAY has to be refreshed, from the smallest unit it shows.
         *
         * An unconditional 1 Hz tick would set a reactive property every second for a
         * deadline shown in DAYS, re-evaluating every expression derived from it for a value
         * that changes once a day. The interval follows `showSeconds` and the unit list
         * instead.
         *
         * One rung under Nyquist on the display, so a minute still turns over visibly.
         */
        _tickMs() {
            const smallest = this.activeUnits[this.activeUnits.length - 1];

            if (smallest === 'seconds' || ! smallest) {
                return 1000;
            }

            return smallest === 'minutes' ? 30000 : 60000;
        },

        /**
         * Fire `sync()` AT the deadline, exactly once.
         *
         * `setTimeout` takes a 32-bit signed delay, and anything past 2^31-1 ms — about
         * 24.8 days — overflows and fires immediately. A legal deadline measured in weeks
         * would announce expiry the moment the page loaded, which is why the arm re-arms
         * instead of scheduling once.
         */
        _armExpiry(sync) {
            const MAX_DELAY = 2147483647;

            if (this._expiryTimer) {
                clearTimeout(this._expiryTimer);
                this._expiryTimer = null;
            }

            const remaining = this.target - Date.now();

            if (remaining <= 0) {
                return;
            }

            const delay = Math.min(remaining, MAX_DELAY);

            this._expiryTimer = setTimeout(() => {
                this._expiryTimer = null;

                // Still short of the deadline: this was a hop, not the arrival. The clock is left
                // where it stands, as a countdown that is static or paused promises, and the timer
                // is armed again.
                if (this.target - Date.now() > 0) {
                    this._armExpiry(sync);

                    return;
                }

                this._setNow(Date.now());
                sync();
            }, delay);
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

            if (! this.separators) {
                return String(seg.value);
            }

            // Grouped as the locale groups, with the application's own mark where it has one.
            return this._numberMarks === null
                ? new Intl.NumberFormat(this.locale).format(seg.value)
                : formatDecimal(seg.value, this.locale, 0, 0, this._numberMarks);
        },

        /**
         * A per-value key, so a changed value re-mounts its node and its change
         * animation plays. Stable per-unit when animation is off or has gone calm,
         * so nothing re-mounts.
         */
        segKey(seg) {
            return this.animate && ! this.calm ? `${seg.unit}-${seg.value}` : seg.unit;
        },

        /**
         * The class that plays the change animation on a segment box, and the one on its
         * number. Bound rather than written into the markup, so the node that re-mounts
         * when the keys turn stable carries neither and plays nothing.
         */
        get boxChangeClass() {
            return ! this.calm && this.animateStyle === 'box' ? 'wk-countdown-pulse' : '';
        },

        get textChangeClass() {
            return ! this.calm && this.animateStyle === 'text' ? 'wk-countdown-text-flash' : '';
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
