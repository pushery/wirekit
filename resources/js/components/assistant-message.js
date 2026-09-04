/**
 * WireKit Assistant Message Alpine Component.
 *
 * Owns ONE job: announcing streamed assistant output to assistive technology
 * WITHOUT flooding it.
 *
 * The naive approach — putting aria-live="polite" on the streaming body — makes
 * a screen reader re-read the growing text on every token. The result is
 * unusable, and it is what most AI chat UIs actually ship.
 *
 * Instead the body itself is silent (aria-live="off"), and this plugin mirrors
 * COMPLETE units into a separate, always-present live region:
 *
 *   - announce="sentence" (default) — flush each finished sentence as it lands.
 *   - announce="all"                — flush everything once streaming stops.
 *   - announce="off"                — never announce (the caller narrates).
 *
 * Cleanup contract:
 *   - _observer (MutationObserver on the body) — disconnected in destroy()
 *   Callbacks null-guard `_body` first: browser-queued observer callbacks can
 *   fire AFTER destroy() has torn the component down.
 */
export default function wirekitAssistantMessage(config = {}) {
    return {
        // 'sentence' | 'all' | 'off'
        announce: config.announce ?? 'sentence',
        // Mirrors into the live region. Never bound to the visible body.
        announced: '',

        _observer: null,
        _body: null,
        _region: null,
        // How much of the body text we have already announced.
        _flushed: 0,

        init() {
            this._body = this.$refs.body || null;
            this._region = this.$refs.announcer || null;

            if (this.announce === 'off' || !this._body || !this._region) {
                return;
            }

            this._observer = new MutationObserver(() => this._onBodyChange());
            this._observer.observe(this._body, {
                childList: true,
                subtree: true,
                characterData: true,
            });
        },

        destroy() {
            this._observer?.disconnect();
            this._observer = null;
            this._body = null;
            this._region = null;
        },

        /**
         * Streaming finished — flush whatever is still unannounced. Call this
         * from the app (or bind it to your streaming flag) when the response is
         * complete.
         */
        flush() {
            if (!this._body) {
                return;
            }
            const text = this._text();
            const rest = text.slice(this._flushed).trim();
            if (rest !== '') {
                this.announced = rest;
                this._flushed = text.length;
            }
        },

        /**
         * The body text as ONE line: every run of whitespace becomes a single space.
         *
         * Streamed markup arrives with the line breaks and indentation of whatever
         * rendered it, and none of that is speech. Collapsing here means every reader
         * below works on one shape — which is also why the terminators those readers
         * look for are followed by a SPACE and never by a newline: after this call
         * there are no newlines left to find.
         */
        _text() {
            return (this._body?.textContent ?? '').replace(/\s+/g, ' ');
        },

        _onBodyChange() {
            // Null-guard: observer callbacks are browser-queued and can land
            // after destroy() nulled the refs.
            if (!this._body) {
                return;
            }

            if (this.announce === 'all') {
                // Nothing to do while tokens land — the caller flushes at the end.
                return;
            }

            const text = this._text();
            const pending = text.slice(this._flushed);

            // Announce only through the LAST sentence terminator, so a
            // half-written clause is never read out.
            //
            // Every terminator here is one _text() can actually leave behind. A period
            // followed by a newline used to be listed as a fifth candidate, and it could
            // never match: _text() has already turned that newline into a space, so the
            // paragraph break arrives as ". " and the first candidate finds it. The line
            // read as coverage of the line-break case while contributing nothing to it,
            // which is the kind of branch that survives a rewrite of the very code that
            // made it unreachable.
            const lastEnd = Math.max(
                pending.lastIndexOf('. '),
                pending.lastIndexOf('! '),
                pending.lastIndexOf('? '),
                pending.lastIndexOf('。'),
            );

            if (lastEnd === -1) {
                return;
            }

            const chunk = pending.slice(0, lastEnd + 1).trim();
            if (chunk === '') {
                return;
            }

            this.announced = chunk;
            this._flushed += lastEnd + 1;
        },
    };
}
