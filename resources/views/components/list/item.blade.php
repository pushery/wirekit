{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('list-item', 'base', '', $scope);
@endphp

<li {{ $attributes->class([$classes]) }}>{{ $slot }}</li>
