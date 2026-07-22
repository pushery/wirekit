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
        // Transport. 'sse' (default) is the EventSource path. 'fetch' POSTs and
        // reads the response body — the shape LLM APIs actually use, because the
        // request IS the payload and EventSource is GET-only and body-less.
        // 'manual' opens no transport at all: the developer drives the component
        // through push()/finish()/fail() or the wirekit-stream-* events, which is
        // what a Reverb/Echo (WebSocket) app needs.
        _source_kind: config.source === 'fetch' || config.source === 'manual' ? config.source : 'sse',
        _method: (config.method || 'POST').toUpperCase(),
        _body: config.body ?? null,
        _headers: config.headers || {},
        _abort: null,   // AbortController (fetch mode)
        _onHostEvent: null,
        _name: config.name || '',
        _eventName: config.eventName || 'message',
        _doneSignal: config.doneSignal ?? '[DONE]',
        _announceMode: config.announce === 'status' ? 'status' : 'result',
        _autoStart: config.autoStart !== false,
        _startMessage: config.startMessage || 'Generating response…',
        _readyMessage: config.readyMessage || 'Response ready',
        // Announced on abort. Was a bare literal with no override at all, so a
        // translated app could not reach it — the other two at least had a config
        // key. All three are handed in from Blade, already translated.
        _stoppedMessage: config.stoppedMessage || 'Response stopped',
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

            // Manual mode has nothing to open but still auto-enters `streaming`
            // so the "generating" announcement happens once, up front, exactly as
            // it does for a live source.
            if (this._autoStart && (this._url || this._simulate || this._source_kind === 'manual')) {
                this.start();
            }

            // Event door onto the same public methods, for a host that is not
            // Alpine (a plain Echo listener, a Livewire dispatch). Mirrors the
            // wirekit-* event convention the other components use. Scoped by
            // `name` so several streams on one page stay independent, and
            // listened for on the element so a dispatch from the host bubbles in.
            if (typeof window !== 'undefined') {
                this._onHostEvent = (event) => {
                    const detail = (event && event.detail) || {};

                    if (this._name && detail.name && detail.name !== this._name) {
                        return;
                    }

                    if (event.type === 'wirekit-stream-push') this.push(detail.chunk ?? detail.text ?? '');
                    if (event.type === 'wirekit-stream-finish') this.finish();
                    if (event.type === 'wirekit-stream-fail') this.fail(detail.message);
                };

                for (const name of ['wirekit-stream-push', 'wirekit-stream-finish', 'wirekit-stream-fail']) {
                    window.addEventListener(name, this._onHostEvent);
                }
            }
        },

        destroy() {
            if (this._onHostEvent && typeof window !== 'undefined') {
                for (const name of ['wirekit-stream-push', 'wirekit-stream-finish', 'wirekit-stream-fail']) {
                    window.removeEventListener(name, this._onHostEvent);
                }
                this._onHostEvent = null;
            }
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
            // 'manual' has no transport to open, so it needs no url — the whole
            // point is that the developer feeds it. Every other mode still does.
            const needsUrl = this._source_kind !== 'manual' && !this._simulate;

            if (this.status === 'streaming' || (needsUrl && !this._url)) {
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
            this._announceText = this._stoppedMessage;
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

            // Manual mode: nothing to open. The component sits in `streaming`
            // until the developer calls finish()/fail() — every a11y guarantee
            // (one "generating" announcement, one result announcement, a defined
            // terminal state) applies exactly as it does to a live source.
            if (this._source_kind === 'manual') {
                return;
            }

            if (this._source_kind === 'fetch') {
                this._openFetch();
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
        /**
         * POST the request and read the response body as it arrives.
         *
         * EventSource cannot do this: it is GET-only and body-less by spec, while
         * an LLM request carries the prompt, the options and the model in its
         * body — none of which belongs in a URL (length, encoding, and the fact
         * that user text has no business in logs, referrers or history).
         *
         * The server still speaks SSE FRAMING (`data:` lines, blank-line
         * separated); it just does so over a POST response. That is the shape
         * OpenAI-, Anthropic- and compatible APIs use, and any proxy in front.
         *
         * Deliberately NOT auto-reconnecting. EventSource retries by itself, and
         * `_open()` above works around it; here the question does not arise, and
         * it must not: a token stream is billed and not idempotent, so a dropped
         * connection is a terminal state the reader is told about, never a silent
         * replay of a request that already cost money.
         */
        _openFetch() {
            if (typeof fetch === 'undefined') {
                return;
            }

            this._abort = typeof AbortController !== 'undefined' ? new AbortController() : null;

            const headers = { Accept: 'text/event-stream', ...this._headers };
            const hasBody = this._body !== null && this._method !== 'GET';

            if (hasBody && !Object.keys(headers).some((k) => k.toLowerCase() === 'content-type')) {
                headers['Content-Type'] = 'application/json';
            }

            fetch(this._url, {
                method: this._method,
                headers,
                body: hasBody
                    ? (typeof this._body === 'string' ? this._body : JSON.stringify(this._body))
                    : undefined,
                signal: this._abort ? this._abort.signal : undefined,
            })
                .then((response) => {
                    if (!response.ok) {
                        this._fail('Stream failed: HTTP ' + response.status);

                        return null;
                    }
                    if (!response.body || typeof response.body.getReader !== 'function') {
                        this._fail('Stream failed: response is not readable');

                        return null;
                    }

                    return this._readFramedStream(response.body.getReader());
                })
                .catch((e) => {
                    // An abort is the reader's own decision — stop() already put
                    // the component in its terminal state, so it is not a failure.
                    if (e && e.name === 'AbortError') {
                        return;
                    }
                    this._fail((e && e.message) || 'Stream failed');
                });
        },

        /**
         * Consume a reader of SSE-framed bytes: events are separated by a blank
         * line, and the payload is the concatenation of that event's `data:`
         * lines. Anything else (`event:`, `id:`, `retry:`, comments) is skipped.
         */
        async _readFramedStream(reader) {
            const decoder = new TextDecoder();
            let pending = '';

            // eslint-disable-next-line no-constant-condition
            while (true) {
                let result;

                try {
                    result = await reader.read();
                } catch (e) {
                    if (e && e.name === 'AbortError') return;
                    this._fail((e && e.message) || 'Stream failed');

                    return;
                }

                if (result.done) {
                    // The body ended without an explicit done signal — the
                    // response is complete, so this is a normal finish.
                    if (this.status === 'streaming') this._finish();

                    return;
                }

                pending += decoder.decode(result.value, { stream: true });

                // Frames are blank-line separated; keep the trailing partial.
                const frames = pending.split(/\r?\n\r?\n/);
                pending = frames.pop() ?? '';

                for (const frame of frames) {
                    const data = frame
                        .split(/\r?\n/)
                        .filter((line) => line.startsWith('data:'))
                        .map((line) => line.slice(5).replace(/^ /, ''))
                        .join('\n');

                    if (data === '') continue;

                    if (data === this._doneSignal) {
                        this._finish();

                        return;
                    }

                    this._push(data);
                }
            }
        },

        // ── Public door to the state machine (WIRE-222) ───────────────────────
        //
        // The transitions below were private by convention, driven only by the
        // EventSource listener or the simulate timer. That made the component
        // unusable for the transport Laravel itself ships — Reverb/Echo over
        // WebSocket — even though the state machine was already decoupled from
        // EventSource. These are that decoupling, made public.

        /** Append a chunk. Starts the stream if it has not begun. */
        push(chunk) {
            if (this.isTerminal || this.status === 'idle') {
                this.start();
            }
            this._push(String(chunk ?? ''));
        },

        /** Settle the stream successfully — announces the result once. */
        finish() {
            if (this.status === 'streaming') {
                this._finish();
            }
        },

        /** Settle the stream as failed, with a message the reader is told. */
        fail(message) {
            if (this.status !== 'failed') {
                this._fail(message || 'Stream failed');
            }
        },

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
            // Abort an in-flight fetch. Without this, stop() would leave the
            // request running and its chunks arriving into a component that has
            // already reached a terminal state.
            if (this._abort) {
                this._abort.abort();
                this._abort = null;
            }
            this._clearSim();
        },
    };
}
