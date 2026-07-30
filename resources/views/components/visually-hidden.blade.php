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

    $classes = WireKit::resolveClasses('visually-hidden', 'base', 'sr-only', $scope);
@endphp

<{{ $as }} {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</{{ $as }}>