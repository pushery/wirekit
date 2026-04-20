@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('spacer', 'base', 'grow', $scope);
@endphp

<div {{ $attributes->class([$classes]) }} aria-hidden="true"></div>
