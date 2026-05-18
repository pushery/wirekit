@props([
    'placement' => 'bottom',
    'offset' => 8,
    'delayShow' => 300,
    'delayHide' => 200,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Hover Card — rich tooltip-like overlay that shows on hover/focus.
    // Unlike tooltip, hover cards display structured content (avatar, bio, actions).
    // Uses Floating UI for positioning and role="dialog" for a11y.
    $wrapperClasses = WireKit::resolveClasses('hover-card', 'wrapper', implode(' ', [
        'relative inline-block',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    $panelClasses = WireKit::resolveClasses('hover-card', 'panel', implode(' ', [
        'fixed z-[var(--z-wk-tooltip)]',
        'w-72',
        'rounded-[var(--radius-wk-lg)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'shadow-[var(--shadow-wk-lg)]',
        'p-[var(--padding-wk-x-md)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);
@endphp

<span
    x-data="wirekitHoverCard({ placement: '{{ $placement }}', offset: {{ $offset }}, delayShow: {{ $delayShow }}, delayHide: {{ $delayHide }} })"
    {{ $attributes->class([$wrapperClasses]) }}
>
    {{-- Trigger element --}}
    <span
        x-ref="trigger"
        @mouseenter="mouseenter()"
        @mouseleave="mouseleave()"
        @focusin="focusin()"
        @focusout="focusout()"
        aria-haspopup="dialog"
        :aria-expanded="open ? 'true' : 'false'"
    >
        {{ $trigger }}
    </span>

    {{--
        Hover-card panel — positioned via Floating UI. Teleported to
        <body> via `x-teleport="body"` so the panel renders OUTSIDE the
        wrapper `<span>` hierarchy. Without the teleport, an inline
        usage like

            <x-wirekit::text>… <x-wirekit::hover-card>…</x-wirekit::hover-card> …</x-wirekit::text>

        renders as `<p><span>…<div panel></div></span>…</p>`. The HTML5
        parser auto-closes the `<p>` (and the wrapping `<span>`) when it
        encounters the panel `<div>` because `<p>` only allows phrasing
        content — no flow content like `<div>`. The fragmentation breaks
        Alpine's `$refs.panel` lookup (the panel is no longer a
        descendant of the x-data root) AND the `@mouseenter` / show
        bindings never see the panel either, so the hover card silently
        never opens. Surfaced on /components/hover-card#inline-in-running-text.

        Teleporting to `body` lifts the panel out of the wrapper, leaving
        only the trigger `<span>` (phrasing content) inside the running
        `<p>`. The Alpine root + refs still resolve across the teleport
        boundary, the hover-intent state machine + Floating UI position
        calculations work unchanged (panel is `position: fixed`,
        coordinates are viewport-relative so the wrapper position is
        irrelevant).
    --}}
    <template x-teleport="body">
        <div
            x-ref="panel"
            x-show="open"
            x-transition:enter="transition ease-out duration-[var(--transition-wk-duration)]"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-[var(--transition-wk-duration)]"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @mouseenter="mouseenter()"
            @mouseleave="mouseleave()"
            @keydown.escape.prevent="close()"
            role="dialog"
            aria-label="Hover card"
            class="{{ $panelClasses }}"
            x-cloak
        >
            {{ $slot }}
        </div>
    </template>
</span>
