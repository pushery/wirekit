@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('list-item', 'base', '', $scope);
@endphp

<li {{ $attributes->class([$classes]) }}>{{ $slot }}</li>
