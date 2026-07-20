/**
 * WireKit Stream Alpine Component.
 *
 * A primitive for streaming text output (LLM responses, live logs) that gets the
 * three hard parts right once, so every developer inherits them instead of
 * re-writing them:
 *
 *  1. Accessibility. A growing `aria-live="polite"` field is unusable — a screen
 *     reader re-reads the whole thing on every token. Instead the visible output is
 *     NOT a live region; a single visually-hidden `role="status"` region announces
 *     that a response is GENERATING (once), then the RESULT (once) when it settles.
 *  2. Reduced motion. A token-by-token build IS motion. Under
 *     `prefers-reduced-motion: reduce` the tokens are buffered and revealed as one
 *     block when the stream finishes — no incremental growth.
 *  3. Abort + error. A half-streamed response whose connection dies has a defined
 *     terminal state (`aborted` / `failed`), not a silent freeze.
 *
 * Source: Server-Sent Events via the browser's own `EventSource`. The developer
 * supplies the URL and renders `text`; the library owns the state machine and the
 * a11y. The state machine is intentionally decoupled from `EventSource` (all
 * transitions go through `_push` / `_finish` / `_fail`) so it is unit-testable in
 * node without a DOM (see scripts/test-stream.mjs).
 *
 * Cleanup contract: the only cleanup-requiring resource is the `EventSource`
 * (`_source`). It is opened in `_open()`, closed in `_close()`, and `_close()` runs
 * from `destroy()` (Alpine teardown), from every terminal transition, and is
 * null-guarded so a queued `error` event firing after teardown cannot throw.
 *
 * @param {Object}  config
 * @param {string}  config.url          - SSE endpoint.
 * @param {string} [config.eventName]   - SSE event to listen for (default 'message').
 * @param {string} [config.doneSignal]  - Payload that ends the stream (default '[DONE]').
 * @param {string} [config.announce]    - 'result' (announce final text once) or
 *                                         'status' (announce only "ready"). Default 'result'.
 * @param {boolean}[config.autoStart]   - Open the stream on init (default true).
 * @param {string} [config.startMessage]- Announced when streaming begins.
 * @param {string} [config.readyMessage]- Announced on completion in 'status' mode.
 * @param {string} [config.initialText] - Seed text (SSR / resume a completed response,
 *                                         and it lets a static demo show content).
 */
