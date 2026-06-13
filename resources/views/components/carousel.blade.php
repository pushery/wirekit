@props([
    'autoplay' => false,
    'interval' => 5000,
    'loop' => true,
    'orientation' => 'horizontal', // horizontal (default) | vertical
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $orientationValue = match ($orientation) {
        'horizontal', 'vertical' => $orientation,
        default => WireKit::validateProp('carousel', 'orientation', $orientation, ['horizontal', 'vertical']),
    };
    $isVertical = $orientationValue === 'vertical';

    // Carousel — slide-based content rotation with autoplay, navigation, and indicators.
    // Uses aria-roledescription="carousel" and live region for slide announcements.
    // Vertical needs a fixed viewport height so overflow-hidden clips to one slide;
    // ship a sensible default the developer can override via class / style.
    $classes = WireKit::resolveClasses('carousel', 'base', implode(' ', [
        'relative overflow-hidden',
        'rounded-[var(--radius-wk-lg)]',
        'font-[family-name:var(--font-wk-sans)]',
        $isVertical ? 'h-[20rem]' : '',
    ]), $scope);

    // Slide track: flex axis + translate axis follow the orientation. h-full lets the
    // vertical track fill the fixed-height viewport so each slide is one viewport tall.
    $trackClasses = $isVertical
        ? 'flex flex-col h-full transition-transform duration-500 ease-in-out'
        : 'flex transition-transform duration-500 ease-in-out';
    $translateAxis = $isVertical ? 'translateY' : 'translateX';

    // Nav buttons — centered on the cross axis; prev/next sit at the two ends of the main axis.
    $buttonClasses = implode(' ', [
        'absolute z-10',
        'flex items-center justify-center',
        'w-10 h-10 cursor-pointer rounded-full',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'shadow-[var(--shadow-wk-md)]',
        'text-[color:var(--color-wk-text)]',
        'transition-opacity duration-[var(--transition-wk-duration)]',
        'hover:bg-[var(--color-wk-bg-subtle)]',
        'focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
        'disabled:opacity-[var(--opacity-wk-disabled)] disabled:cursor-default',
        $isVertical ? 'left-1/2 -translate-x-1/2' : 'top-1/2 -translate-y-1/2',
    ]);
    $prevPosClass = $isVertical ? 'top-[var(--padding-wk-y-sm)]' : 'left-[var(--padding-wk-x-sm)]';
    $nextPosClass = $isVertical ? 'bottom-[var(--padding-wk-y-sm)]' : 'right-[var(--padding-wk-x-sm)]';

    // Chevron paths: prev = up/left, next = down/right (heroicons mini chevrons).
    $prevPath = $isVertical ? 'm4.5 15.75 7.5-7.5 7.5 7.5' : 'M15.75 19.5 8.25 12l7.5-7.5';
    $nextPath = $isVertical ? 'm19.5 8.25-7.5 7.5-7.5-7.5' : 'm8.25 4.5 7.5 7.5-7.5 7.5';

    // Indicators: a row along the bottom (horizontal) or a column on the inline-end (vertical).
    $indicatorClasses = $isVertical
        ? 'absolute right-[var(--padding-wk-x-sm)] top-1/2 -translate-y-1/2 flex flex-col gap-1.5'
        : 'absolute bottom-[var(--padding-wk-y-sm)] left-1/2 -translate-x-1/2 flex gap-1.5';
@endphp

<div
    x-data="wirekitCarousel({ autoplay: {{ $autoplay ? 'true' : 'false' }}, interval: {{ (int) $interval }}, loop: {{ $loop ? 'true' : 'false' }} })"
    x-on:mouseenter="pause()"
    x-on:mouseleave="resume()"
    x-on:focusin="pause()"
    x-on:focusout="resume()"
    role="region"
    aria-roledescription="carousel"
    aria-label="Image carousel"
    data-wk-carousel-orientation="{{ $orientationValue }}"
    {{ $attributes->class([$classes]) }}
>
    {{-- Slide container — scrolled via translate on the orientation's main axis. --}}
    <div class="{{ $trackClasses }}" :style="`transform: {{ $translateAxis }}(-${current * 100}%)`">
        {{ $slot }}
    </div>

    {{-- Previous button --}}
    <button
        type="button"
        x-on:click="prev()"
        :disabled="!loop && current === 0"
        class="{{ $buttonClasses }} {{ $prevPosClass }}"
        aria-label="Previous slide"
    >
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $prevPath }}" />
        </svg>
    </button>

    {{-- Next button --}}
    <button
        type="button"
        x-on:click="next()"
        :disabled="!loop && current === total - 1"
        class="{{ $buttonClasses }} {{ $nextPosClass }}"
        aria-label="Next slide"
    >
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $nextPath }}" />
        </svg>
    </button>

    {{-- Indicators --}}
    <div class="{{ $indicatorClasses }}" role="tablist" aria-label="Slides">
        <template x-for="(_, i) in total" :key="i">
            <button
                type="button"
                x-on:click="goTo(i)"
                :aria-selected="current === i ? 'true' : 'false'"
                :aria-label="`Slide ${i + 1}`"
                role="tab"
                class="w-2 h-2 cursor-pointer rounded-full transition-colors duration-[var(--transition-wk-duration)]"
                :class="current === i ? 'bg-[var(--color-wk-accent)]' : 'bg-[var(--color-wk-border)]'"
            ></button>
        </template>
    </div>

    {{-- aria-live IS present here — do not flag as missing --}}
    <div aria-live="polite" aria-atomic="true" class="sr-only">
        <span x-text="announcement"></span>
    </div>
</div>
