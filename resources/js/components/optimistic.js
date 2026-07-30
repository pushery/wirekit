/**
 * Optimistic UI — show the result before the server has agreed to it.
 *
 * The whole point is the gap between a click and a round trip. The value flips
 * immediately, and one of four things happens afterwards. Three of them are
 * ordinary; the fourth is the reason this file is careful.
 *
 * **A cancel is not a failure.** Livewire's legacy `failed` channel fired for an
 * aborted request and a rejected one alike — it does not exist in v4.3.3 at all —
 * so an implementation built on it announces "could not save" for something that
 * was never refused. Nothing was denied, so nothing is said: the value goes back
 * and the state returns to `idle`, not to `rolled-back`. That difference is
 * invisible in the value and audible only in the announcement.
 *
 * The wiring is `$wire.$intercept(action, callback)`, measured in the shipped
 * bundle rather than taken from the plan: it is scoped to THIS component and
 * THIS action already, and hands each fire its own outcome callbacks. So there
 * is no id to correlate and nothing smuggled into the request payload.
 *
 * Lifecycle resources held on `this`, every one released in destroy():
 *   - _unintercept   the unhook $intercept returns
 *   - _unmorph       the unhook Livewire.hook('morph.updating') returns
 *   - _baseline      the pre-optimistic value; released in onFinish, which is
 *                    the only callback that runs on all four exits
 *
 * Two announcement rules shape the code below, and both are deliberate:
 *
 *   1. The success path speaks ONCE, at the optimistic flip, hedged in the verb
 *      ("Liked, saving"). Confirmation is silent — what was announced is what
 *      happened. Only a deviation speaks a second time, and that is what gives
 *      the rollback its meaning: a second announcement IS the signal.
 *   2. Where the component already renders an error live region carrying a
 *      message, this layer stays silent on failure. "Email is required" tells
 *      the user what to do; "could not save" does not, and the generic one must
 *      not pre-empt the specific one.
 *
 * @param {Object}  config
 * @param {*}       config.value      the server's value at render time
 * @param {string}  config.action     the Livewire method this component fires
 * @param {string}  [config.mode]     'queue' (default) or 'reject'
 * @param {Object}  [config.messages] { pending, reverted } — already translated
 * @param {string}  [config.errorRegion] selector of the field's own error region
 */
import { devWarn } from '../utils/dev-warning.js';

/**
 * The names this layer uses for itself, which a `bind` therefore cannot use.
 *
 * Kept beside the object rather than inside it so the list is readable as a
 * list, and so adding a property to the layer puts the reservation in view.
 * Everything prefixed `_` is reserved too, checked by prefix rather than
 * enumerated here.
 *
 * `current` is NOT here, and its absence was earned. This layer used to expose a
 * `current` getter as a convenience alias for the bound value — no template ever
 * bound to it, and it silently shadowed any component property of the same name.
 * Two components call their value `current` (slider, color-picker), which is the
 * obvious name for it, and the shadowing was invisible from both sides: reading
 * it recursed until the stack gave out, and writing it hit a getter with no
 * setter and did nothing at all. Deleting the alias is what makes `current` an
 * ordinary bindable name.
 *
 * `value` is not here either, and for the opposite reason: it is the DEFAULT
 * binding, declared only when the config hands one over.
 */
const RESERVED_NAMES = [
    'state', 'announcement', 'isPending',
    'bind', 'action', 'after', 'mode', 'messages', 'failure', 'errorRegion', 'args', 'debug',
];

