/**
 * Code block — copying the code, and saying whether it worked.
 *
 * The handler was a promise chain with two arrow-function callbacks and three
 * statements inside each. Alpine's CSP build parses one expression and allows
 * none of that, so under a strict Content-Security-Policy the copy button
 * rendered, took focus, and did nothing.
 *
 * This one is NOT the clipboard-button behavior with different chrome, and the
 * difference is the reason it has its own factory: here the outcome is
 * ANNOUNCED. `srMessage` feeds a live region, so a failed copy has to say so —
 * which means this component, unlike the button, must wait for the promise
 * rather than confirm optimistically. Announcing success and then failing would
 * be worse than a moment's delay.
 *
 * The code is read out of the DOM at click time rather than passed in as
 * config: the block may hold a highlighted, line-numbered rendering of the
 * source, and `textContent` of the `<code>` element is the only place the plain
 * text still exists.
 *
 * Lifecycle resources held on `this`:
 *   - _resetTimer (setTimeout) — cleared in destroy() and before each new one,
 *     so a second copy's message is not cut short by the first one's timer.
 *
 * @param {Object} config
 * @param {string} config.copiedMessage  announced after a successful copy
 * @param {string} config.failedMessage  announced when the copy did not happen
 */
export default function wirekitCodeBlock(config = {}) {
    return {
        copied: false,
        srMessage: '',

        _copiedMessage: config.copiedMessage || '',
        _failedMessage: config.failedMessage || '',
        _resetTimer: null,

        destroy() {
            if (this._resetTimer) {
                clearTimeout(this._resetTimer);
                this._resetTimer = null;
            }
        },

        copy() {
            const code = this.$el.closest('[data-wk-code-block]')?.querySelector('code');

            // No code to copy is a composition mistake rather than a runtime
            // condition — but announcing a success that did not happen is worse
            // than staying quiet, so it reports the failure path.
            if (! code) {
                this._announce(false);

                return;
            }

            // navigator.clipboard is absent outside a secure context, and
            // writeText rejects when the permission is denied. Both end up in
            // the live region rather than in the developer's console.
            if (! navigator.clipboard || ! navigator.clipboard.writeText) {
                this._announce(false);

                return;
            }

            navigator.clipboard.writeText(code.textContent)
                .then(() => this._announce(true))
                .catch(() => this._announce(false));
        },

        _announce(succeeded) {
            this.copied = succeeded;
            this.srMessage = succeeded ? this._copiedMessage : this._failedMessage;

            // A message still pending from an earlier copy would otherwise clear
            // this one early.
            clearTimeout(this._resetTimer);

            this._resetTimer = setTimeout(() => {
                // Null-guard against a post-destroy fire: a queued callback can
                // outlive Alpine teardown and must not write to a dead scope.
                if (! this._resetTimer) {
                    return;
                }

                this._resetTimer = null;
                this.copied = false;
                this.srMessage = '';
            }, 2000);
        },
    };
}
