/**
 * Phone field — a country and a number that together bind one E.164 value.
 *
 * Two focusable things, one value. The country control and the number box are separate tab
 * stops inside one group, and everything awkward about this component comes from that: what is
 * SHOWN is a familiar local number, what is BOUND is E.164, and they are different strings.
 *
 * The assembly rule lives in PHP, in Support\DialingCodes::toE164(). This mirrors it so the
 * field can answer while somebody is still typing, and a browser check holds the two against
 * each other. Two copies of one rule drift; a test that runs both is the only defense, and
 * saying so here is the reminder that this file is a MIRROR rather than the original.
 *
 * What it deliberately does not do:
 *
 *   - It never rewrites what is in the box. A half-typed number is the normal state of this
 *     field for as long as it is in use, and a field that reformats mid-word moves the caret
 *     out from under the next keystroke. Layer A does no grouping at all, so the question does
 *     not arise yet; the comment is here because the first person to add grouping needs it.
 *   - It never decides whether a number is reachable. That needs per-region length data this
 *     package does not carry.
 *
 * @param {Object} config
 * @param {string} config.country   ISO 3166-1 alpha-2 code the field starts on
 * @param {Object} config.regions   code -> { dial: number, trunk: string|null } for every offered region
 * @param {string} config.value     an existing E.164 value to start from, or ''
 */
