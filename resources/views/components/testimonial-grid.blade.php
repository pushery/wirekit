{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    // Accessible name for the set of quotes.
    'label' => config('wirekit.components.testimonial-grid.label') ?? __('wirekit::Testimonials'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('testimonial-grid', $attributes->getAttributes());

    $classes = WireKit::resolveClasses('testimonial-grid', 'base', implode(' ', [
        'list-none',
        'grid gap-[var(--gap-wk-md)]',
        'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
        'items-stretch',
    ]), $scope);
@endphp

{{-- A labeled list, so a screen reader announces how many quotes there are
     rather than dumping them one after another with no boundary.

     The inline list-style is not redundant with list-none: the docs sandbox iframe
     renders previews WITHOUT the developer's Tailwind build, so `list-none` is a dead
     class name there (it DOES load dist/wirekit.css — that is why the tokens in this
     component resolve). --}}
<ul
    role="list"
    aria-label="{{ $label }}"
    data-wk-testimonial-grid
    {{ $attributes->merge(['style' => 'list-style: none; margin: 0; padding: 0;'])->class([$classes]) }}
>
    {{ $slot }}
</ul>
