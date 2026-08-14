{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'as' => 'span',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('visually-hidden', $attributes->getAttributes());

    $classes = WireKit::resolveClasses('visually-hidden', 'base', 'sr-only', $scope);
@endphp

<{{ $as }} {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</{{ $as }}>