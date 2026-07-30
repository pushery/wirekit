/**
 * Input — the trailing affordances: clear the field, copy its value.
 *
 * Only a `clearable` / `copyable` input mounts this. A plain input carries no
 * Alpine at all, and must keep rendering byte-identically.
 *
 * The behavior lived in an inline `x-data` and could not stay there: the object
 * declared methods, and method shorthand is not in the grammar Alpine's CSP
 * build parses. Under a strict Content-Security-Policy the X and the copy
 * button rendered and did nothing — the field kept its text, the clipboard kept
 * its old contents, and nothing said why.
 *
 * The field is read through `$refs.wkField` rather than mirrored into state: it
 * is a real <input> that a developer's `wire:model` / `x-model` also writes to,
 * so the element is the only honest source of its own value.
 *
 * Lifecycle resources held on `this`:
 *   - _copiedTimer (setTimeout) — cleared in destroy(). A 2 s timer that fires
 *     after teardown writes to a component that no longer exists.
 */
export default function wirekitInput() {
    return {
        copied: false,
        hasValue: false,
        _copiedTimer: null,

        init() {
            this.syncHasValue();
        },

        destroy() {
            if (this._copiedTimer) {
                clearTimeout(this._copiedTimer);
                this._copiedTimer = null;
            }
        },

        /**
         * Track whether the field has content, so the X only appears when there
         * is something to clear.
         *
         * Bound to the wrapper's `input` event, which the field's own typing
         * bubbles up to — which is why it reads the element instead of a copy
         * the component would then have to keep in sync.
         */
        syncHasValue() {
            const field = this.$refs.wkField;

            this.hasValue = !! field && field.value.length > 0;
        },

        /**
         * Empty the field and hand focus back to it.
         *
         * The two dispatches are not decoration: assigning `.value` fires no
         * event, so without them a `wire:model` / `x-model` binding keeps the
         * text the reader just cleared.
         */
        clear() {
            const field = this.$refs.wkField;

            if (! field) {
                return;
            }

            field.value = '';
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
            this.hasValue = false;
            field.focus();
        },

        /**
         * Copy the live value, and say so for a moment.
         *
         * `navigator.clipboard` is unavailable on a non-secure origin — plain
         * http, which is where a good deal of local development happens — so the
         * select + execCommand path stays as the fallback. A denied write is
         * swallowed: the reader simply never sees "Copied", which is the honest
         * outcome of a copy that did not happen.
         */
        copy() {
            const field = this.$refs.wkField;

            if (! field) {
                return;
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(field.value)
                    .then(() => this._markCopied())
                    .catch(() => {});

                return;
            }

            try {
                field.select();
                document.execCommand('copy');
                this._markCopied();
            } catch (error) {
                // Neither path available — nothing to report beyond the absent
                // "Copied".
            }
        },

        /** Show the copied state, and take it back after a moment. */
        _markCopied() {
            this.copied = true;
            clearTimeout(this._copiedTimer);
            this._copiedTimer = setTimeout(() => {
                this.copied = false;
            }, 2000);
        },
    };
}
