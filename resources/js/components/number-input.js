import { controlIsDisabled, watchFieldsetDisabled } from '../utils/fieldset-disabled.js';
import { decimalPlaces, scaleText } from '../utils/locale-number.js';
import { watchModelEvents } from '../utils/model-events.js';
import { observeServerValue, WK_SERVER_VALUE_ATTRIBUTE } from '../utils/server-value.js';

/**
 * Number input — the stepper's arithmetic.
 *
 * This was an inline `x-data` and could not stay: the literal declared getters
 * and methods, which Alpine's CSP build does not parse. Under a strict
 * Content-Security-Policy both stepper buttons rendered, looked enabled, and
 * did nothing at all.
 *
 * Two arithmetic decisions are load-bearing, and are why this is more than
 * `value + step`:
 *
 *   Precision. Binary floating point turns 19.01 + 0.01 into
 *   19.020000000000003 — which the field then SHOWS, and which oscillates
 *   between representations on every further click. Each result is snapped back
 *   to the decimal places the step itself implies.
 *
 *   Grid snapping. From an off-grid value the step must move to the next grid
 *   POINT, not add to the value: with value 1.78 and step 0.1, adding gives
 *   1.88, which rounds to 1.9 and skips 1.8 entirely. Snapping matches the W3C
 *   contract for a native <input type="number">.
 *
 * A caller's model on the field (`wire:model`, `x-model`) owns the input, and `value` then
 * only mirrors it: read when the model has written the field, after every keystroke, and
 * after every Livewire commit, because a value the server sets arrives as a property write
 * with no event. The steppers write through to the input and fire what a native stepper
 * fires, so the model hears them.
 *
 * CLEANUP CONTRACT: `_unhookResync` (the Livewire commit hook, bound fields only) is
 * released in destroy(), and so is `_modelEvents`, which fires `blur` on the field for
 * `wire:model.blur` when the reader leaves the component after using only the steppers: the
 * field then never had focus, so its own `blur` never came (utils/model-events.js). So is
 * `_stopLockWatch`, which follows the field's `disabled` and `readonly` and the fieldsets
 * around it for the steppers' bindings. And `_stopServerValue`, which follows the start value the
 * server renders on the root (utils/server-value.js).
 *
 * @param {Object}  config
 * @param {number}  [config.value]  starting value; without it, the root's `data-wk-server-value`
 * @param {?number} config.min    lower bound, or null for unbounded
 * @param {?number} config.max    upper bound, or null for unbounded
 * @param {number}  config.step   grid spacing
 * @param {boolean} config.bound  the field carries a caller's model
 * @param {Object}  [config.adjusted]  translated sentences for a value that leaving the field
 *   changed, each taking `:value`: `highest`, `lowest` and `changed`
 * @param {string}  [config.locale]  the application's locale, BCP-47, for the value in those sentences
 * @param {{group: string, decimal: string}|null} [config.separators]  the application's own marks
 */