export default function wirekitStream(config = {}) {
    return {
        // ── Public reactive state (bind these in Blade) ──
        text: config.initialText || '',
        status: 'idle', // idle | streaming | done | aborted | failed
        error: null,

        // ── Cleanup-requiring resources (underscore = "release in destroy()") ──
        _source: null,   // EventSource (live SSE mode)
        _simTimer: null, // setInterval handle (simulate mode)

        // ── Internal config / buffers ──
        _url: config.url || '',
        _eventName: config.eventName || 'message',
        _doneSignal: config.doneSignal ?? '[DONE]',
        _announceMode: config.announce === 'status' ? 'status' : 'result',
        _autoStart: config.autoStart !== false,
        _startMessage: config.startMessage || 'Generating response…',
        _readyMessage: config.readyMessage || 'Response ready',
        // Simulate mode: stream this text token-by-token from a local timer, no SSE —
        // the docs demo (and any typewriter effect) without a live endpoint.
        _simulate: config.simulate || '',
        _simulateSpeed: Number(config.simulateSpeed) > 0 ? Number(config.simulateSpeed) : 55, // ms per token
        _reduce: false,
        _buffer: '',
        _announceText: '', // bound to the sr-only role="status" region

        init() {
            // A token-by-token build IS motion — honor prefers-reduced-motion by
            // buffering and revealing the whole response at the end.
            this._reduce = typeof window !== 'undefined'
                && typeof window.matchMedia === 'function'
                && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (this._autoStart && (this._url || this._simulate)) {
                this.start();
            }
        },

        destroy() {
            this._close();
        },

        // ── Derived state for x-show / :class in Blade ──
        get isStreaming() { return this.status === 'streaming'; },
        get isDone() { return this.status === 'done'; },
        get isAborted() { return this.status === 'aborted'; },
        get isFailed() { return this.status === 'failed'; },
        get isTerminal() { return this.isDone || this.isAborted || this.isFailed; },

        /** Begin (or restart) streaming. No-op while already streaming. */
        start() {
            if (this.status === 'streaming' || (!this._url && !this._simulate)) {
                return;
            }
            this.text = '';
            this._buffer = '';
            this.error = null;
            this.status = 'streaming';
            this._announceText = this._startMessage;
            this._open();
        },

        /** Abort an in-flight stream — a defined terminal state, not a freeze. */
        stop() {
            if (this.status !== 'streaming') {
                return;
            }
            // Reveal whatever was buffered under reduced motion before stopping.
            if (this._reduce && this._buffer) {
                this.text = this._buffer;
                this._buffer = '';
            }
            this.status = 'aborted';
            this._announceText = 'Response stopped';
            this._close();
        },

        /** Tear down any prior stream and start fresh. */
        restart() {
            this._close();
            this.status = 'idle';
            this.start();
        },

        // ── Source wiring — simulate (local timer) or live EventSource ──
        _open() {
            // Simulate mode: type the configured text out from a local timer, no SSE.
            if (this._simulate) {
                this._runSimulation();
                return;
            }
            // node / SSR has no EventSource; transitions are then driven directly
            // by tests. In the browser this is the live source.
            if (typeof EventSource === 'undefined') {
                return;
            }
            try {
                this._source = new EventSource(this._url);
            } catch (e) {
                this._fail((e && e.message) || 'Stream failed to open');
                return;
            }
            this._source.addEventListener(this._eventName, (e) => {
                if (e && e.data === this._doneSignal) {
                    this._finish();
                    return;
                }
                this._push(e && e.data != null ? e.data : '');
            });
            // EventSource auto-reconnects on error; for a token stream a dropped
            // connection is terminal, so we surface it rather than silently retry
            // into a duplicated half-response.
            this._source.addEventListener('error', () => {
                if (this.status === 'streaming') {
                    this._fail('Connection lost');
                }
            });
        },

        /** Append one chunk. Buffered (not shown) under reduced motion. */
        _push(chunk) {
            if (this.status !== 'streaming') {
                return;
            }
            if (this._reduce) {
                this._buffer += chunk;
            } else {
                this.text += chunk;
            }
        },

        /** Successful completion — flush any buffer, announce the result once. */
        _finish() {
            if (this.status !== 'streaming') {
                return;
            }
            if (this._reduce) {
                this.text = this._buffer;
                this._buffer = '';
            }
            this.status = 'done';
            this._announceText = this._announceMode === 'result'
                ? (this.text || this._readyMessage)
                : this._readyMessage;
            this._close();
        },

        /** Terminal failure — record + announce once. */
        _fail(message) {
            this.error = message || 'Stream failed';
            this.status = 'failed';
            this._announceText = 'Response failed: ' + this.error;
            this._close();
        },

        // Type the simulate text out token-by-token. Under reduced motion (a token
        // build IS motion) the whole text is revealed at once with no timer. The
        // timer is null-guarded + cleared in _close(), the same cleanup discipline
        // as the EventSource.
        _runSimulation() {
            if (this._reduce) {
                this._push(this._simulate);
                this._finish();
                return;
            }
            // Word-level tokens (whitespace kept) read like real LLM streaming.
            const tokens = this._simulate.split(/(\s+)/).filter((t) => t !== '');
            let i = 0;
            if (typeof setInterval === 'undefined') {
                return; // headless: tests drive _push/_finish directly
            }
            this._simTimer = setInterval(() => {
                if (this.status !== 'streaming') {
                    this._clearSim();
                    return;
                }
                if (i >= tokens.length) {
                    this._clearSim();
                    this._finish();
                    return;
                }
                this._push(tokens[i]);
                i += 1;
            }, this._simulateSpeed);
        },

        _clearSim() {
            if (this._simTimer !== null) {
                clearInterval(this._simTimer);
                this._simTimer = null;
            }
        },

        _close() {
            // Null-guard: Alpine teardown or a terminal transition can race a
            // browser-queued `error` event; closing twice / after null must not throw.
            if (this._source) {
                this._source.close();
                this._source = null;
            }
            this._clearSim();
        },
    };
}
