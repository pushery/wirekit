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
        /**
         * The body text already announced — the TEXT, deliberately, not its length.
         *
         * This was an index, and an index is only meaningful against the string it was
         * taken from. A streamed body grows, so it held for the append path; but a body
         * can also be REPLACED — a regenerate, an edit, an error sentence taking the
         * answer's place — and after a Livewire morph the index pointed into a string
         * that no longer existed. Keeping the text instead makes the question answerable:
         * is what is there now a continuation of what has been said, or something else?
         */
        _consumed: '',

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
            this._resync(text);
            const rest = text.slice(this._consumed.length).trim();
            if (rest !== '') {
                this.announced = rest;
                this._consumed = text;
            }
        },

        /**
         * Forget what has been announced when the body stops being a continuation of it.
         *
         * Continuity is decided on the TEXT, never on its length. A shorter replacement
         * and a same-length rewrite are the same event, and only one of them is visible
         * to a length test — which is why the cheap form of this check closes half the
         * hole and reads as if it closed all of it.
         *
         * The two failures it ends look nothing alike, and the quiet one is the worse:
         * against a SHORTER body the pending slice was '' forever, so the live region
         * went silent for the rest of the component's life while the reader still held
         * the last sentence of the PREVIOUS answer; against a longer one it sliced
         * mid-string and read out a fragment. In both, the visible body updates exactly
         * as it should, so there is nothing for a sighted developer to notice.
         *
         * Starting over does NOT re-announce the whole answer token by token, which is
         * the flooding this component exists to prevent: the reset happens once per
         * replacement, and from the next mutation the new text is its own prefix again.
         *
         * @param {string} text - The body text as `_text()` returns it now.
         */
        _resync(text) {
            if (text.startsWith(this._consumed)) {
                return;
            }

            this._consumed = '';
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
            this._resync(text);
            const pending = text.slice(this._consumed.length);

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
            this._consumed = text.slice(0, this._consumed.length + lastEnd + 1);
        },
    };
}
