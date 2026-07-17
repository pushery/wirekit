@props([
    // Accessible name for the transcript log region.
    'label' => 'Conversation',
    // Height cap of the scroll viewport. A transcript needs a bounded height —
    // without one there is nothing to scroll and follow-output is meaningless.
    'maxHeight' => config('wirekit.components.conversation.max-height', '24rem'),
    // px tolerance for "at bottom". Null → the plugin default (24).
    'threshold' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Only pass what the developer actually set — the plugin owns the defaults.
    // Cast to object so an empty config encodes as `{}` (a JS object literal),
    // not `[]` — the plugin signature takes a config object.
    $alpineConfig = json_encode((object) array_filter(
        ['threshold' => $threshold !== null ? (int) $threshold : null],
        static fn ($v): bool => $v !== null,
    ), JSON_THROW_ON_ERROR);

    $rootClasses = WireKit::resolveClasses('conversation', 'base', 'relative', $scope);

    // role="log" is the semantically correct role for a chat transcript (an
    // implicit polite live region), and it satisfies the scroll-container
    // keyboard contract together with tabindex="0" + aria-label: the region is
    // reachable and operable by keyboard (WCAG 2.1.1).
    //
    // The live region is ALWAYS present in the DOM — it is not conditionally
    // rendered — so a Livewire/wire:stream text swap inside it actually
    // announces. A region that appears at the same moment its text does is
    // inert to assistive technology.
    // scrollbar-gutter: stable reserves the scrollbar track permanently, so the
    // content width never jumps when the vertical scrollbar appears/disappears as
    // messages stream in — the "flicker" of a plain overflow-y:auto viewport.
    // Progressive enhancement: browsers below the property's support (Safari
    // < 18.2) simply skip it (unknown-property → ignored) and keep today's
    // behavior; nothing depends on it.
    $viewportClasses = WireKit::resolveClasses('conversation', 'viewport', implode(' ', [
        'wk-scrollbar overflow-y-auto overflow-x-hidden [scrollbar-gutter:stable]',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--color-wk-ring)]',
    ]), $scope);
@endphp

<div
    x-data="wirekitConversation({{ $alpineConfig }})"
    {{ $attributes->class([$rootClasses]) }}
>
    <div
        x-ref="viewport"
        role="log"
        aria-live="polite"
        aria-relevant="additions text"
        tabindex="0"
        aria-label="{{ $label }}"
        class="{{ $viewportClasses }}"
        style="max-height: {{ $maxHeight }};"
    >
        {{ $slot }}

        {{-- Pinned streaming footer (typing indicator / "assistant is thinking…").
             It lives INSIDE the viewport so it participates in follow-output —
             the reader stays glued to it while it is showing. The top margin
             matches the inter-message rhythm (space-md), so the footer reads as
             the next line in the thread instead of crowding the last message. --}}
        @isset($footer)
            <div data-wk-conversation-footer class="mt-[var(--space-wk-md,1rem)]">{{ $footer }}</div>
        @endisset
    </div>

    {{-- Jump-to-latest. Only offered while the reader is scrolled away; the
         count tells them how much they missed (the Slack / Discord affordance),
         not just "there is more". x-cloak keeps it hidden until Alpine
         evaluates atBottom, so it never flashes on load. --}}
    <x-wirekit::button
        type="button"
        size="sm"
        intent="neutral"
        surface="filled"
        x-show="!atBottom"
        x-cloak
        x-transition.opacity
        @click="scrollToBottom()"
        ::aria-label="unread > 0 ? ('Jump to latest, ' + unread + ' new') : 'Jump to latest'"
        class="absolute bottom-[var(--space-wk-sm)] left-1/2 -translate-x-1/2 shadow-[var(--shadow-wk-md)]"
    >
        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
        </svg>
        <span x-show="unread > 0" x-text="unread" x-cloak></span>
    </x-wirekit::button>
</div>
