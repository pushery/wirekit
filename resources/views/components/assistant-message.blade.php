{{-- optimistic-ui: n/a — client-only
     Streaming display of text the server is sending. --}}
@props([
    // Who is speaking: assistant (left, avatar + model chip), user (right),
    // system (centered, muted — a configuration/system turn, not a person).
    'role' => 'assistant',
    // Display name for the speaker. Null uses the role's own wording.
    'name' => null,
    // Optional avatar image for the speaker.
    'avatar' => null,
    // Model / engine chip (e.g. "atlas-2"). Assistant turns only.
    'model' => null,
    // Optional state tint on the bubble — for a turn that IS a state: an error
    // answer (danger), a caution (warning), a confirmation (success), a note
    // (info). neutral (default) keeps the plain role surface. Mirrors the
    // message component's intent tint (bg + border at low alpha; the body text
    // stays regular, never a state color).
    'intent' => 'neutral',
    // True while tokens are still landing. Marks the turn aria-busy and shows
    // the streaming affordance.
    'streaming' => false,
    // The sources this answer cites, as numbered chips under the body. Takes whatever the
    // retrieval layer produced: titles, arrays with label/href/snippet, or the application's
    // own models — `CitationList` normalizes all three and drops an entry with no label,
    // because a chip a screen reader announces as nothing is worse than no chip.
    'citations' => [],
    // Seconds the model spent reasoning, for the disclosure's summary once the stream
    // has ended: "Thought for 12s". The APPLICATION owns that number — it knows when
    // the stream started, and a stopwatch in the browser would disagree with it the
    // moment the page re-renders from the server. Null keeps the plain label.
    'reasoningSeconds' => null,
    // How streamed output reaches assistive technology:
    //   sentence — flush each finished sentence (default; the readable choice)
    //   all      — flush once, when streaming stops
    //   off      — never announce (you narrate it yourself)
    'announce' => config('wirekit.components.assistant-message.announce', 'sentence'),
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('assistant-message', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $streaming = BooleanProp::from($streaming, false);

    /*
     * The disclosure's label follows the stream. While tokens land it says the model is
     * thinking and shimmers with them; afterwards it settles into how long that took, when
     * the application says so, and otherwise back to the plain label.
     *
     * A non-numeric value is no label at all rather than a cast: `reasoning-seconds="soon"`
     * would otherwise read as "Thought for 0s", which is worse than saying nothing.
     */
    $citationList = \Pushery\WireKit\Support\CitationList::from($citations);

    $reasoningSecondsValue = is_numeric($reasoningSeconds) ? (int) $reasoningSeconds : null;

    $reasoningLabel = match (true) {
        $streaming => __('wirekit::Thinking…'),
        $reasoningSecondsValue !== null => __('wirekit::Thought for :seconds s', ['seconds' => $reasoningSecondsValue]),
        default => __('wirekit::Reasoning'),
    };

    // The chip is a button the size of a footnote marker, and it carries the state a reader
    // needs to see it as one: a quiet surface, a full radius, and a focus ring that is the same
    // ring every other control here uses. The touch floor is in the stylesheet's coarse-pointer
    // block rather than here — a 44px chip on a mouse-driven page would read as a tag, not a
    // marker.
    $citationChipClasses = WireKit::resolveClasses('assistant-message', 'citation', implode(' ', [
        // `cursor-pointer` because Tailwind's preflight gives a button `cursor: default`, and a
        // chip that opens its source has to read as something you can press.
        'inline-flex cursor-pointer items-center justify-center',
        'min-w-[1.5rem] px-[var(--padding-wk-x-xs)] py-[var(--padding-wk-y-xs)]',
        'rounded-[var(--radius-wk-full)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border-subtle)]',
        'bg-[var(--color-wk-bg-subtle)] text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]',
        'hover:bg-[var(--color-wk-bg-muted)] hover:text-[color:var(--color-wk-text)]',
        'focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
        'wk-transition',
    ]), $scope);

    $roleValue = in_array($role, ['assistant', 'user', 'system'], true)
        ? $role
        : WireKit::validateProp('assistant-message', 'role', $role, ['assistant', 'user', 'system']);

    $announceValue = in_array($announce, ['sentence', 'all', 'off'], true)
        ? $announce
        : WireKit::validateProp('assistant-message', 'announce', $announce, ['sentence', 'all', 'off']);

    $speaker = $name ?? match ($roleValue) {
        'user' => __('wirekit::You'),
        'system' => __('wirekit::System'),
        default => __('wirekit::Assistant'),
    };

    // Layout per role. Full literal class strings via match so the drift auditor
    // can harvest every one of them.
    $roleLayout = match ($roleValue) {
        'user' => 'flex-row-reverse',
        'system' => 'flex-col items-center text-center',
        default => 'flex-row',
    };

    // State tint. neutral → the plain role surface below; any other intent tints
    // the bubble bg + border with the state color (low alpha), mirroring message.
    $intentValue = in_array($intent, ['neutral', 'info', 'success', 'warning', 'danger'], true)
        ? $intent
        : WireKit::validateProp('assistant-message', 'intent', $intent, ['neutral', 'info', 'success', 'warning', 'danger']);

    // Full literal class strings per intent so the drift auditor harvests them.
    $intentSurface = match ($intentValue) {
        'info' => 'bg-[color-mix(in_srgb,var(--color-wk-accent)_8%,var(--color-wk-bg-elevated))] border-[color-mix(in_srgb,var(--color-wk-accent)_40%,var(--color-wk-border))]',
        'success' => 'bg-[color-mix(in_srgb,var(--color-wk-success)_8%,var(--color-wk-bg-elevated))] border-[color-mix(in_srgb,var(--color-wk-success)_40%,var(--color-wk-border))]',
        'warning' => 'bg-[color-mix(in_srgb,var(--color-wk-warning)_8%,var(--color-wk-bg-elevated))] border-[color-mix(in_srgb,var(--color-wk-warning)_40%,var(--color-wk-border))]',
        'danger' => 'bg-[color-mix(in_srgb,var(--color-wk-danger)_8%,var(--color-wk-bg-elevated))] border-[color-mix(in_srgb,var(--color-wk-danger)_40%,var(--color-wk-border))]',
        default => null,
    };

    $surface = $intentSurface ?? match ($roleValue) {
        'user' => 'bg-[color-mix(in_srgb,var(--color-wk-accent)_10%,var(--color-wk-bg-elevated))] border-[var(--color-wk-border-subtle)]',
        'system' => 'bg-[var(--color-wk-bg-muted)] border-[var(--color-wk-border-subtle)]',
        default => 'bg-[var(--color-wk-bg-elevated)] border-[var(--color-wk-border-subtle)]',
    };

    $classes = WireKit::resolveClasses('assistant-message', 'base', implode(' ', [
        'group flex gap-[var(--space-wk-sm,0.5rem)]',
        $roleLayout,
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // AlpinePayload is the encoder for a directive attribute. `$announceValue` is a
    // validated `sentence|all|off` enum, so no byte above ASCII can reach it today — but
    // the encoder is chosen for the CONTEXT rather than for the payload that happens to be
    // in it, and a plain json_encode leaves the next value added here escaping non-ASCII
    // into `ü` shapes that Alpine's CSP tokenizer flattens to `u00fc`.
    $alpineConfig = \Pushery\WireKit\Support\AlpinePayload::from((object) ['announce' => $announceValue]);
@endphp

<article
    x-data="wirekitAssistantMessage({{ $alpineConfig }})"
    data-wk-assistant-message
    data-role="{{ $roleValue }}"
    role="article"
    aria-label="{{ $speaker }}"
    {{ $attributes->class([$classes]) }}
>
    {{-- A system turn is not a person, so it gets no avatar. --}}
    @if($roleValue !== 'system')
        <div data-wk-assistant-avatar class="shrink-0">
            <x-wirekit::avatar :src="$avatar" :alt="$speaker" size="sm" />
        </div>
    @endif

    <div class="flex min-w-0 flex-1 flex-col gap-[var(--space-wk-xs,0.25rem)]">
        {{-- Speaker line: name + model chip. --}}
        <span class="flex items-center gap-[var(--space-wk-sm,0.5rem)] text-[length:var(--text-wk-sm)]">
            <span class="font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">{{ $speaker }}</span>
            @if($model && $roleValue === 'assistant')
                <x-wirekit::badge size="sm" intent="neutral" data-wk-assistant-model>{{ $model }}</x-wirekit::badge>
            @endif
        </span>

        {{-- Reasoning disclosure — open while the model is still thinking, collapsed once the
             answer is there, because the answer is the point.

             The `wire:key` carries the streaming state on purpose. A disclosure's open state is
             Alpine's, and Livewire MORPHS this element when the turn re-renders — which would
             preserve the open state and leave the reasoning hanging open under a finished answer.
             A key that changes makes the morph replace the element instead, so the disclosure
             comes back closed with the summary on it. --}}
        @isset($reasoning)
            <div data-wk-assistant-reasoning wire:key="wk-assistant-reasoning-{{ $streaming ? 'streaming' : 'settled' }}">
                <x-wirekit::collapsible :open="$streaming">
                    <x-slot:trigger>
                        <x-wirekit::shimmer :active="$streaming" data-wk-assistant-reasoning-label>{{ $reasoningLabel }}</x-wirekit::shimmer>
                    </x-slot:trigger>
                    {{ $reasoning }}
                </x-wirekit::collapsible>
            </div>
        @endisset

        {{-- The body.

             aria-live is explicitly OFF here. Putting a live region on streaming
             text makes a screen reader re-read the whole growing answer on every
             token — the mistake most AI chat UIs ship. The body stays silent and
             the announcer below mirrors COMPLETE sentences instead.

             aria-busy tells assistive technology the turn is still being written. --}}
        <div
            x-ref="body"
            data-wk-assistant-body
            aria-live="off"
            @if($streaming) aria-busy="true" @endif
            class="{{ $surface }} rounded-[var(--radius-wk-lg)] border-[length:var(--border-wk-width)] px-[var(--space-wk-md,1rem)] py-[var(--space-wk-sm,0.5rem)]"
        >
            <x-wirekit::prose size="sm">{{ $slot }}</x-wirekit::prose>
        </div>

        {{-- The announcer. ALWAYS present in the DOM — a live region that is
             created at the same moment its text appears is inert to assistive
             technology, so it must exist before the first flush. --}}
        <span
            x-ref="announcer"
            data-wk-assistant-announcer
            role="status"
            aria-live="polite"
            class="sr-only"
            x-text="announced"
        ></span>

        {{-- Sources, as numbered chips: the marker a reader follows back to where an answer came
             from. Each chip opens a popover carrying the passage and, when there is somewhere to
             go, a link to it — a citation the reader cannot check is decoration.

             The number follows the rendered LIST rather than the caller's index, so an entry
             without a label (dropped upstream) never leaves a hole in the count a reader would
             read as a missing source. --}}
        @if($citationList !== [])
            <ol
                data-wk-prose-skip
                data-wk-assistant-citations
                role="list"
                aria-label="{{ __('wirekit::Sources') }}"
                class="flex list-none flex-wrap items-center gap-[var(--space-wk-xs,0.25rem)] p-0"
                style="list-style: none;"
            >
                @foreach($citationList as $citation)
                    <li data-wk-prose-skip>
                        <x-wirekit::popover :label="$citation['label']" placement="top">
                            <x-slot:trigger>
                                <button
                                    type="button"
                                    data-wk-assistant-citation
                                    aria-label="{{ __('wirekit::Source :number, :label', ['number' => $citation['number'], 'label' => $citation['label']]) }}"
                                    class="{{ $citationChipClasses }}"
                                >{{ $citation['number'] }}</button>
                            </x-slot:trigger>

                            @if($citation['href'])
                                <x-wirekit::link :href="$citation['href']" data-wk-assistant-citation-source>{{ $citation['label'] }}</x-wirekit::link>
                            @else
                                <span data-wk-assistant-citation-source class="block text-[length:var(--text-wk-sm)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">{{ $citation['label'] }}</span>
                            @endif

                            @if($citation['snippet'])
                                <span data-wk-prose-skip class="mt-[var(--space-wk-xs,0.25rem)] block text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">{{ $citation['snippet'] }}</span>
                            @endif
                        </x-wirekit::popover>
                    </li>
                @endforeach
            </ol>
        @endif

        {{-- Footer chips (ambient — latency / tokens / cost) and action controls
             (copy / regenerate / rate) share ONE row at the bottom of the message
             column: chips on the reading side, controls pushed to the far edge.
             Actions belong HERE, right under the body they act on — as a top-right
             sibling of the whole turn they floated detached above the message,
             next to the avatar, reading as chrome that belongs to nothing. --}}
        @if(isset($footer) || isset($actions))
            <div data-wk-assistant-meta class="flex flex-wrap items-center gap-[var(--space-wk-sm,0.5rem)]">
                @isset($footer)
                    <span data-wk-assistant-footer class="flex flex-wrap items-center gap-[var(--space-wk-xs,0.25rem)] text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">
                        {{ $footer }}
                    </span>
                @endisset
                @isset($actions)
                    {{-- ms-auto (margin-inline-start) pushes controls to the far
                         edge only when chips share the row; RTL-correct by using
                         the logical-inline margin, not margin-left. --}}
                    <span data-wk-assistant-actions class="flex items-center gap-[var(--space-wk-xs,0.25rem)] {{ isset($footer) ? 'ms-auto' : '' }}">
                        {{ $actions }}
                    </span>
                @endisset
            </div>
        @endif
    </div>
</article>
