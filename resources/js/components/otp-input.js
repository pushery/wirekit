import { onFormReset } from '../utils/form-reset.js';
import { requiredCheckState } from '../utils/required-check.js';
import { watchModelEvents } from '../utils/model-events.js';

/**
 * One-time-code input — one box per character, with auto-advance and paste
 * distribution.
 *
 * The component's whole behavior lives in this factory rather than in an inline
 * object literal in the template: an inline literal cannot define methods under
 * Alpine's CSP build, so the component would be inert under a strict
 * Content-Security-Policy.
 *
 * ## The alphabet
 *
 * A one-time code is often deliberately not digits — dropping the ambiguous
 * pairs buys entropy per character and survives being read aloud. `alphabet`
 * drives the keystroke filter, the paste filter and the case handling together;
 * a prop that set only one of them would be worse than none, because the field
 * would accept a character it then discarded.
 *
 * Membership is tested by LOOKUP, never by a generated character class. An
 * alphabet may legitimately contain `-`, `]`, `^` or a backslash, and those are
 * exactly the characters a character class would need escaped.
 *
 * ## The bound value
 *
 * `wire:model` sits on the hidden field, and the boxes write to it. The other direction
 * holds as well: when something other than the boxes sets the bound property, the server
 * emptying it after a submit or filling it in, the boxes show the new value. Without that
 * they would keep a code the field no longer holds, and the next submit would send the
 * field's value under a row of filled boxes.
 *
 * Lifecycle resources held on `this`, each released in destroy():
 *   - _unwatch (the unwatch function of `$wire.$watch`). Livewire also releases it when the
 *     element goes; it is nulled so a second destroy() cannot call it twice.
 *   - _modelEvents — `change` and `blur` on the hidden field for `wire:model.change` and
 *     `wire:model.blur`, which listen on that field alone (utils/model-events.js): `change` when
 *     the code becomes whole and when the reader leaves after an edit, then `blur`.
 *   - _stopFormReset — the listener that brings the hidden field back to the boxes when the
 *     form is reset (utils/form-reset.js).
 *
 * @param {Object}  config
 * @param {number}  config.length    number of boxes
 * @param {string}  config.name      the hidden field's name
 * @param {string}  config.alphabet  every character the field accepts
 * @param {boolean} config.caseFold  normalize case (true when the alphabet is single-case)
 * @param {string|null} [config.model]  the Livewire property the hidden field is bound to,
 *   read from its `wire:model` attribute; null when it is bound to nothing
 * @param {boolean} [config.readonly]  the code is read-only: the boxes carry the native attribute,
 *   and the keys and the paste the factory handles itself change nothing either
 */
