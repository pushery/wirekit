import { controlIsDisabled } from '../utils/fieldset-disabled.js';
import { graphemesOf } from '../utils/graphemes.js';
import { formatDecimal } from '../utils/locale-number.js';
import { sameName } from '../utils/same-name.js';

/**
 * A file name in two parts for a list row that cuts it in the middle: the start gives way, and the
 * end stays, the extension and the eight characters before it, where two versions of one file
 * differ. A name too short to be worth cutting stays whole in the start. The parts are split
 * between graphemes, so an emoji or a letter with its accent is never cut in two.
 *
 * @param {unknown} name
 * @returns {{start: string, end: string}}
 */
export function splitFileName(name) {
    const text = String(name ?? '');
    const graphemes = graphemesOf(text);
    const dot = graphemes.lastIndexOf('.');
    const keep = (dot > 0 ? graphemes.length - dot : 0) + 8;

    if (graphemes.length <= keep + 4) {
        return { start: text, end: '' };
    }

    return { start: graphemes.slice(0, -keep).join(''), end: graphemes.slice(-keep).join('') };
}

/**
 * File upload — the drop zone, and keeping the native input in step with it.
 *
 * The component is a skin over `<input type="file">`, and the whole reason it
 * needs JavaScript is that a FileList is read-only: removing one file means
 * rebuilding the list through a DataTransfer and handing it back to the input.
 * Everything else — the form submission, Livewire's upload — reads the input,
 * not this state, so the two must never drift.
 *
 * The drop handler lives here rather than in the template: it is four statements
 * and a `const`, and Alpine's CSP build parses one expression. Inline, under a
 * strict Content-Security-Policy, dropping a file would do nothing at all,
 * silently, while clicking the label still worked — the kind of half-working that
 * reads as a browser quirk rather than a policy failure.
 *
 * The list also has to follow the input when something ELSE empties it, and three
 * things do. Livewire clears a bound input by assigning `value` once the property goes
 * back to empty, which is what a save action does; a native form reset clears it; and a
 * page without either can ask for it with the `wirekit:file-upload-reset` event. None of
 * the three fires `change`, so without listening for them the rows would stay on screen
 * over an input and a property that hold nothing, and the next submit would fail as
 * "required".
 *
 * Lifecycle resources held on `this`, each released in destroy():
 *   - _unwatch (the unwatch function of `$wire.$watch`) — Livewire also releases it
 *     when the element goes, so calling it here is belt and braces, and it is nulled so
 *     a second destroy() cannot call it twice.
 *   - _form + _formResetHandler (a `reset` listener on the input's own form).
 *   - _resetHandler (a window listener for `wirekit:file-upload-reset`).
 *   - _takeUpload (a `change` listener on the input, in the capture phase, while the input is
 *     `multiple` and bound).
 * The one thing held outside `this` is the cached root element below, which is released
 * with the component object itself.
 *
 * A selection on a `multiple` input bound with `wire:model` replaces the one before it, on the
 * property as in the list. Livewire's own handler appends every selection to the array the
 * property holds, so a second selection would add to the first while the list shows only the
 * second, and the `change` a removal sends would upload the files that stay once more beside
 * the ones already there, the removed one among them. So the listener takes the `change` before
 * Livewire's own does and uploads with `append` off.
 *
 * With `uploadSeparately`, a selection of several files goes up one file per request, so a
 * file over a server's or a proxy's limit fails alone. The files go up one after another, and
 * the first that arrives replaces what the property held while the rest append, so a failure
 * at the start of a selection leaves no file of the one before. A selection none of whose files
 * arrives, uploaded either way, empties the property, without a request of its own, so the
 * property never holds a selection the list no longer shows. Livewire's events keep their
 * meaning for the whole selection: `livewire-upload-start` once, `livewire-upload-progress`
 * across all of it with the file and its index in the detail, and once every file has settled
 * `livewire-upload-finish`, or `livewire-upload-error` naming the files that failed, or
 * `livewire-upload-cancel` where one was canceled and none failed. Each row says whether its
 * file failed, and a canceled file leaves the list as it leaves the property.
 *
 * @param {Object} config
 * @param {string} config.removeLabel  accessible name for the per-file remove button
 * @param {string} [config.removedMessage]  translated sentence spoken after a removal,
 *   with `:name` standing in for the file that went
 * @param {string|null} [config.model]  the Livewire property the input is bound to, read
 *   from its `wire:model` attribute; null when it is bound to nothing
 * @param {string} [config.locale]  the application's locale, BCP-47, for the file sizes
 * @param {string[]} [config.sizeUnits]  the five unit symbols from B to TB, from the catalog
 * @param {{group: string, decimal: string}|null} [config.separators]  the application's own marks,
 *   read off `WireKit::formatNumbersUsing()`; null to write the locale's
 * @param {boolean} [config.uploadSeparately]  upload a selection of several files one file per
 *   request; the view sets it only with `multiple` and `wire:model`
 * @param {string} [config.failedLabel]  translated words beside a file whose upload failed
 * @param {string} [config.failedMessage]  translated sentence spoken when a file fails to upload,
 *   with `:name` standing in for the file
 */
