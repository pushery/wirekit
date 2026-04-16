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
    {{-- Tour steps (hidden until active, positioned by Alpine) --}}
    <template x-if="active">
        <div>
            {{-- Overlay backdrop --}}
            <div class="fixed inset-0 z-[var(--z-wk-modal)] bg-[var(--color-wk-overlay)]" aria-hidden="true"></div>

            {{ $slot }}

            {{-- Live region for step announcements --}}
            <div aria-live="polite" aria-atomic="true" class="sr-only">
                <span x-text="progressText"></span>
            </div>
        </div>
    </template>
</div>
