/**
 * WireKit Tags Input Alpine Component.
 *
 * Free-form tag entry: type + Enter/comma to create tags.
 * Backspace on empty input removes the last tag, Escape drops the text being typed.
 *
 * Why every exit says something. All four state changes here are invisible to a
 * screen reader on their own: a tag is committed (the field empties and a chip
 * appears somewhere the reader is not), a tag is taken back by Backspace (a
 * destructive change with no focus move behind it), and two of the exits leave the
 * typed text sitting there with no reason given — a duplicate, and a set already at
 * its maximum. Silence on the last two is the worst of the four, because the reader
 * has evidence that nothing happened and none of why. So each exit writes a sentence
 * into the live region the template renders, and the empty-input exit is the only one
 * that stays silent: nothing was asked for, so nothing happened.
 *
 * The sentences are TEMPLATES handed in from the Blade side rather than built here.
 * A sentence assembled from fragments in JavaScript cannot be translated, and word
 * order is not the same in every language — the same reason the wizard's step
 * announcement is shaped this way.
 *
 * Lifecycle resources held on `this`, released in destroy():
 *   - _modelEvents — `change` and `blur` on the root, which `wire:model` binds through
 *     `x-modelable`, so `wire:model.live.blur` and `.live.change` send when a tag is added or
 *     removed and when the reader leaves (utils/model-events.js). The tags travel as the
 *     event's detail.
 *   - _stopServerValue — the observer on the tags the server renders on the root
 *     (utils/server-value.js).
 *
 * @param {Object} config
 * @param {string} config.name - Input name for form submission
 * @param {number|null} config.maxTags - Maximum number of tags allowed
 * @param {Array<string>} [config.tags] - Pre-existing tags to seed the state; without them, the
 *   root's `data-wk-server-value`
 * @param {boolean} [config.readonly] - The set is read-only: no tag is added or removed
 * @param {Object} [config.announcements] - Translated templates: `added`, `removed`,
 *   `duplicate` (all take `:name`) and `limit` (takes `:count`).
 */
import { onFormReset } from '../utils/form-reset.js';
import { requiredCheckState } from '../utils/required-check.js';
import { watchModelEvents } from '../utils/model-events.js';
import { hasModelBinding, jsonValue, observeServerValue, WK_SERVER_VALUE_ATTRIBUTE } from '../utils/server-value.js';
import { isComposing } from '../utils/ime.js';

/**
 * A sentence template with its `:key` placeholders filled, or '' when there is no template.
 *
 * @param {unknown} template
 * @param {Record<string, string>} replacements
 * @returns {string}
 */
function fillTemplate(template, replacements) {
    if (typeof template !== 'string' || template === '') return '';

    return Object.keys(replacements).reduce(
        (carry, key) => carry.split(`:${key}`).join(replacements[key]),
        template
    );
}

