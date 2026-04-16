@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Carousel slide — individual content panel within the carousel.
    $classes = WireKit::resolveClasses('carousel.slide', 'base', implode(' ', [
        'w-full shrink-0',
    ]), $scope);
@endphp

<div
    data-wk-carousel-slide
    role="tabpanel"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</div>