export default function wirekitFileUpload(config = {}) {
    // The control's root element, resolved ONCE while something is still attached to
    // resolve it from.
    //
    // `$root` is resolved when it is read, by walking up from `$el` to the nearest
    // `[x-data]`. `removeFile()` runs from a row's own remove button, so `$el` is that
    // button — and `_focusAfterRemoval` reads the scope inside `$nextTick`, by which
    // time the button has gone with its row. The walk from a detached node reaches
    // nothing.
    //
    // Deliberately no `?? this.$el` fallback. A button answers `querySelectorAll`
    // perfectly well and has no rows inside it, so the list would come back EMPTY and
    // the code would take its last-resort branch — focus to the control, a plausible
    // enough place for focus that the wrong answer would look right.
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
        dragging: false,
        files: [],
        _rawFiles: [],

        removeLabel: config.removeLabel || '',

        // The application's locale, so a size reads "1,5 MB" on a German page as the server writes it.
        _locale: config.locale || null,
        // The unit symbols, from B to TB, translated by the view: French counts in octets (`Ko`,
        // `Mo`). A factory built without them writes the English symbols.
        _sizeUnits: Array.isArray(config.sizeUnits) && config.sizeUnits.length === 5 ? config.sizeUnits : ['B', 'KB', 'MB', 'GB', 'TB'],
        // The application's own marks, where it formats numbers itself, so the sizes read as every
        // number the server writes; null, the locale's.
        _separators: config.separators && typeof config.separators === 'object' ? config.separators : null,

        /**
         * The sentence spoken after a removal, as a TEMPLATE handed in from the Blade.
         *
         * Assembled from a placeholder rather than concatenated here: a sentence built
         * in JavaScript cannot be translated, and word order is not the same in every
         * language. Same shape, and the same catalog key, as `tags-input` uses.
         */
        removedMessage: config.removedMessage || '',

        /** What a screen reader is told after a removal. Empty until one happens. */
        fileAnnouncement: '',

        model: typeof config.model === 'string' && config.model !== '' ? config.model : null,

        _uploadsSeparately: config.uploadSeparately === true,
        failedLabel: config.failedLabel || '',
        failedMessage: config.failedMessage || '',
        _takeUpload: null,
        // Counts the selections this input has uploaded, so a selection uploading one file after
        // another can tell that a newer one replaced it.
        _selection: 0,
        // Set when _emptyAfterNothingArrived() empties the property, so the watch in init()
        // keeps the rows that hold what became of their files.
        _emptiedItself: false,

        _unwatch: null,
        _form: null,
        _formResetHandler: null,
        _resetHandler: null,

        /**
         * Listen for the three things that empty the input without a `change` event.
         *
         * The watch mirrors Livewire's own: it clears a bound file input when the property
         * becomes `null` or `''`, or an empty array on a `multiple` input, and this empties
         * the list on the same transitions. Outside a Livewire component `$wire.$watch` is
         * a no-op returning nothing, which is why the result is checked rather than assumed.
         * The one empty value it lets pass is the component's own, after a selection none of
         * whose files arrived, so those rows keep the state their files ended in.
         *
         * The event is matched on the input's id, never taken as "every upload on the
         * page": a form with two uploads that resets one would otherwise lose both lists.
         */
        init() {
            const input = this.$refs?.input;

            if (this.model !== null && this.$wire && typeof this.$wire.$watch === 'function') {
                const unwatch = this.$wire.$watch(this.model, (value) => {
                    // Exactly Livewire's condition for clearing the input, so the two never
                    // disagree: an empty array counts only on a `multiple` input.
                    const empty = value === null || value === ''
                        || (Array.isArray(value) && value.length === 0 && input?.multiple === true);
                    // The mark is good for the next value heard and no other, so it never keeps a
                    // later reset from emptying the list.
                    const emptiedItself = this._emptiedItself;

                    this._emptiedItself = false;

                    if (empty && ! emptiedItself) {
                        this.reset();
                    }
                });

                this._unwatch = typeof unwatch === 'function' ? unwatch : null;
            }

            if (input && input.form && typeof input.form.addEventListener === 'function') {
                this._form = input.form;
                // `reset` fires BEFORE the browser resets the controls, so the input still
                // holds the file at this point. The list is emptied without asking it.
                this._formResetHandler = () => this.reset();
                this._form.addEventListener('reset', this._formResetHandler);
            }

            if (typeof window !== 'undefined') {
                this._resetHandler = (event) => {
                    const id = event?.detail?.id;

                    if (input && sameName(id, input.id)) {
                        this.reset();
                    }
                };
                window.addEventListener('wirekit:file-upload-reset', this._resetHandler);
            }

            // In the capture phase, so it runs before Livewire's listener on the same input.
            if (this.model !== null && input?.multiple === true && typeof input.addEventListener === 'function') {
                this._takeUpload = (event) => this._uploadSelection(event);
                input.addEventListener('change', this._takeUpload, true);
            }
        },

        destroy() {
            if (this._unwatch) {
                this._unwatch();
                this._unwatch = null;
            }

            if (this._form && this._formResetHandler) {
                this._form.removeEventListener('reset', this._formResetHandler);
            }

            this._form = null;
            this._formResetHandler = null;

            if (this._resetHandler && typeof window !== 'undefined') {
                window.removeEventListener('wirekit:file-upload-reset', this._resetHandler);
            }

            this._resetHandler = null;

            if (this._takeUpload) {
                this.$refs?.input?.removeEventListener('change', this._takeUpload, true);
            }

            this._takeUpload = null;
        },

        /**
         * Empty the list, and the input with it.
         *
         * Silent on purpose: nobody removed a file. The removal sentence is for a person
         * who pressed a remove button, and reading it out after a save would announce a
         * loss that did not happen. No `change` either — an input emptied by its owner has
         * nothing to upload, and Livewire's own handler would only return early on it.
         */
        reset() {
            this.files = [];
            this._rawFiles = [];
            this.dragging = false;

            const input = this.$refs?.input;

            if (input && input.files && input.files.length > 0) {
                input.value = '';
            }
        },

        /** The part of a file name that gives way in a list row (see splitFileName). */
        fileNameStart(name) {
            return splitFileName(name).start;
        },

        /** The part of a file name that stays in a list row: its extension and what comes before it. */
        fileNameEnd(name) {
            return splitFileName(name).end;
        },

        /**
         * A size a person can read, as `attachment` writes one on the server: bytes whole, then one
         * decimal below 10 (2.4 MB) and none from 10 (24 MB), the convention file managers use.
         * Each boundary is judged on the number as it is shown, so 1023.96 KB reads 1.0 MB, not
         * 1024.0 KB, and 9.96 MB reads 10 MB. Thousands are grouped as the server groups them.
         *
         * The unit index is clamped at the back: anything past a terabyte stays in terabytes
         * rather than indexing off the ladder and rendering "1.0 undefined". Neither end is
         * reachable through a file picker today, which is exactly why it would ship unnoticed if
         * it ever became reachable.
         */
        formatBytes(bytes) {
            const sizes = this._sizeUnits;
            const grouping = this._separators ?? true;

            if (! bytes || bytes < 0) {
                return `0 ${sizes[0]}`;
            }

            if (bytes < 1024) {
                return `${formatDecimal(Math.round(bytes), this._locale, 0, 0, grouping)} ${sizes[0]}`;
            }

            let value = bytes / 1024;
            let unit = 1;

            while (Math.round(value) >= 1024 && unit < sizes.length - 1) {
                value /= 1024;
                unit++;
            }

            const shown = Math.round(value * 10) / 10;

            // In the application's locale, as the server writes the same sizes elsewhere.
            return shown < 10
                ? `${formatDecimal(shown, this._locale, 1, 1, grouping)} ${sizes[unit]}`
                : `${formatDecimal(Math.round(value), this._locale, 0, 0, grouping)} ${sizes[unit]}`;
        },

        /**
         * Light the zone up while a file is dragged over it, unless the field is disabled:
         * a zone that lights up promises to take the file, and handleDrop() will not.
         * Disabled includes a fieldset around the field, which the field's own `disabled` does
         * not report (utils/fieldset-disabled.js).
         */
        dragOver() {
            this.dragging = ! controlIsDisabled(this.$refs.input);
        },

        /**
         * Adopt a FileList.
         *
         * The raw File objects are kept as well as the display rows, because
         * removeFile() has to rebuild a FileList and only the originals can go
         * back into one.
         *
         * An empty list empties the bound property. Livewire uploads what a `change` brings and
         * returns early on a `change` that brings nothing, so without this the earlier upload
         * stays on the property and the next save takes a file the reader removed. An empty
         * list arrives here when removeFile() takes out the last file, whose `change` lands in
         * this method, and with any other `change` that reports no file.
         */
        handleFiles(fileList) {
            this._rawFiles = Array.from(fileList);
            this.files = this._rawFiles.map((f) => ({ name: f.name, size: f.size }));

            if (this._rawFiles.length === 0) {
                this._emptyTheModel();
            }
        },

        /**
         * Upload a selection on a `multiple` input bound with `wire:model` so it replaces the one
         * before it (see the note at the top). The listener stops the `change` from reaching
         * Livewire's handler and this view's own, and does the list's part itself. An empty
         * selection is left to them: Livewire returns early on it, and handleFiles() empties the
         * property.
         *
         * @param {Event} event
         */
        _uploadSelection(event) {
            const input = event?.target;
            const selection = input && input.files ? Array.from(input.files) : [];

            if (selection.length === 0 || ! this.$wire || typeof this.$wire.uploadMultiple !== 'function') {
                return;
            }

            event.stopImmediatePropagation();
            this._selection += 1;
            this.handleFiles(input.files);

            const property = this.model;
            const id = this.$wire.$id;
            const fire = (name, detail = {}) => input.dispatchEvent(new CustomEvent(name, { bubbles: true, detail: { id, property, ...detail } }));

            if (this._uploadsSeparately && selection.length > 1) {
                this._uploadEachFile(selection, fire);

                return;
            }

            // One request for the whole selection, as Livewire's own handler sends it, with its
            // events, and with `append` off: the files replace what the property held. A request
            // that fails or is canceled replaces nothing, so the property is emptied, unless a
            // newer selection has taken the list over in the meantime. The rows of a failed
            // request stay and say so; a canceled one takes its files out of the list, as it
            // took them off the property.
            const selectionId = this._selection;
            const nothingArrived = (state) => {
                if (selectionId === this._selection) {
                    if (state === 'canceled') {
                        this.reset();
                    } else {
                        this.files = this.files.map((row) => ({ ...row, state }));
                    }

                    this._emptyAfterNothingArrived();
                }
            };

            fire('livewire-upload-start');
            this.$wire.uploadMultiple(
                property,
                selection,
                () => fire('livewire-upload-finish'),
                () => {
                    nothingArrived('failed');
                    fire('livewire-upload-error');
                },
                (progress) => fire('livewire-upload-progress', { progress: Number(progress?.detail?.progress) || 0 }),
                () => {
                    nothingArrived('canceled');
                    fire('livewire-upload-cancel');
                },
                false,
            );
        },

        /**
         * Upload a selection one file per request, one file after another.
         *
         * Each file asks for its upload once the one before it has settled, so it knows whether a
         * file of this selection has reached the property yet: the first that arrives replaces what
         * the property held, and every later one appends to it. The next upload is asked for in a
         * microtask, because Livewire starts what waits in its queue right after it calls back
         * from an upload that finished or failed, and an upload asked for inside that call would be
         * started a second time.
         *
         * A newer selection, such as the files that stay after a removal, replaces this one while it
         * uploads. Livewire uploads what it was given in order, so the file in flight still lands
         * before the newer selection replaces it, but this one asks for no further file: one the
         * reader removed would otherwise be appended after the replacement. It ends as canceled,
         * so every start is answered once.
         *
         * @param {File[]} selection
         * @param {function(string, Object=): boolean} fire  dispatches an upload event on the input
         */
        _uploadEachFile(selection, fire) {
            this.files = this.files.map((row) => ({ ...row, state: 'uploading' }));

            const property = this.model;
            const selectionId = this._selection;
            const current = () => selectionId === this._selection;
            const sent = selection.map(() => 0);
            const failed = [];
            let canceled = 0;
            let arrived = 0;

            // Each file ends `uploaded`, `failed` or `canceled`. The rows belong to a newer
            // selection once one has replaced this, so a late answer leaves them to it.
            const settle = (file, state) => {
                if (! current()) {
                    return;
                }

                // The row of this file, found by the file itself: one removed meanwhile has none.
                const at = this._rawFiles.indexOf(file);

                // A canceled file leaves the list, as it never reached the property. A failed one
                // stays and says so, so the reader knows which file to pick again.
                if (at !== -1 && state === 'canceled') {
                    this.files = this.files.filter((_, index) => index !== at);
                    this._rawFiles = this._rawFiles.filter((_, index) => index !== at);
                } else if (at !== -1) {
                    this.files[at].state = state;
                }

                if (state === 'failed') {
                    failed.push(file.name);
                    this.fileAnnouncement = this.failedMessage.replace(':name', file.name);
                }

                arrived += state === 'uploaded' ? 1 : 0;
                canceled += state === 'canceled' ? 1 : 0;
            };

            // The selection ends as Livewire's own upload ends: an error if a file failed, else a
            // cancel if one was canceled, else finished.
            const next = (index) => {
                if (! current()) {
                    fire('livewire-upload-cancel', { failed: [...failed] });

                    return;
                }

                if (index === selection.length) {
                    // No file of it arrived, so none replaced the selection before it.
                    if (arrived === 0) {
                        this._emptyAfterNothingArrived();
                    }

                    fire(failed.length > 0 ? 'livewire-upload-error' : (canceled > 0 ? 'livewire-upload-cancel' : 'livewire-upload-finish'), { failed: [...failed] });

                    return;
                }

                const file = selection[index];
                const done = (state) => {
                    settle(file, state);
                    queueMicrotask(() => next(index + 1));
                };

                this.$wire.uploadMultiple(
                    property,
                    [file],
                    () => done('uploaded'),
                    () => done('failed'),
                    (progress) => {
                        sent[index] = Number(progress?.detail?.progress) || 0;
                        fire('livewire-upload-progress', {
                            progress: Math.round(sent.reduce((sum, part) => sum + part, 0) / selection.length),
                            file: file.name,
                            index,
                        });
                    },
                    () => done('canceled'),
                    arrived > 0,
                );
            };

            fire('livewire-upload-start');
            next(0);
        },

        /**
         * Set the bound property to empty: `null` for one file, an empty array for several, the
         * two values Livewire's own reset reads as empty. Live, as the upload that filled it
         * was, so the property is empty on the server when the list is. Outside a Livewire
         * component, or unbound, there is nothing to empty.
         */
        _emptyTheModel() {
            if (this.model === null || ! this.$wire || typeof this.$wire.set !== 'function') {
                return;
            }

            this.$wire.set(this.model, this.$refs?.input?.multiple === true ? [] : null);
        },

        /**
         * Empty the bound property after a selection none of whose files arrived. A selection
         * replaces the property only with a file that arrives, so the property would still hold
         * the selection before, which the list no longer shows and a submission would send. Set
         * without a request of its own (`live` off): the message the failed upload left on the
         * property stays until the next request, which carries the change.
         *
         * Livewire writes the value into the component's reactive data, so the watch in init()
         * hears it as it hears a reset from the server, and would empty the list with the rows
         * whose files failed or were canceled. The mark tells it this emptying is the
         * component's own.
         */
        _emptyAfterNothingArrived() {
            if (this.model === null || ! this.$wire || typeof this.$wire.set !== 'function') {
                return;
            }

            this._emptiedItself = true;
            this.$wire.set(this.model, this.$refs?.input?.multiple === true ? [] : null, false);
        },

        /**
         * A drop replaces the selection, exactly as picking files would.
         *
         * The `change` event is dispatched by hand: assigning `.files` fires
         * nothing, so without it Livewire would never start the upload.
         */
        handleDrop(event) {
            this.dragging = false;

            // A disabled field takes no file by drop either. Its own picker is closed by
            // the browser; a drop reaches it only through here, so the state is read from
            // the field at the moment of the drop, which also covers a state Livewire set
            // after the page loaded and a disabled fieldset around the field.
            if (controlIsDisabled(this.$refs.input)) {
                return;
            }

            const transfer = event.dataTransfer;

            if (! transfer || ! transfer.files) {
                return;
            }

            this.$refs.input.files = transfer.files;
            this.handleFiles(transfer.files);
            this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
        },

        /**
         * Drop one file — the row's own remove button, and nothing else.
         *
         * The removal takes the focused element away with it. Rows are keyed by file
         * NAME, so the template drops the node whose key vanished, which is the node
         * holding the button that was just pressed: focus falls back to `<body>` and the
         * next Tab restarts at the top of the page, inside a control the reader was in
         * the middle of using. So focus is placed deliberately afterwards rather than
         * left where the DOM happens to leave it.
         *
         * And nothing else reports the change. The list is the only other feedback,
         * which is no feedback at all for a reader who cannot see it — so the removal
         * says which file went.
         *
         * An index naming no file returns early: without it the announcement would read
         * out its own placeholder, focus would move for a gesture that removed nothing,
         * and the input would be handed a rebuilt-but-identical FileList plus a `change`
         * that starts an upload over.
         */
        removeFile(index) {
            const removed = this.files[index];

            if (removed === undefined) {
                return;
            }

            this._rawFiles.splice(index, 1);
            this.files.splice(index, 1);

            // A FileList cannot be constructed or mutated; a DataTransfer is the
            // only way to build one the input will accept.
            const transfer = new DataTransfer();
            this._rawFiles.forEach((f) => transfer.items.add(f));

            this.$refs.input.files = transfer.files;
            this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));

            this._announce(removed.name);
            // Resolved HERE — synchronously, while the button that anchors the scope is
            // still in the document. The move itself waits for the tick.
            rootOf(this);
            this._focusAfterRemoval(index);
        },

        /**
         * Write the removal sentence into the live region the template renders.
         *
         * Cleared first and written in a microtask: a live region assigned the string it
         * already holds is not a change, and assistive technology says nothing. Remove a
         * file, add it back, remove it again — same sentence, and the second removal
         * would pass in silence.
         *
         * @param {string} name - The file that went.
         */
        _announce(name) {
            if (this.removedMessage === '') {
                return;
            }

            const text = this.removedMessage.split(':name').join(name);

            this.fileAnnouncement = '';
            queueMicrotask(() => { this.fileAnnouncement = text; });
        },

        /**
         * Put focus on the row that took the removed one's place.
         *
         * Same position first — that is the file that moved up into the gap, and it is
         * where the reader's eye already is. Nothing there means the last row was the one
         * removed, so the new last row takes focus; an empty list leaves only the control
         * itself, which is where a reader would go next anyway.
         *
         * After a tick: the rows are re-rendered from the array, so querying before the
         * template has caught up finds the buttons as they were. Outside Alpine there is
         * no tick and no DOM — the guards make this a no-op there rather than the throw a
         * bare `this.$nextTick` would be.
         *
         * The root comes from the cache the caller filled, NOT from `$root` read here: by
         * then the element that anchored the scope is detached. See the note at the top
         * of this file.
         *
         * @param {number} index - The position the removed file held.
         */
        _focusAfterRemoval(index) {
            const place = () => {
                const root = rootOf(this);

                if (! root || typeof root.querySelectorAll !== 'function') {
                    return;
                }

                const buttons = root.querySelectorAll('[data-wk-file-remove]');
                const target = buttons[index] ?? buttons[buttons.length - 1] ?? this.$refs?.input;

                if (target && typeof target.focus === 'function') {
                    target.focus();
                }
            };

            if (typeof this.$nextTick === 'function') {
                this.$nextTick(place);

                return;
            }

            place();
        },
    };
}
