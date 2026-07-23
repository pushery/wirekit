@props([
    'autoplay' => false,
    'interval' => 5000,
    'loop' => true,
    'orientation' => 'horizontal', // horizontal (default) | vertical
    // How many slides share the view. '1' is the classic one-up carousel; the
    // rest show neighbors, which is what a product row or a logo rail wants.
    // Only meaningful horizontally — a vertical carousel is one slide tall.
    'perView' => 1,
    // What this carousel is FOR. A carousel of testimonials is not an image
    // carousel, and the name is the first thing a screen reader reads.
    'label' => __('Carousel'),
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $autoplay = BooleanProp::from($autoplay, false);
    $loop = BooleanProp::from($loop, true);

    $orientationValue = match ($orientation) {
        'horizontal', 'vertical' => $orientation,
        default => WireKit::validateProp('carousel', 'orientation', $orientation, ['horizontal', 'vertical']),
    };
    $isVertical = $orientationValue === 'vertical';

    // Vertical stacks one slide per view by construction: the track is one
    // viewport tall, so there is no second slot to share.
    $perViewValue = $isVertical
        ? '1'
        : WireKit::validateProp('carousel', 'perView', (string) $perView, ['1', '2', '3', '4']);

    // How many slides share the view is the PARENT's business, but the width lands
    // on each SLIDE. The bridge is the data attribute below plus a rule in
    // dist/wirekit.css — deliberately not @aware, which cannot see a parent's
    // @props default and would silently give every default carousel the wrong
    // basis. It also keeps the responsive ladder in CSS, where media queries live.

    $classes = WireKit::resolveClasses('carousel', 'base', implode(' ', array_filter([
        'relative',
        'rounded-[var(--radius-wk-lg)]',
        'font-[family-name:var(--font-wk-sans)]',
        // A vertical carousel needs a fixed height, and it lives on the ROOT so an
        // inline `style="height: …"` override actually takes effect (inline beats
        // this class). The viewport then fills it with h-full — previously the
        // viewport hard-coded h-[20rem], so overriding the root's height left the
        // viewport at 20rem and it overflowed the box.
        $isVertical ? 'h-[20rem]' : '',
    ])), $scope);

    // The viewport IS the scroller now. The browser owns snapping and momentum;
    // Alpine only watches where it landed.
    //
    // wk-carousel-viewport carries the scrollbar-hiding and scroll-behavior rules
    // that have no Tailwind utility — see dist/wirekit.css.
    $viewportClasses = implode(' ', [
        'wk-carousel-viewport',
        'flex',
        'overflow-hidden',
        'rounded-[var(--radius-wk-lg)]',
        'focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
        $isVertical
            ? 'flex-col h-full overflow-y-auto snap-y snap-mandatory'
            : 'overflow-x-auto snap-x snap-mandatory',
    ]);

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
    // Horizontal arrows sit at the root's inline edges (left-0 / right-0), which
    // land in the reserved gutter OUTSIDE the scroller (see --wk-carousel-gutter
    // in dist/wirekit.css) — never overlapping the slides. Vertical arrows stay
    // centered at the top / bottom edge of the fixed-height track.
    $prevPosClass = $isVertical ? 'top-[var(--padding-wk-y-sm)]' : 'left-0';
    $nextPosClass = $isVertical ? 'bottom-[var(--padding-wk-y-sm)]' : 'right-0';

    $prevPath = $isVertical ? 'm4.5 15.75 7.5-7.5 7.5 7.5' : 'M15.75 19.5 8.25 12l7.5-7.5';
    $nextPath = $isVertical ? 'm19.5 8.25-7.5 7.5-7.5-7.5' : 'm8.25 4.5 7.5 7.5-7.5 7.5';

    // items-center so the dots and the play/pause button (last child, bigger than a
    // dot) share one baseline — they are one control cluster. Horizontal: a centered
    // row BELOW the slide stage, so the dots sit UNDER the slides instead of
    // overlapping them. Vertical: the right-edge column, absolutely placed.
    $indicatorClasses = $isVertical
        ? 'absolute right-[var(--padding-wk-x-sm)] top-1/2 -translate-y-1/2 flex flex-col items-center gap-1.5'
        : 'mt-[var(--padding-wk-y-sm)] flex items-center justify-center gap-1.5';
@endphp

{{-- The APG carousel shape, without the tabs.

     The slides used to claim role="tabpanel" and the dots role="tab" — but
     neither carried aria-controls or aria-labelledby, so it was a tablist that
     never actually connected to anything. And the model does not survive this
     change: a tablist means ONE selected panel, while perView shows three at
     once. So the slides are now labeled groups inside a scroll region, which is
     APG's non-tabbed carousel and the only model that is honest about several
     slides being visible together. --}}
<div
    x-data="wirekitCarousel({
        autoplay: {{ $autoplay ? 'true' : 'false' }},
        interval: {{ (int) $interval }},
        loop: {{ $loop ? 'true' : 'false' }},
        vertical: {{ $isVertical ? 'true' : 'false' }}
    })"
    x-on:mouseenter="pauseOnHover()"
    x-on:mouseleave="resumeFromHover()"
    x-on:focusin="pauseOnHover()"
    x-on:focusout="resumeFromHover()"
    {{-- role="region" is APG's own shape for a carousel (a named <section>), and
         it makes the carousel a landmark a screen-reader user can jump to. It was
         already region before this rewrite; downgrading it to a plain group would
         have quietly taken that landmark away for no reason at all. The label is
         now configurable, which makes the landmark better, not worse. --}}
    role="region"
    aria-roledescription="carousel"
    aria-label="{{ $label }}"
    data-wk-carousel
    data-wk-carousel-orientation="{{ $orientationValue }}"
    data-wk-carousel-per-view="{{ $perViewValue }}"
    {{ $attributes->class([$classes]) }}