export default function wirekitTagsInput(config = {}) {
    const announcements = config.announcements && typeof config.announcements === 'object'
        ? config.announcements
        : {};

    // The field's root element, resolved ONCE while something is still attached
    // to resolve it from.
    //
    // `$root` is resolved when it is read, by walking up from `$el` to the
    // nearest `[x-data]`. `removeTag()` runs from a chip's own remove button, so
    // `$el` is that button — and `_focusAfterRemoval` reads the scope inside
    // `$nextTick`, by which time the button has gone with its chip. The walk from
    // a detached node reaches nothing.
    //
    // A `?? this.$el` fallback would fail quietly: a button answers
    // `querySelectorAll` perfectly well and has no chips inside it, so the list
    // would come back empty and focus would go to the text field rather than to
    // the new last chip. `toast.js` holds its region the same way, for the same
    // reason.
    let rootEl = null;

    /**
     * @param {{$root?: Element}} ctx
     * @returns {Element|null}
     */
    const rootOf = (ctx) => {
        if (! rootEl && ctx) {
            rootEl = ctx.$root ?? null;
        }

        return rootEl;
    };

    return {
        // Seed with developer-supplied initial tags. Defensive Array.from
        // accepts both plain arrays and array-like inputs (e.g. when the
        // Blade-side @js() encoding produces an iterable proxy). Empty
        // array if no seed provided.
        tags: Array.isArray(config.tags) ? Array.from(config.tags).map(String) : [],

        _modelEvents: null,
        // Puts the starting tags back when the form is reset (utils/form-reset.js), released in
        // destroy().
        _stopFormReset: null,
        _resetValue: Array.isArray(config.tags) ? Array.from(config.tags).map(String) : [],
        _stopServerValue: null,
        // `requiredMessage` and `onRequiredInvalid()`: a required tags input stops an empty submit
        // (utils/required-check.js).
        ...requiredCheckState(),

        init() {
            const root = this.$root;

            // The tags are not in `x-data`, so that the expression stays the same for every set: a
            // Livewire morph that changed it would start the component again, and through
            // `x-modelable` the answer to a `.live` change would send the set of the request back,
            // without a tag added since. `$root` is checked, as a unit harness hands a bare stub.
            if (! Array.isArray(config.tags)) {
                const seed = jsonValue(typeof root?.getAttribute === 'function' ? root.getAttribute(WK_SERVER_VALUE_ATTRIBUTE) : null);

                if (Array.isArray(seed)) {
                    this.tags = seed.map(String);
                }
            }

            this._modelEvents = watchModelEvents(root, () => root, { detail: () => [...this.tags] });

            // A set the server renders is what a form reset returns to. With a caller's model it
            // reaches the chips through the binding, which leaves alone what the server only
            // returned; without one, it is the server's to set.
            this._stopServerValue = observeServerValue(root, (raw) => {
                const value = jsonValue(raw);

                if (! Array.isArray(value)) {
                    return;
                }

                const next = value.map(String);
                this._resetValue = [...next];

                if (hasModelBinding(root) || (next.length === this.tags.length && next.every((tag, i) => tag === this.tags[i]))) {
                    return;
                }

                this.tags = next;
            });

            // One hidden field per tag: an empty list has none, which the helper covers with the
            // form it found while there was one.
            this._resetValue = [...this.tags];
            this._stopFormReset = onFormReset(root, () => (typeof root?.querySelector === 'function' ? root.querySelector('input[type="hidden"]') : null), () => this._restore());
        },

        destroy() {
            this._modelEvents?.dispose();
            this._modelEvents = null;
            this._stopFormReset?.();
            this._stopFormReset = null;
            this._stopServerValue?.();
            this._stopServerValue = null;
        },

        /** Back to the starting tags after a form reset; the hidden fields follow the list. */
        _restore() {
            this.tags = [...this._resetValue];
            this.tagAnnouncement = '';
            this.requiredMessage = '';
        },

        /** What the required check reads: empty exactly while there is no tag. */
        get requiredValue() {
            return this.tags.length > 0 ? String(this.tags.length) : '';
        },

        /** The text field a reader types a tag into. */
        _focusRequiredControl() {
            const root = this.$root;
            const field = typeof root?.querySelector === 'function'
                ? root.querySelector('input:not([type="hidden"]):not([data-wk-required-check])')
                : null;

            field?.focus();
        },
        _maxTags: config.maxTags || null,
        // Read-only, the field carries the native attribute and has no remove buttons; the
        // handlers below refuse as well, for a key or a call that reaches them anyway.
        _readonly: config.readonly === true,

        /**
         * What a screen reader is told after a change. Empty until one happens.
         *
         * NOT called `announcement`, and the name is the whole reason: the optimistic
         * layer nests inside this component and declares a property by that name, so a
         * second one here would be shadowed for everything under the layer's element
         * and readable only outside it — a property whose value depends on where in the
         * template it is read from.
         */
        tagAnnouncement: '',

        /** True once the set is full, so the field can say so instead of ignoring keys. */
        get atMaxTags() {
            return this._maxTags !== null && this.tags.length >= this._maxTags;
        },

        /**
         * Add the current input value as a new tag.
         *
         * Both refusals keep the typed text. It is the reader's word, and clearing it
         * would delete what they wrote to punish them for a condition they cannot see.
         */
        addTag() {
            if (this._readonly) return;

            const input = this.$refs.input;
            const value = input.value.trim();

            if (!value) return;

            if (this.tags.includes(value)) { // no duplicates
                this._announceTag(announcements.duplicate, { name: value });

                return;
            }

            if (this.atMaxTags) {
                this._announceTag(announcements.limit, { count: String(this._maxTags) });

                return;
            }

            this.tags.push(value);
            this._commit();
            input.value = '';
            this._announceTag(announcements.added, { name: value });
        },

        /**
         * A comma that reaches the field without a keydown ends a tag as a typed one does.
         *
         * `@keydown.comma` hears only a key that reports itself as a comma. A pasted list, text
         * dropped into the field, dictation and an on-screen keyboard that reports no key put
         * the comma into the text instead. So the text is read as it changes: every complete
         * piece before the last comma becomes a tag, and what follows the last comma stays in
         * the field to be finished. A duplicate is skipped, since the list holds it already. A
         * full set stops the pieces, and the rest of the reader's text stays in the field,
         * which turns read-only, as a refused `addTag()` leaves it.
         *
         * Not while an input method composes: the comma may still be converted. The
         * composition's end calls this again.
         */
        onInput(event) {
            if (this._readonly || isComposing(event)) return;

            const input = this.$refs.input;
            const value = typeof input?.value === 'string' ? input.value : '';

            if (! value.includes(',')) return;

            const pieces = value.split(',');
            const rest = pieces.pop();
            const added = [];
            let duplicate = null;
            let stoppedAt = -1;

            for (let i = 0; i < pieces.length; i++) {
                const piece = pieces[i].trim();

                if (piece === '') continue;

                if (this.tags.includes(piece)) {
                    duplicate ??= piece;
                    continue;
                }

                if (this.atMaxTags) {
                    stoppedAt = i;
                    break;
                }

                this.tags.push(piece);
                added.push(piece);
            }

            input.value = (stoppedAt === -1 ? rest : [...pieces.slice(stoppedAt), rest].join(',')).trimStart();

            if (added.length > 0) {
                this._commit();
            }

            // One write for the whole batch: a live region written twice in one task reads only
            // the last. The added names come first, then why text was left behind, the two
            // joined as sentences so a screen reader pauses between them.
            const said = [];

            if (added.length > 0) {
                said.push(fillTemplate(announcements.added, { name: added.join(', ') }));
            }

            if (stoppedAt !== -1) {
                said.push(fillTemplate(announcements.limit, { count: String(this._maxTags) }));
            } else if (added.length === 0 && duplicate !== null) {
                said.push(fillTemplate(announcements.duplicate, { name: duplicate }));
            }

            this._announceTagText(said.filter((sentence) => sentence !== '').join('. '));
        },

        /**
         * A list pasted one entry to a line becomes a tag per line, as a list with commas does.
         *
         * The field is one line, so the browser cleans a pasted line break away before the text
         * reaches `onInput()`: Blink and WebKit drop it, which runs the entries together, and
         * Gecko writes a space. A tab from a row of a spreadsheet stays in the text. A paste that
         * holds either is therefore taken over here: its line breaks and tabs become commas, the
         * text goes in where the caret is, over a selection, as a paste puts it, and `onInput()`
         * makes the tags. Text on one line is left to the browser.
         */
        onPaste(event) {
            const text = event?.clipboardData?.getData?.('text') ?? '';
            const input = this.$refs.input;

            if (this._readonly || this.atMaxTags || ! input || ! /[\r\n\t]/.test(text)) return;

            event.preventDefault();

            const listed = text.split(/[\r\n\t]+/).map((piece) => piece.trim()).join(', ');
            const value = typeof input.value === 'string' ? input.value : '';
            const start = typeof input.selectionStart === 'number' ? input.selectionStart : value.length;
            const end = typeof input.selectionEnd === 'number' ? input.selectionEnd : start;

            input.value = value.slice(0, start) + listed + value.slice(end);
            this.onInput(event);
        },

        /**
         * Remove a tag by index — the chip's own remove button, and nothing else.
         *
         * The removal takes the focused element away with it. Chips are keyed by
         * position, so after the splice the LAST chip element is the one the
         * template drops and the ones before it are rewritten in place: press the
         * remove button of the last chip and the button holding focus stops
         * existing, which puts focus back on `<body>` and starts the next Tab at
         * the top of the page. So focus is placed deliberately afterwards rather
         * than left where the DOM happens to leave it.
         */
        removeTag(index) {
            if (this._readonly) return;

            const removed = this.tags[index];

            if (removed === undefined) return;

            this.tags.splice(index, 1);
            this._commit();
            this._announceTag(announcements.removed, { name: removed });
            // Resolved HERE — synchronously, while the button that anchors the
            // scope is still in the document. The move itself waits for the tick.
            rootOf(this);
            this._focusAfterRemoval(index);
        },

        /**
         * Put focus on the chip that took the removed one's place.
         *
         * Same position first — that is the tag that moved up into the gap, and it
         * is where the reader's eye already is. Nothing there means the last chip
         * was the one removed, so the new last chip takes focus; an empty set
         * leaves only the field, which is where a reader would go next anyway.
         *
         * After a tick: the chips are re-rendered from the array, so querying
         * before the template has caught up finds the buttons as they were.
         * Outside Alpine there is no tick and no DOM — the guards make this a
         * no-op there rather than the throw a bare `this.$nextTick` would be.
         *
         * The root comes from the cache the caller filled, NOT from `$root` read
         * here: by then the element that anchored the scope is detached. See the
         * note at the top of this file.
         */
        _focusAfterRemoval(index) {
            const place = () => {
                const root = rootOf(this);

                if (! root || typeof root.querySelectorAll !== 'function') return;

                const buttons = root.querySelectorAll('[data-wk-tag-remove]');
                const target = buttons[index] ?? buttons[buttons.length - 1] ?? this.$refs?.input;

                if (target && typeof target.focus === 'function') target.focus();
            };

            if (typeof this.$nextTick === 'function') {
                this.$nextTick(place);

                return;
            }

            place();
        },

        /**
         * Hand the set to the optimistic layer, if one is nested here.
         *
         * Adding or removing a tag is a completed decision the moment it
         * happens; there is nothing continuous to wait out. The set is ONE value,
         * so the whole set travels, exactly as multi-select does.
         *
         * No `mark()`: this component takes `failure: 'keep'`, so a refusal never
         * writes back and the baseline is never read.
         *
         * `run` is looked up rather than assumed — without the layer this
         * component behaves as a plain field.
         */
        _commit() {
            // Every change the reader makes passes here, so it is also the field's `change`.
            this._modelEvents?.commit();

            if (typeof this.run === 'function') {
                this.run([...this.tags]);
            }
        },

        /**
         * Backspace on empty input removes the last tag.
         *
         * Only a press that begins on the empty field does: a Backspace held to delete the text
         * repeats past its end, and every repeat would take a tag with it.
         */
        onBackspace(event) {
            if (this._readonly || event.repeat) {
                return;
            }

            if (event.target.value === '' && this.tags.length > 0) {
                const removed = this.tags.pop();
                this._commit();
                this._announceTag(announcements.removed, { name: removed });
            }
        },

        /**
         * Escape drops the tag being typed, without committing it.
         *
         * The event is only stopped when there WAS text to drop. An Escape in an empty
         * field belongs to whatever surrounds the control — a modal, a drawer, a command
         * palette — and swallowing it there would leave the reader pressing a key that
         * closes everything else and does nothing here.
         */
        onEscape(event) {
            const input = this.$refs.input;

            if (!input || input.value === '') return;

            input.value = '';

            if (typeof event?.stopPropagation === 'function') {
                event.stopPropagation();
            }
        },

        /**
         * Write one sentence into the live region.
         *
         * Cleared first, then set in a microtask: writing an identical string into a
         * live region changes nothing, so pressing Enter twice on the same duplicate
         * would be answered once. Same shape, same reason, as the optimistic layer's.
         *
         * Not called `_announce`, for the reason `tagAnnouncement` is not called
         * `announcement`: the optimistic layer nests inside this component and declares
         * `_announce(text)`, and Alpine resolves a name to the NEAREST scope that has it.
         * Every caller here runs from the text field or a chip, inside the layer's
         * element, so under that name `addTag()` would reach the layer's method instead,
         * which ignores the replacements: the raw template "Added :name" would go into the
         * layer's region and overwrite the "Saving" it had queued a moment earlier.
         */
        _announceTag(template, replacements) {
            this._announceTagText(fillTemplate(template, replacements));
        },

        /** The live-region write itself, for a sentence already filled; '' says nothing. */
        _announceTagText(text) {
            if (text === '') return;

            this.tagAnnouncement = '';
            queueMicrotask(() => { this.tagAnnouncement = text; });
        },
    };
}
