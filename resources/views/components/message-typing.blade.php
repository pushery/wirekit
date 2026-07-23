@props([
    // Who is typing — used for the announcement ("Ada is typing…"). Null keeps
    // it generic.
    'author' => null,
    // Side the bubble sits on, mirroring the message component's `side` prop.
    'side' => 'left',
    // Announce the typing state to assistive technology. Off by default: a
    // transcript that announces every keystroke-driven typing flicker is
    // unusable with a screen reader. Turn it on for a one-off "assistant is
    // working" row.
    'announce' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $announce = BooleanProp::from($announce, false);

    $sideValue = in_array($side, ['left', 'right'], true)
        ? $side
        : WireKit::validateProp('message-typing', 'side', $side, ['left', 'right']);

    $alignClass = $sideValue === 'right' ? 'flex-row-reverse' : 'flex-row';
    // Column alignment mirrors <x-wirekit::message>: the name + bubble hug the
    // same edge the typist's messages do, so the indicator reads as their next
    // message about to land.
    $columnAlign = $sideValue === 'right' ? 'items-end' : 'items-start';

    $hasAuthor = $author !== null && $author !== '';

    $classes = WireKit::resolveClasses('message-typing', 'base', implode(' ', [
        'flex gap-[var(--space-wk-sm,0.5rem)]',
        $alignClass,
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    $announcement = $hasAuthor
        ? __(':author is typing…', ['author' => $author])
        : __('Typing…');
@endphp

<div
    data-wk-message-typing
    data-side="{{ $sideValue }}"
    @if($announce) role="status" aria-live="polite" @endif
    {{ $attributes->class([$classes]) }}
>
    {{-- Avatar — only when we know who is typing. The initials placeholder aligns
         the indicator with the typist's own messages above it (same avatar the
         message component renders). --}}
    @if($hasAuthor)
        <div class="shrink-0">
            <x-wirekit::avatar :alt="$author" size="sm" />
        </div>
    @endif

    {{-- Content column mirrors the message layout: the name sits above the bubble,
         and the bubble is indented under it — so the reader sees WHO is typing,
         not an anonymous set of dots floating at the thread edge. --}}
    <div class="flex flex-col {{ $columnAlign }} gap-[var(--space-wk-xs,0.25rem)] min-w-0 max-w-[42ch]">
        @if($hasAuthor)
            <span class="text-[length:var(--text-wk-sm)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)] truncate">
                {{ $author }}
            </span>
        @endif

        {{-- The bubble mirrors the message component's neutral surface so a typing
             indicator sits in a thread as if it were the next message. --}}
        <div class="flex items-center gap-[var(--space-wk-xs,0.25rem)] rounded-[var(--radius-wk-lg)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border-subtle)] bg-[var(--color-wk-bg-elevated)] px-[var(--space-wk-md,1rem)] py-[var(--space-wk-sm,0.5rem)]">
            {{-- Three dots, staggered. Decorative: the meaning is carried by the
                 visually-hidden text below, so a reduced-motion user (dots frozen)
                 loses nothing. --}}
            <span class="wk-typing-dots" aria-hidden="true">
                <span class="wk-typing-dot"></span>
                <span class="wk-typing-dot"></span>
                <span class="wk-typing-dot"></span>
            </span>
            <span class="sr-only">{{ $announcement }}</span>
        </div>
    </div>
</div>
