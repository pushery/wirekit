{{-- optimistic-ui: n/a — client-only
     A tool call is something the SERVER did. The one interaction here is opening the result
     disclosure, which never leaves the browser, so there is no round trip to acknowledge and
     the optimistic contract has nothing to arbitrate. --}}
@props([
    // What was called. This is the name a reader recognizes — `search_docs`, `get_weather` —
    // and it is also the accessible name of the whole block, so it is required in practice:
    // a tool call nobody can name is a spinner with extra steps.
    'name' => null,
    // Where the call stands:
    //   pending — queued, nothing has happened yet
    //   running — in flight (the block is aria-busy and the label shimmers)
    //   done    — returned, the result is in the slot
    //   failed  — returned an error, and the slot holds it
    'status' => 'pending',
    // The arguments the model passed, shown as JSON. An array is encoded here; a string is
    // printed as given, so an application that already has the provider's raw JSON does not
    // pay a decode/encode round trip to display it.
    'arguments' => null,
    // Seconds the call took, for the settled label. The APPLICATION owns this number — it
    // knows when the call started, and a stopwatch in the browser would disagree with it the
    // moment the page re-renders from the server. Null keeps the plain status word.
    'seconds' => null,
    // Whether the result opens closed. A finished call's result is reference material, so it
    // is collapsed by default; a failure opens, because an error nobody sees is not reported.
    'open' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('tool-call', $attributes->getAttributes());

    $status = WireKit::validateProp('tool-call', 'status', (string) $status, ['pending', 'running', 'done', 'failed']);

    $isRunning = $status === 'running';
    $isFailed = $status === 'failed';

    // A failure opens; everything else stays closed unless the caller says otherwise. Written
    // as an explicit null check rather than `??`, because `open="false"` on an unbound tag is
    // the string "false" and `??` would never see it.
    $open = $open === null ? $isFailed : BooleanProp::from($open, false);

    /*
     * The label carries the state in WORDS, not only in color. WCAG 1.4.1: a reader who cannot
     * tell the intents apart still has to be able to tell a finished call from a failed one.
     * The duration replaces the word once the call has settled and the application supplies it.
     */
    $secondsValue = is_numeric($seconds) ? (int) $seconds : null;

    $statusLabel = match (true) {
        $isRunning => __('wirekit::Running…'),
        $status === 'pending' => __('wirekit::Queued'),
        $isFailed && $secondsValue !== null => __('wirekit::Failed after :seconds s', ['seconds' => $secondsValue]),
        $isFailed => __('wirekit::Failed'),
        $secondsValue !== null => __('wirekit::Took :seconds s', ['seconds' => $secondsValue]),
        default => __('wirekit::Done'),
    };

    $statusIntent = match ($status) {
        'running' => 'info',
        'done' => 'success',
        'failed' => 'danger',
        default => 'neutral',
    };

    // The arguments as text. An array is encoded with the flags that make JSON readable to a
    // person — a tool call's arguments are read, not parsed, and `/` escaped as `\/` in a URL
    // argument is the single most common thing a reader stumbles over.
    $argumentsText = match (true) {
        $arguments === null => null,
        is_string($arguments) => trim($arguments),
        default => json_encode($arguments, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    };

    $argumentsText = ($argumentsText === '' || $argumentsText === false) ? null : $argumentsText;

    $toolName = is_string($name) ? trim($name) : '';

    /*
     * `min-w-0` is load-bearing, and it was measured rather than added defensively. A grid or
     * flex item's `min-width` defaults to `auto`, which means "never narrower than my content" —
     * and a tool call's content is an arguments block whose longest line is a URL that does not
     * wrap. Put four of these in a `display: grid` column at 390 px and the blocks came out
     * 404 px wide, so the whole PAGE scrolled sideways: the reader drags the entire column to
     * read one argument. With `min-w-0` the block takes its track and the arguments scroll
     * inside their own region, which is what that region is for.
     */
    $rootClasses = WireKit::resolveClasses('tool-call', 'base', implode(' ', [
        'min-w-0',
        'rounded-[var(--radius-wk-md)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border-subtle)]',
        'bg-[var(--color-wk-bg-subtle)] px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-sm)]',
    ]), $scope);
@endphp

{{-- `aria-busy` while the call is in flight, so assistive technology knows the region is still
     changing; the polite status below announces the settled state once, and only once, because
     it is rendered with the state rather than swapped into an existing live region. --}}
<div
    data-wk-tool-call
    data-status="{{ $status }}"
    @if($isRunning) aria-busy="true" @endif
    {{ $attributes->class([$rootClasses]) }}
>
    <div class="flex flex-wrap items-center gap-[var(--gap-wk-sm)]">
        <x-wirekit::code data-wk-tool-call-name>{{ $toolName }}</x-wirekit::code>

        <x-wirekit::badge :intent="$statusIntent" size="sm" data-wk-tool-call-status>
            <x-wirekit::shimmer :active="$isRunning">{{ $statusLabel }}</x-wirekit::shimmer>
        </x-wirekit::badge>
    </div>

    @if($argumentsText !== null)
        <div class="mt-[var(--space-wk-sm)]">
            <x-wirekit::code-block language="json" :label="__('wirekit::Arguments to :tool', ['tool' => $toolName])">{{ $argumentsText }}</x-wirekit::code-block>
        </div>
    @endif

    @if($slot->hasActualContent())
        {{-- `hasActualContent()`, never `filled(trim($slot))`: Livewire's Blade precompiler wraps
             every `@if` and `@foreach` in morph markers, so a caller who fills this slot
             conditionally hands over two HTML comments when the condition is false — and a
             plain emptiness test counts them as content. The disclosure would then open over
             nothing. Laravel's own helper strips comments before it compares.

             The result is reference material behind a disclosure, except when it is a failure.
             `wire:key` carries the status: Livewire MORPHS this element when the turn
             re-renders, and a morph preserves Alpine's open state — so a call that settles
             while the reader has it open would stay open, and one that fails would stay
             closed. A key that changes replaces the element instead. --}}
        <div class="mt-[var(--space-wk-sm)]" wire:key="wk-tool-call-{{ $status }}">
            <x-wirekit::collapsible :open="$open" :trigger="$isFailed ? __('wirekit::Error') : __('wirekit::Result')">
                {{ $slot }}
            </x-wirekit::collapsible>
        </div>
    @endif
</div>
