{{-- optimistic-ui: n/a — client-only
     Its state is which step is current. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'name' => 'tour',
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\TourStepCounter;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('tour', $attributes->getAttributes());

    // Tour — step-by-step product tour overlay.
    // Each step positions near a target element using Floating UI.
    $classes = WireKit::resolveClasses('tour', 'base', implode(' ', [
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // Reset the per-render sequential counter so every child
    // `<x-wirekit::tour.step>` whose `$index` is null gets auto-
    // assigned 0, 1, 2, … in document order. Without this, every
    // step defaulted to `data-wk-tour-step="0"` and the tour's
    // next() JS could only locate the first step. See
    // `Pushery\WireKit\Support\TourStepCounter` for the full
    // mechanism + multi-tour-on-same-page rationale.
    TourStepCounter::reset();
@endphp

<div
    x-data="wirekitTour({ name: {{ \Pushery\WireKit\Support\AlpinePayload::string($name) }} })"
    @keydown.escape.window="active && dismiss()"
    {{ $attributes->class([$classes]) }}
>
    {{-- Teleported to body — escapes any ancestor containing blocks
         (e.g. transform: translateZ(0) in docs PreviewRenderer) so
         fixed positioning resolves against the viewport. --}}
    <template x-teleport="#wk-overlay-root">
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
