@props([
    'inline' => false,
    'as' => 'div',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('center', 'base', implode(' ', [
        $inline ? 'inline-flex' : 'flex',
        'items-center justify-center',
    ]), $scope);
@endphp

<{{ $as }} {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</{{ $as }}>
