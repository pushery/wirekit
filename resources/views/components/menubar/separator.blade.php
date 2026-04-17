@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('menubar.separator', 'base', implode(' ', [
        'my-[var(--padding-wk-y-xs)]',
        'border-t',
        'border-[var(--color-wk-border-subtle)]',
    ]), $scope);
@endphp

<div role="separator" {{ $attributes->class([$classes]) }}></div>
