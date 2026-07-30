/**
 * One-time-code input — one box per character, with auto-advance and paste
 * distribution.
 *
 * ## Why this file was replaced rather than edited
 *
 * A `wirekitOtpInput` factory was registered in every bundle and called by
 * nothing: the template carried its own inline copy, and that copy is what ran.
 * The two had drifted, and the template's was the newer — it had the alphabet
 * support that shipped in v2.22.0, which this file never learned. So the module
 * a reader would open to understand the component described a version that had
 * not existed for a while, and still only accepted digits.
 *
 * The template's copy has moved here whole. It had to: an inline object literal
 * cannot define methods under Alpine's CSP build, so the component was inert
 * under a strict Content-Security-Policy.
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
 * @param {Object}  config
 * @param {number}  config.length    number of boxes
 * @param {string}  config.name      the hidden field's name
 * @param {string}  config.alphabet  every character the field accepts
 * @param {boolean} config.caseFold  normalize case (true when the alphabet is single-case)
 */
export default function wirekitOtpInput(config = {}) {
    return {
        _length: Number(config.length) || 6,
        _name: config.name || 'otp',
        _alphabet: config.alphabet || '0123456789',
        _caseFold: config.caseFold === true,
        /** Whether the code was already whole on the previous sync — see
         *  _announceCompletion(). Declared rather than left implicit so the
         *  scope's shape does not depend on how far the user has typed. */
        _wasComplete: false,

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

        onInput(event, index) {
            const raw = event.target.value;

            // A rejected character clears the box rather than lingering. It is
            // still a silent refusal, which is why the alphabet has to be right.
            if (raw && ! this._accepts(raw)) {
                event.target.value = '';

                return;
            }

            const value = raw ? this._normalize(raw) : raw;
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

        onPaste(event) {
            event.preventDefault();

            // Keep only what the alphabet accepts, so a code pasted with spaces
            // or dashes still fills the boxes.
            const text = event.clipboardData ? event.clipboardData.getData('text') : '';
            const pasted = Array.from(text || '')
                .map((c) => this._normalize(c))
                .filter((c) => this._alphabet.includes(c))
                .join('');

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

            const parent = this.$root.parentElement;
            const hidden = parent ? parent.querySelector('input[name="' + this._name + '"]') : null;

            if (hidden) {
                hidden.value = combined;
                hidden.dispatchEvent(new Event('input', { bubbles: true }));
            }

            this._announceCompletion(combined);
        },

        /**
         * The code is COMPLETE — the commit boundary for a segmented entry.
         *
         * §10 asks what event says the person is finished, and for this field it
         * is not a keystroke: `_sync()` runs on every character, so anything
         * hung on it would fire once per box. The boundary is the code being
         * whole, which is a different question from the value having changed.
         *
         * Emitted as an event rather than called directly, so the component
         * still owes nothing to the optimistic layer: an application can listen
         * for it to auto-submit, which is what a one-time code usually does, and
         * a page that ignores it behaves exactly as before.
         *
         * Guarded against repeating: backspacing out of a full code and retyping
         * the last character is one completion, not two, and a component that
         * announced twice would submit twice.
         */
        _announceCompletion(combined) {
            const complete = combined.length === this._length;

            if (complete && ! this._wasComplete) {
                this.$root.dispatchEvent(new CustomEvent('wirekit:otp-complete', {
                    detail: { value: combined },
                    bubbles: true,
                }));
            }

            this._wasComplete = complete;
        },
    };
}
