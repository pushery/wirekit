@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('dropdown.separator', 'base', implode(' ', [
        'my-[var(--padding-wk-y-xs)]',
        'border-t',
        'border-[var(--color-wk-border-subtle)]',
    ]), $scope);
@endphp

{{-- Visual separator between dropdown item groups --}}
<div role="separator" {{ $attributes->class([$classes]) }}></div>
