import { onFormReset } from '../utils/form-reset.js';
import { watchModelEvents } from '../utils/model-events.js';
import { hasModelBinding, observeServerValue, ownValues, WK_SERVER_VALUE_ATTRIBUTE } from '../utils/server-value.js';
import { watchCurrent } from '../utils/watch-current.js';

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
 * Lifecycle resources held on `this`, released in destroy():
 *   - _modelEvents — `change` and `blur` on the bound input for `wire:model.change` and
 *     `wire:model.blur`, which listen on that input alone (utils/model-events.js). The field is
 *     one text field to them: `change` when the reader leaves it after an edit, then `blur`.
 *   - _stopFormReset — the listener that puts the starting country and number back when the
 *     form is reset (utils/form-reset.js).
 *   - _stopServerValue — the observer on the value the server renders on the root
 *     (utils/server-value.js).
 *
 * @param {Object} config
 * @param {string} config.country   ISO 3166-1 alpha-2 code the field starts on
 * @param {Object} config.regions   code -> { dial: number, trunk: string|null } for every offered region
 * @param {string} [config.value]   an existing E.164 value to start from, or ''; without it, the
 *                                  root's `data-wk-server-value`
 */
export default function wirekitPhone(config = {}) {
    // The numbers this field hands to its model, so the answer to one is not taken for a number
    // the server set (utils/server-value.js).
    const own = ownValues();

    return {
        country: String(config.country || '').toUpperCase(),

        /** What is in the box. Exactly what was typed, never rewritten. */
        national: '',

        _regions: config.regions && typeof config.regions === 'object' ? config.regions : {},

        _modelEvents: null,
        _stopFormReset: null,
        _stopServerValue: null,
        // The country and number a form reset returns to: the ones the field started on, or the
        // ones of the number the server rendered last.
        _resetCountry: '',
        _resetNational: '',
        // The number the server rendered last, which a country change does not hand back to it.
        _serverValue: '',

        init() {
            // The number is not in `x-data`, so that the expression stays the same for every
            // number: a Livewire morph that changed it would start the field again, and the answer
            // to a `wire:model.live` change would put back the digits of the request over the ones
            // typed since. `$root` is checked rather than assumed, as a unit harness hands a stub.
            const seed = config.value !== undefined
                ? config.value
                : (typeof this.$root?.getAttribute === 'function' ? this.$root.getAttribute(WK_SERVER_VALUE_ATTRIBUTE) : null);
            const start = this._split(seed);

            this.country = start.country;
            this.national = start.national;
            this._serverValue = String(seed ?? '');
            this._resetCountry = this.country;
            this._resetNational = this.national;

            this._modelEvents = watchModelEvents(this.$root, () => this.$refs?.bound);
            this._stopFormReset = onFormReset(this.$root, () => this.$refs?.bound, () => this._restore());

            // A number the server sets reaches the box and the country, and becomes what a form
            // reset returns to. The number on screen needs nothing, and neither does the answer to
            // a change of the reader's: with `.live` the reader can be digits further when it
            // arrives, and Livewire leaves its property alone then too.
            this._stopServerValue = observeServerValue(this.$root, (value) => {
                const split = this._split(value);

                this._serverValue = value;
                this._resetCountry = split.country;
                this._resetNational = split.national;

                const answered = hasModelBinding(this.$refs?.bound) && own.answers(value);

                if (answered || value === this.e164) {
                    return;
                }

                this.country = split.country;
                this.national = split.national;
            });

            // Armed LAST, and the `else` above exists for it. The two assignments in that branch
            // are the round-trip split rather than a reader's choice: a watcher armed before them
            // fires on page load, emitting an input event and moving focus on a page nobody has
            // touched. A `return` in that branch would skip this line on exactly the values that
            // need it most -- an editable saved record.
            watchCurrent(this, 'country', () => this.afterCountryChange());
        },

        destroy() {
            this._modelEvents?.dispose();
            this._modelEvents = null;
            this._stopFormReset?.();
            this._stopFormReset = null;
            this._stopServerValue?.();
            this._stopServerValue = null;
        },

        /**
         * A value read into the country and the number the box shows.
         *
         * An E.164 value is split back into a country and a national part, so a round-trip through
         * a form does not degrade. Without this, editing a saved record shows the raw +4915… in a
         * box whose label says "phone number". Anything else leaves the box empty on the country
         * the field starts on.
         *
         * @param {*} value
         * @returns {{ country: string, national: string }}
         */
        _split(value) {
            const incoming = String(value == null ? '' : value);
            const country = String(config.country || '').toUpperCase();

            if (! incoming.startsWith('+')) {
                return { country, national: '' };
            }

            const match = this._regionForE164(incoming);

            // A value whose country code is not among the offered regions is shown AS IS rather
            // than silently reassigned to the default country. The reader then sees the number
            // they stored; a quiet reassignment would change what the form submits without anyone
            // touching the field.
            return match ? { country: match.region, national: match.rest } : { country, national: incoming };
        },

        /**
         * Back to the starting country and number after a form reset.
         *
         * The number box is a native field, which the reset empties to its own default, and its
         * `x-model` then reads that empty value into `national`, which leaves the hidden field
         * empty. The number is written into the box as well, so whichever of the two runs last
         * reads the same one.
         */
        _restore() {
            this.country = this._resetCountry;
            this.national = this._resetNational;

            const number = this.$refs?.number;

            if (number && typeof number === 'object' && 'value' in number) {
                number.value = this.national;
            }
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
            // A country the server set, together with its number, is already what the server
            // holds, and handing it back would only cost a request.
            if (bound && this.e164 !== this._serverValue) {
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
            return this._assemble(this.country, this.national);
        },

        /**
         * E.164 from a region and the digits typed for it, the rule `e164` binds.
         *
         * Digits that start with an international prefix name their own country as a plus does
         * (`_internationalOf`): `0043 1 234567` on a field that stands on Germany is a number in
         * Austria, assembled with Austria's own trunk prefix. Digits that name no offered country
         * bind nothing rather than a number in the wrong one.
         */
        _assemble(region, national) {
            const entry = this._regions[region];
            const code = entry && typeof entry.dial === 'number' ? entry.dial : null;

            if (code === null) {
                return '';
            }

            let digits = String(national ?? '').replace(/\D+/g, '');

            if (digits === '') {
                return '';
            }

            const international = this._internationalOf(digits, code);

            if (international !== null) {
                const match = this._regionForE164('+' + international);

                return match ? this._assemble(match.region, match.rest) : '';
            }

            const trunk = this._trunkFor(region);

            if (trunk !== null && digits.startsWith(trunk)) {
                digits = digits.slice(trunk.length);
            }

            return digits === '' ? '' : '+' + code + digits;
        },

        /**
         * The digits after an international prefix, or null where they start with none. `00` is
         * the prefix most of the world dials, and no national number starts with it. The North
         * American plan, the regions with the code 1, dials abroad with `011`, and none of its
         * national numbers starts with a zero, so there that prefix is unambiguous too. Mirrors
         * DialingCodes::toE164().
         *
         * @param {string} digits
         * @param {?number} code  the dialing code of the field's region
         * @returns {?string}
         */
        _internationalOf(digits, code) {
            if (digits.startsWith('00')) {
                return digits.slice(2);
            }

            return code === 1 && digits.startsWith('011') ? digits.slice(3) : null;
        },



        /**
         * Re-pick the country when somebody types an international prefix.
         *
         * Typing `+43…` or `0043…` into a field that says Germany, or `011 43…` into one that says
         * the United States, should follow the reader rather than argue with them. The country changes silently: announcing it on every keystroke would
         * make the field unusable with a screen reader, because the prefix arrives one digit at
         * a time and several countries share a leading digit.
         */
        onInput(boundEl) {
            // An international prefix names a country as `+` does: `00`, and `011` on the North
            // American plan (`_internationalOf`).
            const international = this._internationalOf(this.national.replace(/\D+/g, ''), this.dialCode);
            const named = this.national.startsWith('+') ? this.national : (international !== null ? '+' + international : null);

            if (named !== null) {
                const match = this._regionForE164(named);

                if (match) {
                    this.country = match.region;
                    this.national = match.rest;
                }
            }

            // The emit happens HERE rather than as a second statement in the template.
            // Alpine's CSP grammar parses ONE expression, so `onInput(); emit($refs.bound)`
            // never runs on the CSP bundle: the first keystroke logs `Alpine Expression
            // Error`, and the value never reaches `wire:model`.
            this.emit(boundEl);
        },

        /**
         * Tell the hidden input it changed, so a `wire:model` on it hears about it.
         *
         * This is not housekeeping. The bound value lives on a hidden input written by an
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
                own.note(this.e164);
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