export default function wirekitNumberInput(config = {}) {
    return {
        value: config.value,
        // Normalized to null rather than left undefined: every bound check below
        // reads `!== null`, and `undefined` passes it — an absent bound would
        // then be compared against, and produce NaN.
        min: config.min ?? null,
        max: config.max ?? null,
        step: config.step ?? 1,
        bound: config.bound === true,
        // What leaving the field did to a value the reader typed, as a sentence, or ''.
        adjustment: '',
        _adjusted: config.adjusted && typeof config.adjusted === 'object' ? config.adjusted : {},
        _locale: typeof config.locale === 'string' ? config.locale : null,
        _separators: config.separators && typeof config.separators === 'object' ? config.separators : null,
        _unhookResync: null,
        _modelEvents: null,
        // Whether the field was locked when last looked at. The lock lives on the field, where
        // no binding sees it change: `_watchLock()` copies each change here, and `locked` reads
        // it, so the steppers' bindings run again when a field is locked or freed later.
        _lockSeen: false,
        _stopLockWatch: null,
        _stopServerValue: null,

        init() {
            // The start value is not in `x-data`, so that the expression stays the same for every
            // value: a Livewire morph that changed it would start the component again, and the
            // answer to a `.live` change would take away the sentence about a corrected value.
            // `$root` is checked rather than assumed, as a unit harness hands a bare stub.
            if (config.value === undefined) {
                const raw = typeof this.$root?.getAttribute === 'function' ? this.$root.getAttribute(WK_SERVER_VALUE_ATTRIBUTE) : null;
                const seed = raw === null || raw === '' ? Number.NaN : Number(raw);

                this.value = Number.isNaN(seed) ? (this.min ?? 0) : seed;
            }

            // A start value the server changes reaches the steppers. With a caller's model the
            // model owns the field and holds what the server sent, unless the reader typed since,
            // so the field is read; without one, the value is the server's to set.
            this._stopServerValue = observeServerValue(this.$root, (raw) => {
                const next = raw === '' ? Number.NaN : Number(raw);

                if (Number.isNaN(next)) {
                    return;
                }

                if (this.bound) {
                    this.$nextTick(() => this.syncFromInput());

                    return;
                }

                if (next !== this.value) {
                    this.value = next;
                }
            });

            // Before the return below: an unbound field is locked and freed the same way.
            this._stopLockWatch = this._watchLock();

            if (!this.bound) {
                return;
            }

            this._modelEvents = watchModelEvents(this.$root, () => this._input());

            // The caller's model writes the field while Alpine starts the elements below
            // this one, which is after this runs, so the first read waits a tick.
            this.$nextTick(() => this.syncFromInput());

            // The same signal the slider resyncs on: a server-side change is a property
            // write on the element, which fires no event and mutates no attribute.
            if (typeof window !== 'undefined' && window.Livewire?.hook) {
                this._unhookResync = window.Livewire.hook('commit', ({ succeed }) => {
                    succeed(() => queueMicrotask(() => this.syncFromInput()));
                });
            }
        },

        destroy() {
            if (this._unhookResync) {
                this._unhookResync();
                this._unhookResync = null;
            }

            this._modelEvents?.dispose();
            this._modelEvents = null;

            this._stopLockWatch?.();
            this._stopLockWatch = null;

            this._stopServerValue?.();
            this._stopServerValue = null;
        },

        /**
         * Follow what locks the field after the page loaded: its own `disabled` and `readonly`,
         * which a binding, `wire:loading.attr` or a Livewire render can set, and the fieldsets
         * around it (utils/fieldset-disabled.js). Each change is copied to `_lockSeen`. Without
         * `MutationObserver` the field's own state is read once, at the start.
         *
         * @returns {Function} stops following; called from destroy()
         */
        _watchLock() {
            const el = this._input();
            const look = () => {
                this._lockSeen = !!el && (controlIsDisabled(el) || el.readOnly === true);
            };
            // Reports at once, which is the first look, and again whenever a fieldset changes.
            const stopFieldsets = watchFieldsetDisabled(this.$root, look);

            if (!el || typeof MutationObserver !== 'function') {
                return stopFieldsets;
            }

            const observer = new MutationObserver(look);
            observer.observe(el, { attributes: true, attributeFilter: ['disabled', 'readonly'] });

            return () => {
                observer.disconnect();
                stopFieldsets();
            };
        },

        /**
         * The field. Looked up rather than referenced: with the optimistic layer the
         * input sits inside that layer's own Alpine root, where a ref would register on
         * the layer and never reach this component.
         */
        _input() {
            return this.$root?.querySelector?.('input[type="number"]') ?? null;
        },

        /**
         * The reader typed: the sentence about the last correction no longer describes the
         * field. Only a trusted event clears it, because writing a corrected value back to a
         * bound field fires `input` too, and that one must not take the sentence away again.
         */
        onTyped(event) {
            if (event?.isTrusted) {
                this.adjustment = '';
            }

            if (this.bound) {
                this.syncFromInput();
            }
        },

        /** Mirror what the field holds. An empty or unreadable field keeps the last number. */
        syncFromInput() {
            const el = this._input();

            if (!el || el.value === '') {
                return;
            }

            const next = Number(el.value);

            if (!Number.isNaN(next) && next !== this.value) {
                this.value = next;
            }
        },

        /**
         * Write the stepper's value to a bound field, and fire what a native stepper
         * fires: `input` for a model that listens to it, `change` for one bound to change.
         */
        _writeThrough() {
            const el = this.bound ? this._input() : null;

            if (!el || el.value === String(this.value)) {
                return;
            }

            el.value = String(this.value);
            el.dispatchEvent(new Event('input', { bubbles: true }));

            // Through the model events once they are armed, so that leaving the component
            // afterwards does not fire `change` a second time.
            if (this._modelEvents) {
                this._modelEvents.commit();
            } else {
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },

        /** Clamp a bound field on leaving it, and tell the model only if that changed it. */
        clampInput() {
            const el = this._input();

            if (!el) {
                return;
            }

            const typed = el.value;

            this.value = this.clamp(typed);
            this._writeThrough();
            this.adjustment = this._adjustmentFor(typed, this.value);
        },

        /** Leave a field without a caller's model: the same normalizing, and the same sentence. */
        leaveField(typed) {
            this.value = this.clamp(typed);
            this.adjustment = this._adjustmentFor(typed, this.value);
        },

        /**
         * The sentence for a value that leaving the field changed, or '' when it kept what was
         * typed.
         *
         * Leaving holds the value to `min`, `max` and the precision of the step, and an emptied
         * field becomes `min` or 0. That corrected the reader's work without a word: 150 became
         * 99 in a quantity field, and the form sent 99 (WCAG 3.3.1). The sentence names the new
         * value and, when a bound set it, which bound, so the reader learns the rule as well
         * (3.3.3). A number typed with trailing zeros, which the field reads as the same value,
         * is not a change.
         */
        _adjustmentFor(typed, next) {
            const text = String(typed ?? '').trim();
            const raw = Number(text);
            const readable = text !== '' && !Number.isNaN(raw);

            if (readable && raw === next) {
                return '';
            }

            // Written as the field shows a number, in the locale's decimal mark and without grouping,
            // with every place the value has: a bound may carry more places than the step.
            const value = this._locale === null ? String(next) : scaleText(next, this._locale, decimalPlaces(next), this._separators);
            const fill = (template, fallback) => (typeof template === 'string' && template !== '' ? template : fallback)
                .replace(':value', value);

            if (readable && this.max !== null && raw > this.max) {
                return fill(this._adjusted.highest, 'Set to :value, the highest value allowed.');
            }

            if (!Number.isNaN(raw) && this.min !== null && raw < this.min) {
                return fill(this._adjusted.lowest, 'Set to :value, the lowest value allowed.');
            }

            return fill(this._adjusted.changed, 'Set to :value.');
        },

        /**
         * Decimal places a value of this field can need: the most that the step and the bounds
         * carry. `5` → 0, `0.1` → 1, `0.01` → 2, and a step below 0.000001, which `String()`
         * writes with an exponent (`1e-8`), counts its places as well. The grid starts at `min`,
         * so `min` 0.99 with step 1 needs two places to reach 1.99, and a value clamped to `max`
         * keeps the places `max` has. Integer steps and bounds stay exact.
         */
        get precision() {
            return decimalPlaces(this.step, this.min ?? 0, this.max ?? 0);
        },

        /**
         * toFixed() returns a string; Number() converts it back, so the field
         * shows 19.02 rather than 19.020000000000003.
         */
        round(n) {
            return Number(n.toFixed(this.precision));
        },

        get atMin() {
            return this.min !== null && this.value <= this.min;
        },

        get atMax() {
            return this.max !== null && this.value >= this.max;
        },

        /**
         * Whether the field is out of use, disabled or read-only. Read from the field
         * itself rather than kept as a flag, because Livewire can set either state after
         * the page loaded: the buttons read it through their bindings, and each step
         * checks it again when it runs. Disabled includes a disabled fieldset around the
         * field, which its own `disabled` does not report (utils/fieldset-disabled.js).
         */
        get locked() {
            const el = this._input();

            // `_lockSeen` first, so a binding on `locked` depends on it and runs again when
            // `_watchLock()` sees the lock change; the field itself answers for the moment
            // between a change and the observer's report.
            return this._lockSeen || (!!el && (controlIsDisabled(el) || el.readOnly));
        },

        /**
         * Step down to the previous grid point, anchored at `min` (or 0).
         *
         * The 1e-10 tolerance absorbs binary-float drift, so a value that is
         * on-grid in intent but stored as 1.7999999998 steps to the correct
         * neighbor rather than to itself.
         */
        decrease() {
            if (this.locked) {
                return;
            }

            this.adjustment = '';

            const origin = this.min !== null ? this.min : 0;
            const ratio = (this.value - origin) / this.step;
            const prevSteps = Math.ceil(ratio - 1e-10) - 1;
            const next = this.round(origin + prevSteps * this.step);

            this.value = this.min !== null ? Math.max(this.min, next) : next;
            this._writeThrough();
            this._commit();
        },

        /** Step up to the next grid point. The mirror of decrease(). */
        increase() {
            if (this.locked) {
                return;
            }

            this.adjustment = '';

            const origin = this.min !== null ? this.min : 0;
            const ratio = (this.value - origin) / this.step;
            const nextSteps = Math.floor(ratio + 1e-10) + 1;
            const next = this.round(origin + nextSteps * this.step);

            this.value = this.max !== null ? Math.min(this.max, next) : next;
            this._writeThrough();
            this._commit();
        },

        /**
         * Hand the value to the optimistic layer, if one is nested here.
         *
         * A stepper click IS a completed decision, so it commits at once;
         * there is nothing continuous to wait out. The FIELD commits separately,
         * when it is left, because typing is not finished until the reader is.
         *
         * No `mark()` here, and the reason is the exit rather than an oversight:
         * this component takes `failure: 'keep'`, so a refusal never writes the
         * value back and the baseline is never read. Marking a gesture whose
         * snapshot nothing consumes would be ceremony.
         *
         * `run` is looked up rather than assumed: without the layer this
         * component behaves as a plain field.
         */
        _commit() {
            if (typeof this.run === 'function') {
                this.run(this.value);
            }
        },

        /**
         * Normalize on blur — the field accepts anything a keyboard can
         * produce, including nothing at all. Rounding here too keeps the typed
         * path and the stepped path on the same precision contract.
         */
        clamp(val) {
            let v = Number(val);

            if (isNaN(v)) {
                v = this.min ?? 0;
            }

            if (this.min !== null) {
                v = Math.max(this.min, v);
            }

            if (this.max !== null) {
                v = Math.min(this.max, v);
            }

            return this.round(v);
        },
    };
}
