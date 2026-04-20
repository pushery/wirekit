@props([
    'ratio' => '16/9',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Parse ratio: accepts "16/9", "4/3", "1/1", or a numeric value
    $aspectValue = is_numeric($ratio) ? $ratio : $ratio;

    $classes = WireKit::resolveClasses('aspect-ratio', 'base', implode(' ', [
        'relative overflow-hidden',
    ]), $scope);
@endphp

<div
    {{ $attributes->class([$classes]) }}
    style="aspect-ratio: {{ $aspectValue }}"
>
    {{ $slot }}
</div>