export default function wirekitOptimistic(config = {}) {
    // A string names one property; an array names a TUPLE read and written as
    // one value (§10 — a range is one value, not two). Anything else falls back
    // to the default rather than arming against a name that is not a name.
    const bind = Array.isArray(config.bind) && config.bind.length > 0
        ? config.bind.slice()
        : (typeof config.bind === 'string' && config.bind !== '' ? config.bind : 'value');

    return {
        // `value` is declared ONLY when the config actually hands one over.
        //
        // That is the test for ownership, and it is better than asking whether
        // `bind` is the default: a component whose own state is called `value` —
        // number-input is one — binds to `value` and does NOT pass one, because
        // the property already exists on the component this layer nests inside.
        // Declaring it anyway would shadow the parent's, since a child's key
        // wins, and the component would find its state replaced by undefined
        // with nothing to say why.
        ...(config.value !== undefined ? { value: config.value } : {}),

        state: 'idle',
        announcement: '',

        action: config.action || null,
        // WHERE the value lives. Default `value`, which the factory then owns
        // itself — that is the toggle case, whose value is a native checkbox.
        //
        // A component that already owns its state names its own property here
        // instead, and the optimistic layer nests INSIDE it: measured in a
        // browser, a nested Alpine component's method reads and writes its
        // parent's properties through `this`, and only in that direction. The
        // stateful component outside, this layer inside, the controls within
        // this layer — any other arrangement satisfies exactly one of the two
        // requirements and looks identical until the first rollback.
        bind,
        // Optional method to call after EVERY write, including the rollback.
        // rating needs it: its _notify() keeps a hidden form field in step, and
        // without the call a plain HTML form would submit the value that was
        // rolled back.
        after: typeof config.after === 'string' && config.after !== '' ? config.after : null,
        mode: config.mode === 'reject' ? 'reject' : 'queue',
        messages: config.messages || {},
        /**
         * What a refusal does to the value: 'undo' (default) or 'keep' (§8).
         *
         * Normalized to the two known words rather than passed through — an
         * unrecognized value must not silently disarm the rollback, which is
         * the safe exit for every discrete control. A typo becomes 'undo', not
         * "keep the user's value and hope".
         */
        failure: config.failure === 'keep' ? 'keep' : 'undo',
        errorRegion: config.errorRegion || null,
        args: config.args || [],
        // Passed in rather than read here: the house rule is that a developer
        // warning is gated in Blade on config('app.debug') and does not reach a
        // production page at all.
        debug: config.debug === true,

        _bindMissing: false,
        _baseline: null,
        _inFlight: 0,
        _unintercept: null,
        _unmorph: null,
        _marked: false,
        _markBaseline: null,

        init() {
            // Marks the subtree as carrying a provisional state, so the stylesheet can
            // give the in-flight control a VISIBLE treatment.
            //
            // Rule 6 of this layer's own contract says "the pending state has to be
            // visible and legible", and it was not: `aria-busy` was bound and nothing in
            // the shipped stylesheet painted it, so the provisional state was audible to
            // a screen reader and invisible to everyone else. A sighted user flipped a
            // toggle and saw a finished change — which is the exact promise the contract
            // says must be shown as withdrawable.
            //
            // Stamped by the FACTORY rather than added to each of the twenty-three
            // templates. The factory is the one place that knows a subtree is optimistic;
            // a per-template marker is twenty-three chances to forget, and a
            // hand-mounted factory would never get one at all.
            // Optional chaining because `$el` is an Alpine magic that a plain unit harness
            // does not provide. Without it `init()` throws there, and the failure names
            // setAttribute rather than anything about the layer — which is how the JS suite
            // went red on a change that was correct in a browser. The marker is still
            // asserted in that harness against an $el double, so the guard against crashing
            // does not weaken the guard.
            this.$el?.setAttribute('data-wk-optimistic', '');

            // The factory owns the value only in the default binding. With an
            // explicit bind, the property already exists on the component and
            // seeding it here would overwrite what the server rendered.
            // A bind that names nothing is the silent-typo case: without this
            // it is indistinguishable from a component that simply never fires.
            // Every name is checked, not just the first. A tuple bind whose
            // second half is a typo would otherwise arm, read undefined for
            // that half, and roll back to undefined — silently, and only on
            // failure, which is the worst moment to discover it.
            const names = Array.isArray(this.bind) ? this.bind : [this.bind];

            // A bind that names one of this layer's OWN properties is not a
            // binding but a collision, and it has to be caught BEFORE the
            // in-scope check below — `'current' in this` is true precisely
            // because the collision exists.
            //
            // A bind on `state` would treat this layer's own state as the user's
            // value and write over it on the next flip — wrong in a way nothing
            // reports, which is why a loud stop is better than the silence.
            //
            // The names that USED to be fatal are gone rather than guarded: see
            // the note on RESERVED_NAMES for why `current` is an ordinary
            // bindable name now.
            const reserved = names.filter((name) => RESERVED_NAMES.includes(name) || String(name).startsWith('_'));

            if (reserved.length > 0) {
                if (this.debug) {
                    devWarn(
                        `optimistic: bind names ${reserved.map((n) => `"${n}"`).join(', ')}, `
                        + 'which this layer already uses for itself. '
                        + 'Binding to it would read this layer instead of your value — and "current" would '
                        + 'read itself until the stack gives out. Rename the property on your component, or '
                        + 'let the layer own the value by passing `value` in the config instead of `bind`.'
                    );
                }

                this._bindMissing = true;

                return;
            }

            const missing = names.filter((name) => ! (name in this));

            if (missing.length > 0) {
                if (this.debug) {
                    devWarn(
                        `optimistic: bind names ${missing.map((n) => `"${n}"`).join(', ')}, `
                        + 'which is not in scope. '
                        + 'The control will not change state. Check the name, and that this layer '
                        + 'is nested INSIDE the component that owns the property.'
                    );
                }

                this._bindMissing = true;

                return;
            }

            // Take the initial value from the CONTROL when there is one.
            //
            // The config carries what the server rendered, which is right for
            // `:checked` and wrong for `wire:model` — there the attribute never
            // reaches this component, so the config says false while the box is
            // checked. The binding would then immediately uncheck it. The
            // element itself is the one source that is correct under both.
            const control = this.$refs && this.$refs.control;

            // Only a checkbox or a radio reports its state in `checked`.
            // `'checked' in element` is true for EVERY <input> — it is a
            // property of HTMLInputElement, not of the checkbox type — so this
            // used to adopt `false` as the initial value of every text, time and
            // date field. The blanket test caught it on the first non-checkbox
            // control that went through.
            if (control && (control.type === 'checkbox' || control.type === 'radio')) {
                this.value = control.checked;
            }

            if (! this.action || ! this.$wire?.$intercept) {
                return;
            }

            this._unintercept = this.$wire.$intercept(this.action, (hooks) => this._onFire(hooks));
        },

        destroy() {
            if (this._unintercept) {
                this._unintercept();
                this._unintercept = null;
            }

            this._releaseMorphGuard();
            this._baseline = null;
            this._marked = false;
            this._markBaseline = null;
        },

        /** True while the server has not answered — drives aria-busy. */
        get isPending() {
            return this.state === 'optimistic';
        },

        /**
         * The gesture has STARTED, though its commit comes later.
         *
         * §10 puts the commit at the end of a gesture, and for a control whose
         * value MOVES during that gesture — a slider thumb following a finger,
         * an arrow key that steps before it commits — the two are not the same
         * moment. By the time `apply()` runs, the property already holds the new
         * value, so the snapshot it takes is the new value, and the rollback
         * restores what it just replaced. That failure is invisible in exactly
         * the wrong way: the thumb stays where the user put it, and a refusal
         * the server sent looks like it was accepted.
         *
         * The component knows when the gesture began; this layer cannot see it.
         * So the component says so here, and `apply()` prefers what was marked
         * over what it can read.
         *
         * Ignored mid-burst: that burst already holds the baseline a rollback
         * has to return to, and re-marking would move it to a value the server
         * never had — the same rule `apply()` states for its own snapshot.
         *
         * @returns {boolean} whether this call took a mark
         */
        mark() {
            if (this._inFlight > 0) {
                return false;
            }

            this._markBaseline = this._snapshot();
            this._marked = true;

            return true;
        },

        /**
         * Show `next` now and let the caller fire the action.
         *
         * Returns false when the flip was refused, so a template can tell the
         * two apart. `reject` exists for controls whose final state would
         * otherwise depend on the ORDER responses come back in — a toggle
         * flipped twice resolves by network timing, which is both wrong and
         * untestable.
         */
        apply(next) {
            if (this.mode === 'reject' && this._inFlight > 0) {
                return false;
            }

            // One baseline per BURST, not per action: a rollback has to return
            // to the value before the first flip. Rolling back to a mid-flight
            // optimistic value would restore something the server never had.
            // The snapshot is taken at the START of a burst, and it is a COPY.
            //
            // A set-valued component (multi-select, tags-input) mutates its
            // array in place, and a snapshot that kept the reference would
            // mutate with it — the rollback would then run, put back exactly
            // what was already there, and look like it worked.
            const first = this._inFlight === 0;

            if (first) {
                // What was marked at the gesture's START wins over what can be
                // read now — for a control that moves during the gesture, "now"
                // is already the new value. See mark().
                this._baseline = this._marked ? this._markBaseline : this._snapshot();
            }

            // Consumed either way. A mark that outlived its commit would become
            // the baseline of an unrelated later burst.
            this._marked = false;
            this._markBaseline = null;

            this._write(next);
            this.state = 'optimistic';
            this._installMorphGuard();

            // Announced once per BURST, not once per change. A debounced field
            // changes many times per interaction and one announcement each
            // would be a barrage; the contract says the flip speaks once. The
            // next burst speaks again — coalescing must not become silence.
            if (first) {
                this._announce(this.messages.pending);
            }

            return true;
        },

        /**
         * Flip and fire, in one call.
         *
         * This exists because a CSP directive holds exactly ONE expression:
         * `@click="apply(true); $wire.save()"` is two statements and Alpine's
         * CSP parser refuses the whole attribute — which does not fail loudly,
         * it leaves the element with an empty scope so every directive on it
         * silently does nothing. So the sequencing lives here, where it is
         * ordinary JavaScript.
         *
         * The order matters and is not interchangeable: the flip happens
         * BEFORE the fire, because the interceptor runs synchronously inside
         * $wire[action]() and reads the state this call just wrote.
         */
        /** Read the value through the binding — the ONLY place that reads it. */
        /**
         * The bound value — one property, or a TUPLE of them read as one value.
         *
         * `bind` accepts an array because §10 says a range is one value, not
         * two: snapshot the pair, restore the pair, and let an unchanged half be
         * a no-op. Per-half bookkeeping would create exactly the divergence it
         * was meant to prevent.
         *
         * A tuple rather than a getter/setter pair on the component, and that
         * choice is deliberate: an accessor would depend on HOW the scopes get
         * merged — `Object.assign` flattens a getter into a plain value and
         * drops the setter, so a rollback would write a property nothing reads.
         * Two plain property names cannot be flattened into something else.
         */
        _read() {
            // A disarmed layer has no value, and saying so HERE is what makes
            // the disarming safe rather than merely correct: with a bind on
            // `current`, `this[this.bind]` re-enters the getter that called
            // this, and the recursion ends the page rather than the read. The
            // guard in init() stops the layer from firing; this stops it from
            // being readable into a blown stack.
            if (this._bindMissing) {
                return undefined;
            }

            if (Array.isArray(this.bind)) {
                return this.bind.map((name) => this[name]);
            }

            return this[this.bind];
        },

        /**
         * The value as it is NOW, detached from what happens to it next.
         *
         * Shallow is enough and deliberately so: the components that hold a
         * collection hold a flat one, and a deep clone would quietly turn a
         * rollback into an object-identity change that every watcher would see
         * as a new value even when nothing differed.
         */
        _snapshot() {
            const value = this._read();

            if (Array.isArray(value)) {
                return value.slice();
            }

            if (value && typeof value === 'object' && Object.getPrototypeOf(value) === Object.prototype) {
                return { ...value };
            }

            return value;
        },

        /**
         * Write the value through the binding, then tell the component.
         *
         * Snapshot and write go through _read/_write for a reason worth stating:
         * if one of them reached past the binding, every rollback would restore
         * a value the user never saw, and nothing would look wrong until it did.
         */
        _write(value) {
            if (Array.isArray(this.bind)) {
                // Positional, and the length is the binding's — a short answer
                // from the server must not silently drop the last half of a
                // range, and a long one must not write a property nobody named.
                this.bind.forEach((name, i) => {
                    this[name] = value[i];
                });
            } else {
                this[this.bind] = value;
            }

            if (this.after && typeof this[this.after] === 'function') {
                this[this.after]();
            }
        },

        /**
         * Run, unless there is nothing to run WITH.
         *
         * For the shape where the trigger may fire with no candidate — Enter on
         * a listbox with nothing highlighted, a keyboard activation before the
         * user has moved — `run(undefined)` would send the server a request for
         * a value nobody chose and then roll back from it.
         *
         * `undefined` means "no candidate"; `null` is a real choice meaning
         * "none of them", and it goes through. The two are deliberately not the
         * same: a combobox's clear button chooses nothing, which is a mutation,
         * while Enter on an empty highlight chose nothing at all.
         */
        runIf(next) {
            if (next === undefined) {
                return false;
            }

            return this.run(next);
        },

        run(next) {
            if (this._bindMissing) {
                return false;
            }

            // Checked BEFORE the flip, not after. Without Livewire — or before
            // it has booted — $wire[action] is not callable, and applying first
            // would leave the value showing a change that nothing was ever asked
            // to make. Silent is the one thing it must not be: a control that
            // does nothing and says nothing is precisely the failure this file
            // is careful about everywhere else.
            if (typeof this.$wire?.[this.action] !== 'function') {
                if (this.debug) {
                    devWarn(
                        `optimistic: no Livewire method "${this.action}" on this component. `
                        + 'The control will not change state. Check the method name, and that Livewire is loaded.'
                    );
                }

                return false;
            }

            if (! this.apply(next)) {
                this._resyncControl();

                return false;
            }

            // The new value goes to the server as the first argument.
            //
            // A component that carries a value has one to send, and a method
            // that does not want it simply does not declare the parameter —
            // PHP binds what it declares and ignores the rest. Deriving it
            // server-side instead would mean every optimistic method had to
            // recompute what the client already decided, and the two could
            // disagree.
            this.$wire[this.action](next, ...(this.args || []));

            return true;
        },

        /** The toggle case, so a template does not need a negation expression. */
        toggle() {
            return this.run(! this._read());
        },

        /**
         * Take the value from the native control and run with it.
         *
         * Every native control reports its value differently — a checkbox in
         * `checked`, everything else in `value` — and a template cannot ask
         * which: under Alpine's CSP build a directive holds one expression and
         * `$el.value` is a member access on a magic, which is exactly the kind
         * of thing that parses in the default build and fails in the strict one.
         * So the reading happens here, where it is ordinary JavaScript, and the
         * directive says only `commitFromControl()`.
         */
        commitFromControl() {
            const el = this.$refs && this.$refs.control;

            if (! el) {
                return false;
            }

            const next = el.type === 'checkbox' || el.type === 'radio' ? el.checked : el.value;

            return this.run(next);
        },

        /** Bind this fire's outcomes. Runs synchronously inside $wire.method(). */
        _onFire({ onSuccess, onFailure, onError, onCancel, onFinish }) {
            this._inFlight += 1;

            // Confirmation waits for the LAST fire of the burst. With `queue`,
            // an earlier response arriving first does not make the value the
            // server has — a second flip is still out, and clearing aria-busy
            // there would tell the user it landed while it had not.
            onSuccess(() => { if (this._inFlight <= 1) this._settle('confirmed'); });

            // A rejection voids the whole burst, whatever else is still out: the
            // server disagreed with the premise the later flips were built on.
            // Which failure exit this component uses is a property of the
            // component, not of the failure: a text field keeps its value
            // whether the server refused it or the network dropped.
            const failureExit = this.failure === 'keep' ? 'rejected' : 'rolled-back';

            onFailure(() => this._settle(failureExit));
            onError(() => this._settle(failureExit));

            // Silent, and back to idle: an abort refused nothing. Only the last
            // one restores — an abort in the middle of a burst leaves the flips
            // that were not aborted standing.
            onCancel(() => { if (this._inFlight <= 1) this._settle('idle'); });

            onFinish(() => {
                this._inFlight = Math.max(0, this._inFlight - 1);

                if (this._inFlight === 0) {
                    this._baseline = null;
                    this._releaseMorphGuard();
                }
            });
        },

        _settle(next) {
            // `rejected` is the fourth exit (§8): the value STAYS. It is the
            // only failure state that does not write, because for a typed value
            // the previous one belongs to the server and the new one is the
            // user's work — restoring it would delete what they just wrote
            // because a save failed, which no editor does.
            const restores = next !== 'confirmed' && next !== 'rejected';

            if (restores && this._baseline !== null) {
                this._write(this._baseline);
            }

            this.state = next;

            if (restores) {
                this._resyncControl();
            }

            if (next === 'rejected') {
                // Same arbitration as a rollback: where the field's own error
                // region is speaking, it says something specific and this layer
                // says something generic.
                if (! this._errorRegionIsSpeaking()) {
                    this._announce(this.messages.kept);
                }
            }

            if (next === 'rolled-back') {
                // Arbitration lives HERE and not in _announce, because §2 is
                // about the FAILURE case. At the optimistic flip there is no
                // error message yet, so there is nothing to pre-empt and the
                // hedge should be heard; at a rollback the field's own message
                // is the actionable one and this layer's is generic.
                if (! this._errorRegionIsSpeaking()) {
                    this._announce(this.messages.reverted);
                }
            }
        },

        /** Write into the live region. Arbitration is the caller's decision. */
        _announce(text) {
            if (! text) {
                return;
            }

            // Clear first: writing an identical string into a live region
            // changes nothing, so a repeated message would never be spoken.
            this.announcement = '';
            queueMicrotask(() => { this.announcement = text; });
        },

        /**
         * Put a native control back in step with the value.
         *
         * A checkbox flips ITSELF when clicked, before any handler runs, and an
         * Alpine binding only writes when the bound value CHANGES. So when a
         * flip is refused — `reject` mode with one already in flight — the value
         * never changed, the binding never fired, and the control sits there
         * showing a state nothing agreed to. Nothing is wrong in the data and
         * everything is wrong on screen.
         *
         * Harmless on the rollback path (the binding handles that one) and
         * deliberately kept anyway: the cost is one comparison, and the failure
         * it prevents is silent.
         */
        _resyncControl() {
            const el = this.$refs && this.$refs.control;

            if (el && 'checked' in el && el.checked !== !! this._read()) {
                el.checked = !! this._read();
            }
        },

        /**
         * Is a field's own error region carrying a message right now?
         *
         * ACTIVE means carrying text, not merely present. An empty error region
         * is not a speaker, and a presence check would silence this layer while
         * nothing else is talking — which is the common case, since eighteen
         * form controls render the region and announce_error defaults to on.
         *
         * "Email is required" tells the user what to do; "could not save" does
         * not, and WCAG 3.3.1 wants the specific one to be the one heard.
         */
        _errorRegionIsSpeaking() {
            if (! this.errorRegion || typeof document === 'undefined') {
                return false;
            }

            const region = document.querySelector(this.errorRegion);

            if (! region || region.textContent.trim() === '') {
                return false;
            }

            // It also has to BE a live region. A component whose announce-error
            // is switched off still renders the message — same element, same id,
            // no aria-live — and then nothing is speaking it. Yielding there
            // would silence the only layer left, which is the opposite of what
            // §2 is for: it protects a message that is being announced, not a
            // message that merely exists.
            return region.hasAttribute('aria-live') || region.getAttribute('role') === 'alert' || region.getAttribute('role') === 'status';
        },

        /**
         * Livewire's morph would paint the server's stale render over the
         * optimistic value. The skip must be LIFTED on the way out of
         * `optimistic` — a permanent skip blocks the truth forever, including
         * the rollback that was supposed to undo the flip.
         */
        _installMorphGuard() {
            if (this._unmorph || typeof window === 'undefined' || ! window.Livewire?.hook) {
                return;
            }

            this._unmorph = window.Livewire.hook('morph.updating', ({ el, skip }) => {
                if (el === this.$el) {
                    skip();
                }
            });
        },

        _releaseMorphGuard() {
            if (this._unmorph) {
                this._unmorph();
                this._unmorph = null;
            }
        },
    };
}
