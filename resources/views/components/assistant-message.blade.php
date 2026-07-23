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

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $streaming = BooleanProp::from($streaming, false);

    $roleValue = in_array($role, ['assistant', 'user', 'system'], true)
        ? $role
        : WireKit::validateProp('assistant-message', 'role', $role, ['assistant', 'user', 'system']);

    $announceValue = in_array($announce, ['sentence', 'all', 'off'], true)
        ? $announce
        : WireKit::validateProp('assistant-message', 'announce', $announce, ['sentence', 'all', 'off']);

    $speaker = $name ?? match ($roleValue) {
        'user' => __('You'),
        'system' => __('System'),
        default => __('Assistant'),
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

    $alpineConfig = json_encode((object) ['announce' => $announceValue], JSON_THROW_ON_ERROR);
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

        {{-- Reasoning disclosure — collapsed by default; the answer is the point. --}}
        @isset($reasoning)
            <div data-wk-assistant-reasoning>
                <x-wirekit::collapsible :trigger="__('Reasoning')">
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
