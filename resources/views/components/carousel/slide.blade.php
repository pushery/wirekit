{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('carousel.slide', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // A slide is a snap target that never shrinks. `basis` (not `w-`) because the
    // parent is a flex row: a width would still let flex-shrink squeeze it, and a
    // squeezed snap target lands the scroller between slides.
    //
    // The basis itself is set by the parent's perView through the marker class in
    // dist/wirekit.css, so a slide does not need to know how many share the view.
    $classes = WireKit::resolveClasses('carousel.slide', 'base', implode(' ', [
        'wk-carousel-slide',
        'snap-start shrink-0 grow-0',
        'w-full',
    ]), $scope);
@endphp

{{-- role="group" + aria-roledescription="slide", per APG's non-tabbed carousel.
     It was role="tabpanel" before, but nothing pointed a tab at it — and the
     tab model cannot describe several slides sharing the view anyway. --}}
<div
    data-wk-carousel-slide
    role="group"
    aria-roledescription="slide"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</div>
