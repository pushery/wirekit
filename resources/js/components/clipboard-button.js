/**
 * Clipboard button — copies a fixed value and shows a brief confirmation.
 *
 * The handler was three statements and an arrow function written straight into
 * the template. Alpine's CSP build parses one expression and accepts neither
 * shape, so under a strict Content-Security-Policy the button rendered, took
 * focus, and copied nothing — with no error a reader could act on.
 *
 * The confirmation is OPTIMISTIC: `copied` flips as soon as the write is
 * requested, not when it resolves. That is what the template did, and changing
 * it here would quietly alter what the live region announces.
 *
 * Lifecycle resources held on `this`:
 *   - _resetTimer (setTimeout) — cleared in destroy(), null-guarded inside the
 *     callback against a post-destroy fire, and cleared before each new one so
 *     the confirmation belongs to the LAST click rather than the first.
 *
 * @param {Object} config
 * @param {string} config.value     text written to the clipboard
 * @param {number} config.duration  milliseconds the confirmation stays up
 */
export default function wirekitClipboardButton(config = {}) {
    return {
        copied: false,
        // Set when the write was REFUSED, so the template can take back the success the
        // optimistic flip announced. Separate from `copied` because they are three states,
        // not two: idle, copied, failed.
        failed: false,
        _value: config.value == null ? '' : String(config.value),
        _duration: Number(config.duration) || 2000,
        _resetTimer: null,

        destroy() {
            if (this._resetTimer) {
                clearTimeout(this._resetTimer);
                this._resetTimer = null;
            }
        },

        copy() {
            // Flip first. The label swap and the live region are the reader's
            // only feedback, and they must not wait on a promise that may never
            // settle in a denied or insecure context.
            this.copied = true;
            this._scheduleReset();

            // navigator.clipboard is absent outside a secure context, and
            // writeText rejects when the permission is denied. Neither belongs
            // in the developer's console as an uncaught error.
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(this._value).catch(() => this._announceFailure());
            } else {
                // No clipboard API at all — outside a secure context, or an old engine. The
                // optimistic flip above already told the reader it worked, so it has to be
                // taken back here too, not only on a rejected promise.
                this._announceFailure();
            }
        },

        /**
         * Take back the success the optimistic flip announced.
         *
         * Flipping first is right — the label swap and the live region are the reader's only
         * feedback and must not wait on a promise that may never settle. What was missing is
         * the other half of that bargain: when the write is REFUSED, "Copied to clipboard"
         * has been announced and nothing corrects it, so the reader believes they have
         * something they do not, pastes nothing, and has no way to find out why.
         *
         * One announcement on the success path, a second only on deviation — the same shape
         * the optimistic-UI contract in this package uses everywhere else.
         */
        _announceFailure() {
            this.copied = false;
            this.failed = true;

            clearTimeout(this._resetTimer);
            this._resetTimer = setTimeout(() => {
                this.failed = false;
            }, this._duration);
        },

        _scheduleReset() {
            // A reset still pending from an earlier click would otherwise cut
            // this confirmation short.
            clearTimeout(this._resetTimer);

            this._resetTimer = setTimeout(() => {
                // Null-guard against a post-destroy fire — a queued callback can
                // outlive Alpine teardown and must not write to a dead scope.
                if (! this._resetTimer) {
                    return;
                }

                this._resetTimer = null;
                this.copied = false;
            }, this._duration);
        },
    };
}
