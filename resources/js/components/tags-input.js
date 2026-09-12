/**
 * WireKit Tags Input Alpine Component.
 *
 * Free-form tag entry: type + Enter/comma to create tags.
 * Backspace on empty input removes the last tag, Escape drops the text being typed.
 *
 * WHY EVERY EXIT SAYS SOMETHING. All four state changes here are invisible to a
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
 * @param {Object} config
 * @param {string} config.name - Input name for form submission
 * @param {number|null} config.maxTags - Maximum number of tags allowed
 * @param {Array<string>} config.tags - Pre-existing tags to seed the state
 * @param {Object} [config.announcements] - Translated templates: `added`, `removed`,
 *   `duplicate` (all take `:name`) and `limit` (takes `:count`).
 */
export default function wirekitTagsInput(config = {}) {
    const announcements = config.announcements && typeof config.announcements === 'object'
        ? config.announcements
        : {};

    // The field's root element, resolved ONCE while something is still attached
    // to resolve it from.
    //
    // ⚠️ `$root` IS RESOLVED WHEN IT IS READ, by walking up from `$el` to the
    // nearest `[x-data]`. `removeTag()` runs from a chip's own remove button, so
    // `$el` is that button — and `_focusAfterRemoval` reads the scope inside
    // `$nextTick`, by which time the button has gone with its chip. The walk from
    // a detached node reaches nothing.
    //
    // The `?? this.$el` fallback that used to stand there is what made it quiet
    // rather than loud: a button answers `querySelectorAll` perfectly well and has
    // no chips inside it, so the list came back EMPTY and the code took its
    // last-resort branch — focus to the text field. That is a plausible place for
    // focus to be, which is exactly why nobody noticed the chip it was meant to go
    // to. Measured in chromium: three chips, remove the last one with its button
    // focused, and focus lands on the INPUT rather than on the new last chip.
    //
    // Same defect as the one fixed in `toast.js`; the two were found by one sweep.
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
        _maxTags: config.maxTags || null,

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
         * component behaves exactly as before, down to the byte.
         */
        _commit() {
            if (typeof this.run === 'function') {
                this.run([...this.tags]);
            }
        },

        /**
         * Backspace on empty input removes the last tag.
         */
        onBackspace(event) {
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
         * ⚠️ NOT called `_announce`, for the reason `tagAnnouncement` is not called
         * `announcement`: the optimistic layer nests inside this component and declares
         * `_announce(text)`, and Alpine resolves a name to the NEAREST scope that has it.
         * Every caller here runs from the text field or a chip, inside the layer's
         * element, so under that name `addTag()` reached the layer's method instead —
         * which ignores the replacements. The raw template "Added :name" went into the
         * layer's region and overwrote the "Saving" it had queued a moment earlier.
         */
        _announceTag(template, replacements) {
            if (typeof template !== 'string' || template === '') return;

            const text = Object.keys(replacements).reduce(
                (carry, key) => carry.split(`:${key}`).join(replacements[key]),
                template
            );

            this.tagAnnouncement = '';
            queueMicrotask(() => { this.tagAnnouncement = text; });
        },
    };
}
