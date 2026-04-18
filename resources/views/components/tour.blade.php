@props([
    'name' => 'tour',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Tour — step-by-step product tour overlay.
    // Each step positions near a target element using Floating UI.
    $classes = WireKit::resolveClasses('tour', 'base', implode(' ', [
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);
@endphp

<div
    x-data="wirekitTour({ name: '{{ $name }}' })"
    @keydown.escape.window="active && dismiss()"
    {{ $attributes->class([$classes]) }}
>
    {{-- Teleported to body — escapes any ancestor containing blocks
         (e.g. transform: translateZ(0) in docs PreviewRenderer) so
         fixed positioning resolves against the viewport. --}}
    <template x-teleport="body">
        <div x-show="active" x-cloak x-ref="overlay">
            {{-- Overlay backdrop --}}
            <div
                class="fixed inset-0 z-[var(--z-wk-modal)] bg-[var(--color-wk-overlay)]"
                x-show="active"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                aria-hidden="true"
            ></div>

            {{ $slot }}

            {{-- Live region for step announcements --}}
            <div aria-live="polite" aria-atomic="true" class="sr-only">
                <span x-text="progressText"></span>
            </div>
        </div>
    </template>
</div>
