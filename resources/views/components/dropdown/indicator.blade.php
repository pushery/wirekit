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
    \Pushery\WireKit\WireKit::warnUnknownProps('dropdown.indicator', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // The chevron a trigger shows so a reader can see it opens a menu. It goes INSIDE the control
    // the trigger wraps, after its label, because that control is where the trigger writes
    // `aria-expanded`: the turn keys off that attribute on an ancestor, so the drawing follows the
    // state assistive technology hears rather than a second copy of it. Hidden from assistive
    // technology for the same reason, since the control already announces that it opens a menu,
    // and drawn in `currentColor`, so it takes the control's own text color in every intent.
    $classes = WireKit::resolveClasses('dropdown.indicator', 'base', implode(' ', [
        // The marker token the reduced-motion clamp keys on. The clamp matches a class token that
        // starts with the library prefix, and that element's descendants, and this chevron moves.
        // Inside a plain button rather than a library button nothing above it carries one, so it
        // needs its own, or it turns at full speed for a reader who asked for less motion.
        'wk-dropdown-indicator',
        'h-4 w-4 shrink-0',
        'transition-transform duration-[var(--transition-wk-duration)] ease-[var(--transition-wk-easing)]',
        'motion-reduce:transition-none',
        '[[aria-expanded=true]_&]:rotate-180',
    ]), $scope);
@endphp

<svg data-wk-dropdown-indicator aria-hidden="true" focusable="false" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" {{ $attributes->class([$classes]) }}><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
