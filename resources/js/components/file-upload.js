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
 * timers or document listeners, so no destroy() hook is required.
 *
 * @param {Object} config
 * @param {string} config.removeLabel  accessible name for the per-file remove button
 */
export default function wirekitFileUpload(config = {}) {
    return {
        dragging: false,
        files: [],
        _rawFiles: [],

        removeLabel: config.removeLabel || '',

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

        removeFile(index) {
            this._rawFiles.splice(index, 1);
            this.files.splice(index, 1);

            // A FileList cannot be constructed or mutated; a DataTransfer is the
            // only way to build one the input will accept.
            const transfer = new DataTransfer();
            this._rawFiles.forEach((f) => transfer.items.add(f));

            this.$refs.input.files = transfer.files;
            this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
        },
    };
}