export default function wirekitPhone(config = {}) {
    return {
        country: String(config.country || '').toUpperCase(),

        /** What is in the box. Exactly what was typed, never rewritten. */
        national: '',

        _regions: config.regions && typeof config.regions === 'object' ? config.regions : {},

        init() {
            // An incoming E.164 value is split back into a country and a national part, so a
            // round-trip through a form does not degrade. Without this, editing a saved record
            // shows the raw +4915… in a box whose label says "phone number".
            const incoming = String(config.value == null ? '' : config.value);

            if (incoming.startsWith('+')) {
                const match = this._regionForE164(incoming);

                if (match) {
                    this.country = match.region;
                    this.national = match.rest;
                } else {
                    // A value whose country code is not among the offered regions is shown AS IS
                    // rather than silently reassigned to the default country. The reader then
                    // sees the number they stored; a quiet reassignment would change what the
                    // form submits without anyone touching the field.
                    this.national = incoming;
                }
            }

            // Armed LAST, and the `else` above exists for it. The two assignments in that branch
            // are the round-trip split rather than a reader's choice: a watcher armed before them
            // fires on page load, emitting an input event and moving focus on a page nobody has
            // touched. The `return` that used to sit there would have skipped this line outright
            // on exactly the values that need it most -- an editable saved record.
            this.$watch('country', () => this.afterCountryChange());
        },

        /**
         * The country changed. Re-publish the bound value, and put focus where the reader was
         * going anyway.
         *
         * The picker is a combobox bound through `x-modelable`, so a choice arrives as a property
         * change rather than as a call -- there is no hook to hang this on but a watcher.
         */
        afterCountryChange() {
            const bound = this.$refs.bound;

            // $nextTick, because `e164` reaches that input through `x-bind:value` and a watcher
            // runs inside the same reactive flush. Dispatching now would have Livewire read the
            // value from BEFORE the change -- the field would be one country behind, forever, and
            // only for the picker: typing a prefix into the box goes through `onInput`, which is
            // called from the template after the binding has settled.
            if (bound) {
                this.$nextTick(() => this.emit(bound));
            }

            const number = this.$refs.number;

            // The combobox pattern returns focus to its own field, which is right for a menu and
            // wrong here: after picking a country the next act is always to keep typing the
            // number. This one hop is the component's only deviation from that pattern.
            //
            // Guarded on where focus actually IS, not on the fact that the value moved. A country
            // the server changed -- a Livewire round trip, a reset -- must not yank the reader out
            // of whatever they are doing elsewhere on the page.
            if (number && this.$root && this.$root.contains(document.activeElement) && document.activeElement !== number) {
                number.focus();
            }
        },

        /** The dialing code for the current country, or null when it is not an offered region. */
        get dialCode() {
            const entry = this._regions[this.country];

            return entry && typeof entry.dial === 'number' ? entry.dial : null;
        },

        /** `+49`, for the button. Empty when the country is not one this field offers. */
        get dialDisplay() {
            return this.dialCode === null ? '' : '+' + this.dialCode;
        },

        /**
         * The value bound to the server. Mirrors DialingCodes::toE164().
         *
         * Returns '' rather than a bare '+49' for an empty box: a lone country code looks like
         * an answer and is a fragment.
         */
        get e164() {
            const code = this.dialCode;

            if (code === null) {
                return '';
            }

            let digits = this.national.replace(/\D+/g, '');

            if (digits === '') {
                return '';
            }

            const trunk = this._trunkFor(this.country);

            if (trunk !== null && digits.startsWith(trunk)) {
                digits = digits.slice(trunk.length);
            }

            return digits === '' ? '' : '+' + code + digits;
        },



        /**
         * Re-pick the country when somebody types an international prefix.
         *
         * Typing `+43…` into a field that says Germany should follow the reader rather than
         * argue with them. The country changes silently: announcing it on every keystroke would
         * make the field unusable with a screen reader, because the prefix arrives one digit at
         * a time and several countries share a leading digit.
         */
        onInput(boundEl) {
            if (this.national.startsWith('+')) {
                const match = this._regionForE164(this.national);

                if (match) {
                    this.country = match.region;
                    this.national = match.rest;
                }
            }

            // ⚠️ The emit happens HERE rather than as a second statement in the template.
            // Alpine's CSP grammar parses ONE expression, so `onInput(); emit($refs.bound)` is
            // rejected outright — and an expression the CSP build cannot parse is never
            // evaluated at all, in either build, with nothing logged.
            this.emit(boundEl);
        },

        /**
         * Tell the hidden input it changed, so a `wire:model` on it hears about it.
         *
         * ⚠️ This is not housekeeping. The bound value lives on a hidden input written by an
         * Alpine binding, and an attribute binding dispatches NOTHING — Livewire listens for an
         * `input` event and never receives one. Without this call the field looks right, submits
         * right through a plain form POST, and binds nothing at all through `wire:model`: the
         * property stays at whatever it was, and no error is raised on either side.
         *
         * Called from the template rather than from a watcher, because the value is a getter over
         * two pieces of state and a watcher would have to guess which of them moved.
         */
        emit(boundEl) {
            if (boundEl && typeof boundEl.dispatchEvent === 'function') {
                boundEl.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },

        /** The trunk prefix for a region, or null where it has none — never '' for "unknown". */
        _trunkFor(region) {
            const entry = this._regions[region];

            if (!entry || entry.trunk == null || entry.trunk === '') {
                return null;
            }

            return String(entry.trunk);
        },


        /**
         * Split an E.164 string into the region it belongs to and the rest.
         *
         * Dialing codes are one to three digits and several regions share one — `US`, `CA` and
         * the Caribbean are all 1 — so this matches the LONGEST code first and, among regions
         * sharing it, keeps the one already selected when that is one of them. Picking
         * arbitrarily would make the field jump to Canada while a reader in the United States
         * types their own number.
         */
        _regionForE164(value) {
            const digits = String(value).replace(/\D+/g, '');

            if (digits === '') {
                return null;
            }

            for (let length = 3; length >= 1; length--) {
                const candidate = digits.slice(0, length);

                if (candidate.length < length) {
                    continue;
                }

                const regions = Object.keys(this._regions).filter(
                    (region) => String(this._regions[region].dial) === candidate
                );

                if (regions.length === 0) {
                    continue;
                }

                const region = regions.includes(this.country) ? this.country : regions[0];

                return { region, rest: digits.slice(length) };
            }

            return null;
        },
    };
}
