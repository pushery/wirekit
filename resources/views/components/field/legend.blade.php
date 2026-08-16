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
    \Pushery\WireKit\WireKit::warnUnknownProps('field.legend', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // Standalone <legend> for when a developer wants rich legend content (markup,
    // a badge, a help icon) inside <x-wirekit::field.set> instead of the plain
    // `legend` string prop. Use ONE or the OTHER, never both.
    $classes = WireKit::resolveClasses('field.legend', 'base', 'mb-3 text-[length:var(--text-wk-md)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]', $scope);
@endphp

<legend {{ $attributes->class([$classes]) }}>{{ $slot }}</legend>