export default function wirekitOtpInput(config = {}) {
    return {
        _length: Number(config.length) || 6,
        _name: config.name || 'otp',
        _alphabet: config.alphabet || '0123456789',
        _caseFold: config.caseFold === true,
        // Read-only stops typing through the native attribute, which the factory's own editing
        // does not go through: it empties a box on Backspace and Delete and spreads a paste.
        _readonly: config.readonly === true,
        /** The whole code announced last, or null — see _announceCompletion().
         *  Declared rather than left implicit so the scope's shape does not
         *  depend on how far the user has typed. */
        _announced: null,

        _model: typeof config.model === 'string' && config.model !== '' ? config.model : null,
        _unwatch: null,
        _modelEvents: null,
        _stopFormReset: null,
        // The code the boxes hold, kept as state so the required check can follow it; `_sync()`,
        // `_follow()` and `_restore()` write it with the hidden field.
        enteredCode: '',
        // `requiredMessage` and `onRequiredInvalid()`: a required code stops an empty submit
        // (utils/required-check.js).
        ...requiredCheckState(),

        /**
         * Follow the bound property: its value when the field starts, and every change after.
         *
         * `$wire.$watch` reports the changes `_sync()` makes as well, since those reach the
         * property through the hidden field. They already match the boxes, and `_follow()`
         * leaves the boxes alone when they do. Outside a Livewire component `$wire.$watch` is
         * a no-op returning nothing, which is why the result is checked rather than assumed.
         */
        init() {
            // First, because the returns below are about following the property, and a field bound
            // with `.change` or `.blur` needs these events whether it follows anything or not.
            this._modelEvents = watchModelEvents(this.$root, () => this._hiddenField());
            this._stopFormReset = onFormReset(this.$root, () => this._hiddenField(), () => this._restore());

            if (this._model === null || ! this.$wire || typeof this.$wire.$watch !== 'function') {
                return;
            }

            const unwatch = this.$wire.$watch(this._model, (value) => this._follow(value));

            if (typeof unwatch !== 'function') {
                return;
            }

            this._unwatch = unwatch;

            if (typeof this.$wire.$get === 'function') {
                this._follow(this.$wire.$get(this._model));
            }
        },

        destroy() {
            if (this._unwatch) {
                this._unwatch();
                this._unwatch = null;
            }

            this._modelEvents?.dispose();
            this._modelEvents = null;
            this._stopFormReset?.();
            this._stopFormReset = null;
        },

        /**
         * After a form reset, the hidden field takes the code the boxes show again.
         *
         * The boxes are native fields, and the reset has put each back to its own default; the
         * hidden field has no default apart from its value and kept the code. Silently, as the
         * reset changes a native field without an event, and a code put back by a reset is not
         * one the reader completed.
         */
        _restore() {
            let combined = '';

            for (let i = 0; i < this._length; i++) {
                const ref = this.$refs['digit' + i];
                combined += (ref && ref.value) || '';
            }

            const hidden = this._hiddenField();

            if (hidden) {
                hidden.value = combined;
            }

            this.enteredCode = combined;
            this.requiredMessage = '';
            // A value the server set is nothing the reader completed: a whole one counts as
            // announced, so typing on does not announce it, and an empty one starts over.
            this._announced = Array.from(combined).length === this._length ? combined : null;
        },

        /** What the required check reads: empty exactly while no box holds a character. */
        get requiredValue() {
            return this.enteredCode;
        },

        /** The first empty box, or the first box, which is where an empty required code sends the focus. */
        _focusRequiredControl() {
            for (let i = 0; i < this._length; i++) {
                const ref = this.$refs['digit' + i];

                if (ref && ! ref.value) {
                    ref.focus();

                    return;
                }
            }

            this.$refs.digit0?.focus();
        },

        /**
         * The hidden field the code is bound through. It sits beside the boxes, outside this root,
         * marked by `data-wk-otp-field`, so a field without a `name` is found as well; the name
         * remains for a factory built by hand around a field of its own.
         */
        _hiddenField() {
            const parent = this.$root?.parentElement;

            if (! parent) {
                return null;
            }

            return parent.querySelector(':scope > input[data-wk-otp-field]')
                ?? parent.querySelector('input[name="' + this._name + '"]');
        },

        /**
         * Show a value the boxes did not enter.
         *
         * Compared as the assembled code, the way `_sync()` assembles it, so a row with a gap
         * in it is not rearranged when its own value comes back. The completion state follows
         * the value without announcing it: the reader did not enter this code, and an
         * announcement would submit it for them wherever an application submits on
         * completion. Focus moves only when it was already in the row, to the first empty
         * box, so a cleared code is typed again from the start.
         */
        _follow(value) {
            const text = value === null || value === undefined ? '' : String(value);
            const chars = Array.from(text).map((c) => this._normalize(c)).slice(0, this._length);

            let current = '';

            for (let i = 0; i < this._length; i++) {
                const ref = this.$refs['digit' + i];
                current += (ref && ref.value) || '';
            }

            if (chars.join('') === current) {
                return;
            }

            const active = typeof document === 'undefined' ? null : document.activeElement;
            let focusInRow = false;

            for (let i = 0; i < this._length; i++) {
                const ref = this.$refs['digit' + i];

                if (ref) {
                    focusInRow = focusInRow || ref === active;
                    ref.value = chars[i] || '';
                }
            }

            this._announced = chars.length === this._length ? chars.join('') : null;
            this.enteredCode = chars.join('');

            if (focusInRow && chars.length < this._length) {
                const target = this.$refs['digit' + chars.length];

                if (target) {
                    target.focus();
                }
            }
        },

        /** Fold a character toward the alphabet's case, if it folds at all. */
        _normalize(char) {
            if (! this._caseFold) {
                return char;
            }

            const upper = char.toUpperCase();

            return this._alphabet.includes(upper) ? upper : char.toLowerCase();
        },

        _accepts(char) {
            return this._alphabet.includes(this._normalize(char));
        },

        /**
         * Select a cell's content when it takes focus, so a filled cell behaves like
         * an empty one.
         *
         * Clicking a filled cell would put the caret after its character with nothing
         * selected, and a key typed there would land beside it. `onInput` keeps only the key
         * in that case, and the selection makes the replacement visible before it happens.
         *
         * Selecting on FOCUS rather than on click covers both ways in: a pointer, and the
         * programmatic `next.focus()` that carries typing across the row. So a corrected
         * code can be typed straight through from the left, which is what the control
         * looks like it should do.
         *
         * `select()` and not `setSelectionRange(0, 1)`: an empty cell has nothing to
         * select and `select()` is a no-op there, where a fixed range would move a caret
         * that was already in the only place it can be.
         */
        onFocus(event) {
            event.target.select();
        },

        /**
         * Keep that selection when a click caused the focus.
         *
         * WebKit finishes the click after the focus handler has run, and the click's
         * default action puts a caret where the pointer is — which collapses the
         * selection and brings back exactly the refused keystroke described above.
         * Chromium keeps the selection.
         *
         * Canceling the default of `mouseup` is what keeps it; canceling `click` does
         * not. Selecting again a moment after focus is no substitute: a frame or a short
         * timer later, WebKit still leaves the caret, and in Chromium a timer that
         * catches an instant click misses a press held as long as a person holds it.
         *
         * `select()` again here, not only the cancel: clicking a cell that already has
         * focus fires no focus event, and the press has already placed a caret.
         */
        onMouseUp(event) {
            event.preventDefault();
            event.target.select();
        },

        onInput(event, index) {
            // An input method is still composing: its text is not a character yet. The box
            // reads it once the composition ends (`onCompositionEnd`).
            if (event.isComposing) {
                return;
            }

            const raw = event.target.value;
            const key = typeof event.data === 'string' && Array.from(event.data).length === 1 ? event.data : null;

            // More than one character in one event, and not a single key, is a code that
            // arrived at once: the autofill of a message, a password manager or an input
            // assistant. It fills the boxes the way a paste does. The boxes carry no
            // `maxlength`, because WebKit cuts such an insertion down to its first character
            // before any handler sees it.
            if (Array.from(raw).length > 1 && key === null) {
                if (this._codeIn(raw) === '') {
                    event.target.value = '';
                    this._sync();

                    return;
                }

                this._fill(raw);

                return;
            }

            // A key pressed in a box that already held a character: the box takes the key.
            const typed = Array.from(raw).length > 1 ? key : raw;

            // A rejected character clears the box rather than lingering. It is
            // still a silent refusal, which is why the alphabet has to be right.
            // The hidden field follows, or it would still carry the character the
            // box showed before.
            if (typed && ! this._accepts(typed)) {
                event.target.value = '';
                this._sync();

                return;
            }

            const value = typed ? this._normalize(typed) : typed;
            event.target.value = value;

            if (value && index < this._length - 1) {
                const next = this.$refs['digit' + (index + 1)];

                if (next) {
                    next.focus();
                }
            }

            this._sync();
        },

        onKeydown(event, index) {
            if (this._readonly && (event.key === 'Backspace' || event.key === 'Delete')) {
                return;
            }

            if (event.key === 'Backspace') {
                // Backspace on an EMPTY box steps back and clears the previous
                // one — otherwise correcting a typo costs two presses per
                // character.
                if (! event.target.value && index > 0) {
                    const prev = this.$refs['digit' + (index - 1)];

                    if (prev) {
                        prev.value = '';
                        prev.focus();
                    }
                } else {
                    event.target.value = '';
                }

                this._sync();

                return;
            }

            // Delete empties the focused cell and keeps focus on it. The native key removes
            // the character after the caret, which is nothing once typing into the last cell
            // leaves the caret behind its character with no selection, so the cell is
            // cleared here wherever the caret sits.
            if (event.key === 'Delete') {
                event.target.value = '';
                this._sync();

                return;
            }

            if (event.key === 'ArrowLeft' && index > 0) {
                const prev = this.$refs['digit' + (index - 1)];

                if (prev) {
                    prev.focus();
                }

                return;
            }

            if (event.key === 'ArrowRight' && index < this._length - 1) {
                const next = this.$refs['digit' + (index + 1)];

                if (next) {
                    next.focus();
                }
            }
        },

        /**
         * A composition ended: what the input method wrote is now the box's text. Chromium and
         * WebKit send no input event after `compositionend`, so the box reads it here, the way
         * a key or a whole code is read.
         */
        onCompositionEnd(event, index) {
            this.onInput({ target: event.target, data: event.data, isComposing: false }, index);
        },

        onPaste(event) {
            event.preventDefault();

            if (this._readonly) {
                return;
            }

            this._fill(event.clipboardData ? event.clipboardData.getData('text') : '');
        },

        /** What the alphabet accepts in a text, in order: a code pasted with spaces or dashes still reads. */
        _codeIn(text) {
            return Array.from(text || '')
                .map((c) => this._normalize(c))
                .filter((c) => this._alphabet.includes(c))
                .join('');
        },

        /**
         * Spread a whole code over the boxes, from the first, whatever box it arrived in, and
         * land on the first box it did not fill, so a short code leaves the caret where typing
         * continues.
         */
        _fill(text) {
            const pasted = this._codeIn(text);

            for (let i = 0; i < this._length; i++) {
                const ref = this.$refs['digit' + i];

                if (ref) {
                    ref.value = pasted[i] || '';
                }
            }

            // Land on the first box the paste did not fill, so a short code
            // leaves the caret where typing continues.
            let firstEmpty = -1;

            for (let i = 0; i < this._length; i++) {
                const ref = this.$refs['digit' + i];

                if (! ref || ! ref.value) {
                    firstEmpty = i;
                    break;
                }
            }

            const target = this.$refs['digit' + (firstEmpty >= 0 ? firstEmpty : this._length - 1)];

            if (target) {
                target.focus();
            }

            this._sync();
        },

        /**
         * Write the assembled code to the hidden field and announce it.
         *
         * The event is not optional: Livewire syncs on it and a plain form reads
         * the DOM at submit, so assigning the value alone reaches neither.
         *
         * $root, not $el. These handlers run off the individual boxes, and a
         * lookup that starts from one of them would climb the wrong subtree.
         */
        _sync() {
            let combined = '';

            for (let i = 0; i < this._length; i++) {
                const ref = this.$refs['digit' + i];
                combined += (ref && ref.value) || '';
            }

            const hidden = this._hiddenField();

            if (hidden) {
                hidden.value = combined;
                hidden.dispatchEvent(new Event('input', { bubbles: true }));
            }

            this.enteredCode = combined;

            this._announceCompletion(combined);
        },

        /**
         * The code is COMPLETE — the commit boundary for a segmented entry.
         *
         * The commit boundary asks what event says the person is finished, and
         * for this field it is not a keystroke: `_sync()` runs on every
         * character, so anything hung on it would fire once per box. The
         * boundary is the code being whole, which is a different question from
         * the value having changed.
         *
         * Emitted as an event rather than called directly, so the component
         * still owes nothing to the optimistic layer: an application can listen
         * for it to auto-submit, which is what a one-time code usually does, and
         * a page that ignores it loses nothing.
         *
         * Bound to the code, not to the moment the boxes fill up: a whole code
         * that differs from the one announced last is announced, so a code
         * corrected by overwriting a filled box, as the field recommends, still
         * submits. The same code is never announced twice in a row, so typing
         * on in a full field, or clearing a box and typing its character back,
         * submits nothing again; a component that announced twice would submit
         * twice. Once every box is empty the next code is a new attempt, even
         * the same one.
         */
        _announceCompletion(combined) {
            const complete = combined.length === this._length;

            if (complete && combined !== this._announced) {
                this.$root.dispatchEvent(new CustomEvent('wirekit:otp-complete', {
                    detail: { value: combined },
                    bubbles: true,
                }));

                // The same boundary is the field's `change`, so `wire:model.change` hears the
                // code once it is whole.
                this._modelEvents?.commit();
            }

            if (complete) {
                this._announced = combined;
            } else if (combined === '') {
                this._announced = null;
            }
        },
    };
}
