/**
 * File upload — the drop zone, and keeping the native input in step with it.
 *
 * The component is a skin over `<input type="file">`, and the whole reason it
 * needs JavaScript is that a FileList is read-only: removing one file means
 * rebuilding the list through a DataTransfer and handing it back to the input.
 * Everything else — the form submission, Livewire's upload — reads the input,
 * not this state, so the two must never drift.
 *
 * The drop handler moved out of the template along with the rest: it was four
 * statements and a `const`, and Alpine's CSP build parses one expression. Under
 * a strict Content-Security-Policy dropping a file did nothing at all, silently,
 * while clicking the label still worked — the kind of half-working that reads as
 * a browser quirk rather than a policy failure.
 *
 * Lifecycle resources held on `this`: NONE. Pure reactive state — no observers,
 * timers or document listeners, so no destroy() hook is required. The one thing
 * held outside `this` is the cached root element below, which is released with the
 * component object itself.
 *
 * @param {Object} config
 * @param {string} config.removeLabel  accessible name for the per-file remove button
 * @param {string} [config.removedMessage]  translated sentence spoken after a removal,
 *   with `:name` standing in for the file that went
 */
export default function wirekitFileUpload(config = {}) {
    // The control's root element, resolved ONCE while something is still attached to
    // resolve it from.
    //
    // ⚠️ `$root` IS RESOLVED WHEN IT IS READ, by walking up from `$el` to the nearest
    // `[x-data]`. `removeFile()` runs from a row's own remove button, so `$el` is that
    // button — and `_focusAfterRemoval` reads the scope inside `$nextTick`, by which
    // time the button has gone with its row. The walk from a detached node reaches
    // nothing.
    //
    // Deliberately no `?? this.$el` fallback: that is precisely what made the same
    // defect quiet in `tags-input`. A button answers `querySelectorAll` perfectly well
    // and has no rows inside it, so the list came back EMPTY and the code took its
    // last-resort branch — focus to the control, a plausible enough place for focus to
    // be that nobody noticed it was the wrong one.
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

        /**
         * A size a person can read.
         *
         * The unit index is clamped at both ends: `Math.log` of a sub-byte value
         * is negative and would index off the front of the ladder, and anything
         * past a terabyte would index off the back and render "1.0 undefined".
         * Neither is reachable through a file picker today, which is exactly why
         * it would ship unnoticed if it ever became reachable.
         */
        formatBytes(bytes) {
            if (! bytes || bytes < 0) {
                return '0 B';
            }

            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.min(sizes.length - 1, Math.max(0, Math.floor(Math.log(bytes) / Math.log(k))));

            return `${(bytes / Math.pow(k, i)).toFixed(1)} ${sizes[i]}`;
        },

        /**
         * Adopt a FileList.
         *
         * The raw File objects are kept as well as the display rows, because
         * removeFile() has to rebuild a FileList and only the originals can go
         * back into one.
         */
        handleFiles(fileList) {
            this._rawFiles = Array.from(fileList);
            this.files = this._rawFiles.map((f) => ({ name: f.name, size: f.size }));
        },

        /**
         * A drop replaces the selection, exactly as picking files would.
         *
         * The `change` event is dispatched by hand: assigning `.files` fires
         * nothing, so without it Livewire would never start the upload.
         */
        handleDrop(event) {
            this.dragging = false;

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
         * And nothing else reports the change. The list is the only feedback there was,
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
