{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    // The slide's accessible name. `role="group"` conformance does not require one, but
    // `aria-roledescription="slide"` is what makes a screen reader say "slide" instead of
    // "group" — and it is unreliable on an element that has no name at all, so a track of
    // unnamed slides reads as N identical groups with nothing to tell them apart.
    //
    // It is a PROP rather than only an `aria-label` passthrough because a prop is what the
    // props parser, the JSON export and the api-map can see; an attribute that happens to
    // work is not a documented affordance, and the parent's own name for the same idea is
    // `label` too.
    //
    // Deliberately NOT defaulted. There is no honest built-in name here: an anonymous Blade
    // slide has no index of its own, so anything this file could invent would name every
    // slide identically — the same reasoning that gates `role="region"` on a caller-supplied
    // name everywhere else in this library, rather than on a name the component made up.
    'label' => null,
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
    {{-- `filled()`, never `??`: an interpolated caller value over a record with no title
         arrives as an empty string, and `aria-label=""` is not a name — it leaves the group
         nameless while looking wired. Same gate, same reason, as every named region here.
         A caller-supplied `aria-label` / `aria-labelledby` still wins, so the two spellings
         never collide on one tag. --}}
    @if(filled($label) && ! $attributes->has('aria-label') && ! $attributes->has('aria-labelledby')) aria-label="{{ $label }}" @endif
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</div>
