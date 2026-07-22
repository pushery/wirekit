@props([
    // The Server-Sent Events endpoint to stream from. Null → the component stays
    // idle (seed it with `initial-text` and drive it from your own controls).
    'url' => null,
    // SSE event name to listen for, and the payload that ends the stream.
    'eventName' => 'message',
    'doneSignal' => '[DONE]',
    // How the result is announced to assistive tech once the stream settles:
    //   'result' (default) — announce the final text once.
    //   'status'           — announce only "Response ready".
    'announce' => 'result',
    // Open the stream on init. Set false to start it from your own control.
    'autoStart' => true,
    // Seed text — resume a completed response (SSR) or show a static example.
    'initialText' => null,
    // Where the tokens come from:
    //   'sse' (default) — the browser's EventSource. GET-only and body-less.
    //   'fetch'         — POST (or any method) and read the response body. The
    //                     shape LLM APIs use, because the request IS the payload:
    //                     prompt, options and model belong in a body, not a URL.
    //   'manual'        — no transport at all. Feed the component yourself with
    //                     push()/finish()/fail() or the wirekit-stream-* events,
    //                     e.g. from Laravel's own Reverb/Echo WebSocket stack.
    'source' => null,
    // fetch mode only — request shape.
    'method' => null,
    'body' => null,
    'headers' => null,
    // Names this stream so the wirekit-stream-* events can address one of several
    // on the same page (a pane per voice).
    'name' => null,
    // Simulate mode: stream THIS text token-by-token from a local timer, with no SSE
    // endpoint. Drives a live-looking demo (and a typewriter effect), and opts the
    // component into the docs "↻ Replay" affordance so a reader can re-watch it.
    'simulate' => null,
    // Milliseconds per token in simulate mode (default 55).
    'simulateSpeed' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Stream — a primitive for streaming text output (LLM responses, live logs). The
    // hard parts live in the Alpine component (resources/js/components/stream.js):
    // a live region that announces "generating" once then the result once (never per
    // token), prefers-reduced-motion that reveals the buffered text at once, and a
    // defined terminal state on abort / connection loss.
    $config = array_filter([
        'url' => $url,
        'eventName' => $eventName,
        'doneSignal' => $doneSignal,
        'announce' => $announce === 'status' ? 'status' : 'result',
        'autoStart' => (bool) $autoStart,
        'initialText' => $initialText,
        'simulate' => $simulate,
        'simulateSpeed' => $simulateSpeed !== null ? (int) $simulateSpeed : null,
        'source' => in_array($source, ['sse', 'fetch', 'manual'], true) ? $source : null,
        'method' => $method,
        'body' => $body,
        'headers' => $headers,
        'name' => $name,
        // Screen-reader announcements, translated HERE. They used to default
        // inside the plugin as bare English literals — a string that never passes
        // through __() cannot be localized by any developer, and these are the
        // only thing a screen-reader user hears about the stream's state.
        'startMessage' => __('Generating response…'),
        'readyMessage' => __('Response ready'),
        'stoppedMessage' => __('Response stopped'),
    ], fn ($v) => $v !== null);
    // autoStart is a bool the filter would drop when false — re-assert it.
    $config['autoStart'] = (bool) $autoStart;

    // No flex gap: the caret trails the text inline, and the error / controls carry
    // their own explicit top margins so the streamed text stays tight while the
    // controls get clear breathing room.
    $wrapperClasses = WireKit::resolveClasses('stream', 'base', 'wk-stream flex flex-col', $scope);
@endphp

{{-- Plain <div> host (never a component tag) so the @js(...) config is compiled and
     reaches Alpine as a real object — see the @js-in-attribute traps. In simulate mode
     the demo is "used up" once it finishes, so it emits data-replayable="true" to opt
     into the docs preview frame's "↻ Replay" affordance (inert in a developer app). --}}
<div x-data="wirekitStream(@js($config))" @if($simulate) data-replayable="true" @endif {{ $attributes->class([$wrapperClasses]) }}>
    {{-- Single live region: announces that a response is GENERATING (once), then the
         RESULT (once) when it settles. The visible output below is deliberately NOT a
         live region, so a screen reader is not re-read on every token. --}}
    <span class="sr-only" role="status" aria-live="polite" aria-atomic="true" x-text="_announceText"></span>

    {{-- The streamed output. In the a11y tree for on-demand reading, but not
         auto-announced. Wraps + preserves newlines. The caret is an INLINE element
         right after the text span (no whitespace between them) so it trails the last
         character as the text grows — a real typing cursor, not a block on its own line. --}}
    <div
        class="wk-stream-output whitespace-pre-wrap break-words text-[length:var(--text-wk-md)] font-[family-name:var(--font-wk-sans)] text-[color:var(--color-wk-text)]"
        :data-status="status"
    ><span x-text="text"></span><span
            x-show="isStreaming"
            x-cloak
            aria-hidden="true"
            class="wk-stream-caret ml-px inline-block h-[1em] w-[0.5ch] translate-y-[0.1em] motion-safe:animate-pulse bg-[var(--color-wk-text-muted)]"
        ></span></div>

    {{-- Terminal failure — a defined state, overridable via the `error` slot. --}}
    <template x-if="isFailed">
        <div role="alert" class="mt-[var(--gap-wk-xs)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">
            @isset($error)
                {{ $error }}
            @else
                <span x-text="error"></span>
            @endisset
        </div>
    </template>

    {{-- Developer controls (Stop / Retry) and custom state UIs render here, inside the
         component's Alpine scope — reference status / isStreaming / start() / stop().
         Given clear breathing room from the streamed text above (only when present). --}}
    @unless($slot->isEmpty())
        <div class="mt-[var(--gap-wk-md)]">{{ $slot }}</div>
    @endunless
</div>