>
    {{-- Slide stage: the scroll region plus the prev/next arrows. It is `relative`
         so the arrows center on the SLIDES, not on the whole component — the dots
         now sit in a row BELOW this stage (horizontal), so centering the arrows on
         the root would drop them below the slide midline. --}}
    <div class="relative {{ $isVertical ? 'h-full' : '' }}">
    {{-- The scroll region. tabindex + role + label are the house rule for any
         scrollable container: without them the slides are reachable by mouse and
         by nothing else. Arrow keys then scroll it natively — no key handler of
         our own to get wrong. --}}
    <div
        x-ref="viewport"
        tabindex="0"
        role="group"
        aria-label="{{ $label }} slides"
        data-wk-carousel-viewport
        data-wk-carousel-per-view="{{ $perViewValue }}"
        class="{{ $viewportClasses }}"
    >
        {{ $slot }}
    </div>

    <button
        type="button"
        x-on:click="prev()"
        :disabled="!loop && current === 0"
        class="{{ $buttonClasses }} {{ $prevPosClass }}"
        aria-label="{{ __('Previous slide') }}"
        data-wk-carousel-prev
    >
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $prevPath }}" />
        </svg>
    </button>

    <button
        type="button"
        x-on:click="next()"
        :disabled="!loop && current === total - 1"
        class="{{ $buttonClasses }} {{ $nextPosClass }}"
        aria-label="{{ __('Next slide') }}"
        data-wk-carousel-next
    >
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $nextPath }}" />
        </svg>
    </button>
    </div>{{-- /stage --}}

    {{-- Slide picker + play/pause, one control cluster. Not a tablist: the dots
         scroll the track, they do not select a panel — and with perView > 1 there
         is no single selected panel to point at. aria-current says which slide
         leads the view. --}}
    <div class="{{ $indicatorClasses }}" role="group" aria-label="{{ __('Choose slide') }}">
        <template x-for="(_, i) in total" :key="i">
            <button
                type="button"
                x-on:click="goTo(i)"
                :aria-current="current === i ? 'true' : 'false'"
                :aria-label="`Go to slide ${i + 1}`"
                data-wk-carousel-dot
                class="w-2 h-2 cursor-pointer rounded-full transition-colors duration-[var(--transition-wk-duration)] focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                :class="current === i ? 'bg-[var(--color-wk-accent)]' : 'bg-[var(--color-wk-border)]'"
            ></button>
        </template>

        @if($autoplay)
            {{-- WCAG 2.2.2: anything that moves on its own for more than five seconds
                 needs a way to stop it. Pausing on hover is not that way — a touch
                 user cannot hover, and neither can a keyboard user reading with a
                 screen reader. This button is the mechanism. It sits WITH the dots
                 (last child of the picker) rather than floating in a corner — one
                 coherent control cluster.

                 aria-pressed reflects `playing`, which hover deliberately does NOT
                 touch: a mouse passing over the carousel must not make the button
                 claim the reader paused it. --}}
            <button
                type="button"
                x-on:click="toggle()"
                :aria-pressed="playing ? 'false' : 'true'"
                :aria-label="playing ? 'Pause carousel' : 'Play carousel'"
                data-wk-carousel-playpause
                class="ms-1 flex h-5 w-5 cursor-pointer items-center justify-center rounded-full bg-[var(--color-wk-bg-elevated)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] text-[color:var(--color-wk-text)] hover:bg-[var(--color-wk-bg-subtle)] focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
            >
                <svg x-show="playing" class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M6 5h4v14H6zM14 5h4v14h-4z" />
                </svg>
                <svg x-show="!playing" x-cloak class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M8 5v14l11-7z" />
                </svg>
            </button>
        @endif
    </div>

    {{-- aria-live IS present here — do not flag as missing --}}
    <div aria-live="polite" aria-atomic="true" class="sr-only">
        <span x-text="announcement"></span>
    </div>
</div>
